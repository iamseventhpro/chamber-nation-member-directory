<?php
/**
 * Settings: defaults, storage and sanitizing.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings store. The same sanitizer handles the admin form and per-page
 * shortcode overrides.
 */
class CNMD_Settings {

	/**
	 * Option name.
	 */
	const OPTION = 'cnmd_settings';

	/**
	 * Boolean keys.
	 *
	 * @var string[]
	 */
	private static $booleans = array(
		'member_pages',
		'hide_email',
		'hide_phone_h',
		'hide_address',
		'hide_website',
		'hide_primary_category',
		'hide_name_position',
		'hide_branches',
		'hide_designations',
		'hide_contact_form',
		'disable_radius_filter',
		'geolocate',
		'hide_alpha_until_filtered',
		'llms_txt',
		'sitemap',
	);

	/**
	 * Defaults.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'org_id'                    => '',
			'server'                    => 'org',
			'refresh_minutes'           => 60,
			'directory_page'            => 0,
			'member_pages'              => 1,
			'initial_view'              => 'list',
			'sorting'                   => 'random',
			'per_page'                  => 12,
			'directory_type'            => 'all',
			'single_cat_id'             => '',
			'about_field'               => 'yes',
			'default_image'             => '',
			'bootstrap'                 => 'auto',
			'hide_email'                => 0,
			'hide_phone_h'              => 0,
			'hide_address'              => 0,
			'hide_website'              => 0,
			'hide_primary_category'     => 0,
			'hide_name_position'        => 0,
			'hide_branches'             => 0,
			'hide_designations'         => 0,
			'hide_contact_form'         => 0,
			'disable_radius_filter'     => 0,
			'geolocate'                 => 0,
			'hide_alpha_until_filtered' => 0,
			'things_to_do'              => '',
			'exclude'                   => '',
			'landing_url'               => 'mlp',
			'docuteam_cname'            => 'https://docu.team',
			'llms_txt'                  => 1,
			'sitemap'                   => 1,
			'default_country'           => 'US',
		);
	}

	/**
	 * Platform keys (API path segment) and labels.
	 *
	 * @return string[]
	 */
	public static function servers() {
		return apply_filters(
			'cnmd_servers',
			array(
				'org'    => 'ChamberOrganizer (chamberorganizer.com)',
				'ectown' => 'ECTownUSA (ectownusa.net)',
			)
		);
	}

	/**
	 * Refresh interval choices in minutes.
	 *
	 * @return array
	 */
	public static function refresh_choices() {
		return array(
			15  => __( 'Every 15 minutes', 'chamber-nation-member-directory' ),
			60  => __( 'Every hour', 'chamber-nation-member-directory' ),
			360 => __( 'Every 6 hours', 'chamber-nation-member-directory' ),
		);
	}

	/**
	 * Saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get() {
		$saved = get_option( self::OPTION, array() );
		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * Interpret yes/no/true/1 style values.
	 *
	 * @param mixed $value Value.
	 * @return int
	 */
	public static function to_bool( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'yes', 'true', 'on' ), true ) ? 1 : 0;
	}

	/**
	 * Sanitize a full settings array. Missing booleans count as off, which is
	 * how unchecked checkboxes arrive.
	 *
	 * @param mixed $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();
		$get      = static function ( $key ) use ( $input, $defaults ) {
			return isset( $input[ $key ] ) && is_scalar( $input[ $key ] ) ? trim( (string) $input[ $key ] ) : (string) $defaults[ $key ];
		};
		$pick     = static function ( $key, array $allowed ) use ( $get, $defaults ) {
			$value = strtolower( $get( $key ) );
			return in_array( $value, $allowed, true ) ? $value : $defaults[ $key ];
		};

		$server  = sanitize_key( $get( 'server' ) );
		$refresh = absint( $get( 'refresh_minutes' ) );
		$country = strtoupper( preg_replace( '/[^A-Za-z]/', '', $get( 'default_country' ) ) );
		$cat     = $get( 'single_cat_id' );

		$clean = array(
			'org_id'          => sanitize_text_field( $get( 'org_id' ) ),
			'server'          => array_key_exists( $server, self::servers() ) ? $server : $defaults['server'],
			'refresh_minutes' => array_key_exists( $refresh, self::refresh_choices() ) ? $refresh : $defaults['refresh_minutes'],
			'directory_page'  => absint( $get( 'directory_page' ) ),
			'initial_view'    => $pick( 'initial_view', array( 'list', 'categories' ) ),
			'sorting'         => $pick( 'sorting', array( 'random', 'alphabetically' ) ),
			'per_page'        => min( 60, max( 3, absint( $get( 'per_page' ) ) ) ),
			'directory_type'  => $pick( 'directory_type', array( 'all', 'single' ) ),
			'single_cat_id'   => ctype_digit( $cat ) ? $cat : '',
			'about_field'     => $pick( 'about_field', array( 'yes', 'no', 'none' ) ),
			'default_image'   => esc_url_raw( $get( 'default_image' ) ),
			'bootstrap'       => $pick( 'bootstrap', array( 'auto', 'always', 'never' ) ),
			'things_to_do'    => sanitize_text_field( $get( 'things_to_do' ) ),
			'exclude'         => sanitize_text_field( $get( 'exclude' ) ),
			'landing_url'     => $pick( 'landing_url', array( 'mlp', 'docuteam' ) ),
			'docuteam_cname'  => esc_url_raw( untrailingslashit( $get( 'docuteam_cname' ) ) ),
			'default_country' => 2 === strlen( $country ) ? $country : '',
		);
		if ( '' === $clean['docuteam_cname'] ) {
			$clean['docuteam_cname'] = $defaults['docuteam_cname'];
		}

		foreach ( self::$booleans as $key ) {
			$clean[ $key ] = isset( $input[ $key ] ) ? self::to_bool( $input[ $key ] ) : 0;
		}

		return $clean;
	}

	/**
	 * Whether two settings arrays point at the same member data source.
	 *
	 * @param array $a Settings.
	 * @param array $b Settings.
	 * @return bool
	 */
	public static function same_source( array $a, array $b ) {
		return $a['org_id'] === $b['org_id'] && $a['server'] === $b['server'];
	}
}
