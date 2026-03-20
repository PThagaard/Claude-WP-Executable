<?php
/**
 * PHP code executor for Claude WP Bridge.
 *
 * Responsible for safely executing PHP code within the full WordPress
 * context. Enforces security policies, captures output, and handles
 * errors gracefully.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_Executor
 *
 * Executes PHP code with security scanning, output capture, and error handling.
 *
 * @since 1.0.0
 */
class CWPB_Executor {

	/**
	 * Security handler.
	 *
	 * @var CWPB_Security
	 */
	private $security;

	/**
	 * Audit logger.
	 *
	 * @var CWPB_Logger
	 */
	private $logger;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 * @param CWPB_Security $security Security handler instance.
	 * @param CWPB_Logger   $logger   Audit logger instance.
	 */
	public function __construct( CWPB_Security $security, CWPB_Logger $logger ) {
		$this->security = $security;
		$this->logger   = $logger;
	}

	/**
	 * Execute PHP code in the WordPress context.
	 *
	 * Scans the code for dangerous functions, executes it within
	 * a controlled environment, captures output and return values,
	 * and logs the execution.
	 *
	 * @since  1.0.0
	 * @param  string $code   The PHP code to execute (without <?php tags).
	 * @param  string $client_ip The client IP for logging.
	 * @return array {
	 *     Execution result.
	 *
	 *     @type bool   $success     Whether execution succeeded.
	 *     @type mixed  $return      The return value of the code.
	 *     @type string $output      Captured output (echo, print, etc.).
	 *     @type float  $time_ms     Execution time in milliseconds.
	 *     @type string $error       Error message if execution failed.
	 *     @type array  $blocked     Blocked functions found (if any).
	 * }
	 */
	public function execute( $code, $client_ip = '' ) {
		$start_time = microtime( true );

		// Security scan.
		$scan = $this->security->scan_code( $code );
		if ( ! $scan['safe'] ) {
			$time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

			$this->logger->log( array(
				'ip_address'        => $client_ip,
				'endpoint'          => 'execute',
				'code_executed'     => $code,
				'result_summary'    => 'Blocked: ' . implode( ', ', $scan['blocked'] ),
				'execution_time_ms' => $time_ms,
				'status'            => 'blocked',
				'error_message'     => 'Code contains blocked functions: ' . implode( ', ', $scan['blocked'] ),
			) );

			return array(
				'success' => false,
				'return'  => null,
				'output'  => '',
				'time_ms' => $time_ms,
				'error'   => 'Code contains blocked functions: ' . implode( ', ', $scan['blocked'] ),
				'blocked' => $scan['blocked'],
			);
		}

		// Set execution time limit.
		$max_time = (int) get_option( 'cwpb_max_execution_time', 10 );

		// Execute the code.
		$result = $this->run_sandboxed( $code, $max_time );

		$time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

		// Enforce output size limit.
		$max_output = (int) get_option( 'cwpb_max_output_size', 65536 );
		if ( strlen( $result['output'] ) > $max_output ) {
			$result['output'] = substr( $result['output'], 0, $max_output )
				. "\n\n[Output truncated at {$max_output} bytes]";
		}

		// Scrub sensitive data from output.
		$result['output'] = $this->scrub_sensitive_data( $result['output'] );

		// Log the execution.
		$this->logger->log( array(
			'ip_address'        => $client_ip,
			'endpoint'          => 'execute',
			'code_executed'     => $code,
			'result_summary'    => $result['success']
				? $this->summarize_result( $result['return'] )
				: $result['error'],
			'execution_time_ms' => $time_ms,
			'status'            => $result['success'] ? 'success' : 'error',
			'error_message'     => $result['success'] ? null : $result['error'],
		) );

		$result['time_ms']    = $time_ms;
		$result['memory_used'] = $result['memory_used'] ?? 0;
		return $result;
	}

