<?php
/**
 * Token Validation Service for PNFPB
 *
 * Handles validation of Firebase Cloud Messaging tokens
 *
 * @since 3.22
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PNFPB_Token_Validation_Service' ) ) {
	/**
	 * Service class for token validation operations
	 */
	class PNFPB_Token_Validation_Service {

		/**
		 * Detect if response indicates a stale/invalid token
		 *
		 * @param mixed $response WordPress HTTP response
		 * @return bool True if token is stale/invalid, false otherwise
		 */
		public static function is_stale_token_response( $response ) {
			if ( is_wp_error( $response ) ) {
				return false;
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			$body      = json_decode( wp_remote_retrieve_body( $response ), true );

			// HTTP 404 (UNREGISTERED)
			if ( 404 === $http_code ) {
				return true;
			}

			// HTTP 400 with specific error codes
			if ( 400 === $http_code || 401 === $http_code ) {
				if ( isset( $body['error']['details'] ) && is_array( $body['error']['details'] ) ) {
					foreach ( $body['error']['details'] as $detail ) {
						$error_code = $detail['errorCode'] ?? '';
						if ( in_array( $error_code, array( 'UNREGISTERED', 'INVALID_ARGUMENT', 'SENDER_ID_MISMATCH' ), true ) ) {
							return true;
						}
					}
				}
			}

			return false;
		}

		/**
		 * Extract error code from FCM response
		 *
		 * @param mixed $response WordPress HTTP response
		 * @return string Error code or empty string if none found
		 */
		public static function extract_error_code( $response ) {
			if ( is_wp_error( $response ) ) {
				return 'wp_error';
			}

			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			if ( isset( $body['error']['details'] ) && is_array( $body['error']['details'] ) ) {
				foreach ( $body['error']['details'] as $detail ) {
					$error_code = $detail['errorCode'] ?? '';
					if ( ! empty( $error_code ) ) {
						return $error_code;
					}
				}
			}

			$http_code = wp_remote_retrieve_response_code( $response );
			return 'HTTP_' . $http_code;
		}

		/**
		 * Get next batch of tokens marked for validation
		 *
		 * @param int $limit Number of tokens to fetch (1-500)
		 * @param string $status Token status to filter by ('unverified', 'pending_verification', 'all')
		 * @return array Array of token records
		 */
		public static function get_next_batch_tokens( $limit = 100, $status = 'pending_verification' ) {
			global $wpdb;

			// Sanitize limit
			$limit = absint( $limit );
			$limit = min( $limit, 500 );
			$limit = max( $limit, 1 );

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			// Build query based on status filter
			if ( 'all' === $status ) {
				$sql = $wpdb->prepare(
					"SELECT id, device_id, userid, token_status, last_verified_at 
					 FROM %i 
					 ORDER BY last_verified_at ASC, id ASC 
					 LIMIT %d",
					$table_name,
					$limit
				);
			} else {
				$sql = $wpdb->prepare(
					"SELECT id, device_id, userid, token_status, last_verified_at 
					 FROM %i 
					 WHERE token_status = %s 
					 ORDER BY last_verified_at ASC, id ASC 
					 LIMIT %d",
					$table_name,
					$status,
					$limit
				);
			}

			$results = $wpdb->get_results( $sql, ARRAY_A );

			return is_array( $results ) ? $results : array();
		}

		/**
		 * Update token status after validation
		 *
		 * @param int $token_id Token record ID
		 * @param string $status New status ('valid', 'invalid', 'unverified', 'pending_verification')
		 * @param string $error_code Optional error code from validation
		 * @return bool True on success, false on failure
		 */
		public static function update_token_status( $token_id, $status = 'valid', $error_code = '' ) {
			global $wpdb;

			$token_id = absint( $token_id );

			if ( $token_id <= 0 ) {
				return false;
			}

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			// Validate status
			$valid_statuses = array( 'valid', 'invalid', 'unverified', 'pending_verification' );
			if ( ! in_array( $status, $valid_statuses, true ) ) {
				$status = 'valid';
			}

			// Prepare update data
			$update_data = array(
				'token_status'       => $status,
				'last_verified_at'   => current_time( 'mysql' ),
			);

			if ( ! empty( $error_code ) ) {
				$update_data['last_validation_error'] = sanitize_text_field( $error_code );
			}

			$result = $wpdb->update(
				$table_name,
				$update_data,
				array( 'id' => $token_id ),
				array( '%s', '%s', '%s' ),
				array( '%d' )
			);

			return false !== $result;
		}

		/**
		 * Mark unvalidated tokens for validation
		 *
		 * @param int $batch_size Number of tokens to mark in this call
		 * @return int Number of tokens marked
		 */
		public static function mark_batch_for_validation( $batch_size = 100 ) {
			global $wpdb;

			$batch_size = absint( $batch_size );
			$batch_size = min( $batch_size, 500 );
			$batch_size = max( $batch_size, 1 );

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			// Get tokens that haven't been validated yet or haven't been validated recently (30+ days)
			$thirty_days_ago = date( 'Y-m-d H:i:s', strtotime( '-30 days' ) );

			$sql = $wpdb->prepare(
				"UPDATE %i 
				 SET token_status = %s 
				 WHERE (last_verified_at IS NULL OR last_verified_at < %s)
				 AND token_status != %s
				 LIMIT %d",
				$table_name,
				'pending_verification',
				$thirty_days_ago,
				'invalid',
				$batch_size
			);

			$wpdb->query( $sql );

			// Return count of marked tokens
			return $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM %i WHERE token_status = %s",
					$table_name,
					'pending_verification'
				)
			);
		}

		/**
		 * Get count of tokens by status
		 *
		 * @param string $status Token status to count
		 * @return int Count of tokens
		 */
		public static function get_token_count_by_status( $status = 'valid' ) {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			if ( 'all' === $status ) {
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM %i",
						$table_name
					)
				);
			} else {
				$count = $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM %i WHERE token_status = %s",
						$table_name,
						$status
					)
				);
			}

			return absint( $count );
		}

		/**
		 * Get validation statistics
		 *
		 * @return array Array with statistics
		 */
		public static function get_validation_statistics() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			$stats = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT token_status, COUNT(*) as count FROM %i GROUP BY token_status",
					$table_name
				),
				ARRAY_A
			);

			$result = array(
				'valid'                   => 0,
				'invalid'                 => 0,
				'unverified'              => 0,
				'pending_verification'    => 0,
				'total'                   => 0,
			);

			if ( is_array( $stats ) ) {
				foreach ( $stats as $stat ) {
					$status = $stat['token_status'];
					$count  = absint( $stat['count'] );

					if ( isset( $result[ $status ] ) ) {
						$result[ $status ] = $count;
					}

					$result['total'] += $count;
				}
			}

			return $result;
		}

		/**
		 * Reset all token statuses to unverified
		 *
		 * @return int Number of tokens reset
		 */
		public static function reset_all_tokens_for_validation() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			$result = $wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET token_status = %s, last_verified_at = NULL, last_validation_error = NULL",
					$table_name,
					'unverified'
				)
			);

			return false !== $result ? $wpdb->rows_affected : 0;
		}

		/**
		 * Get oldest unvalidated tokens
		 *
		 * @param int $days Number of days to consider as "old"
		 * @param int $limit Maximum number to return
		 * @return array Array of token records
		 */
		public static function get_oldest_unvalidated_tokens( $days = 30, $limit = 100 ) {
			global $wpdb;

			$limit = absint( $limit );
			$limit = min( $limit, 500 );
			$limit = max( $limit, 1 );

			$days = absint( $days );
			$days = max( $days, 1 );

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';
			$cutoff_date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			$sql = $wpdb->prepare(
				"SELECT id, device_id, userid, token_status, last_verified_at 
				 FROM %i 
				 WHERE (last_verified_at IS NULL OR last_verified_at < %s)
				 ORDER BY last_verified_at ASC NULLS FIRST, id ASC 
				 LIMIT %d",
				$table_name,
				$cutoff_date,
				$limit
			);

			$results = $wpdb->get_results( $sql, ARRAY_A );

			return is_array( $results ) ? $results : array();
		}

		/**
		 * Clear validation history and reset all statuses
		 *
		 * @return bool True on success
		 */
		public static function clear_validation_history() {
			global $wpdb;

			$table_name = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web';

			// Reset all token statuses
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE %i SET token_status = %s, last_verified_at = NULL, last_validation_error = NULL",
					$table_name,
					'valid'
				)
			);

			// Clear cleanup logs
			$logs_table = $wpdb->prefix . 'pnfpb_token_cleanup_logs';
			$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', $logs_table ) );

			return true;
		}
	}
}
?>
