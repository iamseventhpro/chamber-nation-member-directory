<?php
/**
 * XML sitemap of member pages.
 *
 * Served at /cn-directory-sitemap.xml and listed in robots.txt, so it works
 * whether sitemaps come from WordPress core, Yoast, Rank Math or nothing.
 * It is also added to the core, Yoast and Rank Math sitemap indexes.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Member sitemap.
 */
class CNMD_Sitemap {

	/**
	 * Query var.
	 */
	const QV = 'cnmd_sitemap';

	/**
	 * Path.
	 */
	const PATH = 'cn-directory-sitemap.xml';

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_filter( 'query_vars', array( __CLASS__, 'query_vars' ) );
		add_action( 'template_redirect', array( __CLASS__, 'serve' ), 0 );
		add_filter( 'robots_txt', array( __CLASS__, 'robots_txt' ), 20, 2 );
		add_action( 'wp_sitemaps_init', array( __CLASS__, 'register_core_provider' ) );
		add_filter( 'wpseo_sitemap_index', array( __CLASS__, 'index_entry' ) );
		add_filter( 'rank_math/sitemap/index', array( __CLASS__, 'index_entry' ) );
	}

	/**
	 * Whether the sitemap is enabled.
	 *
	 * @return bool
	 */
	public static function enabled() {
		$settings = CNMD_Settings::get();
		return $settings['sitemap'] && $settings['member_pages'] && $settings['directory_page'] && '' !== $settings['org_id'];
	}

	/**
	 * Rewrite rule.
	 */
	public static function add_rewrite_rule() {
		add_rewrite_rule( '^' . preg_quote( self::PATH, '#' ) . '$', 'index.php?' . self::QV . '=1', 'top' );
	}

	/**
	 * Query var.
	 *
	 * @param string[] $vars Vars.
	 * @return string[]
	 */
	public static function query_vars( $vars ) {
		$vars[] = self::QV;
		return $vars;
	}

	/**
	 * Public sitemap URL.
	 *
	 * @return string
	 */
	public static function url() {
		return get_option( 'permalink_structure' ) ? home_url( '/' . self::PATH ) : add_query_arg( self::QV, '1', home_url( '/' ) );
	}

	/**
	 * Member page URLs with last-modified times.
	 *
	 * @return array[] Each: array( 'loc', 'lastmod' ).
	 */
	public static function entries() {
		$settings = CNMD_Settings::sanitize( CNMD_Settings::get() );
		$snap     = CNMD_Store::get( $settings );
		if ( is_wp_error( $snap ) ) {
			return array();
		}

		$lastmod = gmdate( 'c', (int) $snap['time'] );
		$entries = array();
		foreach ( $snap['members'] as $id => $member ) {
			if ( ! CNMD_Links::has_member_page( $member, $settings ) ) {
				continue;
			}
			$url = CNMD_Links::member_url( CNMD_Store::slug( $snap, $id ), $settings );
			if ( $url ) {
				$entries[] = array(
					'loc'     => $url,
					'lastmod' => $lastmod,
				);
			}
		}
		return $entries;
	}

	/**
	 * Output the XML sitemap.
	 */
	public static function serve() {
		if ( ! get_query_var( self::QV ) ) {
			return;
		}
		if ( ! self::enabled() ) {
			status_header( 404 );
			exit;
		}

		status_header( 200 );
		header( 'Content-Type: application/xml; charset=UTF-8' );
		header( 'X-Robots-Tag: noindex, follow' );

		echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
		echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
		echo '<url><loc>' . esc_url( CNMD_Links::directory_url() ) . '</loc></url>' . "\n";
		foreach ( self::entries() as $entry ) {
			echo '<url><loc>' . esc_url( $entry['loc'] ) . '</loc><lastmod>' . esc_html( $entry['lastmod'] ) . '</lastmod></url>' . "\n";
		}
		echo '</urlset>';
		exit;
	}

	/**
	 * Add the sitemap to robots.txt.
	 *
	 * @param string $output Robots.txt.
	 * @param bool   $public Whether the site is public.
	 * @return string
	 */
	public static function robots_txt( $output, $public ) {
		if ( $public && self::enabled() ) {
			$output .= "\nSitemap: " . esc_url_raw( self::url() ) . "\n";
		}
		return $output;
	}

	/**
	 * Add to Yoast / Rank Math sitemap indexes.
	 *
	 * @param string $xml Index entries.
	 * @return string
	 */
	public static function index_entry( $xml ) {
		if ( self::enabled() ) {
			$xml .= '<sitemap><loc>' . esc_url( self::url() ) . '</loc></sitemap>' . "\n";
		}
		return $xml;
	}

	/**
	 * Register a core sitemap provider (wp-sitemap.xml).
	 *
	 * @param WP_Sitemaps $sitemaps Sitemaps.
	 */
	public static function register_core_provider( $sitemaps ) {
		if ( ! self::enabled() || ! class_exists( 'WP_Sitemaps_Provider' ) ) {
			return;
		}
		require_once CNMD_DIR . 'includes/class-cnmd-core-sitemap-provider.php';
		$sitemaps->registry->add_provider( 'cnmembers', new CNMD_Core_Sitemap_Provider() );
	}
}
