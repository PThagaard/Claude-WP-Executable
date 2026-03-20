/**
 * Claude WP Bridge — Admin JavaScript
 *
 * Handles API key generation, revocation, clipboard copy,
 * and audit log management via AJAX.
 *
 * @package ClaudeWPBridge
 * @since   1.0.0
 */

/* global jQuery, cwpb */
(function ($) {
	'use strict';

	/**
	 * Copy text to the clipboard.
	 *
	 * @param {string} text    The text to copy.
	 * @param {string} message Optional alert message (defaults to key_copied).
	 */
	function copyToClipboard(text, message) {
		var msg = message || cwpb.strings.key_copied;
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				alert(msg);
			});
		} else {
			// Fallback for older browsers.
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();
			alert(msg);
		}
	}

	// Generate API Key.
	$(document).on('click', '#cwpb-generate-key, #cwpb-regenerate-key', function (e) {
		e.preventDefault();

		var isRegenerate = $(this).attr('id') === 'cwpb-regenerate-key';
		if (isRegenerate && !confirm(cwpb.strings.confirm_generate)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true).text('Generating...');

		$.post(cwpb.ajax_url, {
			action: 'cwpb_generate_key',
			nonce: cwpb.nonce
		}, function (response) {
			if (response.success) {
				// Reload so the persistent key display shows the new key.
				location.reload();
			} else {
				alert('Error: ' + (response.data || 'Unknown error'));
				$button.prop('disabled', false).text(isRegenerate ? 'Regenerate Key' : 'Generate API Key');
			}
		}).fail(function () {
			alert('Request failed. Please try again.');
			$button.prop('disabled', false).text(isRegenerate ? 'Regenerate Key' : 'Generate API Key');
		});
	});

	// Revoke API Key.
	$(document).on('click', '#cwpb-revoke-key', function (e) {
		e.preventDefault();

		if (!confirm(cwpb.strings.confirm_revoke)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true);

		$.post(cwpb.ajax_url, {
			action: 'cwpb_revoke_key',
			nonce: cwpb.nonce
		}, function (response) {
			if (response.success) {
				location.reload();
			} else {
				alert('Error: ' + (response.data || 'Unknown error'));
				$button.prop('disabled', false);
			}
		});
	});

	// Copy API Key.
	$(document).on('click', '#cwpb-copy-key', function (e) {
		e.preventDefault();
		var key = $('#cwpb-new-key').text();
		copyToClipboard(key);
	});

	// Copy to AI — full instruction block for any Claude/AI session.
	$(document).on('click', '#cwpb-copy-ai', function (e) {
		e.preventDefault();
		var key = $('#cwpb-new-key').text().trim();
		if (!key) {
			return;
		}
		var base = cwpb.endpoint_url;
		var mode = cwpb.execution_mode || 'read_only';
		var siteName = cwpb.site_name || 'WordPress Site';

		// Derive site URL from the REST base (strip /wp-json/claude-bridge/v1/).
		var siteUrl = base.replace(/\/wp-json\/claude-bridge\/v1\/$/, '');

		var modeLabel = {
			'read_only':  'Read-Only',
			'read_write': 'Read-Write',
			'full':       'Full'
		}[mode] || mode;

		// Build mode explanation.
		var modeSection = '';
		if (mode === 'read_only') {
			modeSection =
				'## Current Mode: Read-Only\n' +
				'\n' +
				'You can read all WordPress data but CANNOT:\n' +
				'- Call write functions: `wp_insert_post`, `wp_update_post`, `wp_insert_user`, `wp_update_user`, ' +
				'`update_option`, `add_option`, `delete_option`, `update_post_meta`, `add_post_meta`, `delete_post_meta`, ' +
				'`update_user_meta`, `add_user_meta`, `delete_user_meta`, `wp_set_object_terms`, `wp_mail`\n' +
				'- Run SQL write statements: `INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `TRUNCATE`, `REPLACE`\n' +
				'\n' +
				'If write access is needed, ask the user to change the mode in WP Admin → Claude Bridge → Settings.\n' +
				'\n';
		} else if (mode === 'read_write') {
			modeSection =
				'## Current Mode: Read-Write\n' +
				'\n' +
				'You can read AND write WordPress data. All standard WordPress functions are available including:\n' +
				'`wp_insert_post`, `wp_update_post`, `update_option`, `add_option`, `update_post_meta`, `wp_mail`, ' +
				'SQL INSERT/UPDATE/DELETE, etc.\n' +
				'\n';
		} else {
			modeSection =
				'## Current Mode: Full Access\n' +
				'\n' +
				'All WordPress/PHP functions are available with no restrictions beyond the always-blocked dangerous functions listed below.\n' +
				'\n';
		}

		var text =
			'# Claude WP Bridge — ' + siteName + '\n' +
			'\n' +
			'You have access to a live WordPress site through the Claude WP Bridge plugin.\n' +
			'This plugin exposes a REST API that lets you execute PHP code, run SQL queries, and inspect the WordPress installation.\n' +
			'All communication happens via `curl` with Bearer token authentication. All responses are JSON.\n' +
			'\n' +
			'## Connection Details\n' +
			'\n' +
			'| Setting | Value |\n' +
			'|---------|-------|\n' +
			'| Site URL | `' + siteUrl + '` |\n' +
			'| API Base | `' + base + '` |\n' +
			'| API Key | `' + key + '` |\n' +
			'| Mode | ' + modeLabel + ' |\n' +
			'\n' +
			'**Important curl flags:** Always use `-sk` in your curl commands:\n' +
			'- `-s` = silent mode (no progress bars)\n' +
			'- `-k` = allow self-signed / invalid SSL certificates (many WordPress sites use these)\n' +
			'\n' +
			'For readable JSON output, pipe through: `| python3 -m json.tool 2>/dev/null`\n' +
			'\n' +
			modeSection +
			'## Quick Start — Test the Connection\n' +
			'\n' +
			'Run this command first to verify everything works:\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -H "Authorization: Bearer ' + key + '" ' + base + 'site-info | python3 -m json.tool 2>/dev/null || curl -sk -H "Authorization: Bearer ' + key + '" ' + base + 'site-info\n' +
			'```\n' +
			'\n' +
			'This returns the full WordPress environment: version, plugins, theme, custom post types, PHP info, and database details.\n' +
			'If you get a valid JSON response, the connection is working.\n' +
			'\n' +
			'## All Endpoints\n' +
			'\n' +
			'### 1. Ping — `GET /ping`\n' +
			'\n' +
			'Simple health check to verify auth and connectivity.\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -H "Authorization: Bearer ' + key + '" ' + base + 'ping\n' +
			'```\n' +
			'\n' +
			'Response:\n' +
			'```json\n' +
			'{ "success": true, "message": "Claude WP Bridge is active and authenticated.", "version": "1.0.0", "mode": "' + mode + '", "timestamp": "..." }\n' +
			'```\n' +
			'\n' +
			'### 2. Site Info — `GET /site-info`\n' +
			'\n' +
			'Returns comprehensive WordPress environment details.\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -H "Authorization: Bearer ' + key + '" ' + base + 'site-info | python3 -m json.tool\n' +
			'```\n' +
			'\n' +
			'Response includes:\n' +
			'- `wordpress`: version, site_url, home_url, name, multisite, locale\n' +
			'- `php`: version, memory_limit, max_execution_time, loaded extensions\n' +
			'- `database`: server info, table prefix, charset\n' +
			'- `theme`: name, version, template, parent theme\n' +
			'- `plugins`: array of active plugins with name, version, file\n' +
			'- `custom_post_types`: array with name, label, publish count\n' +
			'- `bridge`: plugin version and execution mode\n' +
			'\n' +
			'### 3. Execute PHP — `POST /execute`\n' +
			'\n' +
			'The most powerful endpoint. Runs arbitrary PHP code inside the full WordPress environment.\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -X POST \\\n' +
			'  -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return get_option(\\"blogname\\");"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'```\n' +
			'\n' +
			'**How it works:**\n' +
			'- Your code runs inside an anonymous closure with full WordPress context loaded (all plugins, theme, globals).\n' +
			'- Do NOT include `<?php` tags — send raw PHP code only.\n' +
			'- Use `return $value;` to send structured data back → appears in the `return` field of the response.\n' +
			'- Use `echo` / `print` for text output → appears in the `output` field of the response.\n' +
			'- You can use both `return` and `echo` in the same request.\n' +
			'- WordPress globals are accessible: `$wpdb`, `$wp_query`, `$post`, `$wp_rewrite`, etc.\n' +
			'- Objects like `WP_Post` are auto-serialized via `->to_array()`. `WP_Query` returns `{found_posts, post_count, posts}`.\n' +
			'- Max execution time: 10 seconds (configurable up to 60). Max output size: 64 KB.\n' +
			'\n' +
			'**Response format:**\n' +
			'```json\n' +
			'{\n' +
			'  "success": true,\n' +
			'  "return": "<value from your return statement, any JSON type>",\n' +
			'  "output": "<captured echo/print output as string>",\n' +
			'  "time_ms": 12,\n' +
			'  "error": null,\n' +
			'  "blocked": null\n' +
			'}\n' +
			'```\n' +
			'\n' +
			'- `success` (bool): whether code executed without errors\n' +
			'- `return` (mixed): the value from your `return` statement, serialized to JSON\n' +
			'- `output` (string): captured echo/print output\n' +
			'- `time_ms` (int): execution time in milliseconds\n' +
			'- `error` (string|null): error message if execution failed (HTTP 400)\n' +
			'- `blocked` (array|null): list of blocked function names if code was rejected (HTTP 403)\n' +
			'\n' +
			'**Execute examples:**\n' +
			'\n' +
			'```bash\n' +
			'# Get site name\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return get_option(\\"blogname\\");"}\' ' + base + 'execute\n' +
			'\n' +
			'# Get all published posts (last 10)\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return get_posts([\\"post_status\\" => \\"publish\\", \\"numberposts\\" => 10]);"}\' \\\n' +
			'  ' + base + 'execute | python3 -m json.tool\n' +
			'\n' +
			'# Get active theme info\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "$t = wp_get_theme(); return [\\"name\\" => $t->get(\\"Name\\"), \\"version\\" => $t->get(\\"Version\\"), \\"template\\" => $t->get_template()];"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'\n' +
			'# List all active plugins\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return array_map(function($p) { $d = get_plugin_data(WP_PLUGIN_DIR . \\"/\\" . $p); return [\\"name\\" => $d[\\"Name\\"], \\"version\\" => $d[\\"Version\\"]]; }, get_option(\\"active_plugins\\", []));"}\' \\\n' +
			'  ' + base + 'execute | python3 -m json.tool\n' +
			'\n' +
			'# Run WP_Query\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return new WP_Query([\\"post_type\\" => \\"page\\", \\"posts_per_page\\" => 5]);"}\' \\\n' +
			'  ' + base + 'execute | python3 -m json.tool\n' +
			'\n' +
			'# Use $wpdb directly (safe read via get_results)\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "global $wpdb; return $wpdb->get_results(\\"SELECT ID, post_title, post_status FROM {$wpdb->posts} WHERE post_type = \'post\' ORDER BY ID DESC LIMIT 10\\");"}\' \\\n' +
			'  ' + base + 'execute | python3 -m json.tool\n' +
			'\n' +
			'# Get all registered menus and their items\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "$menus = get_nav_menu_locations(); $result = []; foreach ($menus as $loc => $id) { $items = wp_get_nav_menu_items($id); $result[$loc] = array_map(function($i){ return [\\"title\\" => $i->title, \\"url\\" => $i->url]; }, $items ?: []); } return $result;"}\' \\\n' +
			'  ' + base + 'execute | python3 -m json.tool\n' +
			'\n' +
			'# Get WordPress options\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return [\\"siteurl\\" => get_option(\\"siteurl\\"), \\"blogname\\" => get_option(\\"blogname\\"), \\"blogdescription\\" => get_option(\\"blogdescription\\"), \\"admin_email\\" => get_option(\\"admin_email\\"), \\"permalink_structure\\" => get_option(\\"permalink_structure\\")];"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'```\n' +
			'\n' +
			'### 4. Database Query — `POST /query`\n' +
			'\n' +
			'Run SQL queries directly against the WordPress database.\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -X POST \\\n' +
			'  -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"sql": "SELECT COUNT(*) as total FROM wp_posts WHERE post_status = \'publish\'"}\' \\\n' +
			'  ' + base + 'query\n' +
			'```\n' +
			'\n' +
			'Response:\n' +
			'```json\n' +
			'{ "success": true, "data": [{"total": "42"}], "rows": 1, "time_ms": 3, "error": null }\n' +
			'```\n' +
			'\n' +
			'**Note:** The table prefix may not be `wp_`. Use `site-info` to check `database.prefix`, or use the `/execute` endpoint with `$wpdb->prefix` or `$wpdb->posts` etc. for portable queries.\n' +
			'\n' +
			'**SQL examples:**\n' +
			'```bash\n' +
			'# List all tables\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"sql": "SHOW TABLES"}\' ' + base + 'query | python3 -m json.tool\n' +
			'\n' +
			'# Show table structure\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"sql": "DESCRIBE wp_posts"}\' ' + base + 'query | python3 -m json.tool\n' +
			'\n' +
			'# Count posts by type\n' +
			'curl -sk -X POST -H "Authorization: Bearer ' + key + '" -H "Content-Type: application/json" \\\n' +
			'  -d \'{"sql": "SELECT post_type, post_status, COUNT(*) as count FROM wp_posts GROUP BY post_type, post_status ORDER BY count DESC"}\' \\\n' +
			'  ' + base + 'query | python3 -m json.tool\n' +
			'```\n' +
			'\n' +
			'### 5. Debug Log — `GET /debug-log`\n' +
			'\n' +
			'Read the last N lines from `wp-content/debug.log`.\n' +
			'\n' +
			'```bash\n' +
			'curl -sk -H "Authorization: Bearer ' + key + '" "' + base + 'debug-log?lines=50"\n' +
			'```\n' +
			'\n' +
			'Response:\n' +
			'```json\n' +
			'{ "success": true, "log": "...log content...", "lines": 50 }\n' +
			'```\n' +
			'\n' +
			'If `WP_DEBUG_LOG` is not enabled, you will get an empty log with a helpful message.\n' +
			'\n' +
			'## Security & Blocked Functions\n' +
			'\n' +
			'The following functions are ALWAYS blocked regardless of execution mode (HTTP 403 if detected):\n' +
			'\n' +
			'**Shell execution:** `exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `pcntl_exec`\n' +
			'**Process control:** `pcntl_fork`, `pcntl_signal`\n' +
			'**File system (destructive):** `unlink`, `rmdir`, `rename`, `chmod`, `chown`, `chgrp`, `symlink`, `link`\n' +
			'**Network:** `fsockopen`, `pfsockopen`, `socket_create`\n' +
			'**Code inclusion:** `include`, `include_once`, `require`, `require_once`\n' +
			'**Dangerous eval:** `assert`, `create_function`, `call_user_func`, `call_user_func_array`\n' +
			'**Environment:** `putenv`, `ini_set`, `ini_alter`, `dl`, `set_time_limit`\n' +
			'**WordPress destructive:** `wp_delete_post`, `wp_delete_user`, `wpdb::query`\n' +
			'\n' +
			'If your code uses any of these, the response will have `"blocked": ["function_name"]` and HTTP status 403.\n' +
			'Do NOT retry with the same function — find an alternative approach or ask the user.\n' +
			'\n' +
			'**Note on $wpdb:** Direct `$wpdb->query()` is blocked, but `$wpdb->get_results()`, `$wpdb->get_var()`, `$wpdb->get_row()`, and `$wpdb->get_col()` are allowed. ' +
			'You can also use the `/query` endpoint for SQL.\n' +
			'\n' +
			'## Error Handling\n' +
			'\n' +
			'- **HTTP 200** — Success. Check `response.success` and read `return` / `output` / `data`.\n' +
			'- **HTTP 400** — PHP error or SQL error. Check `response.error` for the message.\n' +
			'- **HTTP 401** — Authentication failed. Verify your API key, check IP whitelist, or check rate limits.\n' +
			'- **HTTP 403** — Code contains blocked functions. Check `response.blocked` for which ones.\n' +
			'\n' +
			'## Recommended Workflow\n' +
			'\n' +
			'1. **Test connection:** Run the Quick Start command above to verify auth + see the site environment.\n' +
			'2. **Explore:** Use `site-info` to understand the WordPress setup (plugins, theme, post types, DB prefix).\n' +
			'3. **Read data:** Use `execute` with PHP code for WordPress-specific queries, or `query` for raw SQL.\n' +
			'4. **Modify data:** (If mode allows) Use `execute` with WordPress functions like `wp_insert_post`, `update_option`, etc.\n' +
			'5. **Debug:** Use `debug-log` if something goes wrong, or check `error` in responses.\n' +
			'6. **Always** pipe large JSON responses through `| python3 -m json.tool` for readability.\n';
		copyToClipboard(text, cwpb.strings.ai_copied);
	});

	// Clear Audit Logs.
	$(document).on('click', '#cwpb-clear-logs', function (e) {
		e.preventDefault();

		if (!confirm(cwpb.strings.confirm_clear)) {
			return;
		}

		var $button = $(this);
		$button.prop('disabled', true);

		$.post(cwpb.ajax_url, {
			action: 'cwpb_clear_logs',
			nonce: cwpb.nonce
		}, function (response) {
			if (response.success) {
				location.reload();
			} else {
				alert('Error: ' + (response.data || 'Unknown error'));
				$button.prop('disabled', false);
			}
		});
	});

})(jQuery);
