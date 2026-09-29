<?php
/**
 * Member pages at /{directory-page}/{member-slug}/.
 *
 * The rewrite rule points back at the directory page itself, so the theme
 * renders it normally and the [cn_directory] shortcode prints the member
 * overview instead of the list. The member's external profile stays the
 * source of truth and is linked prominently.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Member page routing, lookup, status codes and titles.
 */
class CNMD_Member_Page {

	/**
	 * Resolved lookup for this request: null = not looked up yet.
	 *
	 * @var array|null array( 'status' => found|gone|missing, 'member', 'slug' ).
	 */
	private static $lookup = null;

	/**
	 * Register hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'add_rewrite_rule' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rules' ), 99 );
		add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
		add_filter( 'the_title', array( __CLASS__, 'filter_the_title' ), 10, 2 );
		add_action( 'post_updated', array( __CLASS__, 'watch_directory_page' ), 10, 1 );
	}

	/**
	 * Route /{directory-page-path}/{slug}/ to the directory page.
	 */
	public static function add_rewrite_rule() {
		$page_id = (int) CNMD_Settings::get()['directory_page'];
		if ( ! $page_id || ! CNMD_Settings::get()['member_pages'] ) {
			return;
		}
		$path = get_page_uri( $page_id );
		if ( $path ) {
			add_rewrite_rule(
				'^' . preg_quote( $path, '#' ) . '/([^/]+)/?$',
				'index.php?page_id=' . $page_id . '&' . CNMD_Links::QV_MEMBER . '=$matches[1]',
				'top'
			);
		}
	}

	/**
	 * Flush rewrite rules once after the directory page or settings change.
	 */
	public static function maybe_flush_rules() {
		// Updates don't run the activation hook, so also flush on version change.
		if ( get_option( 'cnmd_rules_version' ) !== CNMD_VERSION ) {
			update_option( 'cnmd_rules_version', CNMD_VERSION, false );
			update_option( 'cnmd_flush_rules', 1, false );
		}
		if ( get_option( 'cnmd_flush_rules' ) ) {
			delete_option( 'cnmd_flush_rules' );
			flush_rewrite_rules( false );
		}
	}

	/**
	 * Re-flush when the directory page's slug or parent changes.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function watch_directory_page( $post_id ) {
		if ( (int) CNMD_Settings::get()['directory_page'] === (int) $post_id ) {
			update_option( 'cnmd_flush_rules', 1, false );
		}
	}

	/**
	 * Whether this request is for a member page.
	 *
	 * @return bool
	 */
	public static function is_request() {
		$page_id = (int) CNMD_Settings::get()['directory_page'];
		return $page_id && '' !== (string) get_query_var( CNMD_Links::QV_MEMBER ) && (int) get_queried_object_id() === $page_id;
	}

	/**
	 * Look up the requested member in the snapshot (cached per request).
	 *
	 * @return array
	 */
	public static function lookup() {
		if ( null !== self::$lookup ) {
			return self::$lookup;
		}

		$slug         = sanitize_title( rawurldecode( (string) get_query_var( CNMD_Links::QV_MEMBER ) ) );
		self::$lookup = array(
			'status' => 'missing',
			'member' => null,
			'slug'   => $slug,
		);

		$settings = CNMD_Settings::sanitize( CNMD_Settings::get() );
		$snap     = CNMD_Store::get( $settings );
		if ( is_wp_error( $snap ) ) {
			self::$lookup['status'] = 'error';
			return self::$lookup;
		}

		if ( isset( $snap['by_slug'][ $slug ] ) ) {
			$member = $snap['members'][ $snap['by_slug'][ $slug ] ];
			if ( CNMD_Links::has_member_page( $member, $settings ) ) {
				self::$lookup['status'] = 'found';
				self::$lookup['member'] = $member;
			}
		} elseif ( isset( $snap['gone'][ $slug ] ) ) {
			self::$lookup['status'] = 'gone';
		}

		return self::$lookup;
	}

