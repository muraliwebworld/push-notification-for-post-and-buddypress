<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pnfpb_sync_recurring_action' ) ) {
	function pnfpb_sync_recurring_action( $hook, $group, $new_time, $interval ) {
		if ( wp_next_scheduled( $hook ) ) {
			$timestamp = wp_next_scheduled( $hook );
			wp_unschedule_event( $timestamp, $hook );
		}

		as_unschedule_all_actions( $hook, [], $group );
		as_schedule_recurring_action( $new_time, $interval, $hook, [], $group, true );
	}
}

if ( ! function_exists( 'pnfpb_unschedule_recurring_action' ) ) {
	function pnfpb_unschedule_recurring_action( $hook, $group ) {
		if ( wp_next_scheduled( $hook ) ) {
			$timestamp = wp_next_scheduled( $hook );
			wp_unschedule_event( $timestamp, $hook );
		}

		as_unschedule_all_actions( $hook, [], $group );
	}
}

if ( ! function_exists( 'pnfpb_post_schedule_cleanup_options' ) ) {
	function pnfpb_post_schedule_cleanup_options() {
		delete_option( 'pnfpb_ic_fcm_new_post_id' );
		delete_option( 'pnfpb_ic_fcm_new_post_title' );
		delete_option( 'pnfpb_ic_fcm_new_post_content' );
		delete_option( 'pnfpb_ic_fcm_new_post_link' );
		delete_option( 'pnfpb_ic_fcm_new_post_type' );
		delete_option( 'pnfpb_ic_fcm_new_post_author' );
	}
}

