<?php
/**
 * Security helper functions for Push Notification for Post and BuddyPress
 * 
 * Implements device ownership validation and other security checks
 * to prevent unauthorized push notification access.
 * 
 * @package Push_Notification_For_Post_And_BuddyPress
 * @since 3.24
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery

/**
 * Validates that a device token belongs to the specified user
 * by verifying it exists in the device table.
 * 
 * SECURITY: While we cannot definitively prove ownership after device registration,
 * we can verify the device exists for the user and was registered through valid
 * AJAX handler (which now requires authentication).
 * 
 * @param int    $user_id User ID to validate device for
 * @param string $device_id Device token to validate
 * @return bool True if device is registered to user, false otherwise
 * 
 * @since 3.24
 */
function pnfpb_validate_device_ownership( $user_id, $device_id ) {
	global $wpdb;
	
	if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
		return false;
	}
	
	if ( empty( $device_id ) ) {
		return false;
	}
	
	$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';
	
	// Verify device exists for this user in the database
	$device_exists = $wpdb->get_var( $wpdb->prepare(
		'SELECT COUNT(*) FROM %i WHERE userid = %d AND device_id = %s LIMIT 1',
		array( $table_name, $user_id, $device_id )
	) );
	
	return (int) $device_exists > 0;
}

/**
 * Filters device tokens to only include those registered to the user
 * (additional safety check before sending push notifications)
 * 
 * @param int   $user_id User ID 
 * @param array $device_ids Array of device tokens
 * @return array Filtered array of device tokens belonging to the user
 * 
 * @since 3.24
 */
function pnfpb_filter_user_devices( $user_id, $device_ids = array() ) {
	global $wpdb;
	
	if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
		return array();
	}
	
	if ( ! is_array( $device_ids ) || empty( $device_ids ) ) {
		return array();
	}
	
	$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';
	
	// Build placeholders for IN clause
	$placeholders = implode( ',', array_fill( 0, count( $device_ids ), '%s' ) );
	
	// Get only devices that exist in our database for this user
	$safe_devices = $wpdb->get_col( $wpdb->prepare(
		"SELECT device_id FROM %i WHERE userid = %d AND device_id IN ({$placeholders})",
		array_merge( array( $table_name, $user_id ), $device_ids )
	) );
	
	return is_array( $safe_devices ) ? $safe_devices : array();
}

/**
 * Logs suspicious device registration attempts
 * 
 * @param int    $attempted_user_id The user ID being targeted
 * @param int    $requesting_user_id The authenticated user making the request
 * @param string $action Description of the action attempted
 * @param string $device_id Device token involved
 * 
 * @since 3.24
 */
function pnfpb_log_security_event( $attempted_user_id, $requesting_user_id, $action, $device_id = '' ) {
	// Log to a dedicated security event table for monitoring
	// Can be used to detect patterns of abuse
	
	global $wpdb;
	
	$table_name = $wpdb->prefix . 'pnfpb_security_events';
	
	// Only log if table exists (created on plugin update)
	if ( $wpdb->get_var( "SHOW TABLES LIKE '%s'", $table_name ) === $table_name ) {
		$wpdb->insert(
			$table_name,
			array(
				'attempted_userid'   => $attempted_user_id,
				'requesting_userid'  => $requesting_user_id,
				'action'             => $action,
				'device_id'          => $device_id,
				'ip_address'         => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
				'user_agent'         => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
				'logged_at'          => current_time( 'mysql' ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);
	}
}

/**
 * Gets devices for a user with security validation
 * This ensures we only return devices that were legitimately registered
 * 
 * @param int    $user_id User ID to get devices for
 * @param string $device_filter Optional filter for device type (e.g., '%onesignal%')
 * @return array Array of device tokens
 * 
 * @since 3.24
 */
function pnfpb_get_user_devices_safe( $user_id, $device_filter = '' ) {
	global $wpdb;
	
	if ( ! is_numeric( $user_id ) || $user_id <= 0 ) {
		return array();
	}
	
	$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';
	
	if ( ! empty( $device_filter ) ) {
		$devices = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT(SUBSTRING_INDEX(device_id, \'!!\', 1)) FROM %i WHERE userid = %d AND device_id LIKE %s ORDER BY id DESC',
			array( $table_name, $user_id, $device_filter )
		) );
	} else {
		$devices = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT(SUBSTRING_INDEX(device_id, \'!!\', 1)) FROM %i WHERE userid = %d ORDER BY id DESC',
			array( $table_name, $user_id )
		) );
	}
	
	return is_array( $devices ) ? array_filter( $devices ) : array();
}
