<?php
/**
 * Database migration for PNFPB Token Cleanup System
 *
 * @since 3.22
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PNFPB_Token_Cleanup_Migrations' ) ) {
	/**
	 * Handles database schema creation and updates for token cleanup system
	 */
	class PNFPB_Token_Cleanup_Migrations {

		/**
		 * Migration version
		 *
		 * @var string
		 */
		private static $migration_version = '1.0';

		/**
		 * Run all pending migrations
		 *
		 * @return bool True on success, false on failure
		 */
		public static function run_migrations() {
			$current_version = get_option( 'pnfpb_token_cleanup_migration_version', '0.0' );

			if ( version_compare( $current_version, self::$migration_version, '<' ) ) {
				self::migrate_add_token_status_columns();
				self::migrate_create_cleanup_logs_table();
				update_option( 'pnfpb_token_cleanup_migration_version', self::$migration_version );
				return true;
			}

			return false;
		}

		/**
		 * Add token_status and related columns to device tokens table
		 *
		 * @return bool
		 */
		private static function migrate_add_token_status_columns() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			// Check if columns already exist
			$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ) );
			$column_names = array();

			if ( is_array( $columns ) ) {
				$column_names = wp_list_pluck( $columns, 'Field' );
			}

			$errors = array();

			// Add token_status column if it doesn't exist
			if ( ! in_array( 'token_status', $column_names, true ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						"ALTER TABLE %i ADD COLUMN token_status VARCHAR(50) DEFAULT 'valid' AFTER device_id",
						$table_name
					)
				);

				if ( false === $result ) {
					$errors[] = 'Failed to add token_status column: ' . $wpdb->last_error;
				}
			}

			// Add last_verified_at column if it doesn't exist
			if ( ! in_array( 'last_verified_at', $column_names, true ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i ADD COLUMN last_verified_at DATETIME DEFAULT NULL AFTER token_status',
						$table_name
					)
				);

				if ( false === $result ) {
					$errors[] = 'Failed to add last_verified_at column: ' . $wpdb->last_error;
				}
			}

			// Add last_validation_error column if it doesn't exist
			if ( ! in_array( 'last_validation_error', $column_names, true ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i ADD COLUMN last_validation_error VARCHAR(255) DEFAULT NULL AFTER last_verified_at',
						$table_name
					)
				);

				if ( false === $result ) {
					$errors[] = 'Failed to add last_validation_error column: ' . $wpdb->last_error;
				}
			}

			// Add indexes for performance
			$indexes = $wpdb->get_results(
				$wpdb->prepare(
					'SHOW INDEX FROM %i WHERE Key_name IN ("idx_token_status", "idx_last_verified_at")',
					$table_name
				)
			);

			$existing_indexes = wp_list_pluck( $indexes, 'Key_name' );

			if ( ! in_array( 'idx_token_status', $existing_indexes, true ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i ADD INDEX idx_token_status (token_status)',
						$table_name
					)
				);

				if ( false === $result ) {
					$errors[] = 'Failed to add token_status index: ' . $wpdb->last_error;
				}
			}

			if ( ! in_array( 'idx_last_verified_at', $existing_indexes, true ) ) {
				$result = $wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i ADD INDEX idx_last_verified_at (last_verified_at)',
						$table_name
					)
				);

				if ( false === $result ) {
					$errors[] = 'Failed to add last_verified_at index: ' . $wpdb->last_error;
				}
			}

			if ( ! empty( $errors ) ) {
				error_log( 'PNFPB Token Cleanup Migration Errors: ' . implode( ', ', $errors ) );
				return false;
			}

			return true;
		}

		/**
		 * Create cleanup logs table for audit trail
		 *
		 * @return bool
		 */
		private static function migrate_create_cleanup_logs_table() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_token_cleanup_logs';
			$charset_collate = $wpdb->get_charset_collate();

			$sql = $wpdb->prepare(
				"CREATE TABLE IF NOT EXISTS %i (
				id BIGINT AUTO_INCREMENT PRIMARY KEY,
				event_type VARCHAR(50) NOT NULL,
				tokens_processed INT DEFAULT 0,
				invalid_tokens INT DEFAULT 0,
				validation_errors INT DEFAULT 0,
				batch_number INT DEFAULT 0,
				completion_percentage INT DEFAULT 0,
				error_message LONGTEXT,
				duration_seconds INT DEFAULT 0,
				api_calls_made INT DEFAULT 0,
				created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
				INDEX idx_event_type (event_type),
				INDEX idx_created_at (created_at)
			) {$charset_collate}",
				$table_name
			);

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );

			return true;
		}

		/**
		 * Rollback all migrations
		 *
		 * @return bool
		 */
		public static function rollback_migrations() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';
			$logs_table = $wpdb->prefix . 'pnfpb_token_cleanup_logs';

			// Drop columns from device tokens table
			$columns_to_drop = array( 'token_status', 'last_verified_at', 'last_validation_error' );

			foreach ( $columns_to_drop as $column ) {
				$wpdb->query(
					$wpdb->prepare(
						'ALTER TABLE %i DROP COLUMN IF EXISTS %i',
						$table_name,
						$column
					)
				);
			}

			// Drop cleanup logs table
			$wpdb->query( $wpdb->prepare( 'DROP TABLE IF EXISTS %i', $logs_table ) );

			// Reset migration version
			delete_option( 'pnfpb_token_cleanup_migration_version' );
			delete_option( 'pnfpb_token_cleanup_enabled' );
			delete_option( 'pnfpb_token_cleanup_frequency' );
			delete_option( 'pnfpb_token_cleanup_batch_limit' );
			delete_option( 'pnfpb_token_cleanup_run_time' );
			delete_option( 'pnfpb_token_cleanup_progress' );
			delete_option( 'pnfpb_token_cleanup_running' );
			delete_option( 'pnfpb_tokens_validated_today' );
			delete_option( 'pnfpb_tokens_invalid_total' );

			return true;
		}

		/**
		 * Check if migrations are applied
		 *
		 * @return bool
		 */
		public static function is_migrated() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			$columns = $wpdb->get_results( $wpdb->prepare( 'SHOW COLUMNS FROM %i', $table_name ) );
			$column_names = wp_list_pluck( $columns, 'Field' );

			return in_array( 'token_status', $column_names, true ) &&
				   in_array( 'last_verified_at', $column_names, true ) &&
				   in_array( 'last_validation_error', $column_names, true );
		}
	}
}
?>
