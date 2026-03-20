<?php
/**
 * Admin settings page for Claude WP Bridge.
 *
 * Provides a WordPress admin interface for managing API keys,
 * configuring security settings, and viewing the audit log.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_Admin
 *
 * Registers admin menus, settings fields, and handles admin AJAX actions.
 *
 * @since 1.0.0
 */
class CWPB_Admin {

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
	 * Initialize admin hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'add_menu_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'wp_ajax_cwpb_generate_key', array( $this, 'ajax_generate_key' ) );
		add_action( 'wp_ajax_cwpb_revoke_key', array( $this, 'ajax_revoke_key' ) );
		add_action( 'wp_ajax_cwpb_clear_logs', array( $this, 'ajax_clear_logs' ) );
	}

	/**
	 * Add admin menu pages.
	 *
	 * Creates a top-level menu "Claude Bridge" with two sub-pages:
	 * Settings and Audit Log.
	 *
	 * @since 1.0.0
	 */
	public function add_menu_pages() {
		add_menu_page(
			__( 'Claude WP Bridge', 'claude-wp-bridge' ),
			__( 'Claude Bridge', 'claude-wp-bridge' ),
			'manage_options',
			'claude-wp-bridge',
			array( $this, 'render_settings_page' ),
			'dashicons-rest-api',
			100
		);

		add_submenu_page(
			'claude-wp-bridge',
			__( 'Settings', 'claude-wp-bridge' ),
			__( 'Settings', 'claude-wp-bridge' ),
			'manage_options',
			'claude-wp-bridge',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			'claude-wp-bridge',
			__( 'Audit Log', 'claude-wp-bridge' ),
			__( 'Audit Log', 'claude-wp-bridge' ),
			'manage_options',
			'claude-wp-bridge-log',
			array( $this, 'render_log_page' )
		);
	}

	/**
	 * Register all plugin settings with the WordPress Settings API.
	 *
	 * @since 1.0.0
	 */
	public function register_settings() {
		// General settings.
		register_setting( 'cwpb_settings', 'cwpb_enabled', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );

		register_setting( 'cwpb_settings', 'cwpb_execution_mode', array(
			'type'              => 'string',
			'default'           => 'read_only',
			'sanitize_callback' => function ( $value ) {
				return in_array( $value, array( 'read_only', 'read_write', 'full' ), true ) ? $value : 'read_only';
			},
		) );

		register_setting( 'cwpb_settings', 'cwpb_rate_limit', array(
			'type'              => 'integer',
			'default'           => 30,
			'sanitize_callback' => 'absint',
		) );

		register_setting( 'cwpb_settings', 'cwpb_rate_limit_window', array(
			'type'              => 'integer',
			'default'           => 60,
			'sanitize_callback' => 'absint',
		) );

		register_setting( 'cwpb_settings', 'cwpb_ip_whitelist', array(
			'type'              => 'string',
			'default'           => '',
			'sanitize_callback' => 'sanitize_textarea_field',
		) );

		register_setting( 'cwpb_settings', 'cwpb_block_dangerous', array(
			'type'              => 'boolean',
			'default'           => true,
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );

		register_setting( 'cwpb_settings', 'cwpb_max_execution_time', array(
			'type'              => 'integer',
			'default'           => 10,
			'sanitize_callback' => function ( $value ) {
				$value = absint( $value );
				return min( max( $value, 1 ), 60 ); // Clamp between 1–60 seconds.
			},
		) );

		register_setting( 'cwpb_settings', 'cwpb_max_output_size', array(
			'type'              => 'integer',
			'default'           => 65536,
			'sanitize_callback' => 'absint',
		) );

		register_setting( 'cwpb_settings', 'cwpb_log_retention_days', array(
			'type'              => 'integer',
			'default'           => 30,
			'sanitize_callback' => 'absint',
		) );
	}

	/**
	 * Enqueue admin CSS and JavaScript.
	 *
	 * Only loads on the plugin's own admin pages.
	 *
	 * @since 1.0.0
	 * @param string $hook_suffix The current admin page hook suffix.
	 */
	public function enqueue_assets( $hook_suffix ) {
		// Only load on our pages.
		if ( ! str_contains( $hook_suffix, 'claude-wp-bridge' ) ) {
			return;
		}

		wp_enqueue_style(
			'cwpb-admin',
			CWPB_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			CWPB_VERSION
		);

		wp_enqueue_script(
			'cwpb-admin',
			CWPB_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			CWPB_VERSION,
			true
		);

		wp_localize_script( 'cwpb-admin', 'cwpb', array(
			'ajax_url'        => admin_url( 'admin-ajax.php' ),
			'nonce'           => wp_create_nonce( 'cwpb_admin' ),
			'endpoint_url'    => rest_url( 'claude-bridge/v1/' ),
			'site_name'       => get_bloginfo( 'name' ),
			'execution_mode'  => get_option( 'cwpb_execution_mode', 'read_only' ),
			'strings'         => array(
				'confirm_generate' => __( 'Generate a new API key? The current key will be revoked.', 'claude-wp-bridge' ),
				'confirm_revoke'   => __( 'Revoke the API key? All active sessions will be disconnected.', 'claude-wp-bridge' ),
				'confirm_clear'    => __( 'Clear all audit log entries? This cannot be undone.', 'claude-wp-bridge' ),
				'key_copied'       => __( 'API key copied to clipboard!', 'claude-wp-bridge' ),
				'ai_copied'        => __( 'Connection details copied — paste into your Claude session!', 'claude-wp-bridge' ),
			),
		) );
	}

	/**
	 * AJAX handler: Generate a new API key.
	 *
	 * @since 1.0.0
	 */
	public function ajax_generate_key() {
		check_ajax_referer( 'cwpb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$key = $this->security->generate_api_key();

		wp_send_json_success( array(
			'key'     => $key,
			'prefix'  => substr( $key, 0, 10 ) . '...',
			'created' => current_time( 'M j, Y g:i A' ),
		) );
	}

	/**
	 * AJAX handler: Revoke the current API key.
	 *
	 * @since 1.0.0
	 */
	public function ajax_revoke_key() {
		check_ajax_referer( 'cwpb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$this->security->revoke_api_key();

		wp_send_json_success( array(
			'message' => 'API key has been revoked.',
		) );
	}

	/**
	 * AJAX handler: Clear all audit logs.
	 *
	 * @since 1.0.0
	 */
	public function ajax_clear_logs() {
		check_ajax_referer( 'cwpb_admin', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions.' );
		}

		$this->logger->clear_all();

		wp_send_json_success( array(
			'message' => 'Audit log has been cleared.',
		) );
	}

	/**
	 * Render the main settings page.
	 *
	 * @since 1.0.0
	 */
	public function render_settings_page() {
		$has_key   = $this->security->has_api_key();
		$key_plain = get_option( 'cwpb_api_key_plain', '' );
		$key_date  = get_option( 'cwpb_api_key_created', '' );

		?>
		<div class="wrap cwpb-wrap">
			<h1><?php esc_html_e( 'Claude WP Bridge', 'claude-wp-bridge' ); ?></h1>
			<p class="description">
				<?php esc_html_e( 'Secure bridge between Claude Code and your WordPress installation.', 'claude-wp-bridge' ); ?>
			</p>

			<!-- API Key Management -->
			<div class="cwpb-card">
				<h2><?php esc_html_e( 'API Key', 'claude-wp-bridge' ); ?></h2>

				<?php if ( $has_key && $key_plain ) : ?>
					<div class="cwpb-key-container">
						<code id="cwpb-new-key" class="cwpb-key-value"><?php echo esc_html( $key_plain ); ?></code>
						<button type="button" class="button" id="cwpb-copy-key">
							<?php esc_html_e( 'Copy Key', 'claude-wp-bridge' ); ?>
						</button>
						<button type="button" class="button button-primary" id="cwpb-copy-ai">
							<?php esc_html_e( 'Copy to AI', 'claude-wp-bridge' ); ?>
						</button>
					</div>
					<?php if ( $key_date ) : ?>
						<p class="description">
							<?php
							printf(
								/* translators: %s: date string */
								esc_html__( 'Created: %s', 'claude-wp-bridge' ),
								esc_html( $key_date )
							);
							?>
						</p>
					<?php endif; ?>

					<p>
						<button type="button" class="button button-secondary" id="cwpb-regenerate-key">
							<?php esc_html_e( 'Regenerate Key', 'claude-wp-bridge' ); ?>
						</button>
						<button type="button" class="button cwpb-button-danger" id="cwpb-revoke-key">
							<?php esc_html_e( 'Revoke Key', 'claude-wp-bridge' ); ?>
						</button>
					</p>

				<?php else : ?>
					<?php if ( $has_key ) : ?>
						<p><?php esc_html_e( 'An API key exists but was created before plaintext storage was enabled. Regenerate to see the full key here.', 'claude-wp-bridge' ); ?></p>
						<p>
							<button type="button" class="button button-secondary" id="cwpb-regenerate-key">
								<?php esc_html_e( 'Regenerate Key', 'claude-wp-bridge' ); ?>
							</button>
							<button type="button" class="button cwpb-button-danger" id="cwpb-revoke-key">
								<?php esc_html_e( 'Revoke Key', 'claude-wp-bridge' ); ?>
							</button>
						</p>
					<?php else : ?>
						<p><?php esc_html_e( 'No API key configured. Generate one to start using the bridge.', 'claude-wp-bridge' ); ?></p>
						<p>
							<button type="button" class="button button-primary" id="cwpb-generate-key">
								<?php esc_html_e( 'Generate API Key', 'claude-wp-bridge' ); ?>
							</button>
						</p>
					<?php endif; ?>

					<!-- Shown after generating/regenerating a key via AJAX -->
					<div id="cwpb-new-key-display" style="display:none;">
						<div class="cwpb-key-container">
							<code id="cwpb-new-key" class="cwpb-key-value"></code>
							<button type="button" class="button" id="cwpb-copy-key">
								<?php esc_html_e( 'Copy Key', 'claude-wp-bridge' ); ?>
							</button>
							<button type="button" class="button button-primary" id="cwpb-copy-ai">
								<?php esc_html_e( 'Copy to AI', 'claude-wp-bridge' ); ?>
							</button>
						</div>
					</div>
				<?php endif; ?>

				<!-- Connection Info -->
				<div class="cwpb-connection-info">
					<h3><?php esc_html_e( 'Connection Details', 'claude-wp-bridge' ); ?></h3>
					<table class="form-table">
						<tr>
							<th><?php esc_html_e( 'API Endpoint', 'claude-wp-bridge' ); ?></th>
							<td><code><?php echo esc_url( rest_url( 'claude-bridge/v1/' ) ); ?></code></td>
						</tr>
						<tr>
							<th><?php esc_html_e( 'Ping URL', 'claude-wp-bridge' ); ?></th>
							<td><code><?php echo esc_url( rest_url( 'claude-bridge/v1/ping' ) ); ?></code></td>
						</tr>
					</table>
				</div>
			</div>

			<!-- Settings Form -->
			<div class="cwpb-card">
				<h2><?php esc_html_e( 'Settings', 'claude-wp-bridge' ); ?></h2>

				<form method="post" action="options.php">
					<?php settings_fields( 'cwpb_settings' ); ?>

					<table class="form-table">
						<!-- Enable/Disable -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Bridge Enabled', 'claude-wp-bridge' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="cwpb_enabled" value="1"
										<?php checked( get_option( 'cwpb_enabled', true ) ); ?> />
									<?php esc_html_e( 'Accept API requests', 'claude-wp-bridge' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Uncheck to temporarily disable all bridge endpoints without revoking the API key.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Execution Mode -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Execution Mode', 'claude-wp-bridge' ); ?></th>
							<td>
								<select name="cwpb_execution_mode">
									<option value="read_only" <?php selected( get_option( 'cwpb_execution_mode', 'read_only' ), 'read_only' ); ?>>
										<?php esc_html_e( 'Read Only — SELECT queries, read functions only', 'claude-wp-bridge' ); ?>
									</option>
									<option value="read_write" <?php selected( get_option( 'cwpb_execution_mode' ), 'read_write' ); ?>>
										<?php esc_html_e( 'Read/Write — Allows data modification (INSERT, UPDATE, etc.)', 'claude-wp-bridge' ); ?>
									</option>
									<option value="full" <?php selected( get_option( 'cwpb_execution_mode' ), 'full' ); ?>>
										<?php esc_html_e( 'Full Access — No restrictions (use with caution)', 'claude-wp-bridge' ); ?>
									</option>
								</select>
								<p class="description">
									<?php esc_html_e( 'Controls what operations Claude can perform. Start with Read Only and upgrade only when needed.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Block Dangerous Functions -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Block Dangerous Functions', 'claude-wp-bridge' ); ?></th>
							<td>
								<label>
									<input type="checkbox" name="cwpb_block_dangerous" value="1"
										<?php checked( get_option( 'cwpb_block_dangerous', true ) ); ?> />
									<?php esc_html_e( 'Block shell_exec, exec, system, file deletion, etc.', 'claude-wp-bridge' ); ?>
								</label>
								<p class="description">
									<?php esc_html_e( 'Strongly recommended. Prevents code from executing system commands or modifying files outside WordPress.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Rate Limiting -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Rate Limit', 'claude-wp-bridge' ); ?></th>
							<td>
								<input type="number" name="cwpb_rate_limit" value="<?php echo esc_attr( get_option( 'cwpb_rate_limit', 30 ) ); ?>"
									min="0" max="1000" class="small-text" />
								<?php esc_html_e( 'requests per', 'claude-wp-bridge' ); ?>
								<input type="number" name="cwpb_rate_limit_window" value="<?php echo esc_attr( get_option( 'cwpb_rate_limit_window', 60 ) ); ?>"
									min="1" max="3600" class="small-text" />
								<?php esc_html_e( 'seconds', 'claude-wp-bridge' ); ?>
								<p class="description">
									<?php esc_html_e( 'Set requests to 0 to disable rate limiting.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- IP Whitelist -->
						<tr>
							<th scope="row"><?php esc_html_e( 'IP Whitelist', 'claude-wp-bridge' ); ?></th>
							<td>
								<textarea name="cwpb_ip_whitelist" rows="4" cols="40"
									class="regular-text"><?php echo esc_textarea( get_option( 'cwpb_ip_whitelist', '' ) ); ?></textarea>
								<p class="description">
									<?php esc_html_e( 'One IP address per line. Leave empty to allow all IPs (authentication still required).', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Max Execution Time -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Max Execution Time', 'claude-wp-bridge' ); ?></th>
							<td>
								<input type="number" name="cwpb_max_execution_time" value="<?php echo esc_attr( get_option( 'cwpb_max_execution_time', 10 ) ); ?>"
									min="1" max="60" class="small-text" />
								<?php esc_html_e( 'seconds', 'claude-wp-bridge' ); ?>
								<p class="description">
									<?php esc_html_e( 'Maximum time a single code execution can run. Range: 1–60 seconds.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Max Output Size -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Max Output Size', 'claude-wp-bridge' ); ?></th>
							<td>
								<input type="number" name="cwpb_max_output_size" value="<?php echo esc_attr( get_option( 'cwpb_max_output_size', 65536 ) ); ?>"
									min="1024" max="1048576" class="regular-text" />
								<?php esc_html_e( 'bytes', 'claude-wp-bridge' ); ?>
								<p class="description">
									<?php esc_html_e( 'Maximum response size. Larger outputs will be truncated. Default: 65536 (64 KB).', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>

						<!-- Log Retention -->
						<tr>
							<th scope="row"><?php esc_html_e( 'Log Retention', 'claude-wp-bridge' ); ?></th>
							<td>
								<input type="number" name="cwpb_log_retention_days" value="<?php echo esc_attr( get_option( 'cwpb_log_retention_days', 30 ) ); ?>"
									min="0" max="365" class="small-text" />
								<?php esc_html_e( 'days', 'claude-wp-bridge' ); ?>
								<p class="description">
									<?php esc_html_e( 'Audit log entries older than this are automatically deleted. Set to 0 to keep all logs.', 'claude-wp-bridge' ); ?>
								</p>
							</td>
						</tr>
					</table>

					<?php submit_button(); ?>
				</form>
			</div>
		</div>
		<?php
	}

	/**
	 * Render the audit log page.
	 *
	 * @since 1.0.0
	 */
	public function render_log_page() {
		$page     = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
		$per_page = 50;
		$status   = isset( $_GET['status'] ) ? sanitize_key( $_GET['status'] ) : '';

		$logs       = $this->logger->get_logs( $page, $per_page, $status );
		$total      = $logs['total'];
		$entries    = $logs['entries'];
		$total_pages = ceil( $total / $per_page );

		?>
		<div class="wrap cwpb-wrap">
			<h1><?php esc_html_e( 'Audit Log', 'claude-wp-bridge' ); ?></h1>

			<div class="cwpb-log-actions">
				<!-- Status filter -->
				<div class="cwpb-log-filter">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=claude-wp-bridge-log' ) ); ?>"
						class="<?php echo empty( $status ) ? 'current' : ''; ?>">
						<?php esc_html_e( 'All', 'claude-wp-bridge' ); ?>
					</a> |
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=claude-wp-bridge-log&status=success' ) ); ?>"
						class="<?php echo 'success' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Success', 'claude-wp-bridge' ); ?>
					</a> |
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=claude-wp-bridge-log&status=error' ) ); ?>"
						class="<?php echo 'error' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Errors', 'claude-wp-bridge' ); ?>
					</a> |
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=claude-wp-bridge-log&status=blocked' ) ); ?>"
						class="<?php echo 'blocked' === $status ? 'current' : ''; ?>">
						<?php esc_html_e( 'Blocked', 'claude-wp-bridge' ); ?>
					</a>
				</div>

				<button type="button" class="button cwpb-button-danger" id="cwpb-clear-logs">
					<?php esc_html_e( 'Clear All Logs', 'claude-wp-bridge' ); ?>
				</button>
			</div>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th class="column-timestamp"><?php esc_html_e( 'Timestamp', 'claude-wp-bridge' ); ?></th>
						<th class="column-ip"><?php esc_html_e( 'IP Address', 'claude-wp-bridge' ); ?></th>
						<th class="column-endpoint"><?php esc_html_e( 'Endpoint', 'claude-wp-bridge' ); ?></th>
						<th class="column-code"><?php esc_html_e( 'Code / Query', 'claude-wp-bridge' ); ?></th>
						<th class="column-result"><?php esc_html_e( 'Result', 'claude-wp-bridge' ); ?></th>
						<th class="column-time"><?php esc_html_e( 'Time (ms)', 'claude-wp-bridge' ); ?></th>
						<th class="column-status"><?php esc_html_e( 'Status', 'claude-wp-bridge' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $entries ) ) : ?>
						<tr>
							<td colspan="7"><?php esc_html_e( 'No log entries found.', 'claude-wp-bridge' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $entries as $entry ) : ?>
							<tr>
								<td><?php echo esc_html( $entry->timestamp ); ?></td>
								<td><?php echo esc_html( $entry->ip_address ); ?></td>
								<td><code><?php echo esc_html( $entry->endpoint ); ?></code></td>
								<td>
									<details>
										<summary><?php echo esc_html( mb_substr( $entry->code_executed, 0, 60 ) ); ?></summary>
										<pre><?php echo esc_html( $entry->code_executed ); ?></pre>
									</details>
								</td>
								<td><?php echo esc_html( $entry->result_summary ); ?></td>
								<td><?php echo esc_html( $entry->execution_time_ms ); ?></td>
								<td>
									<span class="cwpb-status cwpb-status-<?php echo esc_attr( $entry->status ); ?>">
										<?php echo esc_html( ucfirst( $entry->status ) ); ?>
									</span>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $total_pages > 1 ) : ?>
				<div class="tablenav">
					<div class="tablenav-pages">
						<?php
						echo paginate_links( array(
							'base'    => add_query_arg( 'paged', '%#%' ),
							'format'  => '',
							'current' => $page,
							'total'   => $total_pages,
						) );
						?>
					</div>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