if ( ! function_exists( 'pnfpb_buddypress_activities_cleanup_options' ) ) {
	function pnfpb_buddypress_activities_cleanup_options() {
		delete_option( 'pnfpb_ic_fcm_new_buddypressactivities_content' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressactivities_userid' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressactivities_link' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressactivities_image' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressgroup_link' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressgroup_id' );
		delete_option( 'pnfpb_ic_fcm_new_buddypressgroup_userid' );
	}
}

if ( ! function_exists( 'pnfpb_buddypress_comments_cleanup_options' ) ) {
	function pnfpb_buddypress_comments_cleanup_options() {
		delete_option( 'pnfpb_ic_fcm_new_buddypresscomments_content' );
		delete_option( 'pnfpb_ic_fcm_new_buddypresscomments_link' );
		delete_option( 'pnfpb_ic_fcm_new_buddypresscomments_postuserid' );
		delete_option( 'pnfpb_ic_fcm_new_buddypresscomments_activityuserid' );
		delete_option( 'pnfpb_ic_fcm_new_buddypresscomments_authoractivityuserid' );
	}
}

if ( ! function_exists( 'PNFPB_setup_admin_menu' ) ) {
	function PNFPB_setup_admin_menu( $plugin ) {
		add_menu_page(
			esc_html__( 'PNFPB Push Notification', 'push-notification-for-post-and-buddypress' ),
			esc_html__( 'PNFPB Push Notification', 'push-notification-for-post-and-buddypress' ),
			'manage_options',
			'pnfpb-push-notification-configuration-slug',
			[ $plugin, 'PNFPB_push_notification_configuration_page' ],
			'dashicons-bell',
			98
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Configuration', 'push-notification-for-post-and-buddypress' ),
			__( 'Configuration', 'push-notification-for-post-and-buddypress' ),
			'manage_options',
			'pnfpb-push-notification-configuration-slug',
			[ $plugin, 'PNFPB_push_notification_configuration_page' ],
			1
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Options', 'push-notification-for-post-and-buddypress' ),
			__( 'Options', 'push-notification-for-post-and-buddypress' ),
			'manage_options',
			'pnfpb-icfcm-slug',
			[ $plugin, 'PNFPB_icfcm_admin_page' ],
			2
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'AI assistant', 'push-notification-for-post-and-buddypress' ),
			__( 'AI assistant', 'push-notification-for-post-and-buddypress' ),
			'manage_options',
			'pnfpb_icfm_ai_assistant_settings',
			[ $plugin, 'PNFPB_ai_assistant_admin_page' ],
			3
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Send Push', 'push-notification-for-post-and-buddypress' ),
			'Send Notification',
			'manage_options',
			'pnfpb_icfmtest_notification',
			[ $plugin, $plugin->pre_name . 'icfcm_test_notification' ],
			3
		);

		$hook_delivery_notifications_list = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Delivered & read report', 'push-notification-for-post-and-buddypress' ),
			'Delivered/read report',
			'manage_options',
			'pnfpb_icfm_delivery_notifications_list',
			[ $plugin, $plugin->pre_name . 'icfm_delivery_notifications_list' ],
			4
		);
		add_action( "load-{$hook_delivery_notifications_list}", [ $plugin, $plugin->pre_name . 'push_notifications_delivery_list_screen_option' ] );

		$hook_browser_delivery_notifications_list = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Browser based delivery reports ', 'push-notification-for-post-and-buddypress' ),
			'Browser based Delivery reports',
			'manage_options',
			'pnfpb_icfm_browser_delivery_notifications_list',
			[ $plugin, $plugin->pre_name . 'icfm_browser_delivery_notifications_list' ],
			5
		);
		add_action( "load-{$hook_browser_delivery_notifications_list}", [ $plugin, $plugin->pre_name . 'push_notifications_browser_delivery_list_screen_option' ] );

		$hook_analytics = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Analytics Chart', 'push-notification-for-post-and-buddypress' ),
			'Analytics Chart',
			'manage_options',
			'pnfpb_icfm_analytics_notifications',
			[ $plugin, $plugin->pre_name . 'icfm_analytics_notifications' ],
			6
		);
		add_action( "load-{$hook_analytics}", [ $plugin, $plugin->pre_name . 'push_notifications_analytics_screen_option' ] );

		$hook_notifications_list = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Notifications from admin', 'push-notification-for-post-and-buddypress' ),
			'Notifications from admin',
			'manage_options',
			'pnfpb_icfm_onetime_notifications_list',
			[ $plugin, $plugin->pre_name . 'icfm_onetime_notifications_list' ],
			6
		);
		add_action( "load-{$hook_notifications_list}", [ $plugin, $plugin->pre_name . 'push_notifications_list_screen_option' ] );

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'PWA settings', 'push-notification-for-post-and-buddypress' ),
			'PWA settings',
			'manage_options',
			'pnfpb_icfm_pwa_app_settings',
			[ $plugin, $plugin->pre_name . 'icfcm_pwa_app_settings' ],
			7
		);

		$hook_device_tokens = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Tokens list', 'push-notification-for-post-and-buddypress' ),
			'Tokens list',
			'manage_options',
			'pnfpb_icfm_device_tokens_list',
			[ $plugin, $plugin->pre_name . 'icfcm_device_tokens_list' ],
			8
		);
		add_action( "load-{$hook_device_tokens}", [ $plugin, $plugin->pre_name . 'screen_option' ] );

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Frontend settings', 'push-notification-for-post-and-buddypress' ),
			'Frontend settings',
			'manage_options',
			'pnfpb_icfm_frontend_settings',
			[ $plugin, $plugin->pre_name . 'icfcm_frontend_settings' ],
			9
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Shortcodes', 'push-notification-for-post-and-buddypress' ),
			'Shortcodes',
			'manage_options',
			'pnfpb_icfm_shortcode_settings',
			[ $plugin, $plugin->pre_name . 'icfcm_shortcode_settings' ],
			10
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Buttons', 'push-notification-for-post-and-buddypress' ),
			'Buttons',
			'manage_options',
			'pnfpb_icfm_button_settings',
			[ $plugin, $plugin->pre_name . 'icfcm_button_settings' ],
			11
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Integrate Mobile App', 'push-notification-for-post-and-buddypress' ),
			'Integrate Mobile App',
			'manage_options',
			'pnfpb_icfm_integrate_app',
			[ $plugin, $plugin->pre_name . 'icfcm_integrate_app' ],
			12
		);

		add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'NGNIX', 'push-notification-for-post-and-buddypress' ),
			'NGNIX',
			'manage_options',
			'pnfpb_icfm_settings_for_ngnix_server',
			[ $plugin, $plugin->pre_name . 'icfcm_settings_for_ngnix_server' ],
			13
		);

		$hook_pnfpb_action_scheduler = add_submenu_page(
			'pnfpb-push-notification-configuration-slug',
			__( 'Action scheduler', 'push-notification-for-post-and-buddypress' ),
			'Action scheduler',
			'manage_options',
			'pnfpb_icfm_action_scheduler',
			[ $plugin, $plugin->pre_name . 'icfcm_action_scheduler' ],
			14
		);
		add_action( "load-{$hook_pnfpb_action_scheduler}", [ $plugin, $plugin->pre_name . 'action_scheduler_screen_option' ] );
	}
}

