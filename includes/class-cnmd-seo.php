<?php
/**
 * Canonical URLs, titles, descriptions and robots directives for directory
 * views and member pages, including Yoast SEO, Rank Math and AIOSEO.
 *
 * - Member pages: own canonical, "Name – Category" title, About excerpt as
 *   description. Unknown/removed members are noindex.
 * - Category, A–Z and page-number views: indexable with their own canonical.
 * - Keyword / city / ZIP searches: noindex, follow (endless combinations).
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * SEO integration.
 */
class CNMD_SEO {

	/**
	 * Register hooks.
	 */
	public static function init() {
		foreach ( array( 'get_canonical_url', 'wpseo_canonical', 'rank_math/frontend/canonical', 'aioseo_canonical_url' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'canonical' ), 20 );
		}

		add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ), 20 );
		foreach ( array( 'wpseo_title', 'rank_math/frontend/title', 'aioseo_title' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'title_string' ), 20 );
		}

		foreach ( array( 'wpseo_metadesc', 'rank_math/frontend/description', 'aioseo_description' ) as $hook ) {
			add_filter( $hook, array( __CLASS__, 'description' ), 20 );
		}
		add_action( 'wp_head', array( __CLASS__, 'print_description' ), 1 );

		add_action( 'template_redirect', array( __CLASS__, 'redirect_out_of_range' ), 5 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ), 20 );
		add_filter( 'wpseo_robots', array( __CLASS__, 'robots_string' ), 20 );
		add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'robots_rank_math' ), 20 );
	}

	/**
	 * Whether the current page contains the directory shortcode.
	 *
	 * @return bool
	 */
	public static function page_has_directory() {
		static $has = null;
		if ( null === $has ) {
			$post = is_singular() ? get_queried_object() : null;
			$has  = $post instanceof WP_Post && has_shortcode( $post->post_content, CNMD_Plugin::SHORTCODE );
		}
		return $has;
	}

	/**
	 * Directory state for the request, using saved settings.
	 *
	 * @return array
	 */
	private static function state() {
		return CNMD_Directory::request_state( CNMD_Settings::sanitize( CNMD_Settings::get() ) );
	}

	/**
	 * Send page numbers past the end to the last page (301).
	 */
	public static function redirect_out_of_range() {
		if ( ! self::page_has_directory() || CNMD_Member_Page::is_request() || (int) get_query_var( CNMD_Directory::QV['page'] ) < 2 ) {
			return;
		}
		$directory = new CNMD_Directory( CNMD_Settings::sanitize( CNMD_Settings::get() ) );
		$pages     = $directory->total_pages();
		$state     = self::state();
		if ( $pages && $state['page'] > $pages ) {
			wp_safe_redirect( $directory->url( array( 'page' => $pages ), false ), 301 );
			exit;
		}
	}

	/**
	 * Canonical URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function canonical( $url ) {
		if ( ! $url || ! self::page_has_directory() ) {
			return $url;
		}

		if ( CNMD_Member_Page::is_request() ) {
			$member_url = CNMD_Member_Page::current_url();
			return $member_url ? $member_url : $url;
		}

		$state = self::state();
		$args  = array();
		if ( '' !== $state['cat'] && ! $state['locked'] ) {
			$args[ CNMD_Directory::QV['cat'] ] = $state['cat'];
		}
		if ( '' !== $state['alpha'] ) {
			$args[ CNMD_Directory::QV['alpha'] ] = $state['alpha'];
		}
		if ( $state['page'] > 1 ) {
			$args[ CNMD_Directory::QV['page'] ] = $state['page'];
		}
		return $args ? add_query_arg( $args, $url ) : $url;
	}

	/**
	 * Title prefix for the current view, or ''.
	 *
	 * @return string
	 */
	private static function title_label() {
		if ( ! self::page_has_directory() ) {
			return '';
		}

		$member = CNMD_Member_Page::current_member();
		if ( $member ) {
			$settings = CNMD_Settings::get();
			return $member['name'] . ( $member['cats'] && ! $settings['hide_primary_category'] ? ' – ' . $member['cats'][0] : '' );
		}
		if ( CNMD_Member_Page::is_request() ) {
			return '';
		}

		$state = self::state();
		$parts = array();
		if ( '' !== $state['cat'] && ! $state['locked'] ) {
			$categories = ( new CNMD_API( CNMD_Settings::sanitize( CNMD_Settings::get() ) ) )->categories();
			if ( is_array( $categories ) && isset( $categories[ $state['cat'] ] ) ) {
				$parts[] = $categories[ $state['cat'] ];
			}
		}
		if ( '' !== $state['alpha'] ) {
			/* translators: %s: letter */
			$parts[] = sprintf( __( 'Starting with %s', 'chamber-nation-member-directory' ), strtoupper( $state['alpha'] ) );
		}
		if ( $state['page'] > 1 ) {
			/* translators: %d: page number */
			$parts[] = sprintf( __( 'Page %d', 'chamber-nation-member-directory' ), $state['page'] );
		}
		return implode( ' – ', $parts );
	}

	/**
	 * Core title parts.
	 *
	 * @param array $parts Parts.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$label = self::title_label();
		if ( $label && isset( $parts['title'] ) ) {
			$parts['title'] = CNMD_Member_Page::current_member() ? $label : $label . ' – ' . $parts['title'];
		}
		return $parts;
	}

	/**
	 * SEO plugin title.
	 *
	 * @param string $title Title.
	 * @return string
	 */
	public static function title_string( $title ) {
		$label = self::title_label();
		return $label && is_string( $title ) && false === strpos( $title, $label ) ? $label . ' – ' . $title : $title;
	}

	/**
	 * Member page description (About excerpt), or ''.
	 *
	 * @return string
	 */
	private static function member_description() {
		$member = CNMD_Member_Page::current_member();
		if ( ! $member ) {
			return '';
		}
		$settings = CNMD_Settings::sanitize( CNMD_Settings::get() );
		$about    = CNMD_Links::about( $member, $settings );
		if ( $about ) {
			return wp_html_excerpt( str_replace( "\n", ' ', $about ), 155, '…' );
		}
		$bits = array_filter( array( $member['cats'] ? $member['cats'][0] : '', $member['city'] ) );
		/* translators: 1: business name, 2: category and city, 3: chamber name */
		return sprintf( __( '%1$s – %2$s. Member of %3$s.', 'chamber-nation-member-directory' ), $member['name'], implode( ', ', $bits ), get_bloginfo( 'name' ) );
	}

	/**
	 * SEO plugin description filter.
	 *
	 * @param string $description Description.
	 * @return string
	 */
	public static function description( $description ) {
		$ours = self::member_description();
		return $ours ? $ours : $description;
	}

	/**
	 * Print a meta description when no SEO plugin handles it.
	 */
	public static function print_description() {
		if ( defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || ! self::page_has_directory() ) {
			return;
		}
		$description = self::member_description();
		if ( $description ) {
			echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
		}
	}

	/**
	 * Whether the view should be noindex.
	 *
	 * @return bool
	 */
	private static function should_noindex() {
		if ( ! self::page_has_directory() ) {
			return false;
		}
		if ( CNMD_Member_Page::is_request() ) {
			return ! CNMD_Member_Page::current_member();
		}
		$state = self::state();
		return '' !== $state['search'] || '' !== $state['city'] || '' !== $state['zip'] || ( '' !== $state['ttd'] && $state['ttd'] !== CNMD_Settings::get()['things_to_do'] );
	}

	/**
	 * Core robots.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( self::should_noindex() ) {
			unset( $robots['index'] );
			$robots['noindex'] = true;
			$robots['follow']  = true;
		}
		return $robots;
	}

	/**
	 * Yoast robots.
	 *
	 * @param string $robots Directives.
	 * @return string
	 */
	public static function robots_string( $robots ) {
		return self::should_noindex() ? 'noindex, follow' : $robots;
	}

	/**
	 * Rank Math robots.
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public static function robots_rank_math( $robots ) {
		if ( self::should_noindex() ) {
			$robots['index']  = 'noindex';
			$robots['follow'] = 'follow';
		}
		return $robots;
	}
}
