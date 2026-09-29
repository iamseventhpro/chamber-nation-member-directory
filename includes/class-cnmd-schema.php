<?php
/**
 * schema.org JSON-LD for members.
 *
 * Hidden fields (per the hide_* settings) are left out here too.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds LocalBusiness / Organization structured data.
 */
class CNMD_Schema {

	/**
	 * ItemList for a directory page.
	 *
	 * @param array[] $items    Each: array( 'member' => ..., 'url' => member page URL or '' ).
	 * @param array   $settings Settings.
	 * @param string  $page_url Canonical URL.
	 * @param string  $name     List name.
	 * @param int     $offset   Position offset (pagination).
	 * @return array
	 */
	public static function item_list( array $items, array $settings, $page_url, $name, $offset = 0 ) {
		$elements = array();
		foreach ( array_values( $items ) as $i => $item ) {
			$elements[] = array(
				'@type'    => 'ListItem',
				'position' => $offset + $i + 1,
				'item'     => self::business( $item['member'], $settings, $item['url'], false ),
			);
		}

		return apply_filters(
			'cnmd_schema_item_list',
			array(
				'@context'        => 'https://schema.org',
				'@type'           => 'ItemList',
				'name'            => $name,
				'url'             => $page_url,
				'numberOfItems'   => count( $elements ),
				'itemListElement' => $elements,
			),
			$items
		);
	}

	/**
	 * Graph for a member page: the business plus breadcrumbs.
	 *
	 * @param array  $member      Member.
	 * @param array  $settings    Settings.
	 * @param string $page_url    Member page URL.
	 * @param array  $breadcrumbs Each: array( 'name', 'url' ).
	 * @return array
	 */
	public static function member_page( array $member, array $settings, $page_url, array $breadcrumbs ) {
		$crumbs = array();
		foreach ( array_values( $breadcrumbs ) as $i => $crumb ) {
			$crumbs[] = array(
				'@type'    => 'ListItem',
				'position' => $i + 1,
				'name'     => $crumb['name'],
				'item'     => $crumb['url'],
			);
		}

		$business                     = self::business( $member, $settings, $page_url, true );
		$business['mainEntityOfPage'] = $page_url;

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				$business,
				array(
					'@type'           => 'BreadcrumbList',
					'itemListElement' => $crumbs,
				),
			),
		);
	}

	/**
	 * One member as LocalBusiness (or Organization for nonprofits).
	 *
	 * @param array  $member   Member.
	 * @param array  $settings Settings.
	 * @param string $page_url Member page URL on this site, or ''.
	 * @param bool   $full     Include the full description.
	 * @return array
	 */
	public static function business( array $member, array $settings, $page_url, $full ) {
		$external = CNMD_Links::external_profile( $member, $settings );
		$type     = preg_match( '/501|non-?profit/i', $member['type'] ) ? 'Organization' : 'LocalBusiness';

		$data = array(
			'@type' => $type,
			'@id'   => ( $page_url ? $page_url : ( $external ? $external : home_url( '/' ) ) ) . '#business-' . $member['id'],
			'name'  => $member['name'],
		);

		// The external profile is the source of truth.
		if ( $external ) {
			$data['url'] = $external;
		} elseif ( $page_url ) {
			$data['url'] = $page_url;
		}

		$same_as = array();
		if ( $page_url && $external ) {
			$same_as[] = $page_url;
		}
		if ( $member['website'] && ! $settings['hide_website'] ) {
			$same_as[] = $member['website'];
		}
		$same_as = array_merge( $same_as, array_values( $member['social'] ) );
		if ( $same_as ) {
			$data['sameAs'] = array_values( array_unique( $same_as ) );
		}

		if ( $member['logo'] ) {
			$data['logo']  = $member['logo'];
			$data['image'] = $member['logo'];
		}

		$about = CNMD_Links::about( $member, $settings );
		if ( $about ) {
			$data['description'] = $full ? $about : wp_html_excerpt( str_replace( "\n", ' ', $about ), 300, '…' );
		}

		if ( $member['phone'] && ! $settings['hide_phone_h'] ) {
			$data['telephone'] = $member['phone'];
		}
		if ( $member['email'] && ! $settings['hide_email'] ) {
			$data['email'] = $member['email'];
		}

		if ( ! $settings['hide_address'] ) {
			$address = array_filter(
				array(
					'@type'           => 'PostalAddress',
					'streetAddress'   => trim( $member['street'] . ' ' . $member['street2'] ),
					'addressLocality' => $member['city'],
					'addressRegion'   => $member['state'],
					'postalCode'      => $member['zip'],
					'addressCountry'  => $settings['default_country'],
				)
			);
			if ( count( $address ) > 2 ) {
				$data['address'] = $address;
			}
			if ( null !== $member['lat'] && null !== $member['lng'] ) {
				$data['geo'] = array(
					'@type'     => 'GeoCoordinates',
					'latitude'  => $member['lat'],
					'longitude' => $member['lng'],
				);
			}
		}

		if ( $member['cats'] && ! $settings['hide_primary_category'] ) {
			$data['knowsAbout'] = $member['cats'];
		}

		if ( $member['rep'] && ! $settings['hide_name_position'] ) {
			$person = array(
				'@type' => 'Person',
				'name'  => $member['rep'],
			);
			if ( $member['position'] ) {
				$person['jobTitle'] = $member['position'];
			}
			$data['employee'] = $person;
		}

		$data['memberOf'] = array(
			'@type' => 'Organization',
			'name'  => get_bloginfo( 'name' ),
			'url'   => home_url( '/' ),
		);

		return apply_filters( 'cnmd_schema_business', $data, $member );
	}

	/**
	 * JSON-LD script tag, safe inline.
	 *
	 * @param array $data Data.
	 * @return string
	 */
	public static function script_tag( array $data ) {
		$json = wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP );
		return $json ? '<script type="application/ld+json">' . $json . "</script>\n" : '';
	}
}
