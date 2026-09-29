<?php
/**
 * Member pages in WordPress core sitemaps (wp-sitemap.xml).
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core sitemap provider.
 */
class CNMD_Core_Sitemap_Provider extends WP_Sitemaps_Provider {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->name        = 'cnmembers';
		$this->object_type = 'cnmembers';
	}

	/**
	 * URL list for a page.
	 *
	 * @param int    $page_num       Page.
	 * @param string $object_subtype Unused.
	 * @return array[]
	 */
	public function get_url_list( $page_num, $object_subtype = '' ) {
		$per_page = wp_sitemaps_get_max_urls( $this->object_type );
		return array_slice( CNMD_Sitemap::entries(), ( max( 1, (int) $page_num ) - 1 ) * $per_page, $per_page );
	}

	/**
	 * Number of pages.
	 *
	 * @param string $object_subtype Unused.
	 * @return int
	 */
	public function get_max_num_pages( $object_subtype = '' ) {
		return (int) ceil( count( CNMD_Sitemap::entries() ) / wp_sitemaps_get_max_urls( $this->object_type ) );
	}
}
