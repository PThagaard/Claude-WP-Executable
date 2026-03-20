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
		global $wpdb;

		$base      = rest_url( 'claude-bridge/v1/' );
		$site_url  = get_site_url();
		$site_name = get_bloginfo( 'name' );
		$mode      = get_option( 'cwpb_execution_mode', 'read_only' );
		$has_woo   = class_exists( 'WooCommerce' );
		$prefix    = $wpdb->prefix;
		$theme     = wp_get_theme();
		$parent    = $theme->parent();
		$theme_str = $parent ? $theme->get( 'Name' ) . ' (child of ' . $parent->get( 'Name' ) . ')' : $theme->get( 'Name' );

		$mode_labels = array(
			'read_only'  => 'Read-Only',
			'read_write' => 'Read-Write',
			'full'       => 'Full Access',
		);
		$mode_label = $mode_labels[ $mode ] ?? $mode;

		$rate_limit  = (int) get_option( 'cwpb_rate_limit', 30 );
		$rate_window = (int) get_option( 'cwpb_rate_limit_window', 60 );

		$text = '';

		// Header.
		$text .= "# Claude WP Bridge — {$site_name}\n\n";
		$text .= "You have access to a live WordPress site through the Claude WP Bridge plugin.\n";
		$text .= "This gives you real-time PHP execution, SQL queries, and deep inspection of the WordPress/WooCommerce stack via REST API.\n";
		$text .= "All requests use `curl` with Bearer token auth. All responses are JSON.\n\n";

		// CRITICAL: CLAUDE.md instruction.
		$text .= "## IMPORTANT: Save These Credentials\n\n";
		$text .= "**Your first action** must be to save these connection details so they persist across sessions.\n";
		$text .= "Create or update a `CLAUDE.md` file in the project root:\n\n";
		$text .= "```markdown\n";
		$text .= "# Project: {$site_name}\n\n";
		$text .= "## WordPress Bridge (Claude WP Bridge v" . CWPB_VERSION . ")\n\n";
		$text .= "This project has a live WordPress bridge. Execute PHP and SQL directly on the site.\n\n";
		$text .= "- Site URL: {$site_url}\n";
		$text .= "- API Base: {$base}\n";
		$text .= "- API Key: {$api_key}\n";
		$text .= "- Mode: {$mode_label}\n";
		if ( $has_woo ) {
			$text .= "- WooCommerce: Active (v" . WC_VERSION . ")\n";
		}
		$text .= "- DB Prefix: {$prefix}\n";
		$text .= "- Theme: {$theme_str}\n";
		$text .= "- WordPress: " . get_bloginfo( 'version' ) . "\n";
		$text .= "- PHP: " . PHP_VERSION . "\n";
		$text .= "- Locale: " . get_locale() . "\n\n";
		$text .= "Always use `curl -sk` flags. Pipe JSON through `| python3 -m json.tool 2>/dev/null`.\n\n";
		$text .= "### Available Endpoints (18 total)\n\n";
		$text .= "| Endpoint | Method | Description |\n";
		$text .= "|----------|--------|-------------|\n";
		$text .= "| /ping | GET | Health check |\n";
		$text .= "| /site-info | GET | Full WP environment |\n";
		$text .= "| /execute | POST | Run PHP code |\n";
		$text .= "| /query | POST | Run SQL queries |\n";
		$text .= "| /debug-log | GET | Read debug.log |\n";
		$text .= "| /db-schema | GET | Database tables & structure |\n";
		$text .= "| /options | GET | WordPress options |\n";
		$text .= "| /hooks | GET | Registered actions & filters |\n";
		$text .= "| /cron | GET | Scheduled events |\n";
		$text .= "| /rewrite-rules | GET | URL routing rules |\n";
		$text .= "| /transients | GET | Cached data |\n";
		$text .= "| /woocommerce | GET | WC overview, products, orders, settings, shipping, taxes, gateways |\n";
		$text .= "| /theme-info | GET | Theme files, menus, sidebars |\n";
		$text .= "| /users | GET | User accounts (safe) |\n";
		$text .= "| /taxonomies | GET | Categories, tags, custom taxonomies |\n";
		$text .= "| /media | GET | Media library |\n";
		$text .= "| /widgets | GET | Sidebars & widgets |\n\n";
		$text .= "### Quick Test\n\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}ping\n";
		$text .= "```\n\n";
		$text .= "See the plugin's \"Copy to AI\" button in WP Admin > Claude Bridge for full endpoint documentation with examples.\n";
		$text .= "```\n\n";
		$text .= "**Do this now before doing anything else.** Then verify the connection with a ping.\n\n";

		// Connection details.
		$text .= "## Connection Details\n\n";
		$text .= "| Setting | Value |\n";
		$text .= "|---------|-------|\n";
		$text .= "| Site URL | `{$site_url}` |\n";
		$text .= "| API Base | `{$base}` |\n";
		$text .= "| API Key | `{$api_key}` |\n";
		$text .= "| Mode | {$mode_label} |\n";
		$text .= "| DB Prefix | `{$prefix}` |\n";
		$text .= "| Theme | {$theme_str} |\n";
		$text .= "| WordPress | " . get_bloginfo( 'version' ) . " |\n";
		$text .= "| PHP | " . PHP_VERSION . " |\n";
		$text .= "| Locale | " . get_locale() . " |\n";
		if ( $has_woo ) {
			$text .= "| WooCommerce | v" . WC_VERSION . " |\n";
		}
		$text .= "| Rate Limit | {$rate_limit} requests per {$rate_window}s |\n";
		$text .= "\n";

		// Important curl flags.
		$text .= "**Curl flags:** Always use `-sk` (`-s` = silent, `-k` = allow self-signed SSL).\n";
		$text .= "**JSON output:** Pipe through `| python3 -m json.tool 2>/dev/null`\n\n";

		// Rate limit warning.
		$text .= "**Rate limit:** {$rate_limit} requests per {$rate_window} seconds. Batch related queries into single `/execute` calls when possible.\n\n";

		// Mode section.
		$text .= self::mode_section( $mode );

		// Quick start.
		$text .= "## Quick Start\n\n";
		$text .= "```bash\n";
		$text .= "# 1. Test connection\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}ping\n\n";
		$text .= "# 2. Full site overview (WP version, plugins, theme, DB prefix, PHP info)\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}site-info | python3 -m json.tool\n";
		$text .= "```\n\n";

		// DB prefix warning.
		$text .= "**CRITICAL: Database table prefix is `{$prefix}` (NOT `wp_`).** Always use `{$prefix}` in raw SQL, or use `\$wpdb->prefix` / `\$wpdb->posts` etc. in PHP code.\n\n";

		// All endpoints.
		$text .= "## Endpoints Reference\n\n";

		// Ping.
		$text .= "### GET /ping\n";
		$text .= "Health check. Returns bridge version, mode, and timestamp.\n\n";

		// Site Info.
		$text .= "### GET /site-info\n";
		$text .= "Full WordPress environment: version, plugins, theme, custom post types, PHP info, database details including prefix.\n\n";

		// Execute PHP.
		$text .= "### POST /execute — Run PHP Code\n\n";
		$text .= "The most powerful endpoint. Runs arbitrary PHP inside the full WordPress context (all plugins, theme, globals loaded).\n\n";
		$text .= "```bash\n";
		$text .= "curl -sk -X POST \\\n";
		$text .= "  -H \"Authorization: Bearer {$api_key}\" \\\n";
		$text .= "  -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"return get_option(\\\\\"blogname\\\\\");\"}' \\\n";
		$text .= "  {$base}execute\n";
		$text .= "```\n\n";
		$text .= "**Rules:**\n";
		$text .= "- Do NOT include `<?php` tags — send raw PHP code only.\n";
		$text .= "- Use `return \$value;` to get structured data back (in `return` field).\n";
		$text .= "- Use `echo`/`print` for text output (in `output` field).\n";
		$text .= "- WordPress globals available: `\$wpdb`, `\$wp_query`, `\$post`, `\$wp_rewrite`, etc.\n";
		$text .= "- WP objects (WP_Post, WP_Query, WP_User) are auto-serialized to arrays.\n";
		$text .= "- Max execution: " . get_option( 'cwpb_max_execution_time', 10 ) . "s. Max output: " . size_format( get_option( 'cwpb_max_output_size', 65536 ) ) . ".\n";
		$text .= "- Sensitive data (DB password, auth keys) is automatically redacted.\n\n";

		$text .= "**Response format:**\n";
		$text .= "```json\n";
		$text .= "{\"success\": true, \"return\": \"<any JSON type>\", \"output\": \"<echo output>\", \"time_ms\": 12, \"memory_used\": \"256 KB\", \"error\": null, \"blocked\": null}\n";
		$text .= "```\n\n";

		// PHP examples - using $wpdb properties for portability.
		$text .= "**PHP examples:**\n";
		$text .= "```bash\n";
		$text .= "# Get recent posts\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"return get_posts([\\\\\"post_status\\\\\" => \\\\\"publish\\\\\", \\\\\"numberposts\\\\\" => 10]);\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n\n";
		$text .= "# Use \$wpdb with proper prefix (NEVER hardcode wp_)\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"code\": \"global \$wpdb; return \$wpdb->get_results(\\\\\"SELECT ID, post_title, post_status FROM {\$wpdb->posts} WHERE post_type = '\\''post'\\'' ORDER BY ID DESC LIMIT 10\\\\\");\"}' \\\n";
		$text .= "  {$base}execute | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "**Tip for complex PHP:** When code has many quotes or is multiline, build the JSON payload in a variable:\n";
		$text .= "```bash\n";
		$text .= "PAYLOAD=$(cat <<'PHPEOF'\n";
		$text .= "{\n";
		$text .= "  \"code\": \"global \$wpdb; \$results = \$wpdb->get_results(\\\"SELECT post_type, post_status, COUNT(*) as cnt FROM {\$wpdb->posts} GROUP BY post_type, post_status ORDER BY cnt DESC\\\"); return \$results;\"\n";
		$text .= "}\n";
		$text .= "PHPEOF\n";
		$text .= ")\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d \"\$PAYLOAD\" {$base}execute | python3 -m json.tool\n";
		$text .= "```\n\n";

		// Query.
		$text .= "### POST /query — Run SQL\n\n";
		$text .= "Direct SQL against the WordPress database. Table prefix is **`{$prefix}`**.\n\n";
		$text .= "```bash\n";
		$text .= "curl -sk -X POST \\\n";
		$text .= "  -H \"Authorization: Bearer {$api_key}\" \\\n";
		$text .= "  -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"SHOW TABLES\"}' \\\n";
		$text .= "  {$base}query | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "**SQL examples (using correct prefix `{$prefix}`):**\n";
		$text .= "```bash\n";
		$text .= "# Table structure\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"DESCRIBE {$prefix}posts\"}' {$base}query | python3 -m json.tool\n\n";
		$text .= "# Posts by type and status\n";
		$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
		$text .= "  -d '{\"sql\": \"SELECT post_type, post_status, COUNT(*) as cnt FROM {$prefix}posts GROUP BY post_type, post_status ORDER BY cnt DESC\"}' \\\n";
		$text .= "  {$base}query | python3 -m json.tool\n";
		if ( $has_woo ) {
			$text .= "\n# WooCommerce orders (HPOS table)\n";
			$text .= "curl -sk -X POST -H \"Authorization: Bearer {$api_key}\" -H \"Content-Type: application/json\" \\\n";
			$text .= "  -d '{\"sql\": \"SELECT status, COUNT(*) as cnt FROM {$prefix}wc_orders GROUP BY status ORDER BY cnt DESC\"}' \\\n";
			$text .= "  {$base}query | python3 -m json.tool\n";
		}
		$text .= "```\n\n";
		$text .= "**Prefer `/execute` with `\$wpdb->prefix` for portable queries**, or use `/db-schema` to inspect tables first.\n\n";

		// Debug log.
		$text .= "### GET /debug-log\n";
		$text .= "Read the last N lines from `wp-content/debug.log`. Use `?lines=50` to control how many.\n\n";

		// New endpoints - concise reference.
		$text .= "### GET /db-schema — Database Structure\n";
		$text .= "No params: all tables with row counts and sizes. `?table={$prefix}posts`: columns, indexes, create statement.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}db-schema | python3 -m json.tool\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}db-schema?table={$prefix}posts\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /options — WordPress Options\n";
		$text .= "No params: common WP options. `?keys=key1,key2`: specific keys. `?search=pattern`: search names.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}options?search=woocommerce\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /hooks — Actions & Filters\n";
		$text .= "`?hook=init`: all callbacks on a specific hook. `?search=woocommerce`: find hooks by name.\n";
		$text .= "```bash\n";
		$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}hooks?hook=woocommerce_checkout_process\" | python3 -m json.tool\n";
		$text .= "```\n\n";

		$text .= "### GET /cron — Scheduled Events\n";
		$text .= "All WP cron events with next run, schedule, and interval.\n\n";

		$text .= "### GET /rewrite-rules — URL Routing\n";
		$text .= "All rewrite rules and permalink structure. `?search=product` to filter.\n\n";

		$text .= "### GET /transients — Cached Data\n";
		$text .= "All transients. `?search=wc` to filter by name.\n\n";

		$text .= "### GET /theme-info — Theme Details\n";
		$text .= "Template files, nav menus, sidebars, theme supports, parent/child info.\n\n";

		$text .= "### GET /users — User Accounts\n";
		$text .= "User list (no passwords). `?role=administrator` to filter. Includes role summary.\n\n";

		$text .= "### GET /taxonomies — Taxonomies & Terms\n";
		$text .= "All taxonomies. `?taxonomy=product_cat` to get terms with counts.\n\n";

		$text .= "### GET /media — Media Library\n";
		$text .= "Attachments with sizes and MIME types. `?mime_type=image/jpeg&limit=10`.\n\n";

		$text .= "### GET /widgets — Sidebars & Widgets\n";
		$text .= "All registered sidebars and their active widgets.\n\n";

		// WooCommerce section.
		if ( $has_woo ) {
			$text .= "### GET /woocommerce — WooCommerce Inspector\n\n";
			$text .= "Comprehensive WC data via `?section=X`:\n\n";
			$text .= "| Section | Data |\n";
			$text .= "|---------|------|\n";
			$text .= "| `overview` | Store summary: currency, addresses, product/order counts, pages (default) |\n";
			$text .= "| `products` | Products with prices, SKUs, stock, categories. `&limit=N` |\n";
			$text .= "| `orders` | Orders with status, totals, payment methods. `&limit=N` |\n";
			$text .= "| `settings` | Currency format, checkout options, email config |\n";
			$text .= "| `shipping` | Shipping zones and methods |\n";
			$text .= "| `taxes` | Tax rates, classes, and settings |\n";
			$text .= "| `payment-gateways` | Active/inactive payment methods |\n";
			$text .= "\n";
			$text .= "```bash\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" {$base}woocommerce | python3 -m json.tool\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}woocommerce?section=products&limit=10\" | python3 -m json.tool\n";
			$text .= "curl -sk -H \"Authorization: Bearer {$api_key}\" \"{$base}woocommerce?section=orders&limit=5\" | python3 -m json.tool\n";
			$text .= "```\n\n";
		}

		// When NOT to use bridge.
		$text .= "## When NOT to Use the Bridge\n\n";
		$text .= "The bridge is for **inspecting and interacting with live WordPress data**. Do NOT use it for:\n\n";
		$text .= "- **Reading/writing theme or plugin source files** — use local filesystem tools instead.\n";
		$text .= "- **Version control operations** — use git directly.\n";
		$text .= "- **Installing/updating plugins or themes** — use WP CLI or the admin UI.\n";
		$text .= "- **Bulk data imports** — use WP CLI (`wp import`) or dedicated import tools.\n";
		$text .= "- **Long-running processes** — max execution is " . get_option( 'cwpb_max_execution_time', 10 ) . "s.\n\n";
		$text .= "Use the bridge when you need to: query the database, inspect runtime state, call WordPress/WooCommerce API functions, check configuration, debug hooks, or verify data.\n\n";

		// Security section.
		$text .= "## Security & Blocked Functions\n\n";
		$text .= "These functions are **always blocked** (HTTP 403):\n\n";
		$text .= "- **Shell:** `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `pcntl_exec`, backtick operator\n";
		$text .= "- **File writes:** `file_put_contents`, `fwrite`, `fput`, `fputcsv`, `mkdir`, `copy`, `move_uploaded_file`, `unlink`, `rmdir`, `rename`, `chmod`, `chown`\n";
		$text .= "- **Network:** `fsockopen`, `pfsockopen`, `socket_create`\n";
		$text .= "- **Code inclusion:** `include`, `include_once`, `require`, `require_once`, `eval`, `assert`, `create_function`\n";
		$text .= "- **Environment:** `putenv`, `ini_set`, `ini_alter`, `dl`, `set_time_limit`, `header`\n";
		$text .= "- **WP destructive:** `wp_delete_post`, `wp_delete_user`, `wp_delete_term`, `wp_delete_attachment`, `wp_delete_comment`, `wp_trash_post`\n\n";
		$text .= "**Allowed for reading:** `file_get_contents` (read-only), `file_exists`, `is_file`, `is_dir`, `glob`, `scandir`, `realpath`, `pathinfo`. You CAN read files on the server.\n\n";
		$text .= "If code triggers a block: `\"blocked\": [\"function_name\"]` in response. Do NOT retry — find an alternative approach.\n\n";
		$text .= "**\$wpdb note:** `\$wpdb->query()` is blocked (prevents arbitrary writes). Use `\$wpdb->get_results()`, `\$wpdb->get_var()`, `\$wpdb->get_row()`, `\$wpdb->get_col()` instead.\n\n";

		// Error handling.
		$text .= "## Error Handling\n\n";
		$text .= "| HTTP | Meaning |\n";
		$text .= "|------|--------|\n";
		$text .= "| 200 | Success. Check `response.success`, read `return`/`output`/`data`. |\n";
		$text .= "| 400 | PHP/SQL error. Check `response.error`. |\n";
		$text .= "| 401 | Auth failed. Verify API key, IP whitelist, or rate limit. |\n";
		$text .= "| 403 | Blocked function detected. Check `response.blocked`. |\n";
		$text .= "| 429 | Rate limited. Wait and retry ({$rate_limit} req/{$rate_window}s). |\n\n";

		// Recommended workflow.
		$text .= "## Recommended Workflow\n\n";
		$text .= "1. **Ping** — `GET /ping` to verify auth.\n";
		$text .= "2. **Site info** — `GET /site-info` for WP version, plugins, theme, DB prefix.\n";
		$text .= "3. **Explore DB** — `GET /db-schema` for all tables, `?table=name` for structure.\n";
		$text .= "4. **Read data** — Use dedicated endpoints (`/options`, `/users`, `/taxonomies`, etc.).\n";
		$text .= "5. **Complex queries** — `POST /execute` (PHP) or `POST /query` (SQL).\n";
		if ( $has_woo ) {
			$text .= "6. **WooCommerce** — `GET /woocommerce?section=X` for products, orders, settings, shipping, taxes.\n";
		}
		$text .= "7. **Debug** — `GET /debug-log` for PHP errors. Check `error` in every response.\n";
		$text .= "8. **Batch** — Combine related operations in one `/execute` call to stay within rate limits.\n\n";

		// Developer use cases.
		$text .= "## Developer Use Cases\n\n";
		$text .= "| Scenario | Approach |\n";
		$text .= "|----------|----------|\n";
		$text .= "| Start dev session | `/ping` → `/site-info` → `/woocommerce` |\n";
		$text .= "| Debug checkout | `/hooks?hook=woocommerce_checkout_process` → `/debug-log` |\n";
		$text .= "| Understand DB | `/db-schema` → `/db-schema?table={$prefix}tablename` → `/query` |\n";
		$text .= "| Customize product pages | `/theme-info` → `/taxonomies?taxonomy=product_cat` → `/execute` |\n";
		$text .= "| Performance issues | `/cron` → `/transients` → `/hooks?search=keyword` |\n";
		$text .= "| Verify store config | `/woocommerce?section=settings` → `/options?search=woocommerce` |\n";
		$text .= "| Shipping integration | `/woocommerce?section=shipping` → `/hooks?search=shipping` |\n";
		$text .= "| Payment gateway work | `/woocommerce?section=payment-gateways` → `/hooks?search=payment` |\n";
		$text .= "| Find plugin settings | `/options?search=plugin_name` |\n";
		$text .= "| URL/permalink issues | `/rewrite-rules?search=product` |\n";
		$text .= "| User/role management | `/users` → `/execute` with `wp_roles` |\n";
		$text .= "| Run PHP live | `/execute` — any WP/WC/plugin function |\n";
		$text .= "| Run SQL | `/query` — SELECT/SHOW/DESCRIBE (use `{$prefix}` prefix!) |\n\n";

		// Pro tips.
		$text .= "## Pro Tips\n\n";
		$text .= "- **Use the bridge proactively** — query live data instead of guessing. It's faster and more accurate.\n";
		$text .= "- **DB prefix is `{$prefix}`** — never assume `wp_`. Use `\$wpdb->posts`, `\$wpdb->options`, etc. in PHP.\n";
		$text .= "- **Batch operations** — combine related queries in a single `/execute` call to conserve rate limit.\n";
		$text .= "- For DB structure, prefer `/db-schema` over manual SQL — it includes indexes and row counts.\n";
		$text .= "- Use `/options?search=keyword` to find plugin settings without guessing option names.\n";
		$text .= "- Use `/hooks?search=keyword` to discover what's hooked into specific WP actions.\n";
		$text .= "- The `/execute` endpoint has the full WP context — any WordPress, WooCommerce, or plugin function.\n";
		$text .= "- If the bridge returns an error, check `GET /debug-log` for PHP-level errors.\n";
		$text .= "- **File reading IS allowed** — `file_get_contents('/path/to/file')` works in `/execute`.\n";

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
				$text .= "- Run SQL writes: `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `TRUNCATE`, `REPLACE`\n\n";
				$text .= "If write access is needed, ask the user to change mode in WP Admin > Claude Bridge > Settings.\n\n";
				break;

			case 'read_write':
				$text .= "## Current Mode: Read-Write\n\n";
				$text .= "You can read AND write WordPress data. All standard functions available:\n";
				$text .= "`wp_insert_post`, `wp_update_post`, `update_option`, `update_post_meta`, `wp_mail`, SQL INSERT/UPDATE/DELETE, etc.\n\n";
				$text .= "**Caution:** Always confirm with the user before making significant data changes.\n\n";
				break;

			case 'full':
				$text .= "## Current Mode: Full Access\n\n";
				$text .= "All WordPress/PHP functions available with no restrictions beyond the always-blocked dangerous functions.\n\n";
				$text .= "**Caution:** This mode allows extensive operations. Always confirm destructive actions with the user.\n\n";
				break;
		}

		return $text;
	}
}
