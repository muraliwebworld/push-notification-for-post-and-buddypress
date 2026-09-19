<?php
/**
 * Token Cleanup AJAX Handlers
 *
 * DEPRECATED: AJAX handlers are now managed by the main plugin class
 * in pnfpb_push_notification.php with proper WordPress security standards.
 * This file is kept for backward compatibility only.
 *
 * @since 3.22
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Load required classes for token cleanup functionality
 * These are included here for reference and potential standalone usage
 */
if ( ! function_exists( 'pnfpb_load_token_cleanup_classes' ) ) {
	/**
	 * Load token cleanup classes
	 *
	 * @return void
	 */
	function pnfpb_load_token_cleanup_classes() {
		$classes = array(
			dirname( __DIR__ ) . '/pnfpb_token_cleanup_routines/pnfpb_database/pnfpb_token_cleanup_migrations.php',
			dirname( __DIR__ ) . '/pnfpb_token_cleanup_routines/pnfpb_token_validation/pnfpb_token_validation_service.php',
			dirname( __DIR__ ) . '/pnfpb_token_cleanup_routines/pnfpb_token_cleanup/pnfpb_token_cleanup_background_job.php',
		);

		foreach ( $classes as $class_file ) {
			if ( file_exists( $class_file ) ) {
				require_once $class_file;
			}
		}
	}
}

/**
 * Initialize token cleanup classes on plugins_loaded
 * This ensures classes are available when needed
 *
 * @return void
 */
if ( ! has_action( 'plugins_loaded', 'pnfpb_load_token_cleanup_classes' ) ) {
	add_action( 'plugins_loaded', 'pnfpb_load_token_cleanup_classes', -5 );
}
