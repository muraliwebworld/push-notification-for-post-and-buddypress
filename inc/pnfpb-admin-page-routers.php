<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'PNFPB_icfcm_admin_page' ) ) {
	function PNFPB_icfcm_admin_page() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_ic_push_notification.php';
	}
}

if ( ! function_exists( 'PNFPB_ai_assistant_admin_page' ) ) {
	function PNFPB_ai_assistant_admin_page() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_ai_assistant_settings.php';
	}
}

if ( ! function_exists( 'PNFPB_icfcm_action_scheduler' ) ) {
	function PNFPB_icfcm_action_scheduler() {
		$pnfpb_tab_as_active = 'nav-tab-active';
		?>
		<h1 class="pnfpb_ic_push_settings_header"><?php echo esc_html(
			__(
				'PNFPB - Action scheduler',
				'push-notification-for-post-and-buddypress'
			)
		); ?></h1>
		<?php
		require_once PNFPB_PLUGIN_DIR . 'admin/push_admin_menu_list.php';
		?>
		<div class="pnfpb_column_1200">
			<p>
				<?php echo esc_html(
					__(
						'Action Scheduler library is also used by other plugins, like WPForms and WooCommerce, so you might see tasks that are not related to our plugin in the table below.',
						'push-notification-for-post-and-buddypress'
					)
				); ?>
			</p>
			<?php if ( class_exists( 'ActionScheduler_AdminView' ) ) {
				ActionScheduler_AdminView::instance()->render_admin_ui();
			} ?>
		</div>
		<?php
	}
}

if ( ! function_exists( 'PNFPB_icfcm_button_settings' ) ) {
	function PNFPB_icfcm_button_settings() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_button_customization.php';
	}
}

if ( ! function_exists( 'PNFPB_icfcm_shortcode_settings' ) ) {
	function PNFPB_icfcm_shortcode_settings() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_shortcode_customization.php';
	}
}

if ( ! function_exists( 'PNFPB_icfcm_frontend_settings' ) ) {
	function PNFPB_icfcm_frontend_settings() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_front_end_notification_settings.php';
	}
}

if ( ! function_exists( 'PNFPB_icfcm_pwa_app_settings' ) ) {
	function PNFPB_icfcm_pwa_app_settings() {
		include_once PNFPB_PLUGIN_DIR . 'admin/pnfpb_admin_pwa_app_settings.php';
	}
}