if ( ! function_exists( 'pnfpb_ic_fcm_post_timeschedule_callback' ) ) {
	function pnfpb_ic_fcm_post_timeschedule_callback( $posted_options ) {
		if ( ! isset( $_POST['submit'] ) || 'Save changes' !== $_POST['submit'] ) {
			return $posted_options;
		}

		// Verify nonce for security
		if ( ! isset( $_POST['pnfpb_icfcm_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pnfpb_icfcm_group_nonce'] ) ), 'pnfpb_icfcm_group' ) ) {
			return $posted_options;
		}

		$custposttypes = get_post_types(
			[
				'public'  => true,
				'_builtin' => false,
			],
			'names',
			'and'
		);

		if (
			( get_option( 'pnfpb_ic_fcm_post_schedule_enable' ) && 1 == get_option( 'pnfpb_ic_fcm_post_schedule_enable' ) ) ||
			( get_option( 'pnfpb_ic_fcm_post_schedule_background_enable' ) &&
				1 == get_option( 'pnfpb_ic_fcm_post_schedule_background_enable' ) &&
				in_array( get_option( 'pnfpb_ic_fcm_post_timeschedule_enable' ), [ 'weekly', 'twicedaily', 'daily', 'hourly', 'seconds' ], true ) &&
				( 'seconds' !== get_option( 'pnfpb_ic_fcm_post_timeschedule_enable' ) || get_option( 'pnfpb_ic_fcm_post_timeschedule_seconds' ) > 59 ) )
		) {
			$timeseconds = 3600;
			if ( 'weekly' === $posted_options ) {
				$timeseconds = 604800;
			} elseif ( 'twicedaily' === $posted_options ) {
				$timeseconds = 43200;
			} elseif ( 'daily' === $posted_options ) {
				$timeseconds = 86400;
			} elseif ( 'seconds' === $posted_options ) {
				$timeseconds = get_option( 'pnfpb_ic_fcm_post_timeschedule_seconds' );
			}

			$new_time = strtotime(
				gmdate(
					'Y-m-d H:i:s',
					strtotime( '+' . $timeseconds . ' seconds', strtotime( 'now' ) )
				)
			);

			if ( 1 == get_option( 'pnfpb_ic_fcm_default_post_schedule_enable' ) ) {
				pnfpb_sync_recurring_action( 'PNFPB_cron_post_hook', 'pnfpb_post', $new_time, $timeseconds );
				pnfpb_post_schedule_cleanup_options();
			} else {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_post_hook', '' );
			}

			foreach ( $custposttypes as $post_type ) {
				if ( 1 == get_option( 'pnfpb_ic_fcm_' . $post_type . '_post_schedule_enable' ) ) {
					pnfpb_sync_recurring_action( 'PNFPB_cron_' . $post_type . '_hook', 'pnfpb_' . $post_type, $new_time, $timeseconds );
				} elseif ( as_has_scheduled_action( 'PNFPB_cron_' . $post_type . '_hook' ) ) {
					pnfpb_unschedule_recurring_action( 'PNFPB_cron_' . $post_type . '_hook', '' );
					pnfpb_post_schedule_cleanup_options();
				}
			}
			return $posted_options;
		}

		if ( as_has_scheduled_action( 'PNFPB_cron_post_hook' ) ) {
			pnfpb_unschedule_recurring_action( 'PNFPB_cron_post_hook', '' );
			pnfpb_post_schedule_cleanup_options();
		}

		foreach ( $custposttypes as $post_type ) {
			if ( as_has_scheduled_action( 'PNFPB_cron_' . $post_type . '_hook' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_' . $post_type . '_hook', '' );
				pnfpb_post_schedule_cleanup_options();
			}
		}

		return $posted_options;
	}
}

