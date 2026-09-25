<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}
if (!class_exists("WP_List_Table")) {
    require_once ABSPATH . "wp-admin/includes/class-wp-list-table.php";
}

// phpcs:ignoreFile WordPress.DB.DirectDatabaseQuery

if (!class_exists("PNFPB_ICFM_Device_tokens_List")) {
    class PNFPB_ICFM_Device_tokens_List extends WP_List_Table
    {
        private $table_data;

        /** Class constructor */
        public function __construct()
        {
            parent::__construct([
                "singular" => __(
                    "Devicetoken",
                    "push-notification-for-post-and-buddypress"
                ), //singular name of the listed records
                "plural" => __(
                    "Devicetokens",
                    "push-notification-for-post-and-buddypress"
                ), //plural name of the listed records
                "ajax" => false, //does this table support ajax?
            ]);
        }

        /**
         * Retrieve Device tokens data from the database
         *
         * @param int $per_page
         * @param int $page_number
         *
         * @return mixed
         */
        public static function get_devicetokens(
            $per_page = 20,
            $page_number = 1,
            $search = ""
        ) {
            global $wpdb;

            if (!empty($search) && is_numeric($search)) {
                $sql = "SELECT * FROM {$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web WHERE userid = {$search} OR device_id LIKE '%{$search}%' OR subscription_option LIKE '%{$search}%'";
            } else {
                if (!empty($search) && !is_numeric($search)) {
                    $sql = "SELECT * FROM {$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web WHERE device_id LIKE '%{$search}%' OR subscription_option LIKE '%{$search}%'";
                } else {
                    $sql = "SELECT * FROM {$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web";
                }
            }

            if (!empty($_REQUEST["orderby"])) {
                $sql .= " ORDER BY " . esc_sql($_REQUEST["orderby"]);
                $sql .= !empty($_REQUEST["order"])
                    ? " " . esc_sql($_REQUEST["order"])
                    : " ASC";
            } else {
                $sql .= " ORDER BY id";
                $sql .= " DESC";				
			}

            if ($per_page > 0) {
                $sql .= " LIMIT $per_page";
                $sql .= " OFFSET " . ($page_number - 1) * $per_page;
            }

            $result = $wpdb->get_results($sql, "ARRAY_A");

            return $result;
        }

        /**
         * Delete a customer record.
         *
         * @param int $id device token ID
         */
        public static function delete_devicetoken($id)
        {
            global $wpdb;

            $wpdb->delete(
                "{$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web",
                ["id" => $id],
                ["%d"]
            );
        }

        /**
         * Returns the count of records in the database.
         *
         * @return null|string
         */
        public static function record_count()
        {
            global $wpdb;

            $sql = "SELECT COUNT(*) FROM {$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web";

            return $wpdb->get_var($sql);
        }

        public static function get_trash_tokens( $per_page = 20, $page_number = 1 ) {
            global $wpdb;
            $table = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
            $offset = max( 0, ( absint( $page_number ) - 1 ) * absint( $per_page ) );
            return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i ORDER BY removed_at DESC LIMIT %d OFFSET %d", $table, absint( $per_page ), $offset ), ARRAY_A );
        }

        public static function trash_count() {
            global $wpdb;
            $table = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
            return absint( $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" ) );
        }

        /** Text displayed when no device token data is available */
        public function no_items()
        {
            esc_html_e(
                "No registered device tokens avaliable.",
                "push-notification-for-post-and-buddypress"
            );
        }

        /**
         * Render a column when no column specific method exist.
         *
         * @param array $item
         * @param string $column_name
         *
         * @return mixed
         */
        public function column_default($item, $column_name)
        {
            switch ($column_name) {
                case "id":
                case "device_id":
                case "userid":
                    return $item[$column_name];
                default:
                    return print_r($item, true); //Show the whole array for troubleshooting purposes
            }
        }

        /**
         * Render the bulk edit checkbox
         *
         * @param array $item
         *
         * @return string
         */
        function column_cb($item)
        {
            return sprintf(
                '<input type="checkbox" name="bulk-delete[]" value="%s" />',
                $item["id"]
            );
        }

        /**
         * Render subscription_option as compact visual icon badges.
         * Uses a static map — O(1) memory init per request, O(14) per displayed row.
         */
        function column_subscription_option( $item ) {
            $code = isset( $item['subscription_option'] ) ? (string) $item['subscription_option'] : '';
            if ( $code === '' ) {
                return '<span class="pnfpb-sub-opt-none">—</span>';
            }

            // Position 8 (index 8) = Unsubscribed from all
            if ( strlen( $code ) > 8 && $code[8] === '1' ) {
                return '<span class="pnfpb-sub-badges">'
                    . '<span class="pnfpb-sub-badge pnfpb-sub--unsub" title="' . esc_attr__( 'Unsubscribed from all notifications', 'push-notification-for-post-and-buddypress' ) . '">'
                    . '<span class="dashicons dashicons-dismiss" aria-hidden="true"></span> '
                    . esc_html__( 'Unsubscribed', 'push-notification-for-post-and-buddypress' )
                    . '</span>'
                    . '<span class="pnfpb-sub-raw">' . esc_html( $code ) . '</span>'
                    . '</span>';
            }

            // Position 0 (index 0) = Subscribed to all notifications
            if ( strlen( $code ) > 0 && $code[0] === '1' ) {
                return '<span class="pnfpb-sub-badges">'
                    . '<span class="pnfpb-sub-badge pnfpb-sub--all" title="' . esc_attr__( 'Subscribed to all notifications', 'push-notification-for-post-and-buddypress' ) . '">'
                    . '<span class="dashicons dashicons-bell" aria-hidden="true"></span> '
                    . esc_html__( 'All', 'push-notification-for-post-and-buddypress' )
                    . '</span>'
                    . '<span class="pnfpb-sub-raw">' . esc_html( $code ) . '</span>'
                    . '</span>';
            }

            // Individual subscription bits (positions 1-7, 9-13)
            static $badge_map = null;
            if ( null === $badge_map ) {
                $badge_map = [
                    1  => [ 'Posts',        'dashicons-admin-post',     'posts'      ],
                    2  => [ 'Comments',     'dashicons-admin-comments',  'comments'   ],
                    3  => [ 'My Comments',  'dashicons-format-chat',     'mycomments' ],
                    4  => [ 'Members',      'dashicons-groups',          'members'    ],
                    5  => [ 'Messages',     'dashicons-email-alt',       'messages'   ],
                    6  => [ 'Friend Req.',  'dashicons-plus-alt',        'friendreq'  ],
                    7  => [ 'Friend Acc.',  'dashicons-yes-alt',         'friendacc'  ],
                    9  => [ 'Avatar',       'dashicons-admin-users',     'avatar'     ],
                    10 => [ 'Cover',        'dashicons-format-image',    'cover'      ],
                    11 => [ 'BP Activity',  'dashicons-networking',      'bpactivity' ],
                    12 => [ 'Grp Invite',   'dashicons-admin-site-alt3', 'grpinvite'  ],
                    13 => [ 'Grp Update',   'dashicons-update',          'grpupdate'  ],
                ];
            }

            $html = '<span class="pnfpb-sub-badges">';
            $any  = false;
            $len  = strlen( $code );
            foreach ( $badge_map as $pos => $info ) {
                if ( $len > $pos && $code[ $pos ] === '1' ) {
                    $html .= '<span class="pnfpb-sub-badge pnfpb-sub--' . $info[2] . '" title="' . esc_attr( $info[0] ) . '">'
                           . '<span class="dashicons ' . $info[1] . '" aria-hidden="true"></span>'
                           . '</span>';
                    $any = true;
                }
            }
            if ( ! $any ) {
                $html .= '<span class="pnfpb-sub-opt-none">—</span>';
            }
            $html .= '<span class="pnfpb-sub-raw">' . esc_html( $code ) . '</span>';
            $html .= '</span>';

            return $html;
        }

        /**
         * Method for name column
         *
         * @param array $item an array of DB data
         *
         * @return string
         */
        function column_name($item)
        {
            $delete_nonce = wp_create_nonce("pnfpb_delete_devicetoken");

            $title = "<strong>" . $item["device_id"] . "</strong>";

            $actions = [
                "delete" => sprintf(
                    '<a href="?page=%s&action=%s&devicetoken=%s&_wpdeletenonce=%s">Delete</a>',
                    esc_attr($_REQUEST["page"]),
                    "delete",
                    absint($item["id"]),
                    $delete_nonce
                ),
            ];

            return $title . $this->row_actions($actions);
        }

        /**
         *  Associative array of columns
         *
         * @return array
         */
        function get_columns()
        {
            $columns = [
                "cb" => '<input type="checkbox" />',
                "id" => __("Id", "push-notification-for-post-and-buddypress"),
                "device_id" => __(
                    "Device Token",
                    "push-notification-for-post-and-buddypress"
                ),
                "userid" => __(
                    "User ID",
                    "push-notification-for-post-and-buddypress"
                ),
                "subscription_option" => __(
                    "Subscriptions",
                    "push-notification-for-post-and-buddypress"
                ),
            ];

            return $columns;
        }

        /**
         * Columns to make sortable.
         *
         * @return array
         */
        public function get_sortable_columns()
        {
            $sortable_columns = [
                "id" => ["id", true],
                "device_id" => ["device_id", true],
                "userid" => ["userid", true],
                "subscription_option" => ["subscription_option", true],
            ];

            return $sortable_columns;
        }

        /**
         * Returns an associative array containing the bulk action
         *
         * @return array
         */
        public function get_bulk_actions()
        {
            $delete_nonce = wp_create_nonce("pnfpb_delete_devicetoken");

            $actions = [
                "bulk-delete" => "Delete",
            ];

            return $actions;
        }

        /**
         * Handles data query and filter, sorting, and pagination.
         */
        public function prepare_items($search = "")
        {
			if (isset($_REQUEST["_wpnonce"]) && !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST["_wpnonce"])), "pnfpb_icfcm_device_tokens_list")) {
				die("wnonce failure");
			} else {			
				//data
				if (isset($_REQUEST["s"])) {
					$this->table_data = $this->get_table_data(
						sanitize_text_field(wp_unslash($_REQUEST["s"]))
					);
				} else {
					$this->table_data = $this->get_table_data($search);
				}

				$this->_column_headers = $this->get_column_info();

				/** Process bulk action */
				$this->process_bulk_action();

				$per_page = $this->get_items_per_page("records_per_page", 20);
				$current_page = $this->get_pagenum();
				$total_items = self::record_count();

				if (isset($_REQUEST["s"])) {
					$this->items = self::get_devicetokens(
						$per_page,
						$current_page,
						sanitize_text_field(wp_unslash($_REQUEST["s"]))
					);
					$total_items_search = self::get_devicetokens(
						0,
						$current_page,
						sanitize_text_field(wp_unslash($_REQUEST["s"]))
					);
					$this->set_pagination_args([
						"total_items" => count($total_items_search), //WE have to calculate the total number of items
						"per_page" => $per_page, //WE have to determine how many items to show on a page
					]);
				} else {
					$this->items = self::get_devicetokens(
						$per_page,
						$current_page,
						""
					);
					$this->set_pagination_args([
						"total_items" => $total_items, //WE have to calculate the total number of items
						"per_page" => $per_page, //WE have to determine how many items to show on a page
					]);
				}
			}
        }

        public function render_token_trash() {
            if ( ! current_user_can( 'manage_options' ) ) { return; }
            $items = self::get_trash_tokens( 20, 1 );
            echo '<div id="pnfpb-token-trash" class="pnfpb-token-trash"><h2>' . esc_html__( 'Token Trash', 'push-notification-for-post-and-buddypress' ) . '</h2>';
            echo '<p>' . esc_html__( 'Invalid tokens are retained here until restored or permanently deleted.', 'push-notification-for-post-and-buddypress' ) . '</p>';
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Token', 'push-notification-for-post-and-buddypress' ) . '</th><th>' . esc_html__( 'User', 'push-notification-for-post-and-buddypress' ) . '</th><th>' . esc_html__( 'Reason', 'push-notification-for-post-and-buddypress' ) . '</th><th>' . esc_html__( 'Removed', 'push-notification-for-post-and-buddypress' ) . '</th><th>' . esc_html__( 'Actions', 'push-notification-for-post-and-buddypress' ) . '</th></tr></thead><tbody>';
            foreach ( $items as $item ) {
                $token = (string) $item['device_id'];
                $masked = strlen( $token ) > 12 ? substr( $token, 0, 6 ) . '…' . substr( $token, -6 ) : '••••••••';
                echo '<tr><td><code>' . esc_html( $masked ) . '</code></td><td>' . absint( $item['userid'] ) . '</td><td>' . esc_html( $item['removal_reason'] ) . '</td><td>' . esc_html( $item['removed_at'] ) . '</td><td><button type="button" class="button pnfpb-trash-action" data-id="' . absint( $item['trash_id'] ) . '" data-operation="restore">' . esc_html__( 'Restore', 'push-notification-for-post-and-buddypress' ) . '</button> <button type="button" class="button-link-delete pnfpb-trash-action" data-id="' . absint( $item['trash_id'] ) . '" data-operation="delete">' . esc_html__( 'Permanently delete', 'push-notification-for-post-and-buddypress' ) . '</button></td></tr>';
            }
            if ( empty( $items ) ) { echo '<tr><td colspan="5">' . esc_html__( 'Trash is empty.', 'push-notification-for-post-and-buddypress' ) . '</td></tr>'; }
            echo '</tbody></table><script>(function($){$(document).on("click",".pnfpb-trash-action",function(){var b=$(this),o=b.data("operation");if(o==="delete"&&!window.confirm(' . wp_json_encode( __( 'Permanently delete this token? This cannot be undone.', 'push-notification-for-post-and-buddypress' ) ) . ')){return;}b.prop("disabled",true);$.post(' . wp_json_encode( admin_url( 'admin-ajax.php' ) ) . ',{action:"pnfpb_token_cleanup_trash_action",nonce:' . wp_json_encode( wp_create_nonce( 'pnfpb_cleanup_nonce' ) ) . ',trash_id:b.data("id"),operation:o}).done(function(){window.location.reload();}).fail(function(){window.alert(' . wp_json_encode( __( 'Token action failed.', 'push-notification-for-post-and-buddypress' ) ) . ');b.prop("disabled",false);});});})(jQuery);</script></div>';
        }

        public function pnfpb_url_scheme_start()
        {
            add_filter("set_url_scheme", [$this, "pnfpb_url_scheme"], 10, 3);
        }

        public function pnfpb_url_scheme_stop()
        {
            remove_filter("set_url_scheme", [$this, "pnfpb_url_scheme"], 10);
        }

        public function pnfpb_url_scheme(
            string $url,
            string $scheme,
            $orig_scheme
        ) {
            if (
                !empty($url) &&
                mb_strpos($url, "?page=pnfpb_icfm_device_tokens_list") !==
                    false &&
                isset($_REQUEST["s"])
            ) {
                $search_nonce = wp_create_nonce(
                    "pnfpb_search_device_tokens_list_pushnotification"
                );
                $url = add_query_arg("s", urlencode($_REQUEST["s"]), $url);
                $url = add_query_arg(
                    urlencode($search_nonce),
                    $url
                );
            }

            return $url;
        }

        public function process_bulk_action()
        {
            //Detect when a bulk action is being triggered...
            if ("delete" === $this->current_action()) {
                // In our file that handles the request, verify the nonce.
                $nonce = esc_attr(
                    sanitize_text_field(wp_unslash($_REQUEST["_wpnonce"]))
                );

                if (!wp_verify_nonce($nonce, "pnfpb_icfcm_device_tokens_list")) {
                    die("wnonce failure");
                } else {
                    $devicetokenid = sanitize_text_field(
                        wp_unslash($_GET["devicetoken"])
                    );

                    $devicetokenid = esc_html($devicetokenid);

                    self::delete_devicetoken(absint($devicetokenid));
                }
            }

            // If the delete bulk action is triggered
            if (
                (isset($_REQUEST["action"]) &&
                    $_REQUEST["action"] == "bulk-delete") ||
                (isset($_REQUEST["action2"]) &&
                    $_REQUEST["action2"] == "bulk-delete")
            ) {
                $nonce = esc_attr(
                    sanitize_text_field(wp_unslash($_REQUEST["_wpnonce"]))
                );

                if (!wp_verify_nonce($nonce, "pnfpb_icfcm_device_tokens_list")) {
                    die("wnonce failure");
                } else {				
					$delete_ids = esc_sql($_REQUEST["bulk-delete"]);

					// loop over the array of record IDs and delete them
					foreach ($delete_ids as $id) {
						self::delete_devicetoken($id);
					}
				}
            }
        }
    }
} else {
    exit();
}

