<?php
/**
 * REST API endpoints for Claude WP Bridge.
 *
 * Registers and handles all REST API routes that Claude Code uses
 * to interact with the WordPress installation. Every endpoint requires
 * Bearer token authentication.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_REST_API
 *
 * Registers REST routes under the 'claude-bridge/v1' namespace.
 *
 * @since 1.0.0
 */
class CWPB_REST_API {

	/**
	 * REST API namespace.
	 *
	 * @var string
	 */
	const NAMESPACE = 'claude-bridge/v1';

	/**
	 * Security handler.
	 *
	 * @var CWPB_Security
	 */
	private $security;

	/**
	 * PHP executor.
	 *
	 * @var CWPB_Executor
	 */
	private $executor;

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
	 * @param CWPB_Executor $executor PHP executor instance.
	 * @param CWPB_Logger   $logger   Audit logger instance.
	 */
	public function __construct( CWPB_Security $security, CWPB_Executor $executor, CWPB_Logger $logger ) {
		$this->security = $security;
		$this->executor = $executor;
		$this->logger   = $logger;
	}

	/**
	 * Register all REST API routes.
	 *
	 * Called via the 'rest_api_init' action hook.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Execute arbitrary PHP code.
		register_rest_route( self::NAMESPACE, '/execute', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_execute' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'code' => array(
					'required'          => true,
					'type'              => 'string',
					'description'       => 'PHP code to execute (without <?php tags).',
					'sanitize_callback' => function ( $value ) {
						return wp_unslash( $value ); // Preserve code as-is.
					},
				),
			),
		) );

		// Execute a database query.
		register_rest_route( self::NAMESPACE, '/query', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'handle_query' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'sql' => array(
					'required'          => true,
					'type'              => 'string',
					'description'       => 'SQL query to execute.',
					'sanitize_callback' => function ( $value ) {
						return wp_unslash( $value );
					},
				),
			),
		) );

		// Get site information.
		register_rest_route( self::NAMESPACE, '/site-info', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_site_info' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Get debug log contents.
		register_rest_route( self::NAMESPACE, '/debug-log', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_debug_log' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'lines' => array(
					'required'    => false,
					'type'        => 'integer',
					'default'     => 100,
					'description' => 'Number of lines to return from the end of the log.',
				),
			),
		) );

		// Health check / connectivity test.
		register_rest_route( self::NAMESPACE, '/ping', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_ping' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}

	/**
	 * Permission callback for all endpoints.
	 *
	 * Delegates to the security handler for API key verification,
	 * IP whitelisting, and rate limiting.
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return bool|WP_Error True if authorized, WP_Error otherwise.
	 */
	public function check_permission( WP_REST_Request $request ) {
		if ( ! $this->security->authenticate( $request ) ) {
			return new WP_Error(
				'cwpb_unauthorized',
				'Invalid or missing API key, IP not whitelisted, or rate limit exceeded.',
				array( 'status' => 401 )
			);
		}
		return true;
	}

	/**
	 * Handle PHP code execution requests.
	 *
	 * Endpoint: POST /wp-json/claude-bridge/v1/execute
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response The execution result.
	 */
	public function handle_execute( WP_REST_Request $request ) {
		$code      = $request->get_param( 'code' );
		$client_ip = $this->security->get_client_ip();

		$result = $this->executor->execute( $code, $client_ip );

		$status_code = $result['success'] ? 200 : 400;
		if ( isset( $result['blocked'] ) && ! empty( $result['blocked'] ) ) {
			$status_code = 403;
		}

		return new WP_REST_Response( array(
			'success'  => $result['success'],
			'return'   => $this->serialize_return_value( $result['return'] ),
			'output'   => $result['output'],
			'time_ms'  => $result['time_ms'],
			'error'    => $result['error'] ?? null,
			'blocked'  => $result['blocked'] ?? null,
		), $status_code );
	}

	/**
	 * Handle database query requests.
	 *
	 * Endpoint: POST /wp-json/claude-bridge/v1/query
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response The query result.
	 */
	public function handle_query( WP_REST_Request $request ) {
		$sql       = $request->get_param( 'sql' );
		$client_ip = $this->security->get_client_ip();

		$result = $this->executor->query( $sql, $client_ip );

		$status_code = $result['success'] ? 200 : 400;

		return new WP_REST_Response( array(
			'success' => $result['success'],
			'data'    => $result['data'],
			'rows'    => $result['rows'],
			'time_ms' => $result['time_ms'],
			'error'   => $result['error'] ?? null,
		), $status_code );
	}