if ( ! function_exists( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_callback' ) ) {
	function pnfpb_ic_fcm_buddypressactivities_timeschedule_callback( $posted_options ) {
		if ( ! isset( $_POST['submit'] ) || 'Save changes' !== $_POST['submit'] ) {
			return $posted_options;
		}

		// Verify nonce for security
		if ( ! isset( $_POST['pnfpb_icfcm_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pnfpb_icfcm_group_nonce'] ) ), 'pnfpb_icfcm_group' ) ) {
			return $posted_options;
		}

		$activities_enabled = 1 == get_option( 'pnfpb_ic_fcm_buddypressactivities_schedule_enable' );
		$activities_background_enabled = 1 == get_option( 'pnfpb_ic_fcm_buddypressactivities_schedule_background_enable' );
		$activities_schedule = get_option( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_enable' );
		$seconds_allowed = 'seconds' === $activities_schedule && get_option( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_seconds' ) > 59;
		$schedule_allowed = $activities_enabled || ( $activities_background_enabled && in_array( $activities_schedule, [ 'weekly', 'twicedaily', 'daily', 'hourly', 'seconds' ], true ) && $seconds_allowed );

		if ( $schedule_allowed ) {
			$timeseconds = 3600;
			if ( 'weekly' === $posted_options ) {
				$timeseconds = 604800;
			} elseif ( 'twicedaily' === $posted_options ) {
				$timeseconds = 43200;
			} elseif ( 'daily' === $posted_options ) {
				$timeseconds = 86400;
			} elseif ( 'seconds' === $posted_options ) {
				$timeseconds = get_option( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_seconds' );
			}

			$new_time = strtotime(
				gmdate(
					'Y-m-d H:i:s',
					strtotime( '+' . $timeseconds . ' seconds', strtotime( 'now' ) )
				)
			);

			if ( false === as_has_scheduled_action( 'PNFPB_cron_buddypressactivities_hook' ) && 1 == get_option( 'pnfpb_ic_fcm_buddypress_enable' ) ) {
				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypressactivities_hook', 'pnfpb_buddypressactivities', $new_time, $timeseconds );
				if ( as_has_scheduled_action( 'PNFPB_cron_buddypressgroupactivities_hook' ) ) {
					pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', 'pnfpb_buddypressgroupactivities' );
					pnfpb_buddypress_activities_cleanup_options();
				}
			} elseif ( 1 == get_option( 'pnfpb_ic_fcm_buddypress_enable' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', '' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressactivities_hook', '' );
				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypressactivities_hook', 'pnfpb_buddypressactivities', $new_time, $timeseconds );
				pnfpb_buddypress_activities_cleanup_options();
			}

			if ( false === as_has_scheduled_action( 'PNFPB_cron_buddypressgroupactivities_hook' ) && 2 == get_option( 'pnfpb_ic_fcm_buddypress_enable' ) ) {
				$timeseconds = 3600;
				if ( 'weekly' === $posted_options ) {
					$timeseconds = 604800;
				} elseif ( 'twicedaily' === $posted_options ) {
					$timeseconds = 43200;
				} elseif ( 'daily' === $posted_options ) {
					$timeseconds = 86400;
				} elseif ( 'seconds' === $posted_options ) {
					$timeseconds = get_option( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_seconds' );
				}

				$new_time = strtotime(
					gmdate(
						'Y-m-d H:i:s',
						strtotime( '+' . $timeseconds . ' seconds', strtotime( 'now' ) )
					)
				);

				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', '' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressactivities_hook', '' );
				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', 'pnfpb_buddypressgroupactivities', $new_time, $timeseconds );
				pnfpb_buddypress_activities_cleanup_options();
			} elseif ( 2 == get_option( 'pnfpb_ic_fcm_buddypress_enable' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', '' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressactivities_hook', '' );
				$timeseconds = 3600;
				if ( 'weekly' === $posted_options ) {
					$timeseconds = 604800;
				} elseif ( 'twicedaily' === $posted_options ) {
					$timeseconds = 43200;
				} elseif ( 'daily' === $posted_options ) {
					$timeseconds = 86400;
				} elseif ( 'seconds' === $posted_options ) {
					$timeseconds = get_option( 'pnfpb_ic_fcm_buddypressactivities_timeschedule_seconds' );
				}

				$new_time = strtotime(
					gmdate(
						'Y-m-d H:i:s',
						strtotime( '+' . $timeseconds . ' seconds', strtotime( 'now' ) )
					)
				);

				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', 'pnfpb_buddypressgroupactivities', $new_time, $timeseconds );
				pnfpb_buddypress_activities_cleanup_options();
			}
		} else {
			if ( as_has_scheduled_action( 'PNFPB_cron_buddypressactivities_hook' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressactivities_hook', 'pnfpb_buddypressactivities' );
				pnfpb_buddypress_activities_cleanup_options();
			}

			if ( as_has_scheduled_action( 'PNFPB_cron_buddypressgroupactivities_hook' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypressgroupactivities_hook', 'pnfpb_buddypressgroupactivities' );
				pnfpb_buddypress_activities_cleanup_options();
			}
		}

		return $posted_options;
	}
}

if ( ! function_exists( 'pnfpb_ic_fcm_buddypresscomments_timeschedule_callback' ) ) {
	function pnfpb_ic_fcm_buddypresscomments_timeschedule_callback( $posted_options ) {
		if ( ! isset( $_POST['submit'] ) || 'Save changes' !== $_POST['submit'] ) {
			return $posted_options;
		}

		// Verify nonce for security
		if ( ! isset( $_POST['pnfpb_icfcm_group_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pnfpb_icfcm_group_nonce'] ) ), 'pnfpb_icfcm_group' ) ) {
			return $posted_options;
		}

		$comments_enabled = 1 == get_option( 'pnfpb_ic_fcm_buddypresscomments_schedule_enable' );
		$comments_background_enabled = 1 == get_option( 'pnfpb_ic_fcm_buddypresscomments_schedule_background_enable' );
		$comments_schedule = get_option( 'pnfpb_ic_fcm_buddypresscomments_timeschedule_enable' );
		$seconds_allowed = 'seconds' === $comments_schedule && get_option( 'pnfpb_ic_fcm_buddypresscomments_timeschedule_seconds' ) > 59;
		$schedule_allowed = $comments_enabled || ( $comments_background_enabled && in_array( $comments_schedule, [ 'weekly', 'twicedaily', 'daily', 'hourly', 'seconds' ], true ) && $seconds_allowed );

		if ( $schedule_allowed ) {
			$timeseconds = 3600;
			if ( 'weekly' === $posted_options ) {
				$timeseconds = 604800;
			} elseif ( 'twicedaily' === $posted_options ) {
				$timeseconds = 43200;
			} elseif ( 'daily' === $posted_options ) {
				$timeseconds = 86400;
			} elseif ( 'seconds' === $posted_options ) {
				$timeseconds = get_option( 'pnfpb_ic_fcm_buddypresscomments_timeschedule_seconds' );
			}

			$new_time = strtotime(
				gmdate(
					'Y-m-d H:i:s',
					strtotime( '+' . $timeseconds . ' seconds', strtotime( 'now' ) )
				)
			);

			if ( false === as_has_scheduled_action( 'PNFPB_cron_comments_post_hook' ) ) {
				pnfpb_sync_recurring_action( 'PNFPB_cron_comments_post_hook', 'pnfpb_postcomments', $new_time, $timeseconds );
			} else {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_comments_post_hook', '' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypresscomments_hook', '' );
				pnfpb_buddypress_comments_cleanup_options();
				pnfpb_sync_recurring_action( 'PNFPB_cron_comments_post_hook', 'pnfpb_postcomments', $new_time, $timeseconds );
			}

			if ( false === as_has_scheduled_action( 'PNFPB_cron_buddypresscomments_hook' ) ) {
				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypresscomments_hook', 'pnfpb_buddypresscomments', $new_time, $timeseconds );
			} else {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_comments_post_hook', '' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypresscomments_hook', '' );
				pnfpb_buddypress_comments_cleanup_options();
				pnfpb_sync_recurring_action( 'PNFPB_cron_buddypresscomments_hook', 'pnfpb_buddypresscomments', $new_time, $timeseconds );
			}
		} else {
			if ( as_has_scheduled_action( 'PNFPB_cron_buddypresscomments_hook' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_comments_post_hook', 'pnfpb_postcomments' );
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_buddypresscomments_hook', 'pnfpb_buddypresscomments' );
				pnfpb_buddypress_comments_cleanup_options();
			}

			if ( as_has_scheduled_action( 'PNFPB_cron_comments_post_hook' ) ) {
				pnfpb_unschedule_recurring_action( 'PNFPB_cron_comments_post_hook', 'pnfpb_postcomments' );
				pnfpb_buddypress_comments_cleanup_options();
			}
		}

		return $posted_options;
	}
}

if ( ! function_exists( 'PNFPB_subscribe_to_group_button' ) ) {
	function PNFPB_subscribe_to_group_button( $grp_btn = [] ) {
		global $groups_template;

		if ( isset( $GLOBALS['groups_template']->group ) ) {
			$group = $GLOBALS['groups_template']->group;
		} else {
			$group = groups_get_current_group();
		}

		$bpuserid = 0;

		if ( is_user_logged_in() && 2 == get_option( 'pnfpb_ic_fcm_buddypress_enable' ) ) {
			$bpuserid = get_current_user_id();
			$cookievalue = '';

			if ( isset( $_COOKIE[ 'pnfpb_group_push_notification_' . $group->id ] ) ) {
				$cookievalue = sanitize_text_field( wp_unslash( $_COOKIE[ 'pnfpb_group_push_notification_' . $group->id ] ) );
			}

			$unsubscribe_button_text = esc_html__( 'Unsubscribe push notifications', 'push-notification-for-post-and-buddypress' );
			if ( get_option( 'pnfpb_ic_fcm_unsubscribe_button_text' ) !== false && '' !== get_option( 'pnfpb_ic_fcm_unsubscribe_button_text' ) ) {
				$unsubscribe_button_text = get_option( 'pnfpb_ic_fcm_unsubscribe_button_text' );
			}

			$subscribe_button_text = esc_html__( 'Subscribe to push notifications', 'push-notification-for-post-and-buddypress' );
			if ( get_option( 'pnfpb_ic_fcm_subscribe_button_text' ) !== false && '' !== get_option( 'pnfpb_ic_fcm_subscribe_button_text' ) ) {
				$subscribe_button_text = get_option( 'pnfpb_ic_fcm_subscribe_button_text' );
			}

			$subscribe_button_icon_text = '';
			$unsubscribe_button_icon_text = '';

			if ( get_option( 'pnfpb_subscribe_group_push_notification_icon' ) !== false && '' !== get_option( 'pnfpb_subscribe_group_push_notification_icon' ) ) {
				$subscribe_button_icon_text = get_option( 'pnfpb_subscribe_group_push_notification_icon' );
			}

			if ( get_option( 'pnfpb_unsubscribe_group_push_notification_icon' ) !== false && '' !== get_option( 'pnfpb_unsubscribe_group_push_notification_icon' ) ) {
				$unsubscribe_button_icon_text = get_option( 'pnfpb_unsubscribe_group_push_notification_icon' );
			}

			$link_subscribe_text = $subscribe_button_text;
			$link_unsubscribe_text = $unsubscribe_button_text;

			if ( '' !== $subscribe_button_icon_text && '1' === get_option( 'pnfpb_subscribe_group_push_notification_icon_enable' ) ) {
				$link_subscribe_text = '<img src="' . esc_url( $subscribe_button_icon_text ) . '" alt="' . $subscribe_button_text . '"/>';
			}

			if ( '' !== $unsubscribe_button_icon_text && '1' === get_option( 'pnfpb_subscribe_group_push_notification_icon_enable' ) ) {
				$link_unsubscribe_text = '<img src="' . esc_url( $unsubscribe_button_icon_text ) . '" alt="' . $unsubscribe_button_text . '"/>';
			}

			if ( '' === $cookievalue && groups_is_user_member( $bpuserid, $group->id ) ) {
				$button = [
					'id' => 'subscribe_notification_group',
					'component' => 'groups',
					'must_be_logged_in' => true,
					'block_self' => false,
					'wrapper_class' => 'subscribegroupbutton',
					'wrapper_id' => 'subscribegroupbutton-' . $group->id,
					'link_text' => $link_subscribe_text,
					'link_href' => '',
					'link_class' => 'subscribe-notification-group subscribegroupbutton-' . $group->id,
					'button_element' => 'button',
					'parent_attr' => [
						'id' => '',
						'class' => 'bp-generic-meta groups-meta action subscribegroupbutton ',
					],
					'button_attr' => [
						'data-group-id' => $group->id,
						'data-user-id' => $bpuserid,
						'data-title' => $subscribe_button_text,
						'data-title-displayed' => $subscribe_button_text,
					],
				];
			} else {
				$button = [
					'id' => 'subscribe_notification_group',
					'component' => 'groups',
					'must_be_logged_in' => true,
					'block_self' => false,
					'wrapper_class' => 'subscribegroupbutton subscribe-display-off',
					'wrapper_id' => 'subscribegroupbutton-' . $group->id,
					'link_text' => $link_subscribe_text,
					'link_href' => '',
					'link_class' => 'subscribe-notification-group subscribe-display-off subscribegroupbutton-' . $group->id,
					'button_element' => 'button',
					'parent_attr' => [
						'id' => '',
						'class' => 'bp-generic-meta groups-meta action subscribegroupbutton subscribe-display-off',
					],
					'button_attr' => [
						'data-group-id' => $group->id,
						'data-user-id' => $bpuserid,
						'data-title' => $subscribe_button_text,
						'data-title-displayed' => $subscribe_button_text,
					],
				];
			}

			bp_button( $button );

			if ( '' !== $cookievalue && groups_is_user_member( $bpuserid, $group->id ) ) {
				$button = [
					'id' => 'unsubscribe_notification_group',
					'component' => 'groups',
					'must_be_logged_in' => true,
					'block_self' => false,
					'wrapper_class' => 'unsubscribegroupbutton',
					'wrapper_id' => 'unsubscribegroupbutton-' . $group->id,
					'link_text' => $link_unsubscribe_text,
					'link_href' => '',
					'link_class' => 'unsubscribe-notification-group unsubscribegroupbutton-' . $group->id,
					'button_element' => 'button',
					'parent_attr' => [
						'id' => '',
						'class' => 'bp-generic-meta groups-meta action unsubscribegroupbutton',
					],
					'button_attr' => [
						'data-group-id' => $group->id,
						'data-user-id' => $bpuserid,
						'data-title' => $unsubscribe_button_text,
						'data-title-displayed' => $unsubscribe_button_text,
					],
				];
				bp_button( $button );
			} else {
				$button = [
					'id' => 'unsubscribe_notification_group',
					'component' => 'groups',
					'must_be_logged_in' => true,
					'block_self' => false,
					'wrapper_class' => 'unsubscribegroupbutton subscribe-display-off',
					'wrapper_id' => 'unsubscribegroupbutton-' . $group->id,
					'link_text' => $link_unsubscribe_text,
					'link_href' => '',
					'link_class' => 'unsubscribe-notification-group subscribe-display-off unsubscribegroupbutton-' . $group->id,
					'button_element' => 'button',
					'parent_attr' => [
						'id' => '',
						'class' => 'bp-generic-meta groups-meta action unsubscribegroupbutton subscribe-display-off',
					],
					'button_attr' => [
						'data-group-id' => $group->id,
						'data-user-id' => $bpuserid,
						'data-title' => $unsubscribe_button_text,
						'data-title-displayed' => $unsubscribe_button_text,
					],
				];
				bp_button( $button );
			}
		}

		return $grp_btn;
	}
}
