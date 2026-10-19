<?php
/**
 * Verification System Cleanup / Safe Uninstallation
 *
 * @package VerificationSystem
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Table names to remove upon uninstall
$tables = array(
	$wpdb->prefix . 'vs_branches',
	$wpdb->prefix . 'vs_employees',
	$wpdb->prefix . 'vs_schedules',
	$wpdb->prefix . 'vs_attendance_sessions',
	$wpdb->prefix . 'vs_attendance_events',
	$wpdb->prefix . 'vs_wifi_configs',
	$wpdb->prefix . 'vs_wifi_logs',
	$wpdb->prefix . 'vs_audit_logs',
	$wpdb->prefix . 'vs_leave_requests',
	$wpdb->prefix . 'vs_corrections',
	$wpdb->prefix . 'vs_otps',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

// Remove plugin options
delete_option( 'vs_db_version' );
delete_option( 'vs_app_page_id' );
delete_option( 'vs_timezone' );
delete_option( 'vs_country_code' );
delete_option( 'vs_currency' );
delete_option( 'vs_sms_provider' );
delete_option( 'vs_wifi_mode' );

// Delete application page
$page_id = get_option( 'vs_app_page_id' );
if ( $page_id ) {
	wp_delete_post( $page_id, true );
}
