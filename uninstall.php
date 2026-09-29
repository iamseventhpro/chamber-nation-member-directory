<?php
/**
 * Remove plugin data on uninstall.
 *
 * @package ChamberNationMemberDirectory
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

foreach ( array( 'cnmd_settings', 'cnmd_sources', 'cnmd_status', 'cnmd_flush_rules', 'cnmd_rules_version' ) as $cnmd_option ) {
	delete_option( $cnmd_option );
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-time cleanup of this plugin's data.
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( 'cnmd_snap_' ) . '%',
		$wpdb->esc_like( '_transient_cnmd_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_cnmd_' ) . '%'
	)
);
// phpcs:enable

delete_transient( 'cn_llms_index' );
delete_transient( 'cn_llms_full' );
wp_clear_scheduled_hook( 'cnmd_refresh_snapshots' );
