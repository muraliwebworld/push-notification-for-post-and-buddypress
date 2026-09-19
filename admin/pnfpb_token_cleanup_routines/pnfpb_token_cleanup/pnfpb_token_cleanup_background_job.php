<?php
/**
 * Scheduled and manual recoverable token cleanup.
 *
 * @package PNFPB
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'PNFPB_Token_Cleanup_Background_Job' ) ) {
	/** Runs bounded cleanup batches. */
	class PNFPB_Token_Cleanup_Background_Job {

		const CLEANUP_HOOK = 'pnfpb_token_cleanup_job';
		const GROUP        = 'pnfpb_token_cleanup';
		const LOCK         = 'pnfpb_token_cleanup_lock';

		/** Schedule one recurring Action Scheduler job. */
		public static function schedule_cleanup_job( $schedule = 'daily', $batch_size = 100 ) {
			if ( 'manual' === $schedule ) {
				self::unschedule_cleanup_job();
				return true;
			}
			if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
				return false;
			}

			$intervals = array(
				'hourly'    => HOUR_IN_SECONDS,
				'twicedaily' => 12 * HOUR_IN_SECONDS,
				'daily'     => DAY_IN_SECONDS,
				'weekly'    => WEEK_IN_SECONDS,
			);
			$interval = $intervals[ $schedule ] ?? DAY_IN_SECONDS;
			self::unschedule_cleanup_job();

			return (bool) as_schedule_recurring_action( time() + MINUTE_IN_SECONDS, $interval, self::CLEANUP_HOOK, array( absint( $batch_size ) ), self::GROUP, true );
		}

		/** Remove Action Scheduler and WP-Cron cleanup events. */
		public static function unschedule_cleanup_job() {
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( self::CLEANUP_HOOK, array(), self::GROUP );
			}
			while ( $timestamp = wp_next_scheduled( self::CLEANUP_HOOK ) ) {
				wp_unschedule_event( $timestamp, self::CLEANUP_HOOK );
			}
			return true;
		}

		/** Acquire a short-lived cross-request cleanup lock. */
		public static function acquire_lock() {
			$current = get_option( self::LOCK, 0 );
			if ( $current && absint( $current ) > time() ) {
				return false;
			}
			return add_option( self::LOCK, time() + 15 * MINUTE_IN_SECONDS, '', 'no' ) || update_option( self::LOCK, time() + 15 * MINUTE_IN_SECONDS );
		}

		/** Release the cleanup lock. */
		public static function release_lock() {
			delete_option( self::LOCK );
		}

		/** Execute one bounded validation/move batch. */
		public static function execute_cleanup_batch( $batch_size = 100, $source = 'manual' ) {
			$configured_size = get_option( 'pnfpb_token_cleanup_batch_limit', get_option( 'pnfpb_cleanup_batch_size', 100 ) );
			$batch_size      = min( 500, max( 1, absint( $batch_size ?: $configured_size ) ) );
			$result          = array( 'run_id' => 0, 'status' => 'failed', 'candidates' => 0, 'tokens_processed' => 0, 'tokens_validated' => 0, 'tokens_valid' => 0, 'moved_to_trash' => 0, 'tokens_invalid' => 0, 'retryable' => 0, 'errors' => 0, 'next_cursor' => 0, 'message' => '' );

			if ( ! self::acquire_lock() ) {
				$result['status'] = 'skipped_locked';
				return $result;
			}

			global $wpdb;
			$tables = PNFPB_Token_Validation_Service::tables();
			$wpdb->insert( $tables['runs'], array( 'trigger_source' => sanitize_key( $source ), 'status' => 'running', 'started_at' => current_time( 'mysql' ), 'requested' => $batch_size ), array( '%s', '%s', '%s', '%d' ) );
			$run_id           = absint( $wpdb->insert_id );
			$result['run_id'] = $run_id;

			try {
				$cursor = absint( get_option( 'pnfpb_token_cleanup_cursor', 0 ) );
				$tokens = PNFPB_Token_Validation_Service::get_candidate_tokens( $batch_size, $cursor );
				$result['candidates'] = count( $tokens );

				foreach ( $tokens as $record ) {
					$result['tokens_processed']++;
					$result['next_cursor'] = absint( $record['id'] );
					$validation            = self::validate_token( $record['device_id'] );

					if ( 'valid' === $validation['state'] ) {
						$result['tokens_validated']++;
						$result['tokens_valid']++;
					} elseif ( 'invalid' === $validation['state'] ) {
						$moved = PNFPB_Token_Validation_Service::move_to_trash( $record, $validation['reason'], $source . '_cleanup', $run_id );
						if ( is_wp_error( $moved ) ) {
							$result['errors']++;
						} else {
							$result['moved_to_trash']++;
							$result['tokens_invalid']++;
						}
					} else {
						$result['retryable']++;
					}
				}

				update_option( 'pnfpb_token_cleanup_cursor', $result['next_cursor'] );
				$result['status'] = empty( $tokens ) ? 'no_tokens' : 'completed';
				$wpdb->update( $tables['runs'], array( 'status' => $result['status'], 'completed_at' => current_time( 'mysql' ), 'processed' => $result['tokens_processed'], 'valid_count' => $result['tokens_valid'], 'moved_count' => $result['moved_to_trash'], 'retryable_count' => $result['retryable'], 'error_count' => $result['errors'], 'next_cursor' => $result['next_cursor'] ), array( 'id' => $run_id ), array( '%s', '%s', '%d', '%d', '%d', '%d', '%d', '%d' ), array( '%d' ) );
			} catch ( Exception $exception ) {
				$result['message'] = $exception->getMessage();
				$result['errors']++;
				$wpdb->update( $tables['runs'], array( 'status' => 'failed', 'completed_at' => current_time( 'mysql' ), 'last_error' => sanitize_text_field( $exception->getMessage() ) ), array( 'id' => $run_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
			}

			self::release_lock();
			return $result;
		}

		/** Validate one token through the existing Firebase service-account configuration. */
		private static function validate_token( $token ) {
			$key     = json_decode( get_option( 'pnfpb_sa_json_data', '' ), true );
			$project = get_option( 'pnfpb_ic_fcm_projectid' );
			if ( empty( $key['private_key'] ) || empty( $key['client_email'] ) || empty( $project ) ) {
				return array( 'state' => 'retryable', 'reason' => 'FCM_NOT_CONFIGURED' );
			}

			$oauth      = get_option( 'pnfpb_firebase_oauth_token' );
			$oauth_time = absint( get_option( 'pnfpb_firebase_oauth_timestamp', 0 ) );
			if ( empty( $oauth ) || $oauth_time < time() - HOUR_IN_SECONDS ) {
				$oauth = self::get_access_token( $key );
			}
			if ( empty( $oauth ) ) {
				return array( 'state' => 'retryable', 'reason' => 'FCM_AUTH_FAILED' );
			}

			$response = wp_remote_post( 'https://fcm.googleapis.com/v1/projects/' . rawurlencode( $project ) . '/messages:send', array( 'headers' => array( 'Authorization' => 'Bearer ' . $oauth, 'Content-Type' => 'application/json' ), 'body' => wp_json_encode( array( 'validate_only' => true, 'message' => array( 'token' => $token, 'data' => array( 'pnfpb_validation' => '1' ) ) ) ), 'timeout' => 15 ) );
			return PNFPB_Token_Validation_Service::classify_response( $response );
		}

		/** Obtain and cache an OAuth access token. */
		private static function get_access_token( $key ) {
			$header  = rtrim( strtr( base64_encode( wp_json_encode( array( 'typ' => 'JWT', 'alg' => 'RS256' ) ) ), '+/', '-_' ), '=' );
			$now     = time();
			$payload = rtrim( strtr( base64_encode( wp_json_encode( array( 'iss' => $key['client_email'], 'scope' => 'https://www.googleapis.com/auth/firebase.messaging', 'aud' => $key['token_uri'] ?? 'https://oauth2.googleapis.com/token', 'exp' => $now + HOUR_IN_SECONDS, 'iat' => $now ) ) ), '+/', '-_' ), '=' );
			openssl_sign( $header . '.' . $payload, $signature, $key['private_key'], 'SHA256' );
			$jwt      = $header . '.' . $payload . '.' . rtrim( strtr( base64_encode( $signature ), '+/', '-_' ), '=' );
			$response = wp_remote_post( $key['token_uri'] ?? 'https://oauth2.googleapis.com/token', array( 'body' => array( 'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $jwt ), 'timeout' => 15 ) );
			if ( is_wp_error( $response ) ) {
				return '';
			}
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( empty( $data['access_token'] ) ) {
				return '';
			}
			update_option( 'pnfpb_firebase_oauth_token', $data['access_token'] );
			update_option( 'pnfpb_firebase_oauth_timestamp', time() );
			return $data['access_token'];
		}

		/** Return cleanup dashboard status. */
		public static function get_cleanup_status() {
			global $wpdb;
			$tables = PNFPB_Token_Validation_Service::tables();
			$last   = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT 1', $tables['runs'] ), ARRAY_A );
			return array_merge( PNFPB_Token_Validation_Service::counts(), array( 'last_run' => $last ?: array(), 'lock' => (bool) get_option( self::LOCK ) ) );
		}

		/** Check whether the cleanup action is scheduled. */
		public static function verify_job_scheduled( $batch_size = 0 ) {
			if ( function_exists( 'as_has_scheduled_action' ) ) {
				if ( ! $batch_size ) {
					$batch_size = get_option( 'pnfpb_token_cleanup_batch_limit', get_option( 'pnfpb_cleanup_batch_size', 100 ) );
				}
				$args = array( absint( $batch_size ) );
				return (bool) as_has_scheduled_action( self::CLEANUP_HOOK, $args, self::GROUP );
			}
			return (bool) wp_next_scheduled( self::CLEANUP_HOOK );
		}
	}
}
