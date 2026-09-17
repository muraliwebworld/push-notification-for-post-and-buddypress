<?php
/**
 * Token Cleanup AJAX Handlers
 *
 * Handles AJAX requests for token cleanup operations
 *
 * @since 3.22
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load required classes
require_once dirname( dirname( __FILE__ ) ) . '/public/pnfpb_send_notification_routines/pnfpb_token_validation/pnfpb_token_validation_service.php';
require_once dirname( dirname( __FILE__ ) ) . '/public/pnfpb_send_notification_routines/pnfpb_token_cleanup/pnfpb_token_cleanup_background_job.php';

/**
 * Handle manual token cleanup request
 */
function pnfpb_handle_manual_cleanup() {
	check_ajax_referer( 'pnfpb_cleanup_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Unauthorized', 'push-notification-for-post-and-buddypress' ) ),
			403
		);
	}

	$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 100;

	$result = PNFPB_Token_Cleanup_Background_Job::execute_cleanup_batch( $batch_size );

	wp_send_json_success( $result );
}

/**
 * Handle get cleanup status request
 */
function pnfpb_handle_get_cleanup_status() {
	check_ajax_referer( 'pnfpb_cleanup_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Unauthorized', 'push-notification-for-post-and-buddypress' ) ),
			403
		);
	}

	$status = PNFPB_Token_Cleanup_Background_Job::get_cleanup_status();
	$stats = PNFPB_Token_Validation_Service::get_validation_statistics();

	$response = array_merge( $status, array( 'stats' => $stats ) );

	wp_send_json_success( $response );
}

/**
 * Handle get cleanup logs request
 */
function pnfpb_handle_get_cleanup_logs() {
	check_ajax_referer( 'pnfpb_cleanup_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Unauthorized', 'push-notification-for-post-and-buddypress' ) ),
			403
		);
	}

	$limit = isset( $_POST['limit'] ) ? absint( $_POST['limit'] ) : 50;
	$logs = PNFPB_Token_Cleanup_Background_Job::get_recent_logs( $limit );

	wp_send_json_success( array( 'logs' => $logs ) );
}

/**
 * Handle reset token cleanup system request
 */
function pnfpb_handle_reset_token_cleanup() {
	check_ajax_referer( 'pnfpb_cleanup_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Unauthorized', 'push-notification-for-post-and-buddypress' ) ),
			403
		);
	}

	// Clear all validation history and reset tokens
	PNFPB_Token_Validation_Service::clear_validation_history();

	// Clear logs older than 0 days (clears all)
	PNFPB_Token_Cleanup_Background_Job::clear_old_logs( 0 );

	// Reset status
	PNFPB_Token_Cleanup_Background_Job::update_cleanup_status( array() );

	wp_send_json_success( array( 'message' => 'Cleanup system reset successfully' ) );
}

/**
 * Handle update cleanup settings request
 */
function pnfpb_handle_update_cleanup_settings() {
	check_ajax_referer( 'pnfpb_cleanup_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error(
			array( 'message' => __( 'Unauthorized', 'push-notification-for-post-and-buddypress' ) ),
			403
		);
	}

	$schedule = isset( $_POST['schedule'] ) ? sanitize_text_field( $_POST['schedule'] ) : 'daily';
	$batch_size = isset( $_POST['batch_size'] ) ? absint( $_POST['batch_size'] ) : 100;

	// Validate schedule
	$valid_schedules = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
	if ( ! in_array( $schedule, $valid_schedules, true ) ) {
		$schedule = 'daily';
	}

	// Validate batch size
	$batch_size = min( $batch_size, 500 );
	$batch_size = max( $batch_size, 1 );

	// Unschedule old job
	PNFPB_Token_Cleanup_Background_Job::unschedule_cleanup_job();

	// Schedule new job with updated settings
	$scheduled = PNFPB_Token_Cleanup_Background_Job::schedule_cleanup_job( $schedule, $batch_size );

	if ( $scheduled ) {
		wp_send_json_success(
			array(
				'message'   => 'Cleanup settings updated successfully',
				'schedule'  => $schedule,
				'batch_size' => $batch_size,
			)
		);
	} else {
		wp_send_json_error( array( 'message' => 'Failed to update cleanup settings' ) );
	}
}

/**
 * Register AJAX handlers if this file is being included from admin area
 */
if ( is_admin() ) {
	add_action( 'wp_ajax_pnfpb_manual_token_cleanup', 'pnfpb_handle_manual_cleanup' );
	add_action( 'wp_ajax_pnfpb_get_cleanup_status', 'pnfpb_handle_get_cleanup_status' );
	add_action( 'wp_ajax_pnfpb_get_cleanup_logs', 'pnfpb_handle_get_cleanup_logs' );
	add_action( 'wp_ajax_pnfpb_reset_token_cleanup', 'pnfpb_handle_reset_token_cleanup' );
	add_action( 'wp_ajax_pnfpb_update_cleanup_settings', 'pnfpb_handle_update_cleanup_settings' );
}
?>
