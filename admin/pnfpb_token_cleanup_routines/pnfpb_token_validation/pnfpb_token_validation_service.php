<?php
/**
 * Recoverable FCM token validation and trash operations.
 *
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PNFPB_Token_Validation_Service' ) ) {
	/** Provides token cleanup persistence operations. */
	class PNFPB_Token_Validation_Service {

		/** Return cleanup table names. */
		public static function tables() {
			global $wpdb;
			return array(
				'live'   => $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web',
				'trash'  => $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash',
				'runs'   => $wpdb->prefix . 'pnfpb_token_cleanup_runs',
				'events' => $wpdb->prefix . 'pnfpb_token_cleanup_events',
			);
		}

		/** Fetch live token records after a stable ID cursor. */
		public static function get_candidate_tokens( $limit = 100, $cursor = 0 ) {
			global $wpdb;
			$limit  = min( 500, max( 1, absint( $limit ) ) );
			$tables = self::tables();
			$sql    = $wpdb->prepare(
				' SELECT id, userid, device_id, subscription_option, ip_address,
					web_auth, web_256, subscription_auth_token, firebase_version
				  FROM %i WHERE id > %d ORDER BY id ASC LIMIT %d',
				$tables['live'],
				absint( $cursor ),
				$limit
			);
			return (array) $wpdb->get_results( $sql, ARRAY_A );
		}

		/** Determine whether an FCM response identifies a stale token. */
		public static function is_stale_token_response( $response ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}
			$code    = wp_remote_retrieve_response_code( $response );
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$details = isset( $body['error']['details'] ) && is_array( $body['error']['details'] ) ? $body['error']['details'] : array();
			if ( 404 === $code ) {
				return true;
			}
			foreach ( $details as $detail ) {
				if ( in_array( $detail['errorCode'] ?? '', array( 'UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH' ), true ) ) {
					return true;
				}
			}
			return false;
		}

		/** Extract a safe error classification from an FCM response. */
		public static function extract_error_code( $response ) {
			if ( is_wp_error( $response ) ) {
				return 'WP_ERROR';
			}
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			foreach ( (array) ( $body['error']['details'] ?? array() ) as $detail ) {
				if ( ! empty( $detail['errorCode'] ) ) {
					return sanitize_key( $detail['errorCode'] );
				}
			}
			return 'HTTP_' . absint( wp_remote_retrieve_response_code( $response ) );
		}

		/** Normalize an HTTP response into valid, invalid, or retryable. */
		public static function classify_response( $response ) {
			if ( is_wp_error( $response ) ) {
				return array( 'state' => 'retryable', 'reason' => 'WP_ERROR' );
			}
			$code = wp_remote_retrieve_response_code( $response );
			if ( 200 === $code ) {
				return array( 'state' => 'valid', 'reason' => '' );
			}
			if ( self::is_stale_token_response( $response ) ) {
				return array( 'state' => 'invalid', 'reason' => self::extract_error_code( $response ) );
			}
			return array( 'state' => 'retryable', 'reason' => 'HTTP_' . absint( $code ) );
		}

		/** Move one live record to trash atomically where supported. */
		public static function move_to_trash( $record, $reason, $source, $run_id = 0 ) {
			global $wpdb;
			$tables = self::tables();
			$id     = absint( $record['id'] ?? 0 );
			if ( ! $id || empty( $record['device_id'] ) ) {
				return new WP_Error( 'invalid_token_record' );
			}
			$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$inserted = $wpdb->insert( $tables['trash'], array( 'original_id' => $id, 'userid' => absint( $record['userid'] ?? 0 ), 'device_id' => $record['device_id'], 'subscription_option' => $record['subscription_option'] ?? null, 'ip_address' => $record['ip_address'] ?? null, 'web_auth' => $record['web_auth'] ?? null, 'web_256' => $record['web_256'] ?? null, 'subscription_auth_token' => $record['subscription_auth_token'] ?? null, 'firebase_version' => $record['firebase_version'] ?? 'L', 'removal_reason' => sanitize_text_field( $reason ), 'removal_source' => sanitize_key( $source ), 'last_validation_error' => sanitize_text_field( $reason ), 'removed_at' => current_time( 'mysql' ) ), array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
			if ( false === $inserted ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return new WP_Error( 'trash_insert_failed', $wpdb->last_error );
			}
			$deleted = $wpdb->delete( $tables['live'], array( 'id' => $id ), array( '%d' ) );
			if ( 1 !== $deleted ) {
				$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				return new WP_Error( 'live_delete_failed', $wpdb->last_error );
			}
			$trash_id = absint( $wpdb->insert_id );
			$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			self::event( $run_id, 'moved_to_trash', $trash_id, $reason, array( 'source' => $source ) );
			return $trash_id;
		}

		/** Restore a trashed record if the token is not already live. */
		public static function restore_from_trash( $trash_id ) {
			global $wpdb;
			$tables = self::tables();
			$id     = absint( $trash_id );
			$row    = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE trash_id = %d', $tables['trash'], $id ), ARRAY_A );
			if ( ! $row ) {
				return new WP_Error( 'not_found' );
			}
			if ( $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE device_id = %s LIMIT 1', $tables['live'], $row['device_id'] ) ) ) {
				return new WP_Error( 'already_live' );
			}
			$fields = array( 'userid', 'device_id', 'subscription_option', 'ip_address', 'web_auth', 'web_256', 'subscription_auth_token', 'firebase_version' );
			$data  = array();
			$format = array();
			foreach ( $fields as $field ) {
				$data[ $field ] = $row[ $field ];
				$format[]       = 'userid' === $field ? '%d' : '%s';
			}
			if ( false === $wpdb->insert( $tables['live'], $data, $format ) ) {
				return new WP_Error( 'restore_failed', $wpdb->last_error );
			}
			$wpdb->delete( $tables['trash'], array( 'trash_id' => $id ), array( '%d' ) );
			return true;
		}

		/** Permanently delete one trashed record. */
		public static function permanently_delete_trash( $trash_id ) {
			global $wpdb;
			return false !== $wpdb->delete( self::tables()['trash'], array( 'trash_id' => absint( $trash_id ) ), array( '%d' ) );
		}

		/** Record a sanitized audit event. */
		public static function event( $run_id, $type, $trash_id = 0, $reason = '', $details = array() ) {
			global $wpdb;
			return false !== $wpdb->insert( self::tables()['events'], array( 'run_id' => absint( $run_id ), 'event_type' => sanitize_key( $type ), 'trash_id' => absint( $trash_id ), 'reason' => sanitize_text_field( $reason ), 'details' => wp_json_encode( $details ), 'created_at' => current_time( 'mysql' ) ), array( '%d', '%s', '%d', '%s', '%s', '%s' ) );
		}

		/** Return live and trash counts. */
		public static function counts() {
			global $wpdb;
			$tables = self::tables();
			$live_count  = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['live'] ) );
			$trash_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $tables['trash'] ) );
			$last_run    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT valid_count, moved_count, retryable_count
					 FROM %i
					 WHERE processed > 0
					 ORDER BY id DESC
					 LIMIT 1",
					$tables['runs']
				),
				ARRAY_A
			);

			$live_count = absint( $live_count );
			$trash_count = absint( $trash_count );

			return array(
				'valid'     => $last_run ? absint( $last_run['valid_count'] ) : 0,
				'invalid'   => $trash_count,
				'unverified' => $last_run ? absint( $last_run['retryable_count'] ) : 0,
				'total'     => $live_count,
				'live'      => $live_count,
				'trash'     => $trash_count,
				// Compatibility values for older dashboard markup. Validation
				// state is stored in run results, not in the live table.
			);
		}
	}
}
