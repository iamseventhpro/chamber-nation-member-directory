<?php
/**
 * External landing links, member page URLs and badge images.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Link helpers.
 */
class CNMD_Links {

	/**
	 * Member page query var.
	 */
	const QV_MEMBER = 'cnmd_member';

	/**
	 * The member's external profile (the source of truth), using the
	 * widget's priority: alt launch URL, DocuTeam landing page, member
	 * landing page (mlp), catalog URL.
	 *
	 * @param array $member   Member.
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function external_profile( array $member, array $settings ) {
		if ( $member['alt_launch'] ) {
			return $member['alt_launch'];
		}
		if ( 'docuteam' === $settings['landing_url'] && $member['catalog_url'] ) {
			return self::docuteam_landing( $member['catalog_url'], $settings['docuteam_cname'] );
		}
		if ( $member['mlp'] ) {
			return $member['mlp'];
		}
		return $member['catalog_url'];
	}

	/**
	 * Convert a DocuTeam catalog URL (…/slug/?store=123) into its landing
	 * page (/slug/index.php?v=lp&store=123) on the configured domain.
	 *
	 * @param string $catalog Catalog URL.
	 * @param string $cname   DocuTeam domain.
	 * @return string
	 */
	public static function docuteam_landing( $catalog, $cname ) {
		$parts = wp_parse_url( $catalog );
		$path  = isset( $parts['path'] ) ? array_values( array_filter( explode( '/', $parts['path'] ) ) ) : array();
		$slug  = $path ? end( $path ) : '';
		if ( '' === $slug ) {
			return $catalog;
		}

		$query = array();
		if ( isset( $parts['query'] ) ) {
			wp_parse_str( $parts['query'], $query );
		}

		$url = untrailingslashit( $cname ? $cname : 'https://docu.team' ) . '/' . rawurlencode( $slug ) . '/index.php?v=lp';
		if ( ! empty( $query['store'] ) && is_scalar( $query['store'] ) ) {
			$url .= '&store=' . rawurlencode( (string) $query['store'] );
		}
		return esc_url_raw( $url );
	}

	/**
	 * Whether member pages are available for these settings. They live under
	 * the configured directory page, for the saved data source only.
	 *
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function member_pages_enabled( array $settings ) {
		$saved = CNMD_Settings::get();
		return $saved['member_pages'] && $saved['directory_page'] && CNMD_Settings::same_source( $settings, $saved )
			&& 'publish' === get_post_status( $saved['directory_page'] );
	}

	/**
	 * Whether a member qualifies for its own page. Members without an
	 * external profile are listed but get no page, to avoid thin pages.
	 *
	 * @param array $member   Member.
	 * @param array $settings Settings.
	 * @return bool
	 */
	public static function has_member_page( array $member, array $settings ) {
		return '' !== self::external_profile( $member, $settings );
	}

	/**
	 * URL of a member's page on this site, or ''.
	 *
	 * @param string $slug     Member slug.
	 * @param array  $settings Settings.
	 * @return string
	 */
	public static function member_url( $slug, array $settings ) {
		if ( '' === $slug || ! self::member_pages_enabled( $settings ) ) {
			return '';
		}
		$page_id = (int) CNMD_Settings::get()['directory_page'];
		if ( get_option( 'permalink_structure' ) ) {
			return trailingslashit( get_permalink( $page_id ) ) . rawurlencode( $slug ) . '/';
		}
		return add_query_arg( self::QV_MEMBER, rawurlencode( $slug ), get_permalink( $page_id ) );
	}

	/**
	 * Directory page URL.
	 *
	 * @return string
	 */
	public static function directory_url() {
		$page_id = (int) CNMD_Settings::get()['directory_page'];
		return $page_id ? get_permalink( $page_id ) : '';
	}

	/**
	 * Google Maps search link for an address.
	 *
	 * @param array $member Member.
	 * @return string
	 */
	public static function maps_url( array $member ) {
		$parts = array_filter( array( $member['name'], $member['street'], $member['street2'], $member['city'], $member['state'], $member['zip'] ) );
		return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( implode( ', ', $parts ) );
	}

	/**
	 * One-line address.
	 *
	 * @param array $member Member.
	 * @return string
	 */
	public static function address_line( array $member ) {
		return trim( implode( ' ', array_filter( array( $member['street'], $member['street2'], $member['city'], $member['state'], $member['zip'] ) ) ) );
	}

	/**
	 * Badge images for branches/designations that exist on the badge server.
	 * Existence checks are cached for 30 days, so pages never wait on them
	 * more than once per badge name.
	 *
	 * @param array $member   Member.
	 * @param array $settings Settings.
	 * @return array[] Each: array( 'name', 'url' ).
	 */
	public static function badges( array $member, array $settings ) {
		$names = array();
		if ( ! $settings['hide_designations'] ) {
			$names = array_merge( $names, $member['designations'] );
		}
		if ( ! $settings['hide_branches'] ) {
			$names = array_merge( $names, $member['branches'] );
		}
		if ( ! $names ) {
			return array();
		}

		$known   = get_transient( 'cnmd_badges' );
		$known   = is_array( $known ) ? $known : array();
		$changed = false;
		$badges  = array();

		foreach ( array_unique( $names ) as $name ) {
			$file = sanitize_file_name( strtolower( str_replace( ' ', '_', $name ) ) ) . '.png';
			$url  = 'https://stage.docu.team/images/' . rawurlencode( $file );
			if ( ! isset( $known[ $file ] ) ) {
				$response        = wp_remote_head( $url, array( 'timeout' => 3 ) );
				$known[ $file ]  = ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
				$changed         = true;
			}
			if ( $known[ $file ] ) {
				$badges[] = array(
					'name' => $name,
					'url'  => $url,
				);
			}
		}

		if ( $changed ) {
			set_transient( 'cnmd_badges', $known, 30 * DAY_IN_SECONDS );
		}
		return $badges;
	}

	/**
	 * About text chosen by the about_field setting.
	 *
	 * @param array $member   Member.
	 * @param array $settings Settings.
	 * @return string
	 */
	public static function about( array $member, array $settings ) {
		if ( 'yes' === $settings['about_field'] ) {
			return $member['about_us'] ? $member['about_us'] : $member['profile'];
		}
		if ( 'no' === $settings['about_field'] ) {
			return $member['profile'];
		}
		return '';
	}
}
