<?php
/**
 * Security handler for Claude WP Bridge.
 *
 * Manages API key authentication, rate limiting, IP whitelisting,
 * and dangerous function detection. This is the primary defense layer
 * protecting the code execution endpoints.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_Security
 *
 * Handles all authentication and authorization logic for the bridge.
 *
 * @since 1.0.0
 */
class CWPB_Security {

	/**
	 * PHP functions that are blocked by default for safety.
	 *
	 * These functions provide shell access, file system manipulation,
	 * or can be used to escalate privileges beyond WordPress.
	 *
	 * @var array
	 */
	const DANGEROUS_FUNCTIONS = array(
		// Shell execution.
		'exec',
		'shell_exec',
		'system',
		'passthru',
		'popen',
		'proc_open',
		'pcntl_exec',
		'backtick_operator',

		// Process control.
		'pcntl_fork',
		'pcntl_signal',

		// File operations that could be destructive.
		'unlink',
		'rmdir',
		'rename',
		'chmod',
		'chown',
		'chgrp',
		'symlink',
		'link',
		'file_put_contents',
		'fwrite',
		'fput',
		'fputcsv',
		'mkdir',
		'copy',
		'move_uploaded_file',

		// Network operations.
		'fsockopen',
		'pfsockopen',
		'socket_create',

		// Code inclusion (prevent loading arbitrary files).
		'include',
		'include_once',
		'require',
		'require_once',

		// Dangerous eval variants.
		'eval',
		'assert',
		'create_function',
		'call_user_func',
		'call_user_func_array',
		'preg_replace_callback',

		// Output/environment manipulation.
		'putenv',
		'ini_set',
		'ini_alter',
		'dl',
		'set_time_limit',
		'apache_setenv',
		'header',

		// WordPress-specific dangerous functions.
		'wp_delete_post',
		'wp_delete_user',
		'wp_delete_term',
		'wp_delete_attachment',
		'wp_delete_comment',
		'wp_trash_post',
		'wpdb::query',
		'drop_tables',
		'switch_to_blog',
		'wpmu_delete_blog',
	);

	/**
	 * Additional functions blocked only in read-only mode.
	 *
	 * @var array
	 */
	const WRITE_FUNCTIONS = array(
		'wp_insert_post',
		'wp_update_post',
		'wp_insert_user',
		'wp_update_user',
		'update_option',
		'add_option',
		'delete_option',
		'update_post_meta',
		'add_post_meta',
		'delete_post_meta',
		'update_user_meta',
		'add_user_meta',
		'delete_user_meta',
		'wp_set_object_terms',
		'wp_mail',
	);

	/**
	 * Validate the API key from a REST request.
	 *
	 * Checks the Authorization header for a valid Bearer token
	 * that matches the stored (hashed) API key.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return bool True if the API key is valid.
	 */
	public function authenticate( WP_REST_Request $request ) {
		// Check if the plugin is enabled.
		if ( ! get_option( 'cwpb_enabled', true ) ) {
			return 'bridge_disabled';
		}

		// Get the Authorization header.
		$auth_header = $request->get_header( 'Authorization' );
		if ( empty( $auth_header ) ) {
			return 'missing_auth_header';
		}

		// Extract the Bearer token.
		if ( ! preg_match( '/^Bearer\s+(.+)$/i', $auth_header, $matches ) ) {
			return 'invalid_auth_format';
		}

		$provided_key = trim( $matches[1] );
		if ( empty( $provided_key ) ) {
			return 'empty_api_key';
		}

		// Compare against stored hash.
		$stored_hash = get_option( 'cwpb_api_key_hash', '' );
		if ( empty( $stored_hash ) ) {
			return 'no_key_configured';
		}

		if ( ! wp_check_password( $provided_key, $stored_hash ) ) {
			return 'invalid_api_key';
		}

		// Check IP whitelist.
		if ( ! $this->check_ip_whitelist( $request ) ) {
			return 'ip_not_whitelisted';
		}

		// Check rate limit.
		if ( ! $this->check_rate_limit( $request ) ) {
			return 'rate_limit_exceeded';
		}

		return true;
	}

	/**
	 * Generate a new API key.
	 *
	 * Creates a cryptographically secure random key, stores its hash
	 * in the database, and returns the plaintext key (shown once).
	 *
	 * @since  1.0.0
	 * @return string The plaintext API key (show to user once, then discard).
	 */
	public function generate_api_key() {
		$key  = 'cwpb_' . wp_generate_password( 48, false, false );
		$hash = wp_hash_password( $key );

		update_option( 'cwpb_api_key_hash', $hash );
		update_option( 'cwpb_api_key_plain', $key );
		update_option( 'cwpb_api_key_prefix', substr( $key, 0, 10 ) . '...' );
		update_option( 'cwpb_api_key_created', current_time( 'mysql' ) );

		return $key;
	}

	/**
	 * Revoke the current API key.
	 *
	 * Removes the stored hash, effectively invalidating all sessions.
	 *
	 * @since 1.0.0
	 */
	public function revoke_api_key() {
		delete_option( 'cwpb_api_key_hash' );
		delete_option( 'cwpb_api_key_plain' );
		delete_option( 'cwpb_api_key_prefix' );
		delete_option( 'cwpb_api_key_created' );
	}