	/**
	 * Handle site information requests.
	 *
	 * Returns comprehensive WordPress environment details including
	 * version, active plugins, theme, database info, and PHP configuration.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/site-info
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Site information.
	 */
	public function handle_site_info( WP_REST_Request $request ) {
		global $wpdb;

		// Get active plugins with version info.
		$active_plugins = get_option( 'active_plugins', array() );
		$all_plugins    = get_plugins();
		$plugins_info   = array();

		foreach ( $active_plugins as $plugin_file ) {
			if ( isset( $all_plugins[ $plugin_file ] ) ) {
				$plugins_info[] = array(
					'name'    => $all_plugins[ $plugin_file ]['Name'],
					'version' => $all_plugins[ $plugin_file ]['Version'],
					'file'    => $plugin_file,
				);
			}
		}

		// Get theme info.
		$theme = wp_get_theme();

		// Get custom post types.
		$post_types = get_post_types( array( '_builtin' => false ), 'objects' );
		$cpt_info   = array();
		foreach ( $post_types as $pt ) {
			$cpt_info[] = array(
				'name'  => $pt->name,
				'label' => $pt->label,
				'count' => (int) wp_count_posts( $pt->name )->publish,
			);
		}

		$response = array(
			'wordpress' => array(
				'version'    => get_bloginfo( 'version' ),
				'site_url'   => get_site_url(),
				'home_url'   => get_home_url(),
				'name'       => get_bloginfo( 'name' ),
				'multisite'  => is_multisite(),
				'locale'     => get_locale(),
			),
			'php' => array(
				'version'           => phpversion(),
				'memory_limit'      => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'extensions'        => get_loaded_extensions(),
			),
			'database' => array(
				'server'  => $wpdb->db_server_info(),
				'prefix'  => $wpdb->prefix,
				'charset' => $wpdb->charset,
			),
			'theme' => array(
				'name'     => $theme->get( 'Name' ),
				'version'  => $theme->get( 'Version' ),
				'template' => $theme->get_template(),
				'parent'   => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
			),
			'plugins'         => $plugins_info,
			'custom_post_types' => $cpt_info,
			'bridge' => array(
				'version'        => CWPB_VERSION,
				'execution_mode' => get_option( 'cwpb_execution_mode', 'read_only' ),
			),
		);

		$client_ip = $this->security->get_client_ip();
		$this->logger->log( array(
			'ip_address'        => $client_ip,
			'endpoint'          => 'site-info',
			'code_executed'     => '',
			'result_summary'    => 'Site info requested',
			'execution_time_ms' => 0,
			'status'            => 'success',
		) );

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Handle debug log requests.
	 *
	 * Returns the last N lines of the WordPress debug.log file.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/debug-log
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Debug log contents.
	 */
	public function handle_debug_log( WP_REST_Request $request ) {
		$lines    = $request->get_param( 'lines' );
		$log_file = WP_CONTENT_DIR . '/debug.log';

		if ( ! file_exists( $log_file ) ) {
			return new WP_REST_Response( array(
				'success' => true,
				'log'     => '',
				'message' => 'Debug log file does not exist. Ensure WP_DEBUG_LOG is enabled in wp-config.php.',
			), 200 );
		}

		// Read the last N lines efficiently.
		$log_content = $this->tail_file( $log_file, $lines );

		$client_ip = $this->security->get_client_ip();
		$this->logger->log( array(
			'ip_address'        => $client_ip,
			'endpoint'          => 'debug-log',
			'code_executed'     => "tail -{$lines}",
			'result_summary'    => strlen( $log_content ) . ' bytes returned',
			'execution_time_ms' => 0,
			'status'            => 'success',
		) );

		return new WP_REST_Response( array(
			'success' => true,
			'log'     => $log_content,
			'lines'   => $lines,
		), 200 );
	}

	/**
	 * Handle ping/health check requests.
	 *
	 * Used to verify connectivity and authentication.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/ping
	 *
	 * @since  1.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Health check response.
	 */
	public function handle_ping( WP_REST_Request $request ) {
		return new WP_REST_Response( array(
			'success'   => true,
			'message'   => 'Claude WP Bridge is active and authenticated.',
			'version'   => CWPB_VERSION,
			'mode'      => get_option( 'cwpb_execution_mode', 'read_only' ),
			'timestamp' => current_time( 'c' ),
		), 200 );
	}

	/**
	 * Serialize a return value for JSON response.
	 *
	 * Handles objects, resources, and other non-JSON-safe types.
	 *
	 * @since  1.0.0
	 * @param  mixed $value The value to serialize.
	 * @return mixed A JSON-safe representation of the value.
	 */
	private function serialize_return_value( $value ) {
		if ( is_null( $value ) || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			return array_map( array( $this, 'serialize_return_value' ), $value );
		}

		if ( is_object( $value ) ) {
			// Handle WP_Post, WP_User, etc.
			if ( method_exists( $value, 'to_array' ) ) {
				return $value->to_array();
			}
			// Handle WP_Query.
			if ( $value instanceof WP_Query ) {
				return array(
					'found_posts' => $value->found_posts,
					'post_count'  => $value->post_count,
					'posts'       => array_map( function ( $post ) {
						return $post->to_array();
					}, $value->posts ),
				);
			}
			// Generic object.
			return (array) $value;
		}

		if ( is_resource( $value ) ) {
			return '[resource: ' . get_resource_type( $value ) . ']';
		}

		return '[' . gettype( $value ) . ']';
	}

	/**
	 * Read the last N lines of a file efficiently.
	 *
	 * Uses a reverse-reading approach to avoid loading the entire file
	 * into memory, which is important for large debug.log files.
	 *
	 * @since  1.0.0
	 * @param  string $file  Absolute path to the file.
	 * @param  int    $lines Number of lines to read.
	 * @return string The last N lines of the file.
	 */
	private function tail_file( $file, $lines = 100 ) {
		$handle = fopen( $file, 'r' );
		if ( ! $handle ) {
			return '';
		}

		// For small files, just read the whole thing.
		$filesize = filesize( $file );
		if ( $filesize < 65536 ) {
			$content = fread( $handle, $filesize );
			fclose( $handle );
			$all_lines = explode( "\n", $content );
			return implode( "\n", array_slice( $all_lines, -$lines ) );
		}

		// For large files, seek from the end.
		$buffer     = '';
		$chunk_size = 4096;
		$line_count = 0;
		$position   = $filesize;

		while ( $position > 0 && $line_count < $lines + 1 ) {
			$read_size = min( $chunk_size, $position );
			$position -= $read_size;
			fseek( $handle, $position );
			$chunk      = fread( $handle, $read_size );
			$buffer     = $chunk . $buffer;
			$line_count = substr_count( $buffer, "\n" );
		}

		fclose( $handle );

		$all_lines = explode( "\n", $buffer );
		return implode( "\n", array_slice( $all_lines, -$lines ) );
	}
}
