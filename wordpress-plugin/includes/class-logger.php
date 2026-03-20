<?php
/**
 * Audit logger for Claude WP Bridge.
 *
 * Records every API request and code execution for security auditing.
 * Logs are stored in a custom database table and viewable from the
 * WordPress admin panel.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_Logger
 *
 * Manages the audit log database table and log entries.
 *
 * @since 1.0.0
 */
class CWPB_Logger {

	/**
	 * The database table name (without prefix).
	 *
	 * @var string
	 */
	const TABLE_NAME = 'cwpb_audit_log';

	/**
	 * Create the audit log database table.
	 *
	 * Called on plugin activation. Uses dbDelta for safe table creation
	 * and future schema upgrades.
	 *
	 * @since 1.0.0
	 */
	public function create_table() {
		global $wpdb;

		$table_name      = $wpdb->prefix . self::TABLE_NAME;
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			timestamp datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			ip_address varchar(45) NOT NULL DEFAULT '',
			endpoint varchar(100) NOT NULL DEFAULT '',
			code_executed longtext DEFAULT NULL,
			result_summary text DEFAULT NULL,
			execution_time_ms int(11) unsigned DEFAULT 0,
			status varchar(20) NOT NULL DEFAULT 'success',
			error_message text DEFAULT NULL,
			PRIMARY KEY (id),
			KEY idx_timestamp (timestamp),
			KEY idx_status (status),
			KEY idx_ip_address (ip_address)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Log an API request.
	 *
	 * @since 1.0.0
	 * @param array $data {
	 *     Log entry data.
	 *
	 *     @type string $ip_address      Client IP address.
	 *     @type string $endpoint        REST API endpoint called.
	 *     @type string $code_executed   The PHP code or query executed.
	 *     @type string $result_summary  Truncated result for the log.
	 *     @type int    $execution_time  Execution time in milliseconds.
	 *     @type string $status          'success', 'error', or 'blocked'.
	 *     @type string $error_message   Error details if status is not 'success'.
	 * }
	 */
	public function log( array $data ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;

		$defaults = array(
			'timestamp'         => current_time( 'mysql' ),
			'ip_address'        => '',
			'endpoint'          => '',
			'code_executed'     => '',
			'result_summary'    => '',
			'execution_time_ms' => 0,
			'status'            => 'success',
			'error_message'     => null,
		);

		$data = wp_parse_args( $data, $defaults );

		// Truncate result_summary to prevent oversized log entries.
		if ( strlen( $data['result_summary'] ) > 1000 ) {
			$data['result_summary'] = substr( $data['result_summary'], 0, 997 ) . '...';
		}

		$wpdb->insert(
			$table_name,
			array(
				'timestamp'         => $data['timestamp'],
				'ip_address'        => sanitize_text_field( $data['ip_address'] ),
				'endpoint'          => sanitize_text_field( $data['endpoint'] ),
				'code_executed'     => $data['code_executed'],
				'result_summary'    => $data['result_summary'],
				'execution_time_ms' => absint( $data['execution_time_ms'] ),
				'status'            => sanitize_key( $data['status'] ),
				'error_message'     => $data['error_message'],
			),
			array( '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
		);
	}

	/**
	 * Get log entries with pagination.
	 *
	 * @since  1.0.0
	 * @param  int    $page     Page number (1-indexed).
	 * @param  int    $per_page Entries per page.
	 * @param  string $status   Optional status filter.
	 * @return array {
	 *     @type array $entries Log entries for the current page.
	 *     @type int   $total   Total number of entries.
	 * }
	 */
	public function get_logs( $page = 1, $per_page = 50, $status = '' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$offset     = ( $page - 1 ) * $per_page;

		$where = '';
		$args  = array();

		if ( ! empty( $status ) ) {
			$where = 'WHERE status = %s';
			$args[] = $status;
		}

		// Get total count.
		$count_sql = "SELECT COUNT(*) FROM {$table_name} {$where}";
		if ( ! empty( $args ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $args );
		}
		$total = (int) $wpdb->get_var( $count_sql );

		// Get entries.
		$query_args   = $args;
		$query_args[] = $per_page;
		$query_args[] = $offset;

		$entries_sql = "SELECT * FROM {$table_name} {$where} ORDER BY timestamp DESC LIMIT %d OFFSET %d";
		$entries     = $wpdb->get_results( $wpdb->prepare( $entries_sql, $query_args ) );

		return array(
			'entries' => $entries,
			'total'   => $total,
		);
	}

	/**
	 * Delete log entries older than the retention period.
	 *
	 * @since 1.0.0
	 */
	public function cleanup_old_logs() {
		global $wpdb;

		$table_name     = $wpdb->prefix . self::TABLE_NAME;
		$retention_days = (int) get_option( 'cwpb_log_retention_days', 30 );

		if ( $retention_days <= 0 ) {
			return; // Retention disabled, keep all logs.
		}

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE timestamp < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$retention_days
			)
		);
	}

	/**
	 * Clear all log entries.
	 *
	 * @since 1.0.0
	 */
	public function clear_all() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		$wpdb->query( "TRUNCATE TABLE {$table_name}" );
	}

	/**
	 * Get the total number of log entries.
	 *
	 * @since  1.0.0
	 * @return int
	 */
	public function get_total_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . self::TABLE_NAME;
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	}
}
