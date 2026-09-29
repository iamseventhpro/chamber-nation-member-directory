<?php
/**
 * Updates from GitHub Releases.
 *
 * WordPress (5.8+) calls update_plugins_{hostname} for plugins whose
 * "Update URI" header points at that host. The header also stops
 * WordPress.org from offering an unrelated plugin with the same slug.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checks the latest GitHub Release and offers it as a normal plugin update.
 */
class CNMD_Updater {

	/**
	 * GitHub repository (owner/name).
	 */
	const REPO = 'iamseventhpro/chamber-nation-member-directory';

	/**
	 * Cache key for the latest release info.
	 */
	const CACHE_KEY = 'cnmd_github_release';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_filter( 'update_plugins_github.com', array( __CLASS__, 'check_update' ), 10, 3 );
		add_filter( 'plugins_api', array( __CLASS__, 'plugin_info' ), 20, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ) );
	}

	/**
	 * Plugin slug.
	 *
	 * @return string
	 */
	private static function slug() {
		return dirname( plugin_basename( CNMD_FILE ) );
	}

	/**
	 * Latest release from GitHub, cached for 12 hours (1 hour after errors).
	 *
	 * @return array|null array( version, package, url, notes, published ).
	 */
	public static function latest_release() {
		$cached = get_transient( self::CACHE_KEY );
		if ( false !== $cached ) {
			return $cached ? $cached : null;
		}

		$response = wp_remote_get(
			'https://api.github.com/repos/' . self::REPO . '/releases/latest',
			array(
				'timeout' => 10,
				'headers' => array( 'Accept' => 'application/vnd.github+json' ),
			)
		);

		$release = null;
		if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_array( $data ) && ! empty( $data['tag_name'] ) ) {
				$package = '';
				foreach ( isset( $data['assets'] ) ? (array) $data['assets'] : array() as $asset ) {
					if ( isset( $asset['name'], $asset['browser_download_url'] ) && preg_match( '/^chamber-nation-member-directory.*\.zip$/', $asset['name'] ) ) {
						$package = $asset['browser_download_url'];
						break;
					}
				}
				if ( $package ) {
					$release = array(
						'version'   => ltrim( $data['tag_name'], 'vV' ),
						'package'   => esc_url_raw( $package ),
						'url'       => esc_url_raw( isset( $data['html_url'] ) ? $data['html_url'] : 'https://github.com/' . self::REPO ),
						'notes'     => isset( $data['body'] ) ? (string) $data['body'] : '',
						'published' => isset( $data['published_at'] ) ? (string) $data['published_at'] : '',
					);
				}
			}
		}

		set_transient( self::CACHE_KEY, $release ? $release : array(), $release ? 12 * HOUR_IN_SECONDS : HOUR_IN_SECONDS );
		return $release;
	}

	/**
	 * Provide update data for this plugin.
	 *
	 * @param array|false $update      Update data from other filters.
	 * @param array       $plugin_data Plugin headers.
	 * @param string      $plugin_file Plugin basename.
	 * @return array|false
	 */
	public static function check_update( $update, $plugin_data, $plugin_file ) {
		if ( plugin_basename( CNMD_FILE ) !== $plugin_file ) {
			return $update;
		}

		$release = self::latest_release();
		if ( ! $release ) {
			return $update;
		}

		return array(
			'slug'         => self::slug(),
			'version'      => $release['version'],
			'url'          => $release['url'],
			'package'      => $release['package'],
			'requires_php' => '7.4',
		);
	}

	/**
	 * Content for the "View details" popup on the Plugins screen.
	 *
	 * @param false|object|array $result Result.
	 * @param string             $action API action.
	 * @param object             $args   Request args.
	 * @return false|object|array
	 */
	public static function plugin_info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action || ! isset( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}

		$release = self::latest_release();

		return (object) array(
			'name'          => 'Chamber Nation Member Directory',
			'slug'          => self::slug(),
			'version'       => $release ? $release['version'] : CNMD_VERSION,
			'author'        => '<a href="https://chambernation.com/">Chamber Nation</a>',
			'homepage'      => 'https://github.com/' . self::REPO,
			'requires'      => '6.2',
			'requires_php'  => '7.4',
			'download_link' => $release ? $release['package'] : '',
			'last_updated'  => $release ? $release['published'] : '',
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Server-rendered member directory for ChamberOrganizer / ECTownUSA chambers, with member pages, schema.org markup and llms.txt for search engines and AI crawlers.', 'chamber-nation-member-directory' ) . '</p>',
				'changelog'   => $release && $release['notes'] ? wpautop( esc_html( $release['notes'] ) ) : '',
			),
		);
	}

	/**
	 * Forget the cached release after updates.
	 */
	public static function flush() {
		delete_transient( self::CACHE_KEY );
	}
}
