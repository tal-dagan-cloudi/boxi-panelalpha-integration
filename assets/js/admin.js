/**
 * Admin JavaScript
 *
 * Handles AJAX interactions for Boxi PanelAlpha Integration admin pages.
 */

(function ($) {
	'use strict';

	// ==================== Tab Switching ====================

	$('.boxi-settings .nav-tab').on('click', function (e) {
		e.preventDefault();

		var targetTab = $(this).attr('href');

		// Update active tab
		$('.boxi-settings .nav-tab').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

		// Show target content
		$('.boxi-settings .tab-content').removeClass('active');
		$(targetTab).addClass('active');
	});

	// ==================== API Credentials ====================

	// Test Connection
	$('#test-connection').on('click', function (e) {
		e.preventDefault();

		var $button = $(this);
		var $status = $('#connection-status');

		var apiUrl = $('#api_url').val();
		var apiToken = $('#api_token').val();

		if (!apiUrl || !apiToken) {
			showStatus($status, 'error', 'Please enter both API URL and Token');
			return;
		}

		// Show loading
		$button.prop('disabled', true).html('Testing... <span class="boxi-loading"></span>');
		$status.hide();

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_test_connection',
				nonce: boxiAdmin.nonce,
				api_url: apiUrl,
				api_token: apiToken,
			},
			success: function (response) {
				if (response.success) {
					showStatus($status, 'success', response.data.message);
				} else {
					showStatus($status, 'error', response.data.message);
				}
			},
			error: function () {
				showStatus($status, 'error', 'Connection test failed. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).text('Test Connection');
			},
		});
	});

	// Save Credentials
	$('#boxi-credentials-form').on('submit', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');
		var $status = $('#connection-status');

		var apiUrl = $('#api_url').val();
		var apiToken = $('#api_token').val();

		if (!apiUrl) {
			showStatus($status, 'error', 'Please enter API URL');
			return;
		}

		// Show loading
		$button.prop('disabled', true).html('Saving... <span class="boxi-loading"></span>');
		$status.hide();

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_save_credentials',
				nonce: boxiAdmin.nonce,
				api_url: apiUrl,
				api_token: apiToken,
			},
			success: function (response) {
				if (response.success) {
					showStatus($status, 'success', response.data.message);
					// Clear password field
					$('#api_token').val('').attr('placeholder', '(token is set)');
				} else {
					showStatus($status, 'error', response.data.message);
				}
			},
			error: function () {
				showStatus($status, 'error', 'Failed to save credentials. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).text('Save Credentials');
			},
		});
	});

	// ==================== Product Mappings ====================

	// Add Product Mapping
	$('#boxi-add-mapping-form').on('submit', function (e) {
		e.preventDefault();

		var $form = $(this);
		var $button = $form.find('button[type="submit"]');

		var productId = $('#product_id').val();
		var planId = $('#plan_id').val();
		var autoProvision = $('#auto_provision').is(':checked');

		if (!productId || !planId) {
			alert('Please fill in all required fields');
			return;
		}

		// Show loading
		$button.prop('disabled', true).html('Adding... <span class="boxi-loading"></span>');

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_save_mapping',
				nonce: boxiAdmin.nonce,
				product_id: productId,
				plan_id: planId,
				auto_provision: autoProvision,
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message);
				}
			},
			error: function () {
				alert('Failed to add product mapping. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).text('Add Mapping');
			},
		});
	});

	// Delete Product Mapping
	$('.delete-mapping').on('click', function (e) {
		e.preventDefault();

		var $button = $(this);
		var productId = $button.data('product-id');

		if (!confirm('Are you sure you want to delete this product mapping?')) {
			return;
		}

		// Show loading
		$button.prop('disabled', true).html('Deleting... <span class="boxi-loading"></span>');

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_delete_mapping',
				nonce: boxiAdmin.nonce,
				product_id: productId,
			},
			success: function (response) {
				if (response.success) {
					alert(response.data.message);
					location.reload();
				} else {
					alert(response.data.message);
				}
			},
			error: function () {
				alert('Failed to delete product mapping. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).text('Delete');
			},
		});
	});

	// ==================== Event Logs ====================

	// View Context Modal
	$('.view-context').on('click', function (e) {
		e.preventDefault();

		var context = $(this).data('context');
		var formattedContext = JSON.stringify(context, null, 2);

		$('#context-data').text(formattedContext);
		$('#boxi-context-modal').fadeIn(200);
	});

	// Close Context Modal
	$('.boxi-modal-close, .boxi-modal').on('click', function (e) {
		if (e.target === this) {
			$('#boxi-context-modal').fadeOut(200);
		}
	});

	// Prevent modal content clicks from closing modal
	$('.boxi-modal-content').on('click', function (e) {
		e.stopPropagation();
	});

	// ==================== Order Metabox ====================

	// Retry Provisioning
	$('.boxi-retry-provision').on('click', function (e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data('order-id');

		if (!confirm('Are you sure you want to retry provisioning for this order?')) {
			return;
		}

		// Show loading
		$button.prop('disabled', true).html('<span class="dashicons dashicons-update"></span> Retrying... <span class="boxi-loading"></span>');

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_retry_provision',
				nonce: boxiAdmin.nonce,
				order_id: orderId,
			},
			success: function (response) {
				if (response.success) {
					showActionStatus('success', response.data.message);
					setTimeout(function () {
						location.reload();
					}, 2000);
				} else {
					showActionStatus('error', response.data.message);
				}
			},
			error: function () {
				showActionStatus('error', 'Failed to retry provisioning. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).html('<span class="dashicons dashicons-update"></span> Retry Provisioning');
			},
		});
	});

	// Reveal Credentials
	$('.boxi-reveal-credentials').on('click', function (e) {
		e.preventDefault();

		var $button = $(this);
		var orderId = $button.data('order-id');

		// Show loading
		$button.prop('disabled', true).html('<span class="dashicons dashicons-visibility"></span> Loading... <span class="boxi-loading"></span>');

		// AJAX request
		$.ajax({
			url: boxiAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'boxi_reveal_credentials',
				nonce: boxiAdmin.nonce,
				order_id: orderId,
			},
			success: function (response) {
				if (response.success) {
					var credentials = response.data.credentials;

					// Populate credentials display
					$('.credential-username').text(credentials.username || 'N/A');
					$('.credential-password').text(credentials.password || 'N/A');
					$('.credential-email').text(credentials.email || 'N/A');

					// Show credentials display
					$('#boxi-credentials-display').slideDown(300);

					showActionStatus('success', 'Credentials revealed successfully');
				} else {
					showActionStatus('error', response.data.message);
				}
			},
			error: function () {
				showActionStatus('error', 'Failed to reveal credentials. Please try again.');
			},
			complete: function () {
				$button.prop('disabled', false).html('<span class="dashicons dashicons-visibility"></span> Reveal Credentials');
			},
		});
	});

	// Close Credentials Display
	$('.boxi-credentials-close').on('click', function (e) {
		e.preventDefault();
		$('#boxi-credentials-display').slideUp(300);
	});

	// ==================== Helper Functions ====================

	/**
	 * Show status message
	 *
	 * @param {jQuery} $element Status element
	 * @param {string} type     Status type (success/error)
	 * @param {string} message  Status message
	 */
	function showStatus($element, type, message) {
		$element
			.removeClass('notice-success notice-error')
			.addClass('notice notice-' + type)
			.html('<p>' + message + '</p>')
			.fadeIn(200);
	}

	/**
	 * Show action status message in metabox
	 *
	 * @param {string} type    Status type (success/error)
	 * @param {string} message Status message
	 */
	function showActionStatus(type, message) {
		var $status = $('#boxi-action-status');

		$status
			.removeClass('notice-success notice-error')
			.addClass('notice notice-' + type)
			.html('<p>' + message + '</p>')
			.fadeIn(200);

		// Auto-hide after 5 seconds
		setTimeout(function () {
			$status.fadeOut(200);
		}, 5000);
	}
})(jQuery);