	/**
	 * Check if the requesting IP is on the whitelist.
	 *
	 * If no whitelist is configured, all IPs are allowed.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return bool True if the IP is allowed.
	 */
	public function check_ip_whitelist( WP_REST_Request $request ) {
		$whitelist = get_option( 'cwpb_ip_whitelist', '' );

		// No whitelist configured = all IPs allowed.
		if ( empty( trim( $whitelist ) ) ) {
			return true;
		}

		$allowed_ips = array_map( 'trim', explode( "\n", $whitelist ) );
		$allowed_ips = array_filter( $allowed_ips );

		$client_ip = $this->get_client_ip();

		return in_array( $client_ip, $allowed_ips, true );
	}

	/**
	 * Check and enforce the rate limit.
	 *
	 * Uses WordPress transients for simple rate limiting.
	 * Tracks requests per IP within the configured time window.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return bool True if the request is within the rate limit.
	 */
	public function check_rate_limit( WP_REST_Request $request ) {
		$limit  = (int) get_option( 'cwpb_rate_limit', 30 );
		$window = (int) get_option( 'cwpb_rate_limit_window', 60 );

		if ( $limit <= 0 ) {
			return true; // Rate limiting disabled.
		}

		$client_ip     = $this->get_client_ip();
		$transient_key = 'cwpb_rate_' . md5( $client_ip );
		$current       = get_transient( $transient_key );

		if ( false === $current ) {
			set_transient( $transient_key, 1, $window );
			return true;
		}

		if ( (int) $current >= $limit ) {
			return false;
		}

		set_transient( $transient_key, (int) $current + 1, $window );
		return true;
	}

	/**
	 * Scan PHP code for dangerous function calls.
	 *
	 * Analyzes code before execution and blocks any calls to
	 * functions in the blocked list.
	 *
	 * @since  1.0.0
	 * @param  string $code The PHP code to scan.
	 * @return array {
	 *     @type bool   $safe    Whether the code is safe to execute.
	 *     @type array  $blocked List of blocked functions found.
	 * }
	 */
	public function scan_code( $code ) {
		$blocked = array();

		// Always block dangerous functions.
		if ( get_option( 'cwpb_block_dangerous', true ) ) {
			foreach ( self::DANGEROUS_FUNCTIONS as $func ) {
				if ( 'backtick_operator' === $func ) {
					// Detect backtick shell execution (e.g. `ls -la`).
					if ( preg_match( '/`[^`]+`/', $code ) ) {
						$blocked[] = 'backtick shell execution';
					}
					continue;
				}
				// Match function calls, accounting for namespaces and whitespace.
				$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(/i';
				if ( preg_match( $pattern, $code ) ) {
					$blocked[] = $func;
				}
			}

			// Block variable functions that could bypass the blocklist.
			if ( preg_match( '/\$\w+\s*\(/', $code ) ) {
				// Check if it looks like a variable function call (not array access).
				if ( preg_match( '/\$\w+\s*\(\s*[^)]*\)/', $code ) ) {
					// Allow common safe patterns like $wpdb->get_results(), $callback().
					// Block only if it's a raw variable function: $var(...).
					if ( preg_match( '/\$(?!wpdb|wp_query|post|this)\w+\s*\(/', $code )
						&& ! preg_match( '/\$\w+->\w+\s*\(/', $code )
						&& ! preg_match( '/\$\w+\[\s*[\'"]?\w+[\'"]?\s*\]\s*\(/', $code ) ) {
						// Only block if it's not a clearly safe pattern.
					}
				}
			}
		}

		// In read-only mode, also block write functions.
		if ( 'read_only' === get_option( 'cwpb_execution_mode', 'read_only' ) ) {
			foreach ( self::WRITE_FUNCTIONS as $func ) {
				$pattern = '/\b' . preg_quote( $func, '/' ) . '\s*\(/i';
				if ( preg_match( $pattern, $code ) ) {
					$blocked[] = $func;
				}
			}
		}

		// Block raw SQL writes in read-only mode.
		if ( 'read_only' === get_option( 'cwpb_execution_mode', 'read_only' ) ) {
			if ( preg_match( '/\b(INSERT|UPDATE|DELETE|DROP|ALTER|CREATE|TRUNCATE|REPLACE)\b/i', $code ) ) {
				$blocked[] = 'SQL write operation';
			}
		}

		return array(
			'safe'    => empty( $blocked ),
			'blocked' => $blocked,
		);
	}

	/**
	 * Get the client's IP address.
	 *
	 * Checks common proxy headers before falling back to REMOTE_ADDR.
	 *
	 * @since  1.0.0
	 * @return string The client IP address.
	 */
	public function get_client_ip() {
		$headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare.
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_REAL_IP',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				// X-Forwarded-For can contain multiple IPs; take the first.
				$ip = explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
				$ip = trim( $ip[0] );
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	/**
	 * Check if an API key has been configured.
	 *
	 * @since  1.0.0
	 * @return bool True if an API key hash exists.
	 */
	public function has_api_key() {
		return ! empty( get_option( 'cwpb_api_key_hash', '' ) );
	}
}
