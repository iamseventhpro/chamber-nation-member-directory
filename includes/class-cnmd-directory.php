<?php
/**
 * Server-side port of the Duda member directory widget.
 *
 * Search, category, A–Z and page number are ordinary URLs, so every view is
 * crawlable HTML. Browsing reads the background snapshot; keyword, category
 * and radius searches go to the live API (cached for 15 minutes).
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the [cn_directory] shortcode.
 */
class CNMD_Directory {

	/**
	 * Query vars (prefixed to avoid clashes with WordPress and other plugins).
	 *
	 * @var string[] state key => query var.
	 */
	const QV = array(
		'search' => 'cnd_search',
		'cat'    => 'cnd_cat',
		'alpha'  => 'cnd_alpha',
		'city'   => 'cnd_city',
		'zip'    => 'cnd_zip',
		'dist'   => 'cnd_dist',
		'ttd'    => 'cnd_ttd',
		'page'   => 'cnd_page',
	);

	/**
	 * The widget's parameter names, still accepted so old links keep working.
	 *
	 * @var string[]
	 */
	const LEGACY = array(
		'search' => 'search',
		'cat'    => 'cname',
		'alpha'  => 'alpha',
		'city'   => 'city',
		'zip'    => 'zip',
		'dist'   => 'distance',
		'ttd'    => 'things_to_do',
	);

	/**
	 * Distance choices (miles).
	 */
	const DISTANCES = array( '10', '20', '30', '40', '50', '75' );

	/**
	 * Rendered instances (for unique IDs).
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Request state.
	 *
	 * @var array
	 */
	private $state;

	/**
	 * Page URL without directory query args.
	 *
	 * @var string
	 */
	private $base_url;

	/**
	 * Instance HTML id.
	 *
	 * @var string
	 */
	private $uid;

