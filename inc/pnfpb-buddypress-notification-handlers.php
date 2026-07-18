<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'pnfpb_buddypress_notifications_async_enabled' ) ) {
	function pnfpb_buddypress_notifications_async_enabled() {
		return !( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) && get_option( 'pnfpb_ic_fcm_async_notifications' ) === '1';
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_activity' ) ) {
	function PNFPB_icforum_push_notifications_activity( $plugin, $activity_content = null, $user_id = null, $activity_id = null ) {
		$plugin->PNFPB_flush_pending_topic_sync();

		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_all_activities_notification_obj->PNFPB_all_activities_notification( $activity_content, $user_id, $activity_id );
			return;
		}

		$arguments = array(
			'activity_content' => $activity_content,
			'user_id' => $user_id,
			'activity_id' => $activity_id,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_trigger_activity_push_notification_action', $arguments );
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_group_activity' ) ) {
	function PNFPB_icforum_push_notifications_group_activity( $plugin, $activity_content = null, $user_id = null, $group_id = null, $activity_id = null, $sendschedule = 'no' ) {
		$plugin->PNFPB_flush_pending_topic_sync();

		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_group_activities_notification_obj->PNFPB_group_activities_notification( $activity_content, $user_id, $group_id, $activity_id, $sendschedule );
			return;
		}

		$arguments = array(
			'activity_content' => $activity_content,
			'user_id' => $user_id,
			'group_id' => $group_id,
			'activity_id' => $activity_id,
			'sendschedule' => $sendschedule,
		);
		as_schedule_single_action( time() + 5, 'PNFPB_group_activity_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_private_messages' ) ) {
	function PNFPB_icforum_push_notifications_private_messages( $plugin, $raw_args = array() ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_private_message_notification_obj->PNFPB_private_message_notification( $raw_args );
			return;
		}

		$arguments = array(
			'raw_args' => $raw_args,
		);
		as_schedule_single_action( time() + 10, 'PNFPB_private_message_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_new_member' ) ) {
	function PNFPB_icforum_push_notifications_new_member( $plugin, $pnfpb_user_id, $pnfpb_key, $pnfpb_user ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_new_member_joined_notification_obj->PNFPB_new_member_joined_notification( $pnfpb_user_id, $pnfpb_key, $pnfpb_user );
			return;
		}

		$arguments = array(
			'pnfpb_user_id' => $pnfpb_user_id,
			'pnfpb_key' => $pnfpb_key,
			'user' => $pnfpb_user,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_new_member_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_friendship_request' ) ) {
	function PNFPB_icforum_push_notifications_friendship_request( $plugin, $friendship_id, $initiator_id, $friend_id ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_friendship_request_notification_obj->PNFPB_friendship_request_notification( $friendship_id, $initiator_id, $friend_id );
			return;
		}

		$arguments = array(
			'friendship_id' => $friendship_id,
			'initiator_id' => $initiator_id,
			'friend_id' => $friend_id,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_friendship_request_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_friendship_accepted' ) ) {
	function PNFPB_icforum_push_notifications_friendship_accepted( $plugin, $friendship_id, $initiator_id, $friend_id ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_friendship_accept_notification_obj->PNFPB_friendship_accept_notification( $friendship_id, $initiator_id, $friend_id );
			return;
		}

		$arguments = array(
			'friendship_id' => $friendship_id,
			'initiator_id' => $initiator_id,
			'friend_id' => $friend_id,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_friendship_accept_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_bp_follower' ) ) {
	function PNFPB_icforum_push_notifications_bp_follower( $plugin, $pnfpb_buddypress_follow_array ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_bp_follower_notification_obj->PNFPB_buddypress_follow_notification( $pnfpb_buddypress_follow_array );
			return;
		}

		$arguments = array(
			'pnfpb_buddypress_follow_array' => $pnfpb_buddypress_follow_array,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_bp_follower_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_avatar_change' ) ) {
	function PNFPB_icforum_push_notifications_avatar_change( $plugin, $pnfpb_user_id = 0 ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_avatar_change_notification_obj->PNFPB_avatar_change_notification( $pnfpb_user_id );
			return;
		}

		$arguments = array(
			'pnfpb_user_id' => $pnfpb_user_id,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_avatar_change_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_cover_image_change' ) ) {
	function PNFPB_icforum_push_notifications_cover_image_change( $plugin, $pnfpb_item_id, $pnfpb_cover_url ) {
		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_cover_image_change_notification_obj->PNFPB_cover_image_change_notification( $pnfpb_item_id, $pnfpb_cover_url );
			return;
		}

		$arguments = array(
			'pnfpb_item_id' => $pnfpb_item_id,
			'pnfpb_cover_url' => $pnfpb_cover_url,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_cover_image_change_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_post_comment_web' ) ) {
	function PNFPB_icforum_push_notifications_post_comment_web( $plugin, $comment_ID = null, $comment_approved = null, $commentdata = null ) {
		$plugin->PNFPB_flush_pending_topic_sync();

		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_post_comments_notification_obj->PNFPB_post_comments_notification( $comment_ID, $comment_approved, $commentdata );
			return;
		}

		$arguments = array(
			'comment_ID' => $comment_ID,
			'comment_approved' => $comment_approved,
			'commentdata' => $commentdata,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_post_comments_notification_cron_hook', $arguments );
		spawn_cron();
	}
}

if ( ! function_exists( 'PNFPB_icforum_push_notifications_comment_web' ) ) {
	function PNFPB_icforum_push_notifications_comment_web( $plugin, $comment_id = null, $params = null, $activity = null, $sendschedule = 'no' ) {
		$plugin->PNFPB_flush_pending_topic_sync();

		if ( ! pnfpb_buddypress_notifications_async_enabled() ) {
			$plugin->pnfpb_activities_comments_notification_obj->PNFPB_activities_comments_notification( $comment_id, $params, $activity, $sendschedule );
			return;
		}

		$arguments = array(
			'comment_id' => $comment_id,
			'params' => $params,
			'activity' => $activity,
			'sendschedule' => $sendschedule,
		);
		as_schedule_single_action( time() + 2, 'PNFPB_activities_comments_notification_cron_hook', $arguments );
		spawn_cron();
	}
}