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

	// Copy to AI — full instruction block for any Claude session.
	$(document).on('click', '#cwpb-copy-ai', function (e) {
		e.preventDefault();
		var key = $('#cwpb-new-key').text();
		if (!key) {
			return;
		}
		var base = cwpb.endpoint_url;
		var mode = cwpb.execution_mode || 'read_only';

		// Build mode-specific guidance.
		var modeLabel = {
			'read_only':  'Read-Only',
			'read_write': 'Read-Write',
			'full':       'Full'
		}[mode] || mode;

		var modeNote = '';
		if (mode === 'read_only') {
			modeNote =
				'The bridge is in **read-only** mode. You can read any data but cannot:\n' +
				'- Call write functions (`wp_insert_post`, `wp_update_post`, `update_option`, `wp_mail`, etc.)\n' +
				'- Run SQL write statements (`INSERT`, `UPDATE`, `DELETE`, `DROP`, `ALTER`, `CREATE`, `TRUNCATE`)\n' +
				'If you need to make changes, ask the user to switch the execution mode in the bridge settings.\n';
		} else if (mode === 'read_write') {
			modeNote =
				'The bridge is in **read-write** mode. You can read and write WordPress data freely.\n' +
				'Functions like `wp_insert_post`, `wp_update_post`, `update_option`, and SQL writes are allowed.\n';
		} else {
			modeNote =
				'The bridge is in **full** mode. All WordPress/PHP functions are available with no restrictions beyond the always-blocked dangerous functions.\n';
		}

		var text =
			'# Claude WP Bridge — ' + cwpb.site_name + '\n' +
			'\n' +
			'You have access to a live WordPress site via the Claude WP Bridge plugin.\n' +
			'Use the endpoints below with `curl` to inspect and interact with the site.\n' +
			'\n' +
			'## Connection\n' +
			'- **Base URL:** `' + base + '`\n' +
			'- **API Key:** `' + key + '`\n' +
			'- **Auth header:** `Authorization: Bearer ' + key + '`\n' +
			'- **Execution mode:** ' + modeLabel + '\n' +
			'\n' +
			modeNote +
			'\n' +
			'## Available Endpoints\n' +
			'\n' +
			'### 1. Ping (test connection)\n' +
			'```bash\n' +
			'curl -s -H "Authorization: Bearer ' + key + '" ' + base + 'ping\n' +
			'```\n' +
			'Response: `{ success, message, version, mode, timestamp }`\n' +
			'\n' +
			'### 2. Execute PHP (run WordPress PHP code)\n' +
			'```bash\n' +
			'curl -s -X POST \\\n' +
			'  -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return get_option(\\"blogname\\");"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'```\n' +
			'\n' +
			'**How PHP execution works:**\n' +
			'- Code runs inside a closure in the full WordPress context (all plugins, theme, and globals are available).\n' +
			'- Do NOT include `<?php` tags — send raw PHP code only.\n' +
			'- Use `return $value;` to send a value back in the `return` field of the response.\n' +
			'- Use `echo` / `print` to send text back in the `output` field.\n' +
			'- You can use both `return` and `echo` in the same request.\n' +
			'- WordPress globals like `$wpdb`, `$wp_query`, `$post` are accessible.\n' +
			'- Objects like `WP_Post` and `WP_Query` are automatically serialized to arrays in the response.\n' +
			'- Max execution time: 10 seconds (configurable). Max output size: 64 KB.\n' +
			'\n' +
			'**Response format:**\n' +
			'```json\n' +
			'{ "success": true, "return": mixed, "output": "string", "time_ms": 12, "error": null, "blocked": null }\n' +
			'```\n' +
			'- `success`: whether the code executed without errors.\n' +
			'- `return`: the value from your `return` statement (any type, serialized to JSON).\n' +
			'- `output`: captured `echo`/`print` output as a string.\n' +
			'- `time_ms`: execution time in milliseconds.\n' +
			'- `error`: error message if execution failed.\n' +
			'- `blocked`: list of blocked function names if code was rejected (HTTP 403).\n' +
			'\n' +
			'**Always-blocked functions** (for safety, regardless of mode):\n' +
			'`exec`, `shell_exec`, `system`, `passthru`, `popen`, `proc_open`, `pcntl_exec`, `pcntl_fork`,\n' +
			'`unlink`, `rmdir`, `rename`, `chmod`, `chown`, `symlink`,\n' +
			'`fsockopen`, `socket_create`, `include`, `require`, `assert`, `create_function`,\n' +
			'`call_user_func`, `call_user_func_array`, `putenv`, `ini_set`, `dl`, `set_time_limit`,\n' +
			'`wp_delete_post`, `wp_delete_user`, `wpdb::query`.\n' +
			'\n' +
			'**PHP execution examples:**\n' +
			'```bash\n' +
			'# Get all published posts\n' +
			'curl -s -X POST -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return get_posts([\\"post_status\\" => \\"publish\\", \\"numberposts\\" => 10]);"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'\n' +
			'# Get active theme info\n' +
			'curl -s -X POST -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "$t = wp_get_theme(); return [\\"name\\" => $t->get(\\"Name\\"), \\"version\\" => $t->get(\\"Version\\")];"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'\n' +
			'# Run WP_Query\n' +
			'curl -s -X POST -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "return new WP_Query([\\"post_type\\" => \\"page\\", \\"posts_per_page\\" => 5]);"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'\n' +
			'# Use $wpdb directly\n' +
			'curl -s -X POST -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"code": "global $wpdb; return $wpdb->get_results(\\"SELECT ID, post_title FROM {$wpdb->posts} WHERE post_status = \'publish\' LIMIT 5\\");"}\' \\\n' +
			'  ' + base + 'execute\n' +
			'```\n' +
			'\n' +
			'### 3. Database Query (run SQL directly)\n' +
			'```bash\n' +
			'curl -s -X POST \\\n' +
			'  -H "Authorization: Bearer ' + key + '" \\\n' +
			'  -H "Content-Type: application/json" \\\n' +
			'  -d \'{"sql": "SELECT COUNT(*) as total FROM wp_posts WHERE post_status = \'publish\'"}\' \\\n' +
			'  ' + base + 'query\n' +
			'```\n' +
			'Response: `{ success, data: [rows], rows: count, time_ms, error }`\n' +
			'\n' +
			'### 4. Site Info (WordPress environment overview)\n' +
			'```bash\n' +
			'curl -s -H "Authorization: Bearer ' + key + '" ' + base + 'site-info\n' +
			'```\n' +
			'Returns: WP version, PHP version, active plugins (with versions), theme, custom post types (with counts), DB info, and current bridge execution mode.\n' +
			'\n' +
			'### 5. Debug Log (read wp-content/debug.log)\n' +
			'```bash\n' +
			'curl -s -H "Authorization: Bearer ' + key + '" "' + base + 'debug-log?lines=50"\n' +
			'```\n' +
			'Response: `{ success, log: "string", lines: 50 }`\n' +
			'\n' +
			'## Workflow\n' +
			'1. Start with `ping` to verify the connection and check the current execution mode.\n' +
			'2. Use `site-info` to understand the WordPress installation (plugins, theme, post types).\n' +
			'3. Use `execute` for any WordPress/PHP operation — it is the most powerful endpoint.\n' +
			'4. Use `query` for direct SQL when you need raw database access.\n' +
			'5. Use `debug-log` to troubleshoot errors.\n' +
			'6. All responses are JSON. Check the `success` field and inspect `error`/`blocked` on failure.\n' +
			'7. If a function is blocked, the response will include `"blocked": ["function_name"]` — do not retry with the same function.\n';
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