	/**
	 * Shortcode callback. Any setting can be overridden as an attribute.
	 *
	 * @param array|string $atts Attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts     = is_array( $atts ) ? array_change_key_case( $atts, CASE_LOWER ) : array();
		$settings = CNMD_Settings::sanitize( shortcode_atts( CNMD_Settings::get(), $atts, CNMD_Plugin::SHORTCODE ) );

		CNMD_Plugin::enqueue_assets( $settings );

		// On the directory page, /directory/{slug}/ shows a member page.
		$slug = (string) get_query_var( CNMD_Links::QV_MEMBER );
		if ( '' !== $slug && CNMD_Member_Page::is_request() ) {
			return CNMD_Member_Page::render( $settings );
		}

		$directory = new self( $settings );
		return $directory->render();
	}

	/**
	 * Constructor.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->state    = self::request_state( $settings );
		$this->uid      = 'cn-directory';

		$base           = in_the_loop() || is_singular() ? get_permalink() : '';
		$this->base_url = remove_query_arg( array_merge( array_values( self::QV ), array_values( self::LEGACY ) ), $base ? $base : home_url( '/' ) );
	}

	/**
	 * Read and validate the request.
	 *
	 * @param array $settings Settings.
	 * @return array
	 */
	public static function request_state( array $settings ) {
		$read = static function ( $key ) {
			$value = (string) get_query_var( self::QV[ $key ] );
			if ( '' === $value && isset( self::LEGACY[ $key ] ) && isset( $_GET[ self::LEGACY[ $key ] ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Public read-only filters.
				$value = sanitize_text_field( wp_unslash( $_GET[ self::LEGACY[ $key ] ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			}
			return trim( sanitize_text_field( $value ) );
		};

		$state = array(
			'search' => mb_substr( $read( 'search' ), 0, 50 ),
			'cat'    => ctype_digit( $read( 'cat' ) ) ? $read( 'cat' ) : '',
			'alpha'  => preg_match( '/^[a-z0-9]$/i', $read( 'alpha' ) ) ? strtolower( $read( 'alpha' ) ) : '',
			'city'   => mb_substr( $read( 'city' ), 0, 60 ),
			'zip'    => preg_replace( '/[^A-Za-z0-9 \-]/', '', mb_substr( $read( 'zip' ), 0, 10 ) ),
			'dist'   => in_array( $read( 'dist' ), self::DISTANCES, true ) ? $read( 'dist' ) : '75',
			'ttd'    => mb_substr( $read( 'ttd' ), 0, 60 ),
			'page'   => max( 1, absint( get_query_var( self::QV['page'] ) ) ),
			'locked' => false,
		);

		if ( '' === $state['ttd'] ) {
			$state['ttd'] = $settings['things_to_do'];
		}
		if ( $settings['disable_radius_filter'] ) {
			$state['city'] = '';
			$state['zip']  = '';
		}
		if ( 'single' === $settings['directory_type'] && '' !== $settings['single_cat_id'] ) {
			$state['cat']    = $settings['single_cat_id'];
			$state['locked'] = true;
		}

		return $state;
	}

	/**
	 * Whether any filter besides the page number is active.
	 *
	 * @param array $state State.
	 * @return bool
	 */
	public static function has_filters( array $state ) {
		return '' !== $state['search'] || '' !== $state['cat'] || '' !== $state['alpha'] || '' !== $state['city'] || '' !== $state['zip'] || '' !== $state['ttd'];
	}

	/**
	 * Whether the request needs the live API rather than the snapshot.
	 *
	 * @param array $state State.
	 * @return bool
	 */
	public static function is_live( array $state ) {
		return '' !== $state['search'] || '' !== $state['cat'] || '' !== $state['city'] || '' !== $state['zip'] || '' !== $state['ttd'];
	}

	/**
	 * Render.
	 *
	 * @return string
	 */
	public function render() {
		++self::$instances;
		$this->uid = 1 === self::$instances ? 'cn-directory' : 'cn-directory-' . self::$instances;
		$settings  = $this->settings;
		$state     = $this->state;

		if ( '' === $settings['org_id'] ) {
			return current_user_can( 'manage_options' )
				? '<div class="cnmd-root"><div class="alert alert-warning">' . esc_html__( 'CN Directory: add your Organization ID under CN Directory in the dashboard.', 'chamber-nation-member-directory' ) . '</div></div>'
				: '';
		}

		$api        = new CNMD_API( $settings );
		$categories = $api->categories();
		$categories = is_wp_error( $categories ) ? array() : $categories;

		$vars = array(
			'uid'        => $this->uid,
			'settings'   => $settings,
			'state'      => $state,
			'categories' => $categories,
			'directory'  => $this,
		);

		// Landing view: search form, A–Z and the category grid.
		if ( 'categories' === $settings['initial_view'] && ! $state['locked'] && ! self::has_filters( $state ) && 1 === $state['page'] ) {
			return $this->load_template( 'categories', $vars );
		}

		$result = $this->members();
		$items  = array();
		$error  = '';
		$total  = 0;
		$pages  = 1;

		if ( is_wp_error( $result ) ) {
			$error = current_user_can( 'manage_options' )
				/* translators: %s: error message */
				? sprintf( __( 'Members could not be loaded: %s', 'chamber-nation-member-directory' ), $result->get_error_message() )
				: __( 'The member directory is temporarily unavailable. Please check back soon.', 'chamber-nation-member-directory' );
		} else {
			$total = count( $result['members'] );
			$pages = max( 1, (int) ceil( $total / $settings['per_page'] ) );
			$page  = min( $state['page'], $pages );
			foreach ( array_slice( $result['members'], ( $page - 1 ) * $settings['per_page'], $settings['per_page'] ) as $member ) {
				$items[] = array(
					'member' => $member,
					'url'    => $this->member_url( $member, $result['snap'] ),
				);
			}
			$this->state['page'] = $page;
		}

		$vars = array_merge(
			$vars,
			array(
				'state'      => $this->state,
				'items'      => $items,
				'total'      => $total,
				'pages'      => $pages,
				'error'      => $error,
				'chips'      => $this->chips( $categories ),
				'pagination' => $this->pagination( $pages ),
				'show_alpha' => ! ( $settings['hide_alpha_until_filtered'] && ! self::has_filters( $state ) ),
				'back_url'   => 'categories' === $settings['initial_view'] ? $this->base_url : '',
			)
		);

		$html = $this->load_template( 'directory', $vars );

		if ( $items ) {
			$html .= CNMD_Schema::script_tag(
				CNMD_Schema::item_list(
					$items,
					$settings,
					$this->canonical_url(),
					$this->list_title( $categories ),
					( $this->state['page'] - 1 ) * $settings['per_page']
				)
			);
		}

		return $html;
	}

	/**
	 * Number of result pages for the current request, or 0 on error.
	 *
	 * @return int
	 */
	public function total_pages() {
		$result = $this->members();
		return is_wp_error( $result ) ? 0 : max( 1, (int) ceil( count( $result['members'] ) / $this->settings['per_page'] ) );
	}

	/**
	 * Filtered and sorted member list.
	 *
	 * @return array|WP_Error array( 'members' => list, 'snap' => snapshot|null ).
	 */
	private function members() {
		$settings = $this->settings;
		$state    = $this->state;
		$snap     = CNMD_Store::get( $settings );

		if ( self::is_live( $state ) ) {
			$members = ( new CNMD_API( $settings ) )->search(
				array(
					'keyword'      => $state['search'],
					'city'         => $state['city'],
					'zip'          => $state['zip'],
					'distance'     => $state['dist'],
					'category'     => $state['cat'],
					'things_to_do' => $state['ttd'],
				)
			);
			if ( is_wp_error( $members ) ) {
				return $members;
			}
			// Prefer the snapshot's full record when the member is in it.
			if ( ! is_wp_error( $snap ) ) {
				foreach ( $members as $id => $member ) {
					if ( isset( $snap['members'][ $id ] ) ) {
						$members[ $id ] = $snap['members'][ $id ];
					}
				}
			}
		} else {
			if ( is_wp_error( $snap ) ) {
				return $snap;
			}
			$members = $snap['members'];
		}

		$excluded = array_filter( array_map( 'trim', explode( ',', strtolower( $settings['exclude'] ) ) ) );
		$members  = array_filter(
			$members,
			static function ( $member ) use ( $state, $excluded ) {
				if ( '' !== $state['alpha'] && $member['letter'] !== $state['alpha'] ) {
					return false;
				}
				foreach ( $excluded as $type ) {
					if ( '' !== $member['type'] && false !== strpos( strtolower( $member['type'] ), $type ) ) {
						return false;
					}
				}
				return true;
			}
		);

		$members = array_values( $members );
		if ( '' !== $state['alpha'] || 'alphabetically' === $settings['sorting'] ) {
			usort(
				$members,
				static function ( $a, $b ) {
					return strnatcasecmp( remove_accents( $a['name'] ), remove_accents( $b['name'] ) );
				}
			);
		} else {
			// Random order that stays fixed for a day, so pages don't overlap.
			$seed = wp_date( 'Y-m-d' );
			usort(
				$members,
				static function ( $a, $b ) use ( $seed ) {
					return strcmp( md5( $seed . $a['id'] ), md5( $seed . $b['id'] ) );
				}
			);
		}

		return array(
			'members' => $members,
			'snap'    => is_wp_error( $snap ) ? null : $snap,
		);
	}

	/**
	 * Member page URL for a card, or ''.
	 *
	 * @param array      $member Member.
	 * @param array|null $snap   Snapshot.
	 * @return string
	 */
	private function member_url( array $member, $snap ) {
		if ( ! CNMD_Links::has_member_page( $member, $this->settings ) ) {
			return '';
		}
		return CNMD_Links::member_url( CNMD_Store::slug( $snap, $member['id'] ), $this->settings );
	}

	/**
	 * Build a directory URL from the current state with overrides.
	 *
	 * @param array $changes  State changes (key => value; '' removes).
	 * @param bool  $fragment Jump to the directory.
	 * @return string
	 */
	public function url( array $changes = array(), $fragment = true ) {
		$state = array_merge( $this->state, array( 'page' => 1 ), $changes );
		$args  = array();

		foreach ( array( 'search', 'cat', 'alpha', 'city', 'zip' ) as $key ) {
			if ( '' !== (string) $state[ $key ] && ! ( 'cat' === $key && $this->state['locked'] ) ) {
				$args[ self::QV[ $key ] ] = $state[ $key ];
			}
		}
		if ( '' !== $state['city'] || '' !== $state['zip'] ) {
			$args[ self::QV['dist'] ] = $state['dist'];
		}
		if ( '' !== $state['ttd'] && $state['ttd'] !== $this->settings['things_to_do'] ) {
			$args[ self::QV['ttd'] ] = $state['ttd'];
		}
		if ( (int) $state['page'] > 1 ) {
			$args[ self::QV['page'] ] = (int) $state['page'];
		}

		$url = $args ? add_query_arg( array_map( 'rawurlencode', $args ), $this->base_url ) : $this->base_url;
		return $fragment ? $url . '#' . $this->uid : $url;
	}

	/**
	 * Canonical URL of the current view.
	 *
	 * @return string
	 */
	public function canonical_url() {
		return $this->url( array( 'page' => $this->state['page'] ), false );
	}

	/**
	 * "You searched" chips, each linking to the view without that filter.
	 *
	 * @param array $categories Categories.
	 * @return array[] Each: array( 'label', 'url' ).
	 */
	private function chips( array $categories ) {
		$s     = $this->state;
		$chips = array();

		if ( '' !== $s['search'] ) {
			$chips[] = array( 'label' => $s['search'], 'url' => $this->url( array( 'search' => '' ) ) );
		}
		if ( '' !== $s['cat'] && ! $s['locked'] ) {
			$name    = isset( $categories[ $s['cat'] ] ) ? $categories[ $s['cat'] ] : $s['cat'];
			/* translators: %s: category name */
			$chips[] = array( 'label' => sprintf( __( 'Category: %s', 'chamber-nation-member-directory' ), $name ), 'url' => $this->url( array( 'cat' => '' ) ) );
		}
		if ( '' !== $s['alpha'] ) {
			/* translators: %s: letter or digit */
			$chips[] = array( 'label' => sprintf( __( 'Alphanumeric: %s', 'chamber-nation-member-directory' ), strtoupper( $s['alpha'] ) ), 'url' => $this->url( array( 'alpha' => '' ) ) );
		}
		if ( '' !== $s['ttd'] && $s['ttd'] !== $this->settings['things_to_do'] ) {
			/* translators: %s: things to do filter */
			$chips[] = array( 'label' => sprintf( __( 'Things To Do: %s', 'chamber-nation-member-directory' ), $s['ttd'] ), 'url' => $this->url( array( 'ttd' => $this->settings['things_to_do'] ) ) );
		}
		if ( '' !== $s['city'] ) {
			/* translators: %s: city */
			$chips[] = array( 'label' => sprintf( __( 'City: %s', 'chamber-nation-member-directory' ), $s['city'] ), 'url' => $this->url( array( 'city' => '' ) ) );
		}
		if ( '' !== $s['zip'] ) {
			/* translators: %s: ZIP code */
			$chips[] = array( 'label' => sprintf( __( 'ZIP: %s', 'chamber-nation-member-directory' ), $s['zip'] ), 'url' => $this->url( array( 'zip' => '' ) ) );
		}
		if ( '' !== $s['city'] || '' !== $s['zip'] ) {
			/* translators: %s: distance in miles */
			$chips[] = array( 'label' => sprintf( __( 'Distance: %smi', 'chamber-nation-member-directory' ), $s['dist'] ), 'url' => $this->url( array( 'city' => '', 'zip' => '' ) ) );
		}

		return $chips;
	}

	/**
	 * Pagination links with ellipses (the widget's getPageList, maxLength 6).
	 *
	 * @param int $total_pages Total pages.
	 * @return array[] Each: array( 'label', 'url', 'current', 'disabled', 'rel' ).
	 */
	private function pagination( $total_pages ) {
		if ( $total_pages < 2 ) {
			return array();
		}

		$page   = $this->state['page'];
		$max    = 6;
		$side   = 1;
		$left   = ( $max - $side * 2 - 3 ) >> 1;
		$right  = ( $max - $side * 2 - 2 ) >> 1;
		$range  = static function ( $a, $b ) {
			return $a <= $b ? range( $a, $b ) : array();
		};

		if ( $total_pages <= $max ) {
			$list = $range( 1, $total_pages );
		} elseif ( $page <= $max - $side - 1 - $right ) {
			$list = array_merge( $range( 1, $max - $side - 1 ), array( 0 ), $range( $total_pages - $side + 1, $total_pages ) );
		} elseif ( $page >= $total_pages - $side - 1 - $right ) {
			$list = array_merge( $range( 1, $side ), array( 0 ), $range( $total_pages - $side - 1 - $right - $left, $total_pages ) );
		} else {
			$list = array_merge( $range( 1, $side ), array( 0 ), $range( $page - $left, $page + $right ), array( 0 ), $range( $total_pages - $side + 1, $total_pages ) );
		}

		$links   = array();
		$links[] = array(
			'label'    => __( 'Prev', 'chamber-nation-member-directory' ),
			'url'      => $page > 1 ? $this->url( array( 'page' => $page - 1 ) ) : '',
			'current'  => false,
			'disabled' => $page <= 1,
			'rel'      => 'prev',
		);
		foreach ( $list as $n ) {
			$links[] = array(
				'label'    => $n ? number_format_i18n( $n ) : '…',
				'url'      => $n && $n !== $page ? $this->url( array( 'page' => $n ) ) : '',
				'current'  => $n === $page,
				'disabled' => 0 === $n,
				'rel'      => '',
			);
		}
		$links[] = array(
			'label'    => __( 'Next', 'chamber-nation-member-directory' ),
			'url'      => $page < $total_pages ? $this->url( array( 'page' => $page + 1 ) ) : '',
			'current'  => false,
			'disabled' => $page >= $total_pages,
			'rel'      => 'next',
		);
		return $links;
	}

	/**
	 * Heading for the current view (also the schema list name).
	 *
	 * @param array $categories Categories.
	 * @return string
	 */
	public function list_title( array $categories ) {
		$s = $this->state;
		if ( '' !== $s['cat'] && isset( $categories[ $s['cat'] ] ) ) {
			return $categories[ $s['cat'] ];
		}
		if ( '' !== $s['alpha'] ) {
			/* translators: %s: letter */
			return sprintf( __( 'Members starting with %s', 'chamber-nation-member-directory' ), strtoupper( $s['alpha'] ) );
		}
		/* translators: %s: site name */
		return sprintf( __( '%s Member Directory', 'chamber-nation-member-directory' ), get_bloginfo( 'name' ) );
	}

	/**
	 * Alphabet bar links.
	 *
	 * @return array[] Each: array( 'label', 'url', 'active' ).
	 */
	public function alphabet() {
		$links = array();
		foreach ( array_merge( range( 0, 9 ), range( 'a', 'z' ) ) as $char ) {
			$char    = (string) $char;
			$links[] = array(
				'label'  => strtoupper( $char ),
				'url'    => $this->url( array( 'alpha' => $char ) ),
				'active' => $char === $this->state['alpha'],
			);
		}
		return $links;
	}

	/**
	 * Category grid grouped by first letter.
	 *
	 * @param array $categories ID => name.
	 * @return array Letter => list of array( 'name', 'url' ).
	 */
	public function category_groups( array $categories ) {
		$groups = array();
		foreach ( $categories as $id => $name ) {
			$letter              = strtoupper( substr( remove_accents( $name ), 0, 1 ) );
			$groups[ $letter ][] = array(
				'name' => $name,
				'url'  => $this->url( array( 'cat' => (string) $id ) ),
			);
		}
		return $groups;
	}

	/**
	 * Form action (the page URL).
	 *
	 * @return string
	 */
	public function form_action() {
		return $this->base_url . '#' . $this->uid;
	}

	/**
	 * Load a template; themes can override at {theme}/cn-directory/{name}.php.
	 *
	 * @param string $name Template name.
	 * @param array  $vars Variables.
	 * @return string
	 */
	public static function load_template( $name, array $vars ) {
		$file = locate_template( array( 'cn-directory/' . $name . '.php' ) );
		if ( ! $file ) {
			$file = CNMD_DIR . 'templates/' . $name . '.php';
		}
		$file = apply_filters( 'cnmd_template', $file, $name );

		ob_start();
		( static function ( $cnmd_file, $cnmd_vars ) {
			extract( $cnmd_vars, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
			include $cnmd_file;
		} )( $file, $vars );
		return (string) ob_get_clean();
	}
}
