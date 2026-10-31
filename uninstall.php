<?php
/**
 * Uninstall cleanup.
 *
 * @package MaximumLocalDashboard
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'ml_settings' );
delete_option( 'ml_service_account_json' );

global $wpdb;

$like_patterns = array(
	$wpdb->esc_like( '_transient_ml_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_ml_' ) . '%',
	$wpdb->esc_like( '_transient_mld_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_mld_' ) . '%',
);

foreach ( $like_patterns as $like ) {
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- uninstall cleanup of plugin transients only.
	$wpdb->query(
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
			$like
		)
	);
}
