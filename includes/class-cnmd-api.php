<?php
/**
 * Server-side client for the Chamber Widgets members API.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Members and categories requests plus record normalization.
 */
class CNMD_API {

	/**
	 * API base URL.
	 */
	const API_BASE = 'https://auth.chamberwidgets.com/cn-api/';

	/**
	 * Live search cache lifetime.
	 */
	const SEARCH_TTL = 900;

	/**
	 * Settings.
	 *
	 * @var array
	 */
	private $settings;

	/**
	 * Constructor.
	 *
	 * @param array $settings Sanitized settings.
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * Fetch every member (used for the background snapshot).
	 *
	 * @return array|WP_Error Normalized records keyed by member ID.
	 */
	public function fetch_all() {
		return $this->members_request( array(), 60 );
	}

	/**
	 * Live search (keyword, category, city/ZIP radius, things to do),
	 * cached for 15 minutes with a one-day fallback on API errors.
	 *
	 * @param array $filters keyword, city, zip, distance, category, things_to_do.
	 * @return array|WP_Error Normalized records keyed by member ID.
	 */
	public function search( array $filters ) {
		$hash      = md5( wp_json_encode( array( $this->settings['server'], $this->settings['org_id'], $filters ) ) );
		$fresh_key = 'cnmd_q_' . $hash;
		$stale_key = 'cnmd_qs_' . $hash;

		$cached = get_transient( $fresh_key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$result = $this->members_request( $filters, 20 );
		if ( is_wp_error( $result ) ) {
			$stale = get_transient( $stale_key );
			return is_array( $stale ) ? $stale : $result;
		}

		// Search caches only need what cards show, so trim long text.
		$trimmed = array();
		foreach ( $result as $id => $member ) {
			$member['about_us'] = wp_html_excerpt( $member['about_us'], 600, '…' );
			$member['profile']  = wp_html_excerpt( $member['profile'], 600, '…' );
			$trimmed[ $id ]     = $member;
		}

		set_transient( $fresh_key, $trimmed, self::SEARCH_TTL );
		set_transient( $stale_key, $trimmed, DAY_IN_SECONDS );
		return $trimmed;
	}

	/**
	 * Category list sorted by name.
	 *
	 * @return array|WP_Error ID => name.
	 */
	public function categories() {
		if ( '' === $this->settings['org_id'] ) {
			return new WP_Error( 'cnmd_no_org', __( 'No Organization ID is configured.', 'chamber-nation-member-directory' ) );
		}

		$key    = 'cnmd_cats_' . md5( $this->settings['server'] . '|' . $this->settings['org_id'] );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$url  = add_query_arg( 'org_id', rawurlencode( $this->settings['org_id'] ), self::API_BASE . rawurlencode( $this->settings['server'] ) . '/mms/mms_categories' );
		$data = $this->request( 'GET', $url, null, 20 );
		if ( is_wp_error( $data ) ) {
			$stale = get_transient( $key . '_s' );
			return is_array( $stale ) ? $stale : $data;
		}

		$categories = array();
		foreach ( $data as $id => $name ) {
			if ( is_scalar( $name ) && ctype_digit( (string) $id ) ) {
				$categories[ (string) $id ] = self::clean_text( $name );
			}
		}
		natcasesort( $categories );

		set_transient( $key, $categories, 6 * HOUR_IN_SECONDS );
		set_transient( $key . '_s', $categories, WEEK_IN_SECONDS );
		return $categories;
	}

	/**
	 * POST to the members endpoint using the widget's request format.
	 *
	 * @param array $f       Filters.
	 * @param int   $timeout Seconds.
	 * @return array|WP_Error
	 */
	private function members_request( array $f, $timeout ) {
		if ( '' === $this->settings['org_id'] ) {
			return new WP_Error( 'cnmd_no_org', __( 'No Organization ID is configured. Set it under CN Directory in the dashboard.', 'chamber-nation-member-directory' ) );
		}

		$f = wp_parse_args(
			$f,
			array(
				'keyword'      => '',
				'city'         => '',
				'zip'          => '',
				'distance'     => '',
				'category'     => '',
				'things_to_do' => '',
			)
		);

		$search = 'keyword=' . rawurlencode( $f['keyword'] )
			. '&city=' . rawurlencode( $f['city'] )
			. '&zip=' . rawurlencode( $f['zip'] )
			. '&distance=' . rawurlencode( ( $f['city'] || $f['zip'] ) ? ( $f['distance'] ? $f['distance'] : '75' ) : '' )
			. '&business_category=' . rawurlencode( $f['category'] )
			. '&things_to_do=' . rawurlencode( $f['things_to_do'] )
			. '&org_id=' . rawurlencode( $this->settings['org_id'] )
			. '&limit=10000';

		$data = $this->request(
			'POST',
			self::API_BASE . rawurlencode( $this->settings['server'] ) . '/mms/mms_members',
			array( 'searchValues' => $search ),
			$timeout
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$members = array();
		foreach ( isset( $data['results'] ) ? (array) $data['results'] : array() as $id => $raw ) {
			$member = $this->normalize( (string) $id, $raw );
			if ( $member ) {
				$members[ $member['id'] ] = $member;
			}
		}
		return $members;
	}

	/**
	 * HTTP request returning decoded JSON.
	 *
	 * @param string     $method  GET or POST.
	 * @param string     $url     URL.
	 * @param array|null $body    Form fields.
	 * @param int        $timeout Seconds.
	 * @return array|WP_Error
	 */
	private function request( $method, $url, $body, $timeout ) {
		$args = array(
			'method'  => $method,
			'timeout' => $timeout,
			'headers' => array(
				'Accept'  => 'application/json',
				'Referer' => home_url( '/' ),
			),
		);
		if ( null !== $body ) {
			$args['body'] = $body;
		}

		$response = wp_remote_request( $url, apply_filters( 'cnmd_http_args', $args, $url ) );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			/* translators: %d: HTTP status code */
			return new WP_Error( 'cnmd_http', sprintf( __( 'The directory API returned HTTP %d.', 'chamber-nation-member-directory' ), $code ) );
		}

		$raw  = trim( wp_remote_retrieve_body( $response ) );
		$data = json_decode( $raw, true );
		if ( ! is_array( $data ) ) {
			// Errors such as "Org Not Found - XYZ" arrive as plain text.
			return new WP_Error(
				'cnmd_api_message',
				/* translators: %s: message returned by the API */
				sprintf( __( 'The directory API said: %s', 'chamber-nation-member-directory' ), wp_html_excerpt( sanitize_text_field( $raw ), 200, '…' ) )
			);
		}
		return $data;
	}

	/**
	 * Convert an API record into the internal member shape.
	 *
	 * @param string $id  Member ID (the result key).
	 * @param mixed  $raw API record.
	 * @return array|null
	 */
	public static function normalize( $id, $raw ) {
		if ( ! is_array( $raw ) ) {
			return null;
		}

		$s = static function ( $key ) use ( $raw ) {
			return isset( $raw[ $key ] ) && is_scalar( $raw[ $key ] ) ? trim( (string) $raw[ $key ] ) : '';
		};

		$name = self::clean_text( $s( 'employer' ) );
		if ( '' === $name ) {
			return null;
		}

		$cats = array();
		foreach ( array( 'business_cat_id', 'business_cat_id_2', 'business_cat_id_3', 'business_cat_id_4', 'business_cat_id_5' ) as $key ) {
			$cat = self::clean_text( $s( $key ) );
			if ( '' !== $cat && ! in_array( $cat, $cats, true ) ) {
				$cats[] = $cat;
			}
		}

		$logo = esc_url_raw( $s( 'logo' ) );
		if ( ! $logo && $s( 'mphoto_path' ) ) {
			$logo = esc_url_raw( 'https://' . ltrim( preg_replace( '#^https?://#i', '', $s( 'mphoto_path' ) ), '/' ) );
		}

		$social = array();
		$decoded = json_decode( $s( 'social_media' ), true );
		if ( is_array( $decoded ) ) {
			foreach ( $decoded as $network => $link ) {
				$link = is_scalar( $link ) ? esc_url_raw( self::with_protocol( (string) $link ) ) : '';
				if ( $link ) {
					$social[ sanitize_key( $network ) ] = $link;
				}
			}
		}

		$list = static function ( $value ) {
			if ( is_array( $value ) ) {
				$value = implode( ',', array_filter( $value, 'is_scalar' ) );
			}
			return array_values( array_filter( array_map( array( __CLASS__, 'clean_text' ), explode( ',', (string) $value ) ) ) );
		};

		$first = preg_replace( '/^[^a-z0-9]+/i', '', remove_accents( $name ) );

		return array(
			'id'              => $id,
			'name'            => $name,
			'letter'          => strtolower( substr( '' !== $first ? $first : $name, 0, 1 ) ),
			'type'            => self::clean_text( $s( 'member_type' ) ),
			'active'          => 'A' === $s( 'status' ),
			'cats'            => $cats,
			'rep'             => trim( self::clean_text( $s( 'first_name' ) ) . ' ' . self::clean_text( $s( 'last_name' ) ) ),
			'position'        => self::clean_text( $s( 'position' ) ? $s( 'position' ) : $s( 'title' ) ),
			'phone'           => self::clean_text( $s( 'phone_h' ) ),
			'email'           => sanitize_email( $s( 'email' ) ),
			'website'         => esc_url_raw( self::with_protocol( $s( 'url' ) ) ),
			'street'          => self::clean_text( $s( 'address' ) ),
			'street2'         => self::clean_text( $s( 'address2' ) ? $s( 'address2' ) : $s( 'address_2' ) ),
			'city'            => self::clean_text( $s( 'city' ) ),
			'state'           => self::clean_text( $s( 'state' ) ),
			'zip'             => self::clean_text( $s( 'zip' ) ),
			'lat'             => is_numeric( $s( 'lat' ) ) && 0.0 !== (float) $s( 'lat' ) ? (float) $s( 'lat' ) : null,
			'lng'             => is_numeric( $s( 'lng' ) ) && 0.0 !== (float) $s( 'lng' ) ? (float) $s( 'lng' ) : null,
			'logo'            => $logo,
			'about_us'        => wp_html_excerpt( self::about_text( isset( $raw['about_us'] ) ? $raw['about_us'] : '' ), 4000, '…' ),
			'profile'         => wp_html_excerpt( self::html_to_text( $s( 'profile' ) ), 4000, '…' ),
			'texting_keyword' => self::clean_text( $s( 'texting_keyword' ) ),
			'texting_number'  => self::clean_text( $s( 'texting_number' ) ),
			'contact_form'    => esc_url_raw( self::with_protocol( $s( 'contact_form' ) ) ),
			'alt_launch'      => esc_url_raw( self::with_protocol( $s( 'url_alt_launch' ) ) ),
			'catalog_url'     => esc_url_raw( self::with_protocol( $s( 'catalog_url' ) ) ),
			'mlp'             => esc_url_raw( self::with_protocol( $s( 'mlp' ) ) ),
			'social'          => $social,
			'branches'        => $list( isset( $raw['branches'] ) ? $raw['branches'] : '' ),
			'designations'    => $list( isset( $raw['designations'] ) ? $raw['designations'] : '' ),
		);
	}

	/**
	 * about_us is usually HTML wrapped in a JSON string.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function about_text( $value ) {
		if ( is_string( $value ) ) {
			$decoded = json_decode( $value, true );
			if ( is_string( $decoded ) ) {
				$value = $decoded;
			}
		}
		$text = self::html_to_text( is_scalar( $value ) ? (string) $value : '' );
		return 'undefined' === strtolower( $text ) ? '' : $text;
	}

	/**
	 * Strip HTML, CSS and scripts to plain text.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		$html = preg_replace( '#<br\s*/?>|</p>|</div>|</li>#i', "\n", (string) $html );
		$text = wp_strip_all_tags( html_entity_decode( $html, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = preg_replace( '/\{[^}]*\}/', ' ', $text );
		$text = str_replace( "\xC2\xA0", ' ', $text );
		$text = preg_replace( "/[ \t]+/u", ' ', $text );
		$text = preg_replace( "/\s*\n\s*/u", "\n", $text );
		return trim( self::fix_encoding( $text ) );
	}

	/**
	 * Decode entities and repair double-encoded UTF-8.
	 *
	 * @param mixed $str Text.
	 * @return string
	 */
	public static function clean_text( $str ) {
		$str = html_entity_decode( (string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return sanitize_text_field( self::fix_encoding( $str ) );
	}

	/**
	 * Repair UTF-8 that was read as Latin-1 (the widget's fixEncoding()).
	 *
	 * @param string $str Text.
	 * @return string
	 */
	private static function fix_encoding( $str ) {
		if ( '' !== $str && preg_match( '/[\x{80}-\x{FF}]/u', $str ) && ! preg_match( '/[^\x{00}-\x{FF}]/u', $str ) ) {
			$bytes = '';
			foreach ( preg_split( '//u', $str, -1, PREG_SPLIT_NO_EMPTY ) as $char ) {
				$bytes .= 1 === strlen( $char ) ? $char : chr( ( ( ord( $char[0] ) & 0x1F ) << 6 ) | ( ord( $char[1] ) & 0x3F ) );
			}
			if ( preg_match( '//u', $bytes ) ) {
				return $bytes;
			}
		}
		return $str;
	}

	/**
	 * Add https:// to bare domains.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function with_protocol( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return 'https:' . $url;
		}
		return preg_match( '#^https?://#i', $url ) ? $url : 'https://' . $url;
	}
}
