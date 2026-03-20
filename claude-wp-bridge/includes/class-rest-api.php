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

		// Database schema inspection.
		register_rest_route( self::NAMESPACE, '/db-schema', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_db_schema' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'table' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Specific table name to describe. If omitted, lists all tables.',
				),
			),
		) );

		// WordPress options reader.
		register_rest_route( self::NAMESPACE, '/options', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_options' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'keys' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Comma-separated option keys to retrieve. If omitted, returns common options.',
				),
				'search' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Search option names by pattern (SQL LIKE).',
				),
			),
		) );

		// Registered hooks/filters inspector.
		register_rest_route( self::NAMESPACE, '/hooks', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_hooks' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'hook' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Specific hook name to inspect. If omitted, lists all hooks.',
				),
				'search' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Search hooks by pattern.',
				),
			),
		) );

		// Cron schedule inspector.
		register_rest_route( self::NAMESPACE, '/cron', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_cron' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Rewrite rules inspector.
		register_rest_route( self::NAMESPACE, '/rewrite-rules', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_rewrite_rules' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'search' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Filter rules matching this pattern.',
				),
			),
		) );

		// Transients inspector.
		register_rest_route( self::NAMESPACE, '/transients', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_transients' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'search' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Search transient names by pattern.',
				),
			),
		) );

		// WooCommerce inspector.
		register_rest_route( self::NAMESPACE, '/woocommerce', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_woocommerce' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'section' => array(
					'required'    => false,
					'type'        => 'string',
					'default'     => 'overview',
					'description' => 'Section: overview, products, orders, settings, shipping, taxes, payment-gateways.',
				),
				'limit' => array(
					'required'    => false,
					'type'        => 'integer',
					'default'     => 20,
					'description' => 'Number of items to return for lists.',
				),
			),
		) );

		// Theme details inspector.
		register_rest_route( self::NAMESPACE, '/theme-info', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_theme_info' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );

		// Users inspector (safe, no passwords).
		register_rest_route( self::NAMESPACE, '/users', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_users' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'role' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Filter by user role.',
				),
				'limit' => array(
					'required'    => false,
					'type'        => 'integer',
					'default'     => 50,
				),
			),
		) );

		// Taxonomies and terms inspector.
		register_rest_route( self::NAMESPACE, '/taxonomies', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_taxonomies' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'taxonomy' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Specific taxonomy to get terms for.',
				),
			),
		) );

		// Media library inspector.
		register_rest_route( self::NAMESPACE, '/media', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_media' ),
			'permission_callback' => array( $this, 'check_permission' ),
			'args'                => array(
				'limit' => array(
					'required'    => false,
					'type'        => 'integer',
					'default'     => 20,
				),
				'mime_type' => array(
					'required'    => false,
					'type'        => 'string',
					'description' => 'Filter by MIME type (e.g., image/jpeg).',
				),
			),
		) );

		// Widgets and sidebars inspector.
		register_rest_route( self::NAMESPACE, '/widgets', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'handle_widgets' ),
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
			'success'     => $result['success'],
			'return'      => $this->serialize_return_value( $result['return'] ),
			'output'      => $result['output'],
			'time_ms'     => $result['time_ms'],
			'memory_used' => isset( $result['memory_used'] ) ? size_format( $result['memory_used'] ) : null,
			'error'       => $result['error'] ?? null,
			'blocked'     => $result['blocked'] ?? null,
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
	 * Handle database schema inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/db-schema
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Database schema information.
	 */
	public function handle_db_schema( WP_REST_Request $request ) {
		global $wpdb;

		$table = $request->get_param( 'table' );

		if ( $table ) {
			// Describe a specific table.
			$columns = $wpdb->get_results( $wpdb->prepare( 'DESCRIBE %i', $table ) );
			if ( empty( $columns ) && $wpdb->last_error ) {
				return new WP_REST_Response( array(
					'success' => false,
					'error'   => 'Table not found or access denied.',
				), 400 );
			}

			$indexes = $wpdb->get_results( $wpdb->prepare( 'SHOW INDEX FROM %i', $table ) );
			$count   = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table ) );

			return new WP_REST_Response( array(
				'success' => true,
				'table'   => $table,
				'columns' => $columns,
				'indexes' => $indexes,
				'rows'    => (int) $count,
			), 200 );
		}

		// List all tables with row counts.
		$tables = $wpdb->get_results( 'SHOW TABLE STATUS' );
		$result = array();
		foreach ( $tables as $t ) {
			$result[] = array(
				'name'      => $t->Name,
				'engine'    => $t->Engine,
				'rows'      => (int) $t->Rows,
				'size_mb'   => round( ( $t->Data_length + $t->Index_length ) / 1048576, 2 ),
				'collation' => $t->Collation,
			);
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'prefix'   => $wpdb->prefix,
			'tables'   => $result,
			'wp_tables' => array(
				'posts'       => $wpdb->posts,
				'postmeta'    => $wpdb->postmeta,
				'options'     => $wpdb->options,
				'users'       => $wpdb->users,
				'usermeta'    => $wpdb->usermeta,
				'terms'       => $wpdb->terms,
				'term_taxonomy' => $wpdb->term_taxonomy,
				'term_relationships' => $wpdb->term_relationships,
				'comments'    => $wpdb->comments,
				'commentmeta' => $wpdb->commentmeta,
			),
		), 200 );
	}

	/**
	 * Handle WordPress options reading.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/options
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Options data.
	 */
	public function handle_options( WP_REST_Request $request ) {
		global $wpdb;

		$keys   = $request->get_param( 'keys' );
		$search = $request->get_param( 'search' );

		// Sensitive options that should never be returned.
		$forbidden = array(
			'cwpb_api_key_hash', 'cwpb_api_key_plain',
		);

		if ( $keys ) {
			$key_list = array_map( 'trim', explode( ',', $keys ) );
			$result   = array();
			foreach ( $key_list as $key ) {
				if ( in_array( $key, $forbidden, true ) ) {
					$result[ $key ] = '[REDACTED]';
					continue;
				}
				$result[ $key ] = get_option( $key, null );
			}
			return new WP_REST_Response( array(
				'success' => true,
				'options' => $result,
			), 200 );
		}

		if ( $search ) {
			$options = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name LIMIT 100",
				'%' . $wpdb->esc_like( $search ) . '%'
			) );
			$result = array();
			foreach ( $options as $opt ) {
				if ( in_array( $opt->option_name, $forbidden, true ) ) {
					continue;
				}
				$value = maybe_unserialize( $opt->option_value );
				$result[ $opt->option_name ] = $value;
			}
			return new WP_REST_Response( array(
				'success' => true,
				'options' => $result,
				'count'   => count( $result ),
			), 200 );
		}

		// Default: return common WordPress options.
		$common = array(
			'siteurl', 'home', 'blogname', 'blogdescription',
			'admin_email', 'permalink_structure', 'date_format', 'time_format',
			'timezone_string', 'WPLANG', 'posts_per_page', 'default_role',
			'template', 'stylesheet', 'active_plugins',
			'current_theme', 'wp_page_for_privacy_policy',
			'show_on_front', 'page_on_front', 'page_for_posts',
		);

		$result = array();
		foreach ( $common as $key ) {
			$result[ $key ] = get_option( $key, null );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'options' => $result,
		), 200 );
	}

	/**
	 * Handle hooks/filters inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/hooks
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Hooks data.
	 */
	public function handle_hooks( WP_REST_Request $request ) {
		global $wp_filter;

		$hook   = $request->get_param( 'hook' );
		$search = $request->get_param( 'search' );

		if ( $hook ) {
			if ( ! isset( $wp_filter[ $hook ] ) ) {
				return new WP_REST_Response( array(
					'success'   => true,
					'hook'      => $hook,
					'callbacks' => array(),
					'message'   => 'No callbacks registered for this hook.',
				), 200 );
			}

			$callbacks = array();
			foreach ( $wp_filter[ $hook ]->callbacks as $priority => $funcs ) {
				foreach ( $funcs as $id => $func ) {
					$name = $this->get_callback_name( $func['function'] );
					$callbacks[] = array(
						'priority'      => $priority,
						'function'      => $name,
						'accepted_args' => $func['accepted_args'],
					);
				}
			}

			return new WP_REST_Response( array(
				'success'   => true,
				'hook'      => $hook,
				'callbacks' => $callbacks,
			), 200 );
		}

		// List hooks (optionally filtered by search).
		$hooks  = array_keys( $wp_filter );
		sort( $hooks );

		if ( $search ) {
			$hooks = array_filter( $hooks, function ( $h ) use ( $search ) {
				return false !== stripos( $h, $search );
			} );
			$hooks = array_values( $hooks );
		}

		$result = array();
		foreach ( array_slice( $hooks, 0, 200 ) as $h ) {
			$count = 0;
			if ( isset( $wp_filter[ $h ] ) ) {
				foreach ( $wp_filter[ $h ]->callbacks as $funcs ) {
					$count += count( $funcs );
				}
			}
			$result[] = array(
				'hook'           => $h,
				'callback_count' => $count,
			);
		}

		return new WP_REST_Response( array(
			'success'    => true,
			'hooks'      => $result,
			'total'      => count( $hooks ),
			'showing'    => min( 200, count( $hooks ) ),
		), 200 );
	}

	/**
	 * Handle cron schedule inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/cron
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Cron data.
	 */
	public function handle_cron( WP_REST_Request $request ) {
		$cron_events = _get_cron_array();
		$schedules   = wp_get_schedules();
		$result      = array();

		if ( is_array( $cron_events ) ) {
			foreach ( $cron_events as $timestamp => $hooks ) {
				foreach ( $hooks as $hook => $events ) {
					foreach ( $events as $key => $event ) {
						$result[] = array(
							'hook'       => $hook,
							'next_run'   => gmdate( 'Y-m-d H:i:s', $timestamp ),
							'schedule'   => $event['schedule'] ?: 'single',
							'interval'   => $event['interval'] ?? null,
							'args'       => $event['args'],
						);
					}
				}
			}
		}

		// Sort by next_run.
		usort( $result, function ( $a, $b ) {
			return strcmp( $a['next_run'], $b['next_run'] );
		} );

		return new WP_REST_Response( array(
			'success'    => true,
			'events'     => $result,
			'total'      => count( $result ),
			'schedules'  => $schedules,
			'server_time' => gmdate( 'Y-m-d H:i:s' ),
		), 200 );
	}

	/**
	 * Handle rewrite rules inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/rewrite-rules
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Rewrite rules data.
	 */
	public function handle_rewrite_rules( WP_REST_Request $request ) {
		global $wp_rewrite;

		$search = $request->get_param( 'search' );
		$rules  = get_option( 'rewrite_rules', array() );

		if ( $search && is_array( $rules ) ) {
			$rules = array_filter( $rules, function ( $query, $pattern ) use ( $search ) {
				return false !== stripos( $pattern, $search ) || false !== stripos( $query, $search );
			}, ARRAY_FILTER_USE_BOTH );
		}

		return new WP_REST_Response( array(
			'success'             => true,
			'permalink_structure' => get_option( 'permalink_structure' ),
			'rules'               => $rules,
			'total'               => is_array( $rules ) ? count( $rules ) : 0,
		), 200 );
	}

	/**
	 * Handle transients inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/transients
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Transients data.
	 */
	public function handle_transients( WP_REST_Request $request ) {
		global $wpdb;

		$search = $request->get_param( 'search' );

		if ( $search ) {
			$transients = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s AND option_name LIKE %s ORDER BY option_name LIMIT 100",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				'%' . $wpdb->esc_like( '_transient_' . $search ) . '%'
			) );
		} else {
			$transients = $wpdb->get_results( $wpdb->prepare(
				"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s AND option_name NOT LIKE %s ORDER BY option_name LIMIT %d",
				$wpdb->esc_like( '_transient_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				100
			) );
		}
		$result     = array();

		foreach ( $transients as $t ) {
			$name       = str_replace( '_transient_', '', $t->option_name );
			$timeout    = get_option( '_transient_timeout_' . $name );
			$value      = maybe_unserialize( $t->option_value );
			$expires_at = $timeout ? gmdate( 'Y-m-d H:i:s', (int) $timeout ) : 'never';

			$result[] = array(
				'name'       => $name,
				'value_type' => gettype( $value ),
				'value_size' => strlen( $t->option_value ),
				'expires_at' => $expires_at,
				'expired'    => $timeout && time() > (int) $timeout,
			);
		}

		return new WP_REST_Response( array(
			'success'    => true,
			'transients' => $result,
			'total'      => count( $result ),
		), 200 );
	}

	/**
	 * Handle WooCommerce inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/woocommerce
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response WooCommerce data.
	 */
	public function handle_woocommerce( WP_REST_Request $request ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new WP_REST_Response( array(
				'success' => false,
				'error'   => 'WooCommerce is not active on this site.',
			), 400 );
		}

		$section = $request->get_param( 'section' );
		$limit   = min( (int) $request->get_param( 'limit' ), 100 );

		switch ( $section ) {
			case 'products':
				return $this->wc_products( $limit );
			case 'orders':
				return $this->wc_orders( $limit );
			case 'settings':
				return $this->wc_settings();
			case 'shipping':
				return $this->wc_shipping();
			case 'taxes':
				return $this->wc_taxes();
			case 'payment-gateways':
				return $this->wc_payment_gateways();
			case 'overview':
			default:
				return $this->wc_overview();
		}
	}

	/**
	 * Handle detailed theme information.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/theme-info
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Theme data.
	 */
	public function handle_theme_info( WP_REST_Request $request ) {
		$theme = wp_get_theme();

		$template_files = array();
		$theme_dir      = get_template_directory();
		if ( is_dir( $theme_dir ) ) {
			$files = glob( $theme_dir . '/*.php' );
			foreach ( $files as $file ) {
				$template_files[] = basename( $file );
			}
		}

		// Check child theme.
		$child_files = array();
		if ( is_child_theme() ) {
			$child_dir = get_stylesheet_directory();
			$files     = glob( $child_dir . '/*.php' );
			foreach ( $files as $file ) {
				$child_files[] = basename( $file );
			}
		}

		// Registered nav menus.
		$menus     = get_registered_nav_menus();
		$locations = get_nav_menu_locations();
		$menu_data = array();
		foreach ( $menus as $location => $description ) {
			$menu_id    = $locations[ $location ] ?? 0;
			$menu_obj   = $menu_id ? wp_get_nav_menu_object( $menu_id ) : null;
			$menu_data[ $location ] = array(
				'description' => $description,
				'menu_name'   => $menu_obj ? $menu_obj->name : null,
				'item_count'  => $menu_obj ? $menu_obj->count : 0,
			);
		}

		// Registered sidebars.
		global $wp_registered_sidebars;
		$sidebars = array();
		foreach ( $wp_registered_sidebars as $id => $sidebar ) {
			$sidebars[] = array(
				'id'          => $id,
				'name'        => $sidebar['name'],
				'description' => $sidebar['description'] ?? '',
			);
		}

		// Theme supports.
		$supports = array();
		$features = array(
			'title-tag', 'custom-logo', 'post-thumbnails', 'automatic-feed-links',
			'html5', 'custom-header', 'custom-background', 'menus',
			'woocommerce', 'wc-product-gallery-zoom', 'wc-product-gallery-lightbox',
			'wc-product-gallery-slider',
		);
		foreach ( $features as $feature ) {
			$supports[ $feature ] = current_theme_supports( $feature );
		}

		return new WP_REST_Response( array(
			'success' => true,
			'theme'   => array(
				'name'            => $theme->get( 'Name' ),
				'version'         => $theme->get( 'Version' ),
				'template'        => $theme->get_template(),
				'stylesheet'      => $theme->get_stylesheet(),
				'theme_uri'       => $theme->get( 'ThemeURI' ),
				'author'          => $theme->get( 'Author' ),
				'is_child_theme'  => is_child_theme(),
				'parent_theme'    => $theme->parent() ? $theme->parent()->get( 'Name' ) : null,
				'template_files'  => $template_files,
				'child_files'     => $child_files,
			),
			'nav_menus'     => $menu_data,
			'sidebars'      => $sidebars,
			'theme_support' => $supports,
		), 200 );
	}

	/**
	 * Handle users inspection (safe — no passwords).
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/users
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Users data.
	 */
	public function handle_users( WP_REST_Request $request ) {
		$role  = $request->get_param( 'role' );
		$limit = min( (int) $request->get_param( 'limit' ), 200 );

		$args = array(
			'number' => $limit,
			'orderby' => 'ID',
			'order'   => 'ASC',
		);
		if ( $role ) {
			$args['role'] = $role;
		}

		$users  = get_users( $args );
		$result = array();

		foreach ( $users as $user ) {
			$result[] = array(
				'ID'           => $user->ID,
				'user_login'   => $user->user_login,
				'user_email'   => $user->user_email,
				'display_name' => $user->display_name,
				'roles'        => $user->roles,
				'registered'   => $user->user_registered,
			);
		}

		// Role summary.
		$role_counts = array();
		$wp_roles    = wp_roles();
		foreach ( $wp_roles->role_names as $role_key => $role_name ) {
			$count = count( get_users( array( 'role' => $role_key, 'fields' => 'ID' ) ) );
			if ( $count > 0 ) {
				$role_counts[ $role_key ] = array(
					'name'  => $role_name,
					'count' => $count,
				);
			}
		}

		return new WP_REST_Response( array(
			'success'     => true,
			'users'       => $result,
			'total'       => count( $result ),
			'role_counts' => $role_counts,
		), 200 );
	}

	/**
	 * Handle taxonomies and terms inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/taxonomies
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Taxonomy data.
	 */
	public function handle_taxonomies( WP_REST_Request $request ) {
		$taxonomy = $request->get_param( 'taxonomy' );

		if ( $taxonomy ) {
			$tax_obj = get_taxonomy( $taxonomy );
			if ( ! $tax_obj ) {
				return new WP_REST_Response( array(
					'success' => false,
					'error'   => "Taxonomy '{$taxonomy}' not found.",
				), 400 );
			}

			$terms = get_terms( array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 200,
			) );

			$term_data = array();
			if ( ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_data[] = array(
						'term_id' => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'parent'  => $term->parent,
						'count'   => $term->count,
					);
				}
			}

			return new WP_REST_Response( array(
				'success'  => true,
				'taxonomy' => array(
					'name'         => $tax_obj->name,
					'label'        => $tax_obj->label,
					'hierarchical' => $tax_obj->hierarchical,
					'public'       => $tax_obj->public,
					'object_type'  => $tax_obj->object_type,
				),
				'terms'    => $term_data,
				'total'    => count( $term_data ),
			), 200 );
		}

		// List all taxonomies.
		$taxonomies = get_taxonomies( array(), 'objects' );
		$result     = array();

		foreach ( $taxonomies as $tax ) {
			$result[] = array(
				'name'         => $tax->name,
				'label'        => $tax->label,
				'hierarchical' => $tax->hierarchical,
				'public'       => $tax->public,
				'object_type'  => $tax->object_type,
				'term_count'   => wp_count_terms( array( 'taxonomy' => $tax->name ) ),
			);
		}

		return new WP_REST_Response( array(
			'success'    => true,
			'taxonomies' => $result,
		), 200 );
	}

	/**
	 * Handle media library inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/media
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Media data.
	 */
	public function handle_media( WP_REST_Request $request ) {
		$limit     = min( (int) $request->get_param( 'limit' ), 100 );
		$mime_type = $request->get_param( 'mime_type' );

		$args = array(
			'post_type'      => 'attachment',
			'posts_per_page' => $limit,
			'post_status'    => 'inherit',
			'orderby'        => 'date',
			'order'          => 'DESC',
		);
		if ( $mime_type ) {
			$args['post_mime_type'] = $mime_type;
		}

		$attachments = get_posts( $args );
		$result      = array();

		foreach ( $attachments as $att ) {
			$meta = wp_get_attachment_metadata( $att->ID );
			$result[] = array(
				'ID'        => $att->ID,
				'title'     => $att->post_title,
				'url'       => wp_get_attachment_url( $att->ID ),
				'mime_type' => $att->post_mime_type,
				'date'      => $att->post_date,
				'width'     => $meta['width'] ?? null,
				'height'    => $meta['height'] ?? null,
				'filesize'  => $meta['filesize'] ?? null,
			);
		}

		// Media summary.
		global $wpdb;
		$mime_counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_mime_type, COUNT(*) as count FROM {$wpdb->posts} WHERE post_type = %s GROUP BY post_mime_type ORDER BY count DESC",
			'attachment'
		) );

		return new WP_REST_Response( array(
			'success'     => true,
			'media'       => $result,
			'total'       => count( $result ),
			'mime_counts' => $mime_counts,
			'upload_dir'  => wp_upload_dir(),
		), 200 );
	}

	/**
	 * Handle widgets and sidebars inspection.
	 *
	 * Endpoint: GET /wp-json/claude-bridge/v1/widgets
	 *
	 * @since  2.0.0
	 * @param  WP_REST_Request $request The incoming REST request.
	 * @return WP_REST_Response Widgets data.
	 */
	public function handle_widgets( WP_REST_Request $request ) {
		global $wp_registered_sidebars, $wp_registered_widgets;

		$sidebars_widgets = wp_get_sidebars_widgets();
		$result           = array();

		foreach ( $wp_registered_sidebars as $id => $sidebar ) {
			$widgets = array();
			if ( isset( $sidebars_widgets[ $id ] ) ) {
				foreach ( $sidebars_widgets[ $id ] as $widget_id ) {
					$name = isset( $wp_registered_widgets[ $widget_id ] )
						? $wp_registered_widgets[ $widget_id ]['name']
						: $widget_id;
					$widgets[] = array(
						'id'   => $widget_id,
						'name' => $name,
					);
				}
			}

			$result[] = array(
				'id'          => $id,
				'name'        => $sidebar['name'],
				'description' => $sidebar['description'] ?? '',
				'widgets'     => $widgets,
			);
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'sidebars' => $result,
		), 200 );
	}

	// =========================================================================
	// WooCommerce helper methods.
	// =========================================================================

	/**
	 * WooCommerce overview data.
	 *
	 * @since  2.0.0
	 * @return WP_REST_Response
	 */
	private function wc_overview() {
		$product_counts = wp_count_posts( 'product' );
		$order_counts   = wp_count_posts( 'shop_order' );

		// Currency and store settings.
		$data = array(
			'woocommerce_version' => defined( 'WC_VERSION' ) ? WC_VERSION : 'unknown',
			'currency'            => get_woocommerce_currency(),
			'currency_symbol'     => get_woocommerce_currency_symbol(),
			'weight_unit'         => get_option( 'woocommerce_weight_unit' ),
			'dimension_unit'      => get_option( 'woocommerce_dimension_unit' ),
			'store_address'       => array(
				'address'  => get_option( 'woocommerce_store_address' ),
				'city'     => get_option( 'woocommerce_store_city' ),
				'postcode' => get_option( 'woocommerce_store_postcode' ),
				'country'  => get_option( 'woocommerce_default_country' ),
			),
			'product_counts'      => (array) $product_counts,
			'order_counts'        => (array) $order_counts,
			'tax_enabled'         => wc_tax_enabled(),
			'shipping_enabled'    => wc_shipping_enabled(),
			'coupons_enabled'     => wc_coupons_enabled(),
			'pages'               => array(
				'shop'     => get_option( 'woocommerce_shop_page_id' ),
				'cart'     => get_option( 'woocommerce_cart_page_id' ),
				'checkout' => get_option( 'woocommerce_checkout_page_id' ),
				'myaccount' => get_option( 'woocommerce_myaccount_page_id' ),
				'terms'    => get_option( 'woocommerce_terms_page_id' ),
			),
		);

		return new WP_REST_Response( array(
			'success' => true,
			'data'    => $data,
		), 200 );
	}

	/**
	 * WooCommerce products data.
	 *
	 * @since  2.0.0
	 * @param  int $limit Max items.
	 * @return WP_REST_Response
	 */
	private function wc_products( $limit ) {
		$products = wc_get_products( array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
			'return'  => 'objects',
		) );

		$result = array();
		foreach ( $products as $product ) {
			$result[] = array(
				'id'             => $product->get_id(),
				'name'           => $product->get_name(),
				'type'           => $product->get_type(),
				'status'         => $product->get_status(),
				'sku'            => $product->get_sku(),
				'price'          => $product->get_price(),
				'regular_price'  => $product->get_regular_price(),
				'sale_price'     => $product->get_sale_price(),
				'stock_status'   => $product->get_stock_status(),
				'stock_quantity' => $product->get_stock_quantity(),
				'categories'     => wp_get_post_terms( $product->get_id(), 'product_cat', array( 'fields' => 'names' ) ),
				'date_created'   => $product->get_date_created() ? $product->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			);
		}

		// Product type summary.
		global $wpdb;
		$type_counts = $wpdb->get_results( $wpdb->prepare(
			"SELECT t.name as type, COUNT(*) as count
			FROM {$wpdb->posts} p
			JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
			JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
			JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
			WHERE p.post_type = %s AND p.post_status = %s
			GROUP BY t.name",
			'product_type',
			'product',
			'publish'
		) );

		return new WP_REST_Response( array(
			'success'     => true,
			'products'    => $result,
			'total'       => count( $result ),
			'type_counts' => $type_counts,
		), 200 );
	}

	/**
	 * WooCommerce orders data.
	 *
	 * @since  2.0.0
	 * @param  int $limit Max items.
	 * @return WP_REST_Response
	 */
	private function wc_orders( $limit ) {
		$orders = wc_get_orders( array(
			'limit'   => $limit,
			'orderby' => 'date',
			'order'   => 'DESC',
		) );

		$result = array();
		foreach ( $orders as $order ) {
			$result[] = array(
				'id'               => $order->get_id(),
				'status'           => $order->get_status(),
				'total'            => $order->get_total(),
				'currency'         => $order->get_currency(),
				'payment_method'   => $order->get_payment_method_title(),
				'customer_id'      => $order->get_customer_id(),
				'billing_email'    => $order->get_billing_email(),
				'item_count'       => $order->get_item_count(),
				'date_created'     => $order->get_date_created() ? $order->get_date_created()->format( 'Y-m-d H:i:s' ) : null,
			);
		}

		return new WP_REST_Response( array(
			'success' => true,
			'orders'  => $result,
			'total'   => count( $result ),
		), 200 );
	}

	/**
	 * WooCommerce settings data.
	 *
	 * @since  2.0.0
	 * @return WP_REST_Response
	 */
	private function wc_settings() {
		$settings = array(
			'general' => array(
				'store_address'        => get_option( 'woocommerce_store_address' ),
				'store_city'           => get_option( 'woocommerce_store_city' ),
				'store_postcode'       => get_option( 'woocommerce_store_postcode' ),
				'default_country'      => get_option( 'woocommerce_default_country' ),
				'currency'             => get_option( 'woocommerce_currency' ),
				'currency_pos'         => get_option( 'woocommerce_currency_pos' ),
				'price_decimal_sep'    => get_option( 'woocommerce_price_decimal_sep' ),
				'price_num_decimals'   => get_option( 'woocommerce_price_num_decimals' ),
				'price_thousand_sep'   => get_option( 'woocommerce_price_thousand_sep' ),
			),
			'products' => array(
				'weight_unit'     => get_option( 'woocommerce_weight_unit' ),
				'dimension_unit'  => get_option( 'woocommerce_dimension_unit' ),
				'manage_stock'    => get_option( 'woocommerce_manage_stock' ),
				'stock_format'    => get_option( 'woocommerce_stock_format' ),
			),
			'checkout' => array(
				'enable_guest_checkout' => get_option( 'woocommerce_enable_guest_checkout' ),
				'enable_signup_login'   => get_option( 'woocommerce_enable_signup_and_login_from_checkout' ),
				'calc_taxes'            => get_option( 'woocommerce_calc_taxes' ),
			),
			'emails' => array(
				'email_from_name'    => get_option( 'woocommerce_email_from_name' ),
				'email_from_address' => get_option( 'woocommerce_email_from_address' ),
			),
		);

		return new WP_REST_Response( array(
			'success'  => true,
			'settings' => $settings,
		), 200 );
	}

	/**
	 * WooCommerce shipping data.
	 *
	 * @since  2.0.0
	 * @return WP_REST_Response
	 */
	private function wc_shipping() {
		$zones  = \WC_Shipping_Zones::get_zones();
		$result = array();

		foreach ( $zones as $zone_data ) {
			$zone    = new \WC_Shipping_Zone( $zone_data['id'] );
			$methods = array();
			foreach ( $zone->get_shipping_methods() as $method ) {
				$methods[] = array(
					'id'      => $method->id,
					'title'   => $method->title,
					'enabled' => $method->enabled,
				);
			}
			$result[] = array(
				'id'       => $zone_data['id'],
				'name'     => $zone_data['zone_name'],
				'methods'  => $methods,
			);
		}

		return new WP_REST_Response( array(
			'success' => true,
			'zones'   => $result,
		), 200 );
	}

	/**
	 * WooCommerce tax data.
	 *
	 * @since  2.0.0
	 * @return WP_REST_Response
	 */
	private function wc_taxes() {
		global $wpdb;

		$rates = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i ORDER BY tax_rate_order LIMIT %d',
			$wpdb->prefix . 'woocommerce_tax_rates',
			100
		) );

		return new WP_REST_Response( array(
			'success'     => true,
			'tax_enabled' => wc_tax_enabled(),
			'prices_include_tax' => get_option( 'woocommerce_prices_include_tax' ),
			'tax_based_on'       => get_option( 'woocommerce_tax_based_on' ),
			'tax_classes'        => \WC_Tax::get_tax_classes(),
			'rates'              => $rates,
		), 200 );
	}

	/**
	 * WooCommerce payment gateways data.
	 *
	 * @since  2.0.0
	 * @return WP_REST_Response
	 */
	private function wc_payment_gateways() {
		$gateways = WC()->payment_gateways()->payment_gateways();
		$result   = array();

		foreach ( $gateways as $gw ) {
			$result[] = array(
				'id'          => $gw->id,
				'title'       => $gw->title,
				'description' => $gw->description,
				'enabled'     => $gw->enabled,
				'method_title' => $gw->method_title,
			);
		}

		return new WP_REST_Response( array(
			'success'  => true,
			'gateways' => $result,
		), 200 );
	}

	/**
	 * Get a human-readable name for a callback function.
	 *
	 * @since  2.0.0
	 * @param  mixed $callback The callback to inspect.
	 * @return string The callback name.
	 */
	private function get_callback_name( $callback ) {
		if ( is_string( $callback ) ) {
			return $callback;
		}
		if ( is_array( $callback ) ) {
			$class  = is_object( $callback[0] ) ? get_class( $callback[0] ) : $callback[0];
			$method = $callback[1];
			return $class . '::' . $method;
		}
		if ( $callback instanceof \Closure ) {
			return '{closure}';
		}
		return '{unknown}';
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
