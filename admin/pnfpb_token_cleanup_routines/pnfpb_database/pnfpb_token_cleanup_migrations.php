<?php
/** Database schema for recoverable FCM token cleanup. */
if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'PNFPB_Token_Cleanup_Migrations' ) ) {
	class PNFPB_Token_Cleanup_Migrations {
		private static $migration_version = '2.0';
		public static function run_migrations() {
			global $wpdb;
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			$charset = $wpdb->get_charset_collate();
			$trash = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
			$runs = $wpdb->prefix . 'pnfpb_token_cleanup_runs';
			$events = $wpdb->prefix . 'pnfpb_token_cleanup_events';
			dbDelta( "CREATE TABLE {$trash} (
				trash_id bigint(20) unsigned NOT NULL AUTO_INCREMENT, original_id bigint(20) unsigned NULL,
				userid bigint(20) NOT NULL, device_id varchar(300) NOT NULL, subscription_option varchar(50) NULL,
				ip_address varchar(100) NULL, web_auth varchar(600) NULL, web_256 varchar(600) NULL,
				subscription_auth_token varchar(300) NULL, firebase_version varchar(100) DEFAULT 'L',
				removal_reason varchar(100) NOT NULL, removal_source varchar(50) NOT NULL,
				last_validation_error text NULL, removed_at datetime NOT NULL, restore_count int(10) unsigned NOT NULL DEFAULT 0,
				restored_at datetime NULL, PRIMARY KEY (trash_id), KEY idx_trash_device (device_id(191)),
				KEY idx_trash_user (userid), KEY idx_trash_removed (removed_at), KEY idx_trash_reason (removal_reason)
			) {$charset};" );
			dbDelta( "CREATE TABLE {$runs} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT, trigger_source varchar(30) NOT NULL,
				status varchar(30) NOT NULL DEFAULT 'running', started_at datetime NOT NULL, completed_at datetime NULL,
				requested int(10) unsigned NOT NULL DEFAULT 0, processed int(10) unsigned NOT NULL DEFAULT 0,
				valid_count int(10) unsigned NOT NULL DEFAULT 0, moved_count int(10) unsigned NOT NULL DEFAULT 0,
				retryable_count int(10) unsigned NOT NULL DEFAULT 0, error_count int(10) unsigned NOT NULL DEFAULT 0,
				next_cursor bigint(20) unsigned NOT NULL DEFAULT 0, last_error text NULL, PRIMARY KEY (id),
				KEY idx_runs_started (started_at), KEY idx_runs_status (status)
			) {$charset};" );
			dbDelta( "CREATE TABLE {$events} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT, run_id bigint(20) unsigned NULL, event_type varchar(50) NOT NULL,
				trash_id bigint(20) unsigned NULL, reason varchar(100) NULL, details longtext NULL, created_at datetime NOT NULL,
				PRIMARY KEY (id), KEY idx_events_run (run_id), KEY idx_events_created (created_at)
			) {$charset};" );
			update_option( 'pnfpb_token_cleanup_migration_version', self::$migration_version );
			return self::tables_exist();
		}
		public static function tables_exist() {
			global $wpdb;
			foreach ( array( 'pnfpb_ic_subscribed_deviceids_web_trash', 'pnfpb_token_cleanup_runs', 'pnfpb_token_cleanup_events' ) as $suffix ) {
				$table = $wpdb->prefix . $suffix;
				if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) { return false; }
			}
			return true;
		}
	}
}
