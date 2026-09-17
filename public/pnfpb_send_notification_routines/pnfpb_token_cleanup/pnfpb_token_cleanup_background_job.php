<?php
/**
 * Token Cleanup Background Job
 *
 * Handles background job execution for token cleanup using Action Scheduler
 *
 * @since 3.22
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load the validation service
require_once dirname( dirname( __FILE__ ) ) . '/pnfpb_token_validation/pnfpb_token_validation_service.php';

if ( ! class_exists( 'PNFPB_Token_Cleanup_Background_Job' ) ) {
	/**
	 * Background job handler for token cleanup operations
	 */
	class PNFPB_Token_Cleanup_Background_Job {

		/**
		 * Hook name for cleanup job
		 */
		const CLEANUP_HOOK = 'pnfpb_token_cleanup_job';

		/**
		 * Schedule cleanup job via Action Scheduler
		 *
		 * @param string $schedule Frequency ('hourly', 'twicedaily', 'daily', 'weekly')
		 * @param int $batch_size Number of tokens to process per batch (default 100)
		 * @return bool True on success
		 */
		public static function schedule_cleanup_job( $schedule = 'daily', $batch_size = 100 ) {
			// Check if Action Scheduler is available
			if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
				return false;
			}

			// Validate schedule
			$valid_schedules = array( 'hourly', 'twicedaily', 'daily', 'weekly' );
			if ( ! in_array( $schedule, $valid_schedules, true ) ) {
				$schedule = 'daily';
			}

			// Convert schedule to recurrence
			switch ( $schedule ) {
				case 'hourly':
					$recurrence = 'hourly';
					break;
				case 'twicedaily':
					$recurrence = 'twicedaily';
					break;
				case 'weekly':
					$recurrence = 'weekly';
					break;
				default:
					$recurrence = 'daily';
			}

			// Check if job already scheduled
			$next = as_next_scheduled_action(
				self::CLEANUP_HOOK,
				array( 'batch_size' => $batch_size )
			);

			if ( $next ) {
				// Job already scheduled
				return true;
			}

			// Schedule the recurring action
			try {
				as_schedule_recurring_action(
					time(),
					$recurrence,
					self::CLEANUP_HOOK,
					array( 'batch_size' => $batch_size ),
					'pnfpb_token_cleanup',
					true
				);

				// Log scheduling event
				self::log_cleanup_event(
					'job_scheduled',
					array(
						'schedule'   => $schedule,
						'batch_size' => $batch_size,
					)
				);

				// Store settings
				update_option( 'pnfpb_cleanup_schedule', $schedule );
				update_option( 'pnfpb_cleanup_batch_size', absint( $batch_size ) );

				return true;
			} catch ( Exception $e ) {
				self::log_cleanup_event(
					'scheduling_error',
					array( 'error' => $e->getMessage() )
				);
				return false;
			}
		}

		/**
		 * Unschedule cleanup job
		 *
		 * @return bool True on success
		 */
		public static function unschedule_cleanup_job() {
			if ( ! function_exists( 'as_unschedule_all_actions' ) ) {
				return false;
			}

			try {
				as_unschedule_all_actions( self::CLEANUP_HOOK, array(), 'pnfpb_token_cleanup' );

				self::log_cleanup_event( 'job_unscheduled', array() );

				return true;
			} catch ( Exception $e ) {
				return false;
			}
		}

		/**
		 * Execute cleanup batch - main job handler
		 *
		 * @param int $batch_size Number of tokens to process
		 * @return array Result with counts and status
		 */
		public static function execute_cleanup_batch( $batch_size = 100 ) {
			global $wpdb;

			// Validate batch size
			$batch_size = absint( $batch_size );
			$batch_size = min( $batch_size, 500 );
			$batch_size = max( $batch_size, 1 );

			$result = array(
				'batch_size'       => $batch_size,
				'tokens_processed' => 0,
				'tokens_validated' => 0,
				'tokens_invalid'   => 0,
				'tokens_valid'     => 0,
				'errors'           => 0,
				'skipped'          => 0,
				'timestamp'        => current_time( 'mysql' ),
				'status'           => 'completed',
			);

			try {
				// Get Firebase settings
				$firebase_settings = self::get_firebase_settings();
				if ( empty( $firebase_settings ) ) {
					$result['status']  = 'failed';
					$result['errors']  = 1;
					self::log_cleanup_event( 'batch_error', array( 'error' => 'Firebase settings not configured' ) );
					return $result;
				}

				// Mark unvalidated tokens for validation
				$pending_count = PNFPB_Token_Validation_Service::mark_batch_for_validation( $batch_size );

				// Get batch of tokens to validate
				$tokens = PNFPB_Token_Validation_Service::get_next_batch_tokens( $batch_size, 'pending_verification' );

				if ( empty( $tokens ) ) {
					$result['status'] = 'no_tokens';
					self::log_cleanup_event( 'batch_completed', $result );
					return $result;
				}

				// Process each token
				foreach ( $tokens as $token ) {
					$result['tokens_processed']++;

					try {
						// Validate token against Firebase
						$validation_result = self::validate_token_with_firebase(
							$token['device_id'],
							$firebase_settings
						);

						if ( is_array( $validation_result ) && isset( $validation_result['is_valid'] ) ) {
							if ( $validation_result['is_valid'] ) {
								// Token is valid
								PNFPB_Token_Validation_Service::update_token_status(
									$token['id'],
									'valid',
									''
								);
								$result['tokens_valid']++;
							} else {
								// Token is invalid/stale
								$error_code = $validation_result['error_code'] ?? 'UNKNOWN_ERROR';
								PNFPB_Token_Validation_Service::update_token_status(
									$token['id'],
									'invalid',
									$error_code
								);
								$result['tokens_invalid']++;
							}
							$result['tokens_validated']++;
						} else {
							// Validation failed, mark as unverified for retry
							PNFPB_Token_Validation_Service::update_token_status(
								$token['id'],
								'unverified',
								'VALIDATION_ERROR'
							);
							$result['skipped']++;
						}
					} catch ( Exception $e ) {
						$result['errors']++;
						self::log_cleanup_event(
							'token_validation_error',
							array(
								'token_id' => $token['id'],
								'error'    => $e->getMessage(),
							)
						);
					}
				}

				// Log batch completion
				self::log_cleanup_event( 'batch_completed', $result );

			} catch ( Exception $e ) {
				$result['status']  = 'failed';
				$result['errors']++;
				self::log_cleanup_event(
					'batch_error',
					array( 'error' => $e->getMessage() )
				);
			}

			// Update cleanup status
			self::update_cleanup_status( $result );

			return $result;
		}

		/**
		 * Validate token with Firebase FCM
		 *
		 * @param string $token Device token to validate
		 * @param array $firebase_settings Firebase configuration
		 * @return array Validation result with is_valid flag and error_code
		 */
		private static function validate_token_with_firebase( $token, $firebase_settings ) {
			// Get access token
			$access_token = self::get_fcm_access_token( $firebase_settings );
			if ( empty( $access_token ) ) {
				return array(
					'is_valid'   => false,
					'error_code' => 'NO_ACCESS_TOKEN',
				);
			}

			// Prepare validation payload
			$payload = array(
				'message' => array(
					'token' => $token,
					'data' => array(
						'test' => 'validation',
					),
				),
				'validate_only' => true, // Only validate, don't actually send
			);

			// Make request to FCM
			$response = wp_remote_post(
				'https://fcm.googleapis.com/v1/projects/' . $firebase_settings['project_id'] . '/messages:send',
				array(
					'headers' => array(
						'Authorization' => 'Bearer ' . $access_token,
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode( $payload ),
					'timeout' => 10,
				)
			);

			// Check response
			if ( is_wp_error( $response ) ) {
				return array(
					'is_valid'   => false,
					'error_code' => 'HTTP_ERROR',
				);
			}

			$http_code = wp_remote_retrieve_response_code( $response );

			// 200 = valid token
			if ( 200 === $http_code ) {
				return array(
					'is_valid'   => true,
					'error_code' => '',
				);
			}

			// Detect stale/invalid token
			$is_stale = PNFPB_Token_Validation_Service::is_stale_token_response( $response );
			if ( $is_stale ) {
				$error_code = PNFPB_Token_Validation_Service::extract_error_code( $response );
				return array(
					'is_valid'   => false,
					'error_code' => $error_code,
				);
			}

			// Other errors - don't mark as invalid yet
			return array(
				'is_valid'   => null, // Unknown status
				'error_code' => 'HTTP_' . $http_code,
			);
		}

		/**
		 * Get FCM access token via OAuth 2.0
		 *
		 * @param array $firebase_settings Firebase configuration
		 * @return string|null Access token or null on failure
		 */
		private static function get_fcm_access_token( $firebase_settings ) {
			// Check for cached token
			$cached_token = get_transient( 'pnfpb_fcm_access_token' );
			if ( ! empty( $cached_token ) ) {
				return $cached_token;
			}

			// Get service account JSON
			$service_account_json = $firebase_settings['service_account_json'] ?? '';
			if ( empty( $service_account_json ) ) {
				return null;
			}

			try {
				$service_account = json_decode( $service_account_json, true );

				if ( ! isset( $service_account['private_key'], $service_account['client_email'] ) ) {
					return null;
				}

				// Create JWT
				$jwt = self::create_fcm_jwt(
					$service_account['private_key'],
					$service_account['client_email']
				);

				if ( empty( $jwt ) ) {
					return null;
				}

				// Exchange JWT for access token
				$token_response = wp_remote_post(
					'https://oauth2.googleapis.com/token',
					array(
						'headers' => array( 'Content-Type' => 'application/x-www-form-urlencoded' ),
						'body'    => array(
							'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
							'assertion'  => $jwt,
						),
						'timeout' => 10,
					)
				);

				if ( is_wp_error( $token_response ) ) {
					return null;
				}

				$response_body = json_decode( wp_remote_retrieve_body( $token_response ), true );
				if ( isset( $response_body['access_token'] ) ) {
					$access_token = $response_body['access_token'];
					$expires_in    = $response_body['expires_in'] ?? 3600;

					// Cache token for slightly less than expiration time
					set_transient( 'pnfpb_fcm_access_token', $access_token, $expires_in - 60 );

					return $access_token;
				}
			} catch ( Exception $e ) {
				return null;
			}

			return null;
		}

		/**
		 * Create JWT for FCM authentication
		 *
		 * @param string $private_key Private key from service account
		 * @param string $client_email Client email from service account
		 * @return string|null JWT token or null on failure
		 */
		private static function create_fcm_jwt( $private_key, $client_email ) {
			try {
				$header  = wp_json_encode(
					array(
						'alg' => 'RS256',
						'typ' => 'JWT',
					)
				);
				$payload = wp_json_encode(
					array(
						'iss'   => $client_email,
						'sub'   => $client_email,
						'aud'   => 'https://oauth2.googleapis.com/token',
						'iat'   => time(),
						'exp'   => time() + 3600,
						'scope' => 'https://www.googleapis.com/auth/cloud-platform',
					)
				);

				// Base64URL encode
				$header_encoded  = rtrim( strtr( base64_encode( $header ), '+/', '-_' ), '=' );
				$payload_encoded = rtrim( strtr( base64_encode( $payload ), '+/', '-_' ), '=' );

				// Sign
				$signature_input = $header_encoded . '.' . $payload_encoded;

				if ( ! function_exists( 'openssl_sign' ) ) {
					return null;
				}

				$signature = '';
				if ( ! openssl_sign( $signature_input, $signature, $private_key, 'sha256' ) ) {
					return null;
				}

				$signature_encoded = rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );

				return $signature_input . '.' . $signature_encoded;
			} catch ( Exception $e ) {
				return null;
			}
		}

		/**
		 * Log cleanup event to database
		 *
		 * @param string $event Event type
		 * @param array $data Event data
		 * @return bool True on success
		 */
		public static function log_cleanup_event( $event, $data = array() ) {
			global $wpdb;

			$logs_table = $wpdb->prefix . 'pnfpb_token_cleanup_logs';

			// Serialize data
			$data_json = wp_json_encode( $data );

			$result = $wpdb->insert(
				$logs_table,
				array(
					'event_type' => sanitize_text_field( $event ),
					'event_data' => $data_json,
					'created_at' => current_time( 'mysql' ),
				),
				array( '%s', '%s', '%s' )
			);

			return false !== $result;
		}

		/**
		 * Update cleanup status
		 *
		 * @param array $status Status data
		 * @return bool True on success
		 */
		public static function update_cleanup_status( $status = array() ) {
			$current_status = get_option( 'pnfpb_cleanup_status', array() );

			$new_status = array_merge(
				$current_status,
				array(
					'last_run'        => current_time( 'mysql' ),
					'tokens_processed' => $status['tokens_processed'] ?? 0,
					'tokens_validated' => $status['tokens_validated'] ?? 0,
					'tokens_invalid'   => $status['tokens_invalid'] ?? 0,
					'tokens_valid'     => $status['tokens_valid'] ?? 0,
					'errors'           => $status['errors'] ?? 0,
					'status'           => $status['status'] ?? 'idle',
				)
			);

			return update_option( 'pnfpb_cleanup_status', $new_status );
		}

		/**
		 * Get cleanup status
		 *
		 * @return array Cleanup status
		 */
		public static function get_cleanup_status() {
			$status = get_option( 'pnfpb_cleanup_status', array() );

			// Ensure all keys exist
			$defaults = array(
				'last_run'        => null,
				'tokens_processed' => 0,
				'tokens_validated' => 0,
				'tokens_invalid'   => 0,
				'tokens_valid'     => 0,
				'errors'           => 0,
				'status'           => 'idle',
			);

			return array_merge( $defaults, $status );
		}

		/**
		 * Get Firebase settings from options
		 *
		 * @return array|null Firebase settings or null if not configured
		 */
		private static function get_firebase_settings() {
			$project_id = get_option( 'pnfpb_firebase_project_id' );
			$service_account = get_option( 'pnfpb_firebase_service_account_json' );

			if ( empty( $project_id ) || empty( $service_account ) ) {
				return null;
			}

			return array(
				'project_id'            => $project_id,
				'service_account_json'  => $service_account,
			);
		}

		/**
		 * Get recent cleanup logs
		 *
		 * @param int $limit Number of logs to retrieve
		 * @return array Array of log entries
		 */
		public static function get_recent_logs( $limit = 50 ) {
			global $wpdb;

			$limit  = absint( $limit );
			$limit  = min( $limit, 500 );
			$limit  = max( $limit, 1 );
			$logs_table = $wpdb->prefix . 'pnfpb_token_cleanup_logs';

			$logs = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT id, event_type, event_data, created_at 
					 FROM %i 
					 ORDER BY created_at DESC 
					 LIMIT %d",
					$logs_table,
					$limit
				),
				ARRAY_A
			);

			// Decode JSON data
			if ( is_array( $logs ) ) {
				foreach ( $logs as &$log ) {
					if ( ! empty( $log['event_data'] ) ) {
						$log['event_data'] = json_decode( $log['event_data'], true );
					}
				}
			}

			return is_array( $logs ) ? $logs : array();
		}

		/**
		 * Clear old cleanup logs
		 *
		 * @param int $days Delete logs older than X days (default 30)
		 * @return int Number of deleted records
		 */
		public static function clear_old_logs( $days = 30 ) {
			global $wpdb;

			$days  = absint( $days );
			$days  = max( $days, 1 );
			$logs_table = $wpdb->prefix . 'pnfpb_token_cleanup_logs';
			$cutoff = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );

			$wpdb->query(
				$wpdb->prepare(
					"DELETE FROM %i WHERE created_at < %s",
					$logs_table,
					$cutoff
				)
			);

			return $wpdb->rows_affected;
		}
	}
}
?>
