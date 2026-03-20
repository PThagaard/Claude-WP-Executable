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
	 * Display the newly generated API key to the user.
	 *
	 * @param {string} key    The full API key.
	 * @param {string} prefix The key prefix for display.
	 */
	function showNewKey(key, prefix) {
		$('#cwpb-new-key').text(key);
		$('#cwpb-new-key-display').slideDown();
		$('#cwpb-key-prefix').text(prefix);
	}

	/**
	 * Copy text to the clipboard.
	 *
	 * @param {string} text The text to copy.
	 */
	function copyToClipboard(text) {
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(text).then(function () {
				alert(cwpb.strings.key_copied);
			});
		} else {
			// Fallback for older browsers.
			var $temp = $('<textarea>');
			$('body').append($temp);
			$temp.val(text).select();
			document.execCommand('copy');
			$temp.remove();
			alert(cwpb.strings.key_copied);
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
				showNewKey(response.data.key, response.data.prefix);
				alert(cwpb.strings.copy_warning);
			} else {
				alert('Error: ' + (response.data || 'Unknown error'));
			}
			$button.prop('disabled', false).text(isRegenerate ? 'Regenerate Key' : 'Generate API Key');
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
