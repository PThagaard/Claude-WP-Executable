<?php
/**
 * Plugin Name:       Claude WP Bridge
 * Plugin URI:        https://github.com/PThagaard/Claude-WP-Executable
 * Description:       Secure bridge between Claude Code and WordPress. Allows Claude to execute PHP, run database queries, and inspect your WordPress installation in real-time via authenticated REST API endpoints.
 * Version:           2.0.0
 * Requires at least: 5.9
 * Requires PHP:      7.4
 * Author:            PThagaard
 * Author URI:        https://github.com/PThagaard
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       claude-wp-bridge
 * Domain Path:       /languages
 *
 * @package ClaudeWPBridge
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin constants.
 */
define( 'CWPB_VERSION', '2.0.0' );
define( 'CWPB_PLUGIN_FILE', __FILE__ );
define( 'CWPB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CWPB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'CWPB_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Autoload plugin classes.
 */
require_once CWPB_PLUGIN_DIR . 'includes/class-security.php';
require_once CWPB_PLUGIN_DIR . 'includes/class-logger.php';
require_once CWPB_PLUGIN_DIR . 'includes/class-executor.php';
require_once CWPB_PLUGIN_DIR . 'includes/class-rest-api.php';
require_once CWPB_PLUGIN_DIR . 'includes/class-admin.php';
require_once CWPB_PLUGIN_DIR . 'includes/class-ai-guide.php';

/**
 * Main plugin class.
 *
 * Orchestrates plugin initialization, activation, deactivation, and
 * wires together the security, executor, REST API, and admin components.
 *
 * @since 1.0.0
 */
final class Claude_WP_Bridge {

	/**
	 * Singleton instance.
	 *
	 * @var Claude_WP_Bridge|null
	 */
	private static $instance = null;

	/**
	 * Security handler.
	 *
	 * @var CWPB_Security
	 */
	public $security;

	/**
	 * Audit logger.
	 *
	 * @var CWPB_Logger
	 */
	public $logger;

	/**
	 * PHP executor.
	 *
	 * @var CWPB_Executor
	 */
	public $executor;

	/**
	 * REST API handler.
	 *
	 * @var CWPB_REST_API
	 */
	public $rest_api;

	/**
	 * Admin settings page.
	 *
	 * @var CWPB_Admin
	 */
	public $admin;

	/**
	 * Get the singleton instance.
	 *
	 * @since  1.0.0
	 * @return Claude_WP_Bridge
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor. Initializes components and hooks.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->security = new CWPB_Security();
		$this->logger   = new CWPB_Logger();
		$this->executor = new CWPB_Executor( $this->security, $this->logger );
		$this->rest_api = new CWPB_REST_API( $this->security, $this->executor, $this->logger );
		$this->admin    = new CWPB_Admin( $this->security, $this->logger );

		// Register activation and deactivation hooks.
		register_activation_hook( CWPB_PLUGIN_FILE, array( $this, 'activate' ) );
		register_deactivation_hook( CWPB_PLUGIN_FILE, array( $this, 'deactivate' ) );

		// Initialize REST API endpoints.
		add_action( 'rest_api_init', array( $this->rest_api, 'register_routes' ) );

		// Initialize admin pages.
		if ( is_admin() ) {
			$this->admin->init();
		}
	}

	/**
	 * Plugin activation.
	 *
	 * Creates the audit log database table and sets default options.
	 *
	 * @since 1.0.0
	 */
	public function activate() {
		// Create the audit log table.
		$this->logger->create_table();

		// Set default options if they don't exist.
		$defaults = array(
			'cwpb_enabled'              => true,
			'cwpb_execution_mode'       => 'read_only',
			'cwpb_rate_limit'           => 30,
			'cwpb_rate_limit_window'    => 60,
			'cwpb_ip_whitelist'         => '',
			'cwpb_block_dangerous'      => true,
			'cwpb_max_execution_time'   => 10,
			'cwpb_max_output_size'      => 65536,
			'cwpb_log_retention_days'   => 30,
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// Flush rewrite rules for REST API.
		flush_rewrite_rules();
	}

	/**
	 * Plugin deactivation.
	 *
	 * Cleans up rewrite rules. Does NOT delete data (that's uninstall.php).
	 *
	 * @since 1.0.0
	 */
	public function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Prevent cloning.
	 */
	private function __clone() {}

	/**
	 * Prevent unserialization.
	 */
	public function __wakeup() {
		throw new \Exception( 'Cannot unserialize singleton.' );
	}
}

/**
 * Returns the main plugin instance.
 *
 * @since  1.0.0
 * @return Claude_WP_Bridge
 */
function claude_wp_bridge() {
	return Claude_WP_Bridge::instance();
}

// Initialize the plugin.
claude_wp_bridge();
