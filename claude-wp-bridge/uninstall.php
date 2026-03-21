<?php
/**
 * Claude WP Bridge — Uninstall Script
 *
 * Runs when the plugin is deleted (not just deactivated) from WordPress.
 * Removes all plugin data: options, database tables, and transients.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Exit if not called by WordPress.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Remove all plugin options.
$options = array(
	'cwpb_enabled',
	'cwpb_execution_mode',
	'cwpb_rate_limit',
	'cwpb_rate_limit_window',
	'cwpb_ip_whitelist',
	'cwpb_block_dangerous',
	'cwpb_max_execution_time',
	'cwpb_max_output_size',
	'cwpb_log_retention_days',
	'cwpb_api_key_hash',
	'cwpb_api_key_plain',
	'cwpb_api_key_prefix',
	'cwpb_api_key_created',
);

foreach ( $options as $option ) {
	delete_option( $option );
}

// Remove the audit log table.
$table_name = $wpdb->prefix . 'cwpb_audit_log';
$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $table_name ) );

// Clean up any transients.
$wpdb->query( $wpdb->prepare(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
	$wpdb->esc_like( '_transient_cwpb_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_cwpb_' ) . '%'
) );
