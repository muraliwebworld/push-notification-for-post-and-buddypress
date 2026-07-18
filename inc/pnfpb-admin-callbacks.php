<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'PNFPB_icpushcallback_callback' ) ) {
	function PNFPB_icpushcallback_callback() {
		global $wpdb;
		include_once PNFPB_PLUGIN_DIR . 'public/ajax_routines/pnfpb_update_deviceid_ajax.php';
		wp_die();
	}
}

if ( ! function_exists( 'PNFPB_icpushadmincallback_callback' ) ) {
	function PNFPB_icpushadmincallback_callback() {
		global $wpdb;
		include_once PNFPB_PLUGIN_DIR . 'admin/ajax_routines/pnfpb_admin_notice_ajax.php';
		wp_die();
	}
}

if ( ! function_exists( 'PNFPB_unsubscribe_push_callback' ) ) {
	function PNFPB_unsubscribe_push_callback() {
		global $wpdb;
		include_once PNFPB_PLUGIN_DIR . 'public/ajax_routines/pnfpb_update_unsubscribe_deviceids.php';
		wp_die();
	}
}

if ( ! function_exists( 'PNFPB_rest_api_subscription_tokens_from_app' ) ) {
	function PNFPB_rest_api_subscription_tokens_from_app() {
		register_rest_route( 'PNFPBpush/v1', '/subscriptiontoken', [
			'methods' => 'POST',
			'callback' => 'PNFPB_get_subscription_tokens_from_app',
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'PNFPBpush/v2', '/notification-delivery-counts', [
			'methods' => 'POST',
			'callback' => 'PNFPB_get_notification_delivery_counts_from_serviceworker',
			'permission_callback' => '__return_true',
		] );
	}
}

if ( ! function_exists( 'PNFPB_get_subscription_tokens_from_app' ) ) {
	function PNFPB_get_subscription_tokens_from_app( WP_REST_Request $pnfpb_request ) {
		return include_once PNFPB_PLUGIN_DIR . 'public/pnfpb_mobile_app_notification_api_routine/pnfpb_mobile_app_notification_api_routine.php';
	}
}

if ( ! function_exists( 'PNFPB_get_notification_delivery_counts_permission_callback' ) ) {
	function PNFPB_get_notification_delivery_counts_permission_callback( WP_REST_Request $request ) {
		include_once PNFPB_PLUGIN_DIR . 'public/pnfpb_delivery_counts_api_routine/pnfpb_delivery_count_api_permission_callback.php';
	}
}

if ( ! function_exists( 'PNFPB_get_notification_delivery_counts_from_serviceworker' ) ) {
	function PNFPB_get_notification_delivery_counts_from_serviceworker( WP_REST_Request $pnfpb_request ) {
		return include_once PNFPB_PLUGIN_DIR . 'public/pnfpb_delivery_counts_api_routine/pnfpb_delivery_count_api_routine.php';
	}
}