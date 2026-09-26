<?php
/**
 * Plugin settings page — Token Cleanup Configuration
 *
 * @since 3.22
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;
?>
<h1 class="pnfpb_ic_push_settings_header"><?php esc_html_e( 'PNFPB - Token Cleanup Settings', 'push-notification-for-post-and-buddypress' ); ?></h1>
<?php
$pnfpb_tab_token_cleanup_active = 'nav-tab-active';
require_once plugin_dir_path( __FILE__ ) . 'push_admin_menu_list.php';

// Security check
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( esc_html__( 'Unauthorized access', 'push-notification-for-post-and-buddypress' ) );
}

// Get cleanup status and statistics
$status = array();
$stats = array();

if ( class_exists( 'PNFPB_Token_Cleanup_Background_Job' ) ) {
	$status = PNFPB_Token_Cleanup_Background_Job::get_cleanup_status();
}

if ( class_exists( 'PNFPB_Token_Validation_Service' ) ) {
	$stats = PNFPB_Token_Validation_Service::counts();
}

$schedule = get_option( 'pnfpb_token_cleanup_frequency', get_option( 'pnfpb_cleanup_schedule', 'manual' ) );
$batch_size = get_option( 'pnfpb_token_cleanup_batch_limit', get_option( 'pnfpb_cleanup_batch_size', 100 ) );
$nonce = wp_create_nonce( 'pnfpb_cleanup_nonce' );
?>

<div class="pnfpb_column_1200">

	<!-- Status Cards -->
	<div class="pnfpb-status-cards-grid">
		
		<!-- Valid Tokens Card -->
		<div class="pnfpb-status-card pnfpb-status-card--valid">
			<div class="pnfpb-status-card__header">
				<div>
					<div class="pnfpb-status-card__label">
						<?php esc_html_e( 'Valid Tokens', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-status-card__number">
						<?php echo isset( $stats['valid'] ) ? absint( $stats['valid'] ) : 0; ?>
					</div>
				</div>
				<div class="pnfpb-status-card__icon">
					<span class="dashicons dashicons-yes-alt"></span>
				</div>
			</div>
		</div>

		<!-- Invalid Tokens Card -->
		<div class="pnfpb-status-card pnfpb-status-card--invalid">
			<div class="pnfpb-status-card__header">
				<div>
					<div class="pnfpb-status-card__label">
						<?php esc_html_e( 'Invalid Tokens', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-status-card__number">
						<?php echo isset( $stats['invalid'] ) ? absint( $stats['invalid'] ) : 0; ?>
					</div>
				</div>
				<div class="pnfpb-status-card__icon">
					<span class="dashicons dashicons-dismiss"></span>
				</div>
			</div>
		</div>

		<!-- Unverified Tokens Card -->
		<div class="pnfpb-status-card pnfpb-status-card--unverified">
			<div class="pnfpb-status-card__header">
				<div>
					<div class="pnfpb-status-card__label">
						<?php esc_html_e( 'Unverified Tokens', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-status-card__number">
						<?php echo isset( $status['last_run']['retryable_count'] ) ? absint( $status['last_run']['retryable_count'] ) : 0; ?>
					</div>
				</div>
				<div class="pnfpb-status-card__icon">
					<span class="dashicons dashicons-editor-help"></span>
				</div>
			</div>
		</div>

		<!-- Total Tokens Card -->
		<div class="pnfpb-status-card pnfpb-status-card--total">
			<div class="pnfpb-status-card__header">
				<div>
					<div class="pnfpb-status-card__label">
						<?php esc_html_e( 'Total Tokens', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-status-card__number">
						<?php echo isset( $stats['total'] ) ? absint( $stats['total'] ) : 0; ?>
					</div>
				</div>
				<div class="pnfpb-status-card__icon">
					<span class="dashicons dashicons-database"></span>
				</div>
			</div>
		</div>

		<!-- Trash Tokens Card -->
		<div class="pnfpb-status-card pnfpb-status-card--trash">
			<div class="pnfpb-status-card__label">
				<?php esc_html_e( 'Tokens in Trash', 'push-notification-for-post-and-buddypress' ); ?>
			</div>
			<div class="pnfpb-status-card__number">
				<?php echo isset( $stats['trash'] ) ? absint( $stats['trash'] ) : 0; ?>
			</div>
		</div>

	</div>

	<!-- Info Box -->
	<div class="pnfpb-info-box pnfpb-info-box--blue pnfpb-info-box--margin-bottom">
		<span class="dashicons dashicons-info pnfpb-info-box__icon"></span>
		<div>
			<strong><?php esc_html_e( 'About Token Cleanup', 'push-notification-for-post-and-buddypress' ); ?></strong>
			<p>
				<?php esc_html_e( 'Before executing token cleanup, take backup of database. The token cleanup system automatically verifies Firebase Cloud Messaging (FCM) tokens to identify and remove stale, invalid, or expired tokens from your database. This improves delivery rates and reduces unnecessary data storage.', 'push-notification-for-post-and-buddypress' ); ?>
			</p>
		</div>
	</div>

	<!-- Main Settings Form -->
	<form id="pnfpb-cleanup-settings-form" method="post" class="pnfpb-cleanup-form">

		<?php wp_nonce_field( 'pnfpb_cleanup_settings_nonce', 'pnfpb_cleanup_settings_nonce' ); ?>

		<!-- Schedule Configuration Section -->
		<div class="pnfpb-settings-section">
			<h3 class="pnfpb-settings-section__title">
				<span class="dashicons dashicons-clock pnfpb-settings-section__icon"></span>
				<?php esc_html_e( 'Cleanup Schedule', 'push-notification-for-post-and-buddypress' ); ?>
			</h3>

			<div class="pnfpb-settings-grid pnfpb-settings-grid--2col">
				<div class="pnfpb-field-card">
					<div class="pnfpb-field-card__label">
						<?php esc_html_e( 'Cleanup Frequency', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-field-card__control">
					<select id="pnfpb_cleanup_schedule" name="pnfpb_cleanup_schedule" class="pnfpb-select-full">
						<option value="hourly" <?php selected( $schedule, 'hourly' ); ?>><?php esc_html_e( 'Hourly', 'push-notification-for-post-and-buddypress' ); ?></option>
						<option value="twicedaily" <?php selected( $schedule, 'twicedaily' ); ?>><?php esc_html_e( 'Twice Daily', 'push-notification-for-post-and-buddypress' ); ?></option>
						<option value="daily" <?php selected( $schedule, 'daily' ); ?>><?php esc_html_e( 'Daily', 'push-notification-for-post-and-buddypress' ); ?></option>
						<option value="weekly" <?php selected( $schedule, 'weekly' ); ?>><?php esc_html_e( 'Weekly', 'push-notification-for-post-and-buddypress' ); ?></option>
					</select>
					<small class="pnfpb-help-text">
							<?php esc_html_e( 'How often the cleanup job should run.', 'push-notification-for-post-and-buddypress' ); ?>
						</small>
					</div>
				</div>

				<div class="pnfpb-field-card">
					<div class="pnfpb-field-card__label">
						<?php esc_html_e( 'Batch Size', 'push-notification-for-post-and-buddypress' ); ?>
					</div>
					<div class="pnfpb-field-card__control">
					<input type="number" id="pnfpb_cleanup_batch_size" name="pnfpb_cleanup_batch_size" value="<?php echo absint( $batch_size ); ?>" min="1" max="100" class="pnfpb-input-number-full" />
					<small class="pnfpb-help-text">
							<?php esc_html_e( 'Number of tokens to verify per batch (1-100). Set batch size 50 to 100 to reduce server load for shared hosting', 'push-notification-for-post-and-buddypress' ); ?>
						</small>
					</div>
				</div>
			</div>

			<div class="pnfpb-section-margin-top">
				<?php submit_button( __( 'Save Schedule Settings', 'push-notification-for-post-and-buddypress' ), 'primary', 'pnfpb_save_schedule_settings' ); ?>
				<div id="pnfpb-cleanup-settings-result" role="status" aria-live="polite" class="pnfpb-cleanup-result-margin"></div>
			</div>
		</div>

	</form>

	<div class="pnfpb-section-margin-top-lg">
		<div class="pnfpb-settings-section pnfpb-cleanup-form">
			<h3 class="pnfpb-settings-section__title"><span class="dashicons dashicons-trash pnfpb-settings-section__icon"></span><?php esc_html_e( 'Token Trash', 'push-notification-for-post-and-buddypress' ); ?></h3>
			<p><?php esc_html_e( 'Invalid tokens are moved here instead of being immediately destroyed. Use the token list for restore and permanent deletion actions.', 'push-notification-for-post-and-buddypress' ); ?></p>
			<a class="button button-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=pnfpb_icfm_device_tokens_list' ) ); ?>#pnfpb-token-trash"><?php esc_html_e( 'Review token trash', 'push-notification-for-post-and-buddypress' ); ?></a>
		</div>

		<!-- Quick Actions Section -->
		<div class="pnfpb-settings-section pnfpb-cleanup-form">
			<h3 class="pnfpb-settings-section__title">
				<span class="dashicons dashicons-controls-play pnfpb-settings-section__icon"></span>
				<?php esc_html_e( 'Quick Actions', 'push-notification-for-post-and-buddypress' ); ?>
			</h3>

			<div class="pnfpb-quick-actions-grid">

				<!-- Manual Cleanup Button -->
				<div class="pnfpb-quick-action-card">
					<h4>
						<?php esc_html_e( 'Manual Cleanup', 'push-notification-for-post-and-buddypress' ); ?>
					</h4>
					<p>
						<?php esc_html_e( 'Trigger an immediate cleanup batch to verify tokens against Firebase.', 'push-notification-for-post-and-buddypress' ); ?>
					</p>
					<button type="button" id="pnfpb-manual-cleanup-btn" class="button button-primary pnfpb-manual-cleanup-button pnfpb-action-button-full">
						<span class="dashicons dashicons-controls-play" aria-hidden="true"></span>
						<?php esc_html_e( 'Run Cleanup Now', 'push-notification-for-post-and-buddypress' ); ?>
					</button>
					<div id="pnfpb-cleanup-result" class="pnfpb-cleanup-result-margin"></div>
				</div>

				<!-- View Logs Button -->
				<div class="pnfpb-quick-action-card">
					<h4>
						<?php esc_html_e( 'Cleanup Logs', 'push-notification-for-post-and-buddypress' ); ?>
					</h4>
					<p>
						<?php esc_html_e( 'View recent cleanup activities and logs.', 'push-notification-for-post-and-buddypress' ); ?>
					</p>
					<button type="button" id="pnfpb-view-logs-btn" class="button button-secondary pnfpb-action-button-full">
						<span class="dashicons dashicons-text-page"></span>
						<?php esc_html_e( 'View Logs', 'push-notification-for-post-and-buddypress' ); ?>
					</button>
				</div>

				<!-- Reset System Button -->
				<div class="pnfpb-quick-action-card pnfpb-quick-action-card--warning">
					<h4>
						<?php esc_html_e( 'Reset Token cleanup count', 'push-notification-for-post-and-buddypress' ); ?>
					</h4>
					<p>
						<?php esc_html_e( 'Reset token count and clear token cleanup logs.', 'push-notification-for-post-and-buddypress' ); ?>
					</p>
					<button type="button" id="pnfpb-reset-system-btn" class="button pnfpb-reset-button pnfpb-action-button-full">
						<span class="dashicons dashicons-trash"></span>
						<?php esc_html_e( 'Reset cleanup count', 'push-notification-for-post-and-buddypress' ); ?>
					</button>
				</div>				
			</div>
		</div>

		<!-- Cleanup Logs Modal -->
		<div id="pnfpb-cleanup-logs-modal" class="pnfpb-cleanup-logs-modal">
			<div class="pnfpb-cleanup-logs-modal__content">
				<div class="pnfpb-cleanup-logs-modal__header">
					<h2 class="pnfpb-cleanup-logs-modal__title">
						<?php esc_html_e( 'Cleanup Logs', 'push-notification-for-post-and-buddypress' ); ?>
					</h2>
					<button type="button" id="pnfpb-logs-modal-close" class="button pnfpb-cleanup-logs-modal__close">×</button>
				</div>
				<div id="pnfpb-logs-content" class="pnfpb-cleanup-logs-content">
					<p class="pnfpb-logs-loading">
						<?php esc_html_e( 'Loading logs...', 'push-notification-for-post-and-buddypress' ); ?>
					</p>
				</div>
			</div>
		</div>

		<!-- Cleanup Logs Table Section (Initially hidden) -->
		<div id="pnfpb-logs-section" class="pnfpb-logs-section">
			<h3 class="pnfpb-settings-section__title pnfpb-logs-section__title">
				<span class="dashicons dashicons-text-page pnfpb-settings-section__icon"></span>
				<?php esc_html_e( 'Recent Cleanup Logs', 'push-notification-for-post-and-buddypress' ); ?>
			</h3>
			<div id="pnfpb-logs-table-container" class="pnfpb-logs-table-container">
				<table class="widefat striped">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Timestamp', 'push-notification-for-post-and-buddypress' ); ?></th>
							<th><?php esc_html_e( 'Event', 'push-notification-for-post-and-buddypress' ); ?></th>
							<th><?php esc_html_e( 'Details', 'push-notification-for-post-and-buddypress' ); ?></th>
						</tr>
					</thead>
					<tbody id="pnfpb-logs-tbody">
						<tr>
							<td colspan="3">
								<?php esc_html_e( 'No logs available', 'push-notification-for-post-and-buddypress' ); ?>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>

	</div>

</div>

<!-- Inline JavaScript -->
<script>
	(function($) {
		'use strict';

		const ajaxurl = '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>';
		const nonce = '<?php echo esc_attr( $nonce ); ?>';

		$(document).on('click', '.pnfpb-trash-action', function() {
			const button = $(this);
			const operation = button.data('operation');
			if (operation === 'delete' && !window.confirm('<?php echo esc_js( __( 'Permanently delete this token? This cannot be undone.', 'push-notification-for-post-and-buddypress' ) ); ?>')) return;
			button.prop('disabled', true);
			$.post(ajaxurl, { action: 'pnfpb_token_cleanup_trash_action', nonce: nonce, trash_id: button.data('id'), operation: operation })
				.done(function(response) { window.alert(response.data && response.data.message ? response.data.message : '<?php echo esc_js( __( 'Token action completed.', 'push-notification-for-post-and-buddypress' ) ); ?>'); window.location.reload(); })
				.fail(function(xhr) { window.alert(xhr.responseJSON && xhr.responseJSON.data ? xhr.responseJSON.data.message : '<?php echo esc_js( __( 'Token action failed.', 'push-notification-for-post-and-buddypress' ) ); ?>'); button.prop('disabled', false); });
		});

		// Manual cleanup button click handler
		$('#pnfpb-manual-cleanup-btn').on('click', function() {
			const $btn = $(this);
			const $result = $('#pnfpb-cleanup-result');
			const originalText = $btn.html();

			$btn.prop('disabled', true).html('<span class="pnfpb-loader"></span> <?php esc_html_e( 'Running...', 'push-notification-for-post-and-buddypress' ); ?>');
			$result.hide();

			$.post(ajaxurl, {
				action: 'pnfpb_manual_token_cleanup',
				nonce: nonce,
				batch_size: <?php echo absint( $batch_size ); ?>
			}, function(response) {
				if (response.success) {
					const data = response.data;
					let html = '<strong><?php esc_html_e( 'Cleanup result:', 'push-notification-for-post-and-buddypress' ); ?></strong> ' + escapeHtml(data.status || '') + '<br>';
					html += '<?php esc_html_e( 'Candidates:', 'push-notification-for-post-and-buddypress' ); ?> ' + (data.candidates || 0) + '<br>';
					html += '<?php esc_html_e( 'Validated:', 'push-notification-for-post-and-buddypress' ); ?> ' + (data.tokens_validated || 0) + '<br>';
					html += '<?php esc_html_e( 'Valid:', 'push-notification-for-post-and-buddypress' ); ?> ' + (data.tokens_valid || 0) + '<br>';
					html += '<?php esc_html_e( 'Invalid:', 'push-notification-for-post-and-buddypress' ); ?> ' + (data.tokens_invalid || 0);

					$result.html(html).addClass('success').removeClass('error').show();
				} else {
					$result.html('<strong><?php esc_html_e( 'Error:', 'push-notification-for-post-and-buddypress' ); ?></strong> ' + (response.data?.message || '<?php esc_html_e( 'Unknown error occurred', 'push-notification-for-post-and-buddypress' ); ?>')).addClass('error').removeClass('success').show();
				}
			}).fail(function(xhr) {
				const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: '<?php esc_html_e( 'Request failed', 'push-notification-for-post-and-buddypress' ); ?>';
				$result.html('<strong><?php esc_html_e( 'Error:', 'push-notification-for-post-and-buddypress' ); ?></strong> ' + escapeHtml(message)).addClass('error').removeClass('success').show();
			}).always(function() {
				$btn.prop('disabled', false).html(originalText);
			});
		});

		// View logs button click handler
		$('#pnfpb-view-logs-btn').on('click', function() {
			const $modal = $('#pnfpb-cleanup-logs-modal');
			const $content = $('#pnfpb-logs-content');

			$modal.addClass('is-open');
			$content.html('<p class="pnfpb-logs-loading-message"><?php esc_html_e( 'Loading logs...', 'push-notification-for-post-and-buddypress' ); ?></p>');

			$.post(ajaxurl, {
				action: 'pnfpb_get_cleanup_logs',
				nonce: nonce,
				limit: 50
			}, function(response) {
				if (response.success && response.data.logs && response.data.logs.length > 0) {
					let html = '<table class="widefat striped pnfpb-logs-table">';
					html += '<thead><tr><th><?php esc_html_e( 'Timestamp', 'push-notification-for-post-and-buddypress' ); ?></th><th><?php esc_html_e( 'Event', 'push-notification-for-post-and-buddypress' ); ?></th><th><?php esc_html_e( 'Details', 'push-notification-for-post-and-buddypress' ); ?></th></tr></thead>';
					html += '<tbody>';

					$.each(response.data.logs, function(i, log) {
						const timestamp = new Date(log.created_at).toLocaleString();
						html += '<tr>';
						html += '<td class="pnfpb-logs-table-cell">' + escapeHtml(timestamp) + '</td>';
						html += '<td>' + escapeHtml(log.event_type) + '</td>';
						html += '<td class="pnfpb-logs-table-cell">' + escapeHtml(JSON.stringify(log.event_data, null, 2)) + '</td>';
						html += '</tr>';
					});

					html += '</tbody></table>';
					$content.html(html);
				} else {
					$content.html('<p class="pnfpb-logs-no-logs-message"><?php esc_html_e( 'No logs available', 'push-notification-for-post-and-buddypress' ); ?></p>');
				}
			}).fail(function() {
				$content.html('<p class="pnfpb-logs-error-message"><?php esc_html_e( 'Failed to load logs', 'push-notification-for-post-and-buddypress' ); ?></p>');
			});
		});

		// Close logs modal
		$('#pnfpb-logs-modal-close').on('click', function() {
			$('#pnfpb-cleanup-logs-modal').removeClass('is-open');
		});

		$(document).on('click', function(e) {
			const $modal = $('#pnfpb-cleanup-logs-modal');
			if ($modal.hasClass('is-open') && e.target === $modal[0]) {
				$modal.removeClass('is-open');
			}
		});

		// Reset system button click handler
		$('#pnfpb-reset-system-btn').on('click', function() {
			if (confirm('<?php esc_attr_e( 'Are you sure you want to reset the token cleanup system? This will reset all token statuses and clear all logs.', 'push-notification-for-post-and-buddypress' ); ?>')) {
				const $btn = $(this);
				const originalText = $btn.html();

				$btn.prop('disabled', true).html('<span class="pnfpb-loader"></span> <?php esc_html_e( 'Resetting...', 'push-notification-for-post-and-buddypress' ); ?>');

				$.post(ajaxurl, {
					action: 'pnfpb_reset_token_cleanup',
					nonce: nonce
				}, function(response) {
					if (response.success) {
						alert('<?php esc_attr_e( 'System reset successfully! Please refresh the page.', 'push-notification-for-post-and-buddypress' ); ?>');
						location.reload();
					} else {
						alert('<?php esc_attr_e( 'Error resetting system:', 'push-notification-for-post-and-buddypress' ); ?> ' + (response.data?.message || '<?php esc_attr_e( 'Unknown error', 'push-notification-for-post-and-buddypress' ); ?>'));
					}
				}).fail(function() {
					alert('<?php esc_attr_e( 'Request failed', 'push-notification-for-post-and-buddypress' ); ?>');
				}).always(function() {
					$btn.prop('disabled', false).html(originalText);
				});
			}
		});

		// Save schedule settings form handler
		$('#pnfpb-cleanup-settings-form').on('submit', function(e) {
			e.preventDefault();

			const $form = $(this);
			const $submit = $form.find('input[type="submit"]');
			const $settingsResult = $('#pnfpb-cleanup-settings-result');
			const originalText = $submit.val();

			$submit.prop('disabled', true).val('<?php esc_attr_e( 'Saving...', 'push-notification-for-post-and-buddypress' ); ?>');
			$settingsResult.hide().removeClass('success error').empty();

			$.post(ajaxurl, {
				action: 'pnfpb_update_cleanup_settings',
				nonce: nonce,
				schedule: $('#pnfpb_cleanup_schedule').val(),
				batch_size: $('#pnfpb_cleanup_batch_size').val()
			}, function(response) {
				if (response.success) {
					$settingsResult.text(response.data && response.data.message ? response.data.message : '<?php esc_attr_e( 'Settings saved successfully!', 'push-notification-for-post-and-buddypress' ); ?>').addClass('success').show();
				} else {
					$settingsResult.text(response.data && response.data.message ? response.data.message : '<?php esc_attr_e( 'Unknown error', 'push-notification-for-post-and-buddypress' ); ?>').addClass('error').show();
				}
			}).fail(function(xhr) {
				const message = xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
					? xhr.responseJSON.data.message
					: '<?php esc_attr_e( 'Request failed', 'push-notification-for-post-and-buddypress' ); ?>';
				$settingsResult.text(message).addClass('error').show();
			}).always(function() {
				$submit.prop('disabled', false).val(originalText);
			});
		});

		// Helper function to escape HTML
		function escapeHtml(text) {
			if (!text) return '';
			const map = {
				'&': '&amp;',
				'<': '&lt;',
				'>': '&gt;',
				'"': '&quot;',
				"'": '&#039;'
			};
			return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
		}

	})(jQuery);
</script>
