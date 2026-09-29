<?php
/**
 * Plugin bootstrap.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Wires everything together.
 */
final class CNMD_Plugin {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'cn_directory';

	/**
	 * Bootstrap stylesheet used when the theme doesn't load Bootstrap.
	 */
	const BOOTSTRAP_CSS = 'https://docu.team/theme/assets/css/theme.min.css';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register_shortcode' ) );
		add_filter( 'query_vars', array( __CLASS__, 'register_query_vars' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'register_assets' ), 5 );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_for_page' ), 100 );
		add_filter( 'cn_llms_txt_enabled', array( __CLASS__, 'llms_enabled' ) );
		add_filter( 'cn_llms_txt_sections', array( __CLASS__, 'llms_sections' ), 10, 2 );

		CNMD_Store::init();
		CNMD_Member_Page::init();
		CNMD_SEO::init();
		CNMD_Sitemap::init();
		CNMD_Updater::init();

		if ( is_admin() ) {
			CNMD_Admin::init();
		}
	}

	/**
	 * Activation: find the directory page, schedule refreshes, add rules.
	 */
	public static function activate() {
		$settings = CNMD_Settings::get();
		if ( ! $settings['directory_page'] ) {
			$page = self::find_directory_page();
			if ( $page ) {
				$settings['directory_page'] = $page;
				update_option( CNMD_Settings::OPTION, CNMD_Settings::sanitize( $settings ) );
			}
		}
		CNMD_Store::ensure_cron();
		CN_Llms_Txt::add_rewrite_rules();
		CNMD_Member_Page::add_rewrite_rule();
		CNMD_Sitemap::add_rewrite_rule();
		flush_rewrite_rules( false );
	}

	/**
	 * Deactivation.
	 */
	public static function deactivate() {
		CNMD_Store::clear_cron();
		flush_rewrite_rules( false );
	}

	/**
	 * First published page containing the shortcode.
	 *
	 * @return int
	 */
	public static function find_directory_page() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off lookup on activation/settings save.
		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[' . self::SHORTCODE ) . '%'
			)
		);
	}

	/**
	 * Register the shortcode.
	 */
	public static function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( 'CNMD_Directory', 'shortcode' ) );
	}

	/**
	 * Public query vars.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function register_query_vars( $vars ) {
		$vars   = array_merge( $vars, array_values( CNMD_Directory::QV ) );
		$vars[] = CNMD_Links::QV_MEMBER;
		return $vars;
	}

	/**
	 * Asset version from file modification time.
	 *
	 * @param string $path Relative path.
	 * @return string
	 */
	public static function asset_version( $path ) {
		$mtime = @filemtime( CNMD_DIR . $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		return CNMD_VERSION . ( $mtime ? '.' . $mtime : '' );
	}

	/**
	 * Register assets.
	 */
	public static function register_assets() {
		wp_register_style( 'cnmd-bootstrap', self::BOOTSTRAP_CSS, array(), '5.2.2' );
		wp_register_style( 'cnmd-directory', CNMD_URL . 'assets/css/directory.css', array(), self::asset_version( 'assets/css/directory.css' ) );
		wp_register_script(
			'cnmd-directory',
			CNMD_URL . 'assets/js/directory.js',
			array(),
			self::asset_version( 'assets/js/directory.js' ),
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}

	/**
	 * Enqueue early on pages with the shortcode (after the theme's own
	 * assets, so Bootstrap detection sees them).
	 */
	public static function enqueue_for_page() {
		if ( CNMD_SEO::page_has_directory() ) {
			self::enqueue_assets( CNMD_Settings::sanitize( CNMD_Settings::get() ) );
		}
	}

	/**
	 * Enqueue the directory's assets.
	 *
	 * @param array $settings Settings.
	 */
	public static function enqueue_assets( array $settings ) {
		if ( 'always' === $settings['bootstrap'] || ( 'auto' === $settings['bootstrap'] && ! self::bootstrap_present() ) ) {
			// Enqueued first, so the directory's own CSS comes after it.
			wp_enqueue_style( 'cnmd-bootstrap' );
		}
		wp_enqueue_style( 'cnmd-directory' );
		wp_enqueue_script( 'cnmd-directory' );
	}

	/**
	 * Whether the theme or another plugin already loads Bootstrap.
	 *
	 * @return bool
	 */
	public static function bootstrap_present() {
		foreach ( array( wp_styles(), wp_scripts() ) as $deps ) {
			foreach ( array_merge( $deps->queue, $deps->done ) as $handle ) {
				if ( 'cnmd-bootstrap' === $handle ) {
					continue;
				}
				$src = isset( $deps->registered[ $handle ] ) ? (string) $deps->registered[ $handle ]->src : '';
				if ( false !== stripos( $handle, 'bootstrap' ) || false !== stripos( $src, 'bootstrap' ) ) {
					return true;
				}
			}
		}
		return (bool) apply_filters( 'cnmd_bootstrap_present', false );
	}

	/**
	 * Opt in to /llms.txt.
	 *
	 * @param bool $enabled Enabled.
	 * @return bool
	 */
	public static function llms_enabled( $enabled ) {
		$settings = CNMD_Settings::get();
		return $enabled || ( $settings['llms_txt'] && '' !== $settings['org_id'] );
	}

	/**
	 * Member directory section of llms.txt / llms-full.txt.
	 *
	 * @param array[] $sections Sections.
	 * @param string  $type     index or full.
	 * @return array[]
	 */
	public static function llms_sections( $sections, $type ) {
		$settings = CNMD_Settings::sanitize( CNMD_Settings::get() );
		if ( ! $settings['llms_txt'] || '' === $settings['org_id'] ) {
			return $sections;
		}
		$snap = CNMD_Store::get( $settings );
		if ( is_wp_error( $snap ) ) {
			return $sections;
		}

		$dir_url = CNMD_Links::directory_url();
		$site    = CN_Llms_Txt::line( get_bloginfo( 'name' ) );
		$count   = count( $snap['members'] );

		if ( 'index' === $type ) {
			/* translators: 1: chamber name, 2: number of members */
			$body = sprintf( __( '%1$s has %2$d member businesses and organizations.', 'chamber-nation-member-directory' ), $site, $count ) . "\n\n";
			if ( $dir_url ) {
				$body .= '- [' . __( 'Member Directory', 'chamber-nation-member-directory' ) . '](' . $dir_url . '): ' . __( 'searchable by name, category, A–Z and location; each member has its own page.', 'chamber-nation-member-directory' ) . "\n";
			}
			$body .= '- [' . __( 'Full member list', 'chamber-nation-member-directory' ) . '](' . CN_Llms_Txt::url( 'full' ) . '): ' . __( 'every member with category, address, phone, website and profile link.', 'chamber-nation-member-directory' ) . "\n";

			$categories = ( new CNMD_API( $settings ) )->categories();
			if ( $dir_url && is_array( $categories ) && $categories ) {
				$body .= "\n### " . __( 'Categories', 'chamber-nation-member-directory' ) . "\n\n";
				foreach ( $categories as $id => $name ) {
					$body .= '- [' . CN_Llms_Txt::line( $name ) . '](' . add_query_arg( CNMD_Directory::QV['cat'], $id, $dir_url ) . ")\n";
				}
			}
		} else {
			$members = $snap['members'];
			uasort(
				$members,
				static function ( $a, $b ) {
					return strnatcasecmp( $a['name'], $b['name'] );
				}
			);
			$body = '';
			foreach ( $members as $id => $member ) {
				$body .= '### ' . CN_Llms_Txt::line( $member['name'] ) . "\n\n";
				if ( $member['cats'] && ! $settings['hide_primary_category'] ) {
					$body .= '- ' . __( 'Category', 'chamber-nation-member-directory' ) . ': ' . CN_Llms_Txt::line( implode( ', ', $member['cats'] ) ) . "\n";
				}
				if ( ! $settings['hide_address'] && CNMD_Links::address_line( $member ) ) {
					$body .= '- ' . __( 'Address', 'chamber-nation-member-directory' ) . ': ' . CN_Llms_Txt::line( CNMD_Links::address_line( $member ) ) . "\n";
				}
				if ( $member['phone'] && ! $settings['hide_phone_h'] ) {
					$body .= '- ' . __( 'Phone', 'chamber-nation-member-directory' ) . ': ' . CN_Llms_Txt::line( $member['phone'] ) . "\n";
				}
				if ( $member['website'] && ! $settings['hide_website'] ) {
					$body .= '- ' . __( 'Website', 'chamber-nation-member-directory' ) . ': ' . $member['website'] . "\n";
				}
				if ( CNMD_Links::has_member_page( $member, $settings ) ) {
					$page = CNMD_Links::member_url( CNMD_Store::slug( $snap, $id ), $settings );
					if ( $page ) {
						$body .= '- ' . __( 'Member page', 'chamber-nation-member-directory' ) . ': ' . $page . "\n";
					}
					$body .= '- ' . __( 'Full profile', 'chamber-nation-member-directory' ) . ': ' . CNMD_Links::external_profile( $member, $settings ) . "\n";
				}
				$about = CNMD_Links::about( $member, $settings );
				if ( $about ) {
					$body .= "\n" . CN_Llms_Txt::line( wp_html_excerpt( $about, 500, '…' ) ) . "\n";
				}
				$body .= "\n";
			}
		}

		$sections[] = array(
			/* translators: %d: number of members */
			'title' => 'full' === $type ? sprintf( __( 'Member Directory (%d members)', 'chamber-nation-member-directory' ), $count ) : __( 'Member Directory', 'chamber-nation-member-directory' ),
			'body'  => $body,
		);
		return $sections;
	}
}