	/**
	 * Execute a database query.
	 *
	 * Provides a dedicated interface for database queries that's safer
	 * than raw PHP execution. In read-only mode, only SELECT queries are allowed.
	 *
	 * @since  1.0.0
	 * @param  string $query     The SQL query to execute.
	 * @param  string $client_ip The client IP for logging.
	 * @return array {
	 *     Query result.
	 *
	 *     @type bool   $success  Whether the query succeeded.
	 *     @type array  $data     Query results (for SELECT queries).
	 *     @type int    $rows     Number of rows affected or returned.
	 *     @type float  $time_ms  Execution time in milliseconds.
	 *     @type string $error    Error message if query failed.
	 * }
	 */
	public function query( $query, $client_ip = '' ) {
		global $wpdb;

		$start_time = microtime( true );
		$query      = trim( $query );

		// Determine query type.
		$is_select = preg_match( '/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $query );

		// In read-only mode, only allow SELECT/SHOW/DESCRIBE/EXPLAIN.
		if ( 'read_only' === get_option( 'cwpb_execution_mode', 'read_only' ) && ! $is_select ) {
			$time_ms = round( ( microtime( true ) - $start_time ) * 1000 );

			$this->logger->log( array(
				'ip_address'        => $client_ip,
				'endpoint'          => 'query',
				'code_executed'     => $query,
				'result_summary'    => 'Blocked: write query in read-only mode',
				'execution_time_ms' => $time_ms,
				'status'            => 'blocked',
				'error_message'     => 'Write queries are not allowed in read-only mode.',
			) );

			return array(
				'success' => false,
				'data'    => null,
				'rows'    => 0,
				'time_ms' => $time_ms,
				'error'   => 'Write queries are not allowed in read-only mode. Change the execution mode in settings to allow write operations.',
			);
		}

		// Execute the query.
		if ( $is_select ) {
			$results = $wpdb->get_results( $query );
			$rows    = count( $results );
			$error   = $wpdb->last_error;
		} else {
			$results = $wpdb->query( $query );
			$rows    = (int) $results;
			$error   = $wpdb->last_error;
		}

		$time_ms = round( ( microtime( true ) - $start_time ) * 1000 );
		$success = empty( $error );

		// Log the query.
		$this->logger->log( array(
			'ip_address'        => $client_ip,
			'endpoint'          => 'query',
			'code_executed'     => $query,
			'result_summary'    => $success ? "Rows: {$rows}" : $error,
			'execution_time_ms' => $time_ms,
			'status'            => $success ? 'success' : 'error',
			'error_message'     => $success ? null : $error,
		) );

		return array(
			'success' => $success,
			'data'    => $is_select ? $results : null,
			'rows'    => $rows,
			'time_ms' => $time_ms,
			'error'   => $success ? null : $error,
		);
	}

	/**
	 * Execute PHP code in a sandboxed environment.
	 *
	 * Uses output buffering and error handling to safely execute code.
	 * The code is wrapped in a closure to prevent variable leakage.
	 *
	 * @since  1.0.0
	 * @param  string $code     The PHP code to execute.
	 * @param  int    $max_time Maximum execution time in seconds.
	 * @return array  Execution result with 'success', 'return', 'output', 'error' keys.
	 */
	private function run_sandboxed( $code, $max_time ) {
		$output  = '';
		$return  = null;
		$error   = null;
		$success = true;

		// Enforce execution time limit.
		$original_time_limit = (int) ini_get( 'max_execution_time' );
		@set_time_limit( $max_time ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		// Track memory usage.
		$memory_before = memory_get_usage( true );

		// Set a custom error handler to catch warnings and notices.
		$errors_caught = array();
		$previous_handler = set_error_handler( function ( $errno, $errstr, $errfile, $errline ) use ( &$errors_caught ) {
			$errors_caught[] = array(
				'type'    => $errno,
				'message' => $errstr,
				'file'    => $errfile,
				'line'    => $errline,
			);
			return true; // Prevent default handler.
		} );

		ob_start();

		try {
			// Wrap in a closure so the code can use 'return' statements.
			// The eval runs with full WordPress context available.
			$closure = function () use ( $code ) {
				return eval( $code );
			};

			$return = $closure();
		} catch ( \Throwable $e ) {
			$success = false;
			$error   = sprintf(
				'%s: %s in evaluated code on line %d',
				get_class( $e ),
				$e->getMessage(),
				$e->getLine()
			);
		}

		$output = ob_get_clean();

		// Restore previous error handler and time limit.
		restore_error_handler();
		@set_time_limit( $original_time_limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors

		// Calculate memory delta.
		$memory_after = memory_get_usage( true );
		$memory_used  = $memory_after - $memory_before;

		// If there were non-fatal errors, append them to the output.
		if ( ! empty( $errors_caught ) && $success ) {
			$error_messages = array();
			foreach ( $errors_caught as $err ) {
				$type_name = $this->error_type_name( $err['type'] );
				$error_messages[] = "[{$type_name}] {$err['message']} (line {$err['line']})";
			}
			$output .= "\n\nPHP Notices/Warnings:\n" . implode( "\n", $error_messages );
		}

		return array(
			'success'     => $success,
			'return'      => $return,
			'output'      => $output,
			'error'       => $error,
			'memory_used' => $memory_used,
		);
	}

	/**
	 * Create a human-readable summary of a return value.
	 *
	 * Used for the audit log — truncates large values to keep
	 * the log table manageable.
	 *
	 * @since  1.0.0
	 * @param  mixed $value The value to summarize.
	 * @return string A short string summary.
	 */
	private function summarize_result( $value ) {
		if ( is_null( $value ) ) {
			return 'null';
		}
		if ( is_bool( $value ) ) {
			return $value ? 'true' : 'false';
		}
		if ( is_scalar( $value ) ) {
			$str = (string) $value;
			return strlen( $str ) > 200 ? substr( $str, 0, 197 ) . '...' : $str;
		}
		if ( is_array( $value ) ) {
			return 'Array(' . count( $value ) . ' items)';
		}
		if ( is_object( $value ) ) {
			return 'Object(' . get_class( $value ) . ')';
		}
		return gettype( $value );
	}

	/**
	 * Scrub sensitive data from output strings.
	 *
	 * Redacts database passwords, secret keys, and other credentials
	 * that might leak through code execution results.
	 *
	 * @since  2.0.0
	 * @param  string $text The text to scrub.
	 * @return string The scrubbed text.
	 */
	private function scrub_sensitive_data( $text ) {
		if ( empty( $text ) ) {
			return $text;
		}

		// Redact known WordPress constants that contain secrets.
		$secret_constants = array(
			'DB_PASSWORD',
			'AUTH_KEY',
			'SECURE_AUTH_KEY',
			'LOGGED_IN_KEY',
			'NONCE_KEY',
			'AUTH_SALT',
			'SECURE_AUTH_SALT',
			'LOGGED_IN_SALT',
			'NONCE_SALT',
		);

		foreach ( $secret_constants as $const ) {
			if ( defined( $const ) ) {
				$value = constant( $const );
				if ( ! empty( $value ) && strlen( $value ) > 3 ) {
					$text = str_replace( $value, '[REDACTED]', $text );
				}
			}
		}

		return $text;
	}

	/**
	 * Convert a PHP error type constant to a human-readable name.
	 *
	 * @since  1.0.0
	 * @param  int $type The PHP error type constant.
	 * @return string Human-readable error type name.
	 */
	private function error_type_name( $type ) {
		$map = array(
			E_WARNING     => 'Warning',
			E_NOTICE      => 'Notice',
			E_DEPRECATED  => 'Deprecated',
			E_USER_ERROR  => 'User Error',
			E_USER_WARNING => 'User Warning',
			E_USER_NOTICE => 'User Notice',
		);

		return $map[ $type ] ?? 'Error';
	}
}
