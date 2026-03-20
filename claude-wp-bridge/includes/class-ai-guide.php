<?php
/**
 * AI Guide generator for Claude WP Bridge.
 *
 * Generates the comprehensive instruction text that users paste into
 * their Claude Code / AI sessions. This replaces the old hardcoded
 * JavaScript text generation with a server-side PHP template.
 *
 * @package ClaudeWPBridge
 * @since   2.0.0
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CWPB_AI_Guide
 *
 * Generates a dynamic Markdown instruction document for AI sessions.
 *
 * @since 2.0.0
 */
class CWPB_AI_Guide {

	/**
	 * Generate the full AI guide text.
	 *
	 * @since  2.0.0
	 * @param  string $api_key The plaintext API key.
	 * @return string The full Markdown guide text.
	 */
	public static function generate( $api_key ) {
		$base      = rest_url( 'claude-bridge/v1/' );
		$site_url  = get_site_url();
		$site_name = get_bloginfo( 'name' );
		$mode      = get_option( 'cwpb_execution_mode', 'read_only' );
		$has_woo   = class_exists( 'WooCommerce' );

		$mode_labels = array(
			'read_only'  => 'Read-Only',
			'read_write' => 'Read-Write',
			'full'       => 'Full Access',
		);
		$mode_label = $mode_labels[ $mode ] ?? $mode;

		$text = '';

		// Header.
		$text .= "# Claude WP Bridge — {$site_name}\n\n";
		$text .= "You have access to a live WordPress site through the Claude WP Bridge plugin.\n";
		$text .= "This plugin exposes a REST API that lets you execute PHP code, run SQL queries, and deeply inspect the WordPress/WooCommerce installation in real-time.\n";
		$text .= "All communication happens via `curl` with Bearer token authentication. All responses are JSON.\n\n";

		// CRITICAL: CLAUDE.md instruction.
		$text .= "## IMPORTANT: Save These Credentials\n\n";
		$text .= "**Your first action** must be to save these connection details so they persist across sessions.\n";
		$text .= "Create or update a `CLAUDE.md` file in the project root with the following content:\n\n";
		$text .= "```markdown\n";
		$text .= "# Project: {$site_name}\n\n";
		$text .= "## WordPress Bridge (Claude WP Bridge v" . CWPB_VERSION . ")\n\n";
		$text .= "This project has a live WordPress bridge. You can execute PHP code and SQL queries directly on the site.\n\n";
		$text .= "- Site URL: {$site_url}\n";
		$text .= "- API Base: {$base}\n";
		$text .= "- API Key: {$api_key}\n";
		$text .= "- Mode: {$mode_label}\n";
		if ( $has_woo ) {
			$text .= "- WooCommerce: Active (v" . WC_VERSION . ")\n";
		}
		$text .= "\n";
		$text .= "Always use `curl -sk` flags. Pipe JSON through `| python3 -m json.tool 2>/dev/null`.\n";
		$text .= "See the plugin's \"Copy to AI\" button in WP Admin > Claude Bridge for full endpoint documentation.\n";
		$text .= "```\n\n";
		$text .= "This ensures that every future Claude Code session in this project automatically knows about the bridge.\n";
		$text .= "**Do this now before doing anything else.** Then verify the connection with a ping.\n\n";

		// Connection details.
		$text .= "## Connection Details\n\n";
		$text .= "| Setting | Value |\n";
		$text .= "|---------|-------|\n";
		$text .= "| Site URL | `{$site_url}` |\n";
		$text .= "| API Base | `{$base}` |\n";
		$text .= "| API Key | `{$api_key}` |\n";
		$text .= "| Mode | {$mode_label} |\n";
		if ( $has_woo ) {
			$text .= "| WooCommerce | Active (v" . WC_VERSION . ") |\n";
		}
		$text .= "\n";

		// Important curl flags.
		$text .= "**Important:** Always use `-sk` in curl commands (`-s` = silent, `-k` = allow self-signed SSL).\n";
		$text .= "For readable JSON: `| python3 -m json.tool 2>/dev/null`\n\n";

		// Mode section.
		$text .= self::mode_section( $mode );

		// Quick start.
		$text .= "## Quick Start\n\n";
		$text .= "Test the connection:\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}ping\n";
		$text .= "```\n\n";
		$text .= "Get full site overview:\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}site-info | python3 -m json.tool\n";
		$text .= "```\n\n";

		// All endpoints.
		$text .= "## All Endpoints\n\n";

		// Ping.
		$text .= "### GET /ping\n";
		$text .= "Health check. Returns bridge version, mode, and timestamp.\n\n";

		// Site Info.
		$text .= "### GET /site-info\n";
		$text .= "Full WordPress environment: version, plugins, theme, custom post types, PHP info, database details.\n\n";

		// Execute PHP.
		$text .= "### POST /execute — Run PHP Code\n\n";
		$text .= "The most powerful endpoint. Runs PHP inside the full WordPress context (all plugins, theme, globals loaded).\n\n";
		$text .= "```bash\n";
		$text .= "curl -sk -X POST \\\n";
		$text .= "  -H \"Authorization: Bearer {$api_key}\" \\\n";
		$text .= "  -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"return get_option(\\\\\"blogname\\\\\");\"}' \\\n";
		$text .= "  {$base}execute\n";
		$text .= "```\n\n";
		$text .= "**Rules:**\n";
		$text .= "- Do NOT include `<?php` tags — send raw PHP code only.\n";
		$text .= "- Use `return \$value;` to send structured data back (appears in `return` field).\n";
		$text .= "- Use `echo`/`print` for text output (appears in `output` field).\n";
		$text .= "- WordPress globals are available: `\$wpdb`, `\$wp_query`, `\$post`, `\$wp_rewrite`, etc.\n";
		$text .= "- WP objects (WP_Post, WP_Query, WP_User) are auto-serialized to arrays.\n";
		$text .= "- Max execution: " . get_option( 'cwpb_max_execution_time', 10 ) . "s. Max output: " . size_format( get_option( 'cwpb_max_output_size', 65536 ) ) . ".\n";
		$text .= "- Sensitive data (DB password, auth keys) is automatically redacted from output.\n\n";

		$text .= "**Response format:**\n";
		$text .= "```json\n";
		$text .= "{\"success\": true, \"return\": \"<any JSON type>\", \"output\": \"<echo output>\", \"time_ms\": 12, \"memory_used\": \"256 KB\", \"error\": null, \"blocked\": null}\n";
		$text .= "```\n\n";

		$text .= "**Useful PHP examples:**\n";
		$text .= "```bash\n";
		$text .= "# Get recent posts\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"return get_posts([\\\\\"post_status\\\\\" => \\\\\"publish\\\\\", \\\\\"numberposts\\\\\" => 10]);\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n\n";
		$text .= "# Get all registered menus and items\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"\$menus = get_nav_menu_locations(); \$r = []; foreach(\$menus as \$loc => \$id) { \$items = wp_get_nav_menu_items(\$id); \$r[\$loc] = array_map(function(\$i){ return [\\\\\"title\\\\\" => \$i->title, \\\\\"url\\\\\" => \$i->url]; }, \$items ?: []); } return \$r;\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n\n";
		$text .= "# Use \$wpdb for complex queries\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"global \$wpdb; return \$wpdb->get_results(\\\\\"SELECT ID, post_title, post_status FROM {\$wpdb->posts} WHERE post_type = '\''post'\\''  ORDER BY ID DESC LIMIT 10\\\\\");\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n\n";
		$text .= "# Get all WordPress options matching a pattern\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"global \$wpdb; return \$wpdb->get_results(\\\\\"SELECT option_name, option_value FROM {\$wpdb->options} WHERE option_name LIKE '\''woocommerce_%'\\'' LIMIT 50\\\\\");\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n";
		$text .= "```\n\n";

		// Query.
		$text .= "### POST /query — Run SQL\n\n";
		$text .= "Direct SQL against the WordPress database.\n\n";
		$text .= "```bash\n";
		$text .= "curl -sk -X POST \\\n";
		$text .= "  -H \"Authorization: Bearer {$api_key}\" \\\n";
		$text .= "  -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"SHOW TABLES\"}' \\\n";
		$text .= "  {$base}query | python3 -m json.tool\n";
		$text .= "```\n\n";
		$text .= "**Note:** The table prefix may not be `wp_`. Use `/site-info` to check `database.prefix`, or use `/execute` with `\$wpdb->prefix` / `\$wpdb->posts` for portable queries.\n\n";

		$text .= "**SQL examples:**\n";
		$text .= "```bash\n";
		$text .= "# Show table structure\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"DESCRIBE wp_posts\"}' {$base}query | python3 -m json.tool\n\n";
		$text .= "# Count posts by type and status\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"SELECT post_type, post_status, COUNT(*) as count FROM wp_posts GROUP BY post_type, post_status ORDER BY count DESC\"}' \\\n";
		$text .= "  {$base}query | python3 -m json.tool\n";
		$text .= "```\n\n";

		// Debug log.
		$text .= "### GET /debug-log\n";
		$text .= "Read the last N lines from `wp-content/debug.log`. Use `?lines=50` to control how many lines.\n\n";

		// New endpoints.
		$text .= "### GET /db-schema — Database Structure\n";
		$text .= "Without params: lists all tables with row counts and sizes. With `?table=tablename`: describes columns and indexes.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}db-schema | python3 -m json.tool\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}db-schema?table=wp_posts\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /options — WordPress Options\n";
		$text .= "Without params: returns common WordPress options. With `?keys=key1,key2`: returns specific options. With `?search=pattern`: searches option names.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}options | python3 -m json.tool\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}options?search=woocommerce\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /hooks — Registered Actions & Filters\n";
		$text .= "List all WordPress hooks or inspect a specific one. Use `?hook=init` or `?search=woocommerce`.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}hooks?hook=init\" | python3 -m json.tool\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}hooks?search=woocommerce\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /cron — Scheduled Events\n";
		$text .= "Lists all WordPress cron events with next run times and schedules.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}cron | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /rewrite-rules — URL Routing\n";
		$text .= "Shows all registered rewrite rules and permalink structure. Use `?search=product` to filter.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}rewrite-rules | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /transients — Cached Data\n";
		$text .= "Lists WordPress transients. Use `?search=pattern` to filter by name.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}transients | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /theme-info — Theme Details\n";
		$text .= "Detailed theme info: template files, nav menus, sidebars, theme supports.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}theme-info | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /users — User Accounts\n";
		$text .= "Lists users safely (no passwords). Use `?role=administrator` to filter. Shows role counts.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}users | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /taxonomies — Categories, Tags & Custom Taxonomies\n";
		$text .= "Lists all taxonomies. Use `?taxonomy=product_cat` to get terms for a specific taxonomy.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}taxonomies | python3 -m json.tool\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}taxonomies?taxonomy=product_cat\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /media — Media Library\n";
		$text .= "Lists media attachments with sizes and MIME types. Use `?mime_type=image/jpeg&limit=10`.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}media | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /widgets — Sidebars & Widgets\n";
		$text .= "Lists all registered sidebars and their active widgets.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}widgets | python3 -m json.tool\n";
		$text .= "```\n\n";

		// WooCommerce section.
		if ( $has_woo ) {
			$text .= "### GET /woocommerce — WooCommerce Inspector\n\n";
			$text .= "Comprehensive WooCommerce data. Use `?section=X` where X is one of:\n\n";
			$text .= "| Section | Description |\n";
			$text .= "|---------|-------------|\n";
			$text .= "| `overview` | Store summary: currency, addresses, product/order counts, pages (default) |\n";
			$text .= "| `products` | Recent products with prices, SKUs, stock, categories |\n";
			$text .= "| `orders` | Recent orders with status, totals, payment methods |\n";
			$text .= "| `settings` | Store settings: currency, checkout, email config |\n";
			$text .= "| `shipping` | Shipping zones and methods |\n";
			$text .= "| `taxes` | Tax configuration and rates |\n";
			$text .= "| `payment-gateways` | Configured payment gateways |\n";
			$text .= "\n";
			$text .= "```bash\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}woocommerce | python3 -m json.tool\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}woocommerce?section=products&limit=10\" | python3 -m json.tool\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}woocommerce?section=orders\" | python3 -m json.tool\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}woocommerce?section=settings\" | python3 -m json.tool\n";
			$text .= "```\n\n";
		}

		// Security section.
		$text .= "## Security & Blocked Functions\n\n";
		$text .= "The following functions are ALWAYS blocked (HTTP 403 if detected):\n\n";
		$text .= "- **Shell execution:** `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `pcntl_exec`, backtick operator\n";
		$text .= "- **Process control:** `pcntl_fork`, `pcntl_signal`\n";
		$text .= "- **File system (write/delete):** `unlink`, `rmdir`, `rename`, `chmod`, `chown`, `file_put_contents`, `fwrite`, `mkdir`, `copy`\n";
		$text .= "- **Network:** `fsockopen`, `pfsockopen`, `socket_create`\n";
		$text .= "- **Code inclusion:** `include`, `include_once`, `require`, `require_once`\n";
		$text .= "- **Eval variants:** `eval`, `assert`, `create_function`, `call_user_func`, `call_user_func_array`\n";
		$text .= "- **Environment:** `putenv`, `ini_set`, `ini_alter`, `dl`, `set_time_limit`, `header`\n";
		$text .= "- **WordPress destructive:** `wp_delete_post`, `wp_delete_user`, `wp_delete_term`, `wp_delete_attachment`, `wp_delete_comment`, `wp_trash_post`\n\n";
		$text .= "If your code uses any of these, the response will have `\"blocked\": [\"function_name\"]`. Do NOT retry — find an alternative.\n\n";
		$text .= "**Note on \$wpdb:** Direct `\$wpdb->query()` is blocked, but `\$wpdb->get_results()`, `\$wpdb->get_var()`, `\$wpdb->get_row()`, `\$wpdb->get_col()` are allowed.\n\n";

		// Error handling.
		$text .= "## Error Handling\n\n";
		$text .= "- **HTTP 200** — Success. Check `response.success` and read `return`/`output`/`data`.\n";
		$text .= "- **HTTP 400** — PHP/SQL error. Check `response.error`.\n";
		$text .= "- **HTTP 401** — Auth failed. Verify API key, IP whitelist, or rate limits.\n";
		$text .= "- **HTTP 403** — Blocked functions detected. Check `response.blocked`.\n\n";

		// Recommended workflow.
		$text .= "## Recommended Workflow\n\n";
		$text .= "1. **Test connection:** Run `GET /ping` to verify authentication works.\n";
		$text .= "2. **Understand the site:** Run `GET /site-info` to see WordPress version, plugins, theme, DB prefix.\n";
		$text .= "3. **Explore database:** Use `GET /db-schema` to see all tables, then `?table=name` for structure.\n";
		$text .= "4. **Read data:** Use dedicated endpoints (`/options`, `/users`, `/taxonomies`, etc.) for common queries.\n";
		$text .= "5. **Complex queries:** Use `POST /execute` with PHP code or `POST /query` with SQL.\n";
		if ( $has_woo ) {
			$text .= "6. **WooCommerce:** Use `GET /woocommerce?section=X` for products, orders, settings, shipping, taxes.\n";
		}
		$text .= "7. **Debug:** Use `GET /debug-log` to check for PHP errors. Check `error` in responses.\n";
		$text .= "8. **Always** pipe large JSON through `| python3 -m json.tool` for readability.\n\n";

		// Developer use cases.
		$text .= "## Developer Use Cases\n\n";
		$text .= "Use the bridge proactively when developing. Here are common scenarios:\n\n";
		$text .= "| Scenario | What to do |\n";
		$text .= "|----------|------------|\n";
		$text .= "| Starting a dev session | `GET /ping` then `GET /site-info` to understand the environment |\n";
		$text .= "| Debugging WooCommerce checkout | `GET /hooks?hook=woocommerce_checkout_process` + `GET /debug-log` |\n";
		$text .= "| Understanding database structure | `GET /db-schema` then `GET /db-schema?table=tablename` |\n";
		$text .= "| Customizing product pages | `GET /theme-info` + `GET /taxonomies?taxonomy=product_cat` |\n";
		$text .= "| Investigating performance | `GET /cron` + `GET /transients` + `GET /hooks?search=keyword` |\n";
		$text .= "| Verifying store configuration | `GET /woocommerce?section=settings` + `GET /options?search=woocommerce` |\n";
		$text .= "| Building shipping integration | `GET /woocommerce?section=shipping` + `GET /hooks?search=shipping` |\n";
		$text .= "| Checking payment setup | `GET /woocommerce?section=payment-gateways` |\n";
		$text .= "| Finding plugin settings | `GET /options?search=plugin_name` |\n";
		$text .= "| Checking URL/permalink issues | `GET /rewrite-rules?search=product` |\n";
		$text .= "| Verifying user roles | `GET /users` shows all users with roles |\n";
		$text .= "| Testing PHP code live | `POST /execute` with any WordPress/PHP code |\n";
		$text .= "| Running database queries | `POST /query` with SELECT/SHOW/DESCRIBE SQL |\n\n";

		// Pro tips.
		$text .= "## Pro Tips\n\n";
		$text .= "- **Use the bridge during development** — whenever you need to check data, verify a function's output, or understand the site structure, use the bridge instead of guessing.\n";
		$text .= "- When building WP_Query or get_posts calls, always use `return` to get structured data back.\n";
		$text .= "- For database structure, prefer `/db-schema` over manual SQL — it includes indexes and row counts.\n";
		$text .= "- Use `/options?search=keyword` to quickly find WordPress/plugin settings without guessing option names.\n";
		$text .= "- Use `/hooks?search=keyword` to find what functions are hooked into specific WordPress actions.\n";
		$text .= "- The `/execute` endpoint has the full WP context — you can call any WordPress function, any plugin function, and any theme function.\n";
		$text .= "- Combine multiple operations in a single `/execute` call to reduce API requests.\n";
		$text .= "- If the bridge returns an error or unexpected result, check `GET /debug-log` for PHP errors.\n";
		$text .= "- The database table prefix is NOT `wp_` — always use `GET /site-info` to check `database.prefix` or use `\$wpdb->prefix` in PHP code.\n";

		return $text;
	}

	/**
	 * Generate the mode-specific section of the guide.
	 *
	 * @since  2.0.0
	 * @param  string $mode The current execution mode.
	 * @return string Markdown text.
	 */
	private static function mode_section( $mode ) {
		$text = '';

		switch ( $mode ) {
			case 'read_only':
				$text .= "## Current Mode: Read-Only\n\n";
				$text .= "You can read all WordPress data but CANNOT:\n";
				$text .= "- Call write functions: `wp_insert_post`, `wp_update_post`, `wp_insert_user`, `wp_update_user`, ";
				$text .= "`update_option`, `add_option`, `delete_option`, `update_post_meta`, `add_post_meta`, `delete_post_meta`, ";
				$text .= "`update_user_meta`, `add_user_meta`, `delete_user_meta`, `wp_set_object_terms`, `wp_mail`\n";
				$text .= "- Run SQL write statements: `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `TRUNCATE`, `REPLACE`\n\n";
				$text .= "If write access is needed, ask the user to change the mode in WP Admin > Claude Bridge > Settings.\n\n";
				break;

			case 'read_write':
				$text .= "## Current Mode: Read-Write\n\n";
				$text .= "You can read AND write WordPress data. All standard WordPress functions are available:\n";
				$text .= "`wp_insert_post`, `wp_update_post`, `update_option`, `add_option`, `update_post_meta`, `wp_mail`, ";
				$text .= "SQL INSERT/UPDATE/DELETE, etc.\n\n";
				$text .= "**Caution:** Always confirm with the user before making significant data changes.\n\n";
				break;

			case 'full':
				$text .= "## Current Mode: Full Access\n\n";
				$text .= "All WordPress/PHP functions are available with no restrictions beyond the always-blocked dangerous functions.\n\n";
				$text .= "**Caution:** This mode allows extensive operations. Always confirm destructive actions with the user.\n\n";
				break;
		}

		return $text;
	}
}