	/**
	 * The member for this request, or null.
	 *
	 * @return array|null
	 */
	public static function current_member() {
		if ( ! self::is_request() ) {
			return null;
		}
		$lookup = self::lookup();
		return 'found' === $lookup['status'] ? $lookup['member'] : null;
	}

	/**
	 * Send 410 for removed members and 404 for unknown slugs.
	 */
	public static function template_redirect() {
		if ( ! self::is_request() ) {
			return;
		}
		$lookup = self::lookup();
		if ( 'gone' === $lookup['status'] ) {
			status_header( 410 );
			nocache_headers();
		} elseif ( 'missing' === $lookup['status'] ) {
			status_header( 404 );
			nocache_headers();
		} elseif ( 'error' === $lookup['status'] ) {
			status_header( 503 );
			header( 'Retry-After: 300' );
		}
	}

	/**
	 * Show the member's name as the page heading.
	 *
	 * @param string $title   Title.
	 * @param int    $post_id Post ID.
	 * @return string
	 */
	public static function filter_the_title( $title, $post_id = 0 ) {
		if ( is_admin() || (int) $post_id !== (int) get_queried_object_id() || ! ( in_the_loop() || wp_is_block_theme() ) ) {
			return $title;
		}
		$member = self::current_member();
		return $member ? $member['name'] : $title;
	}

	/**
	 * Member page URL for the current request.
	 *
	 * @return string
	 */
	public static function current_url() {
		$member = self::current_member();
		return $member ? CNMD_Links::member_url( self::$lookup['slug'], CNMD_Settings::sanitize( CNMD_Settings::get() ) ) : '';
	}

	/**
	 * Render the member page body (called from the shortcode).
	 *
	 * @param array $settings Settings from the shortcode.
	 * @return string
	 */
	public static function render( array $settings ) {
		$lookup        = self::lookup();
		$directory_url = CNMD_Links::directory_url();

		if ( 'found' !== $lookup['status'] ) {
			$message = 'gone' === $lookup['status']
				? __( 'This business is no longer listed in our member directory.', 'chamber-nation-member-directory' )
				: ( 'error' === $lookup['status']
					? __( 'The member directory is temporarily unavailable. Please check back soon.', 'chamber-nation-member-directory' )
					: __( 'We couldn\'t find that member.', 'chamber-nation-member-directory' ) );

			return '<div class="cnmd-root"><div class="card"><div class="card-body text-center p-5"><p>' . esc_html( $message ) . '</p>'
				. '<a class="btn btn-primary text-white" href="' . esc_url( $directory_url ) . '">' . esc_html__( 'Browse the member directory', 'chamber-nation-member-directory' ) . '</a></div></div></div>';
		}

		$member   = $lookup['member'];
		$page_url = self::current_url();
		$external = CNMD_Links::external_profile( $member, $settings );

		// The directory page's own title (our title filter would return the member name).
		remove_filter( 'the_title', array( __CLASS__, 'filter_the_title' ), 10 );
		$directory_title = get_the_title( (int) CNMD_Settings::get()['directory_page'] );
		add_filter( 'the_title', array( __CLASS__, 'filter_the_title' ), 10, 2 );

		$breadcrumbs = array(
			array(
				'name' => $directory_title,
				'url'  => $directory_url,
			),
			array(
				'name' => $member['name'],
				'url'  => $page_url,
			),
		);

		$html = CNMD_Directory::load_template(
			'member-page',
			array(
				'member'        => $member,
				'settings'      => $settings,
				'external'      => $external,
				'about'         => CNMD_Links::about( $member, $settings ),
				'badges'        => CNMD_Links::badges( $member, $settings ),
				'directory_url' => $directory_url,
				'breadcrumbs'   => $breadcrumbs,
			)
		);

		return $html . CNMD_Schema::script_tag( CNMD_Schema::member_page( $member, $settings, $page_url, $breadcrumbs ) );
	}
}