/**
 * Trash Tokens List Table Class
 */
if (!class_exists("PNFPB_ICFM_Device_Trash_Tokens_List")) {
    class PNFPB_ICFM_Device_Trash_Tokens_List extends WP_List_Table
    {
        private $table_data;

        /** Class constructor */
        public function __construct()
        {
            parent::__construct([
                "singular" => __(
                    "Trash Token",
                    "push-notification-for-post-and-buddypress"
                ),
                "plural" => __(
                    "Trash Tokens",
                    "push-notification-for-post-and-buddypress"
                ),
                "ajax" => false,
            ]);
        }

        /**
         * Retrieve Trash tokens from database
         *
         * @param int $per_page
         * @param int $page_number
         * @param string $search
         *
         * @return mixed
         */
        public static function get_trash_tokens(
            $per_page = 20,
            $page_number = 1,
            $search = ""
        ) {
            global $wpdb;
            $table = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
            $offset = max( 0, ( absint( $page_number ) - 1 ) * absint( $per_page ) );
            
            $sql = "SELECT * FROM {$table}";
            
            if ( ! empty( $search ) && is_numeric( $search ) ) {
                $sql .= " WHERE userid = " . absint( $search );
            } elseif ( ! empty( $search ) ) {
                $sql .= $wpdb->prepare( " WHERE device_id LIKE %s OR removal_reason LIKE %s", 
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%'
                );
            }
            
            if ( ! empty( $_REQUEST["orderby"] ) ) {
                $sql .= " ORDER BY " . esc_sql( $_REQUEST["orderby"] );
                $sql .= ! empty( $_REQUEST["order"] ) ? " " . esc_sql( $_REQUEST["order"] ) : " ASC";
            } else {
                $sql .= " ORDER BY removed_at DESC";
            }
            
            if ( $per_page > 0 ) {
                $sql .= " LIMIT " . absint( $per_page );
                $sql .= " OFFSET " . absint( $offset );
            }
            
            return $wpdb->get_results( $sql, ARRAY_A );
        }

        /**
         * Get trash tokens count
         *
         * @param string $search
         * @return int
         */
        public static function get_trash_count( $search = "" ) {
            global $wpdb;
            $table = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
            
            $sql = "SELECT COUNT(*) FROM {$table}";
            
            if ( ! empty( $search ) && is_numeric( $search ) ) {
                $sql .= " WHERE userid = " . absint( $search );
            } elseif ( ! empty( $search ) ) {
                $sql .= $wpdb->prepare( " WHERE device_id LIKE %s OR removal_reason LIKE %s", 
                    '%' . $wpdb->esc_like( $search ) . '%',
                    '%' . $wpdb->esc_like( $search ) . '%'
                );
            }
            
            return absint( $wpdb->get_var( $sql ) );
        }

        /**
         * Delete or restore a trash token
         *
         * @param int $trash_id
         * @param string $operation restore or delete
         */
        public static function process_trash_action( $trash_id, $operation = 'delete' ) {
            global $wpdb;
            $table = $wpdb->prefix . 'pnfpb_ic_subscribed_deviceids_web_trash';
            
            $trash_id = absint( $trash_id );
            $item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE trash_id = %d", $trash_id ), ARRAY_A );
            
            if ( ! $item ) {
                return new WP_Error( 'not_found', __( 'Trash item not found', 'push-notification-for-post-and-buddypress' ) );
            }
            
            if ( $operation === 'restore' ) {
                // Restore to main tokens table
                $wpdb->insert(
                    "{$wpdb->prefix}pnfpb_ic_subscribed_deviceids_web",
                    [
                        'device_id' => $item['device_id'],
                        'userid' => $item['userid'],
                        'subscription_option' => isset( $item['subscription_option'] ) ? $item['subscription_option'] : '1000000000000',
                    ],
                    [ '%s', '%d', '%s' ]
                );
            }
            
            // Delete from trash
            $wpdb->delete( $table, [ 'trash_id' => $trash_id ], [ '%d' ] );
            
            return true;
        }

        /** Text displayed when no trash data is available */
        public function no_items()
        {
            esc_html_e(
                "Trash is empty.",
                "push-notification-for-post-and-buddypress"
            );
        }

        /**
         * Render a column when no column specific method exist.
         *
         * @param array $item
         * @param string $column_name
         *
         * @return mixed
         */
        public function column_default( $item, $column_name )
        {
            if ( isset( $item[ $column_name ] ) ) {
                $value = $item[ $column_name ];
                // If it's an array, convert to string representation
                if ( is_array( $value ) ) {
                    return '<code>' . esc_html( json_encode( $value ) ) . '</code>';
                }
                return esc_html( $value );
            }
            return '—';
        }

        /**
         * Render the bulk edit checkbox
         *
         * @param array $item
         *
         * @return string
         */
        public function column_cb( $item )
        {
            return sprintf(
                '<input type="checkbox" name="bulk-delete[]" value="%s" />',
                $item["trash_id"]
            );
        }

        /**
         * Render trash ID column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_trash_id( $item )
        {
            return esc_html( $item['trash_id'] );
        }

        /**
         * Render device token column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_device_id( $item )
        {
            $token = (string) $item['device_id'];
            //$masked = strlen( $token ) > 12 ? substr( $token, 0, 6 ) . '…' . substr( $token, -6 ) : '••••••••';
            return '<code>' . esc_html( $token ) . '</code>';
        }

        /**
         * Render user ID column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_userid( $item )
        {
            return esc_html( $item['userid'] );
        }

        /**
         * Render removal reason column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_removal_reason( $item )
        {
            $reason = isset( $item['removal_reason'] ) ? $item['removal_reason'] : '—';
            return esc_html( $reason );
        }

        /**
         * Render removed at column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_removed_at( $item )
        {
            $date = isset( $item['removed_at'] ) ? $item['removed_at'] : '—';
            return esc_html( $date );
        }

        /**
         * Render actions column
         *
         * @param array $item
         *
         * @return string
         */
        public function column_actions( $item )
        {
            $restore_nonce = wp_create_nonce( "pnfpb_restore_trash_token_" . $item['trash_id'] );
            $delete_nonce = wp_create_nonce( "pnfpb_delete_trash_token_" . $item['trash_id'] );
            
            $actions = [
                "restore" => sprintf(
                    '<a href="?page=%s&tab=trash&action=%s&trash_id=%s&_wpnonce=%s">Restore</a>',
                    esc_attr( $_REQUEST["page"] ),
                    "restore-trash",
                    absint( $item["trash_id"] ),
                    $restore_nonce
                ),
                "delete" => sprintf(
                    '<a href="?page=%s&tab=trash&action=%s&trash_id=%s&_wpnonce=%s" class="delete">Delete Permanently</a>',
                    esc_attr( $_REQUEST["page"] ),
                    "delete-trash",
                    absint( $item["trash_id"] ),
                    $delete_nonce
                ),
            ];

            return $this->row_actions( $actions );
        }

        /**
         * Associative array of columns
         *
         * @return array
         */
        public function get_columns()
        {
            $columns = [
                "cb" => '<input type="checkbox" />',
                "trash_id" => __( "ID", "push-notification-for-post-and-buddypress" ),
                "device_id" => __( "Device Token", "push-notification-for-post-and-buddypress" ),
                "userid" => __( "User ID", "push-notification-for-post-and-buddypress" ),
                "removal_reason" => __( "Removal Reason", "push-notification-for-post-and-buddypress" ),
                "removed_at" => __( "Removed At", "push-notification-for-post-and-buddypress" ),
                "actions" => __( "Actions", "push-notification-for-post-and-buddypress" ),
            ];

            return $columns;
        }

        /**
         * Columns to make sortable
         *
         * @return array
         */
        public function get_sortable_columns()
        {
            $sortable_columns = [
                "trash_id" => [ "trash_id", false ],
                "device_id" => [ "device_id", true ],
                "userid" => [ "userid", true ],
                "removed_at" => [ "removed_at", false ],
            ];

            return $sortable_columns;
        }

        /**
         * Returns an associative array containing the bulk actions
         *
         * @return array
         */
        public function get_bulk_actions()
        {
            $actions = [
                "bulk-restore" => __( "Restore", "push-notification-for-post-and-buddypress" ),
                "bulk-delete" => __( "Delete Permanently", "push-notification-for-post-and-buddypress" ),
            ];

            return $actions;
        }

        /**
         * Handles data query and filter, sorting, and pagination.
         */
        public function prepare_items( $search = "" )
        {
            if ( isset( $_REQUEST["_wpnonce"] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST["_wpnonce"] ) ), "pnfpb_icfcm_trash_tokens_list" ) ) {
                die( "nonce failure" );
            }

            $this->_column_headers = $this->get_column_info();

            /** Process bulk action */
            $this->process_bulk_action();

            $per_page = $this->get_items_per_page( "trash_records_per_page", 20 );
            $current_page = $this->get_pagenum();

            if ( isset( $_REQUEST["s"] ) ) {
                $search = sanitize_text_field( wp_unslash( $_REQUEST["s"] ) );
                $total_items = self::get_trash_count( $search );
                $this->items = self::get_trash_tokens( $per_page, $current_page, $search );
            } else {
                $total_items = self::get_trash_count();
                $this->items = self::get_trash_tokens( $per_page, $current_page, "" );
            }

            $this->set_pagination_args( [
                "total_items" => $total_items,
                "per_page" => $per_page,
            ] );
        }

        /**
         * Process trash bulk actions
         */
        public function process_bulk_action()
        {
            // Single item restore
            if ( "restore-trash" === $this->current_action() ) {
                $nonce = esc_attr( sanitize_text_field( wp_unslash( $_REQUEST["_wpnonce"] ) ) );
                $expected_nonce = "pnfpb_restore_trash_token_" . sanitize_text_field( wp_unslash( $_REQUEST["trash_id"] ) );
                
                if ( ! wp_verify_nonce( $nonce, $expected_nonce ) ) {
                    die( "nonce failure" );
                }
                
                $trash_id = absint( sanitize_text_field( wp_unslash( $_REQUEST["trash_id"] ) ) );
                self::process_trash_action( $trash_id, 'restore' );
                wp_safe_remote_post( add_query_arg( [ 'page' => 'pnfpb_icfm_device_tokens_list', 'tab' => 'trash' ], admin_url( 'admin.php' ) ) );
            }

            // Single item delete
            if ( "delete-trash" === $this->current_action() ) {
                $nonce = esc_attr( sanitize_text_field( wp_unslash( $_REQUEST["_wpnonce"] ) ) );
                $expected_nonce = "pnfpb_delete_trash_token_" . sanitize_text_field( wp_unslash( $_REQUEST["trash_id"] ) );
                
                if ( ! wp_verify_nonce( $nonce, $expected_nonce ) ) {
                    die( "nonce failure" );
                }
                
                $trash_id = absint( sanitize_text_field( wp_unslash( $_REQUEST["trash_id"] ) ) );
                self::process_trash_action( $trash_id, 'delete' );
            }

            // Bulk restore
            if ( ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] === "bulk-restore" ) ||
                 ( isset( $_REQUEST["action2"] ) && $_REQUEST["action2"] === "bulk-restore" ) ) {
                
                $nonce = esc_attr( sanitize_text_field( wp_unslash( $_REQUEST["_wpnonce"] ) ) );
                if ( ! wp_verify_nonce( $nonce, "pnfpb_icfcm_trash_tokens_list" ) ) {
                    die( "nonce failure" );
                }

                if ( isset( $_REQUEST["bulk-delete"] ) && is_array( $_REQUEST["bulk-delete"] ) ) {
                    foreach ( $_REQUEST["bulk-delete"] as $trash_id ) {
                        $trash_id = absint( $trash_id );
                        self::process_trash_action( $trash_id, 'restore' );
                    }
                }
            }

            // Bulk delete
            if ( ( isset( $_REQUEST["action"] ) && $_REQUEST["action"] === "bulk-delete" ) ||
                 ( isset( $_REQUEST["action2"] ) && $_REQUEST["action2"] === "bulk-delete" ) ) {
                
                $nonce = esc_attr( sanitize_text_field( wp_unslash( $_REQUEST["_wpnonce"] ) ) );
                if ( ! wp_verify_nonce( $nonce, "pnfpb_icfcm_trash_tokens_list" ) ) {
                    die( "nonce failure" );
                }

                if ( isset( $_REQUEST["bulk-delete"] ) && is_array( $_REQUEST["bulk-delete"] ) ) {
                    foreach ( $_REQUEST["bulk-delete"] as $trash_id ) {
                        $trash_id = absint( $trash_id );
                        self::process_trash_action( $trash_id, 'delete' );
                    }
                }
            }
        }
    }
} else {
    exit();
}
?>
