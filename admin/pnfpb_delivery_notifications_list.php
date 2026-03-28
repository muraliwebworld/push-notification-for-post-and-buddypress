<h1 class="pnfpb_ic_push_settings_header">
	<?php echo esc_html(
     	__(
         	"PNFPB - Push Notification reports",
         	"push-notification-for-post-and-buddypress"
     	)
 	); ?>
</h1>
<?php
	$pnfpb_tab_reportdelivery_active = "nav-tab-active";
	require_once( plugin_dir_path( __FILE__ ) . 'push_admin_menu_list.php' );
?>
<div id="pnfpb-notifications-list" class="pnfpb_ic_push_settings_table pnfpb_notifications_list">
	<h2>
		<?php echo esc_html(
	__(
		"Push notifications list and delivery statistics",
		"push-notification-for-post-and-buddypress"
	)
); ?>
	</h2>
	<h4>
		<?php echo esc_html(
	__(		
		"(only for Firebase and web-push notifications in web-browser/mobile-browser/PWA/Android,ios mobile-app)",
		"push-notification-for-post-and-buddypress"
	)
); ?>	
	</h4>
	<h4>
		<?php echo esc_html(
	__(		
		"(Turn ON/OFF delivery and read reports in OPTIONS tab)",
		"push-notification-for-post-and-buddypress"
	)); ?>		
	</h4>	
</div>

<div class="nav-tab-wrapper">
	<a class="nav-tab nav-tab-active" id="pnfpb-Notificationdefault" href="<?php echo esc_url(
	admin_url()."admin.php?page=pnfpb_icfm_delivery_notifications_list&orderby=id&order=desc"); ?>">
		<?php echo esc_html(__("Delivery and read report", "push-notification-for-post-and-buddypress")); ?>
	</a>
	<a class="nav-tab" href="<?php echo esc_url(
	admin_url()."admin.php?page=pnfpb_icfm_browser_delivery_notifications_list&orderby=id&order=desc"); ?>">
		<?php echo esc_html(__("Delivery report with browser details", "push-notification-for-post-and-buddypress")); ?>
	</a>
	<a class="nav-tab" href="<?php echo esc_url(
	admin_url()."admin.php?page=pnfpb_icfm_onetime_notifications_list&orderby=id&order=desc"); ?>">
		<?php echo esc_html(__("Notifications sent from admin", "push-notification-for-post-and-buddypress")); ?>
	</a>
</div>

<div id="pnfpb-deliveryConfirmation" class="pnfpb_notification_list_tabcontent pnfpb_ic_push_settings_table">
	<div class="pnfpb_column_1200">
		<div class="wrap">
			<div class="pnfpb_row">
				<div class="pnfpb_column_400">					
					<h2>
						<?php echo esc_html(
			 			__(
				 			"Delivered and read report for all push notifications sent using Firebase",
				 			"push-notification-for-post-and-buddypress"
			 			)
		 				); ?>
					</h2>
				</div>
			</div>
			<div id="poststuff">
				<div id="post-body" class="metabox-holder">
					<div id="post-body-content">
						<div class="meta-box-sortables ui-sortable">
							<form method="post">
								<?php
								$this->pushnotifications_delivered_obj->prepare_items();
								$this->pushnotifications_delivered_obj->pnfpb_url_scheme_start();
								$this->pushnotifications_delivered_obj->search_box(
									"Search",
									"pnfpb_push_notifications_search"
								);
								$this->pushnotifications_delivered_obj->display();
								wp_nonce_field( 'pnfpb_delivery_report-bulk_delete_items' ); // Action name for the nonce
								wp_nonce_field( 'pnfpb_search_delivery_pushnotification', '_wpnonce' );
								$this->pushnotifications_delivered_obj->pnfpb_url_scheme_stop();
								?>
							</form>
						</div>
					</div>
				</div>
				<br class="clear">
			</div>
		</div>
	</div>
</div>
