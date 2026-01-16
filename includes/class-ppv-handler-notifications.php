<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Handler Notifications
 * One-time notifications for handler events (linking, etc.)
 * Version: 1.0
 */
class PPV_Handler_Notifications {

    /**
     * Initialize hooks
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'ensure_db_table'], 5);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_footer', [__CLASS__, 'render_notification_modal']);
    }

    /**
     * Ensure notifications table exists
     */
    public static function ensure_db_table() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ppv_handler_notifications';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            data JSON DEFAULT NULL,
            seen TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            seen_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_store_seen (store_id, seen),
            INDEX idx_type (notification_type)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        ppv_log("✅ [PPV_Handler_Notifications] Created notifications table");
    }

    /**
     * Register REST routes
     */
    public static function register_rest_routes() {
        // Get pending notifications
        register_rest_route('ppv/v1', '/handler/notifications', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_notifications'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Mark notification as seen
        register_rest_route('ppv/v1', '/handler/notifications/(?P<id>\d+)/seen', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_mark_seen'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /**
     * Get pending notifications for current store
     */
    public static function rest_get_notifications($request) {
        global $wpdb;

        $store_id = self::get_current_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'notifications' => []], 200);
        }

        $notifications = $wpdb->get_results($wpdb->prepare("
            SELECT id, notification_type, title, message, data, created_at
            FROM {$wpdb->prefix}ppv_handler_notifications
            WHERE store_id = %d AND seen = 0
            ORDER BY created_at DESC
            LIMIT 5
        ", $store_id));

        return new WP_REST_Response([
            'success' => true,
            'notifications' => $notifications ?: []
        ], 200);
    }

    /**
     * Mark notification as seen
     */
    public static function rest_mark_seen($request) {
        global $wpdb;

        $notification_id = intval($request['id']);
        $store_id = self::get_current_store_id();

        if (!$store_id || !$notification_id) {
            return new WP_REST_Response(['success' => false], 400);
        }

        $wpdb->update(
            "{$wpdb->prefix}ppv_handler_notifications",
            [
                'seen' => 1,
                'seen_at' => current_time('mysql')
            ],
            [
                'id' => $notification_id,
                'store_id' => $store_id
            ],
            ['%d', '%s'],
            ['%d', '%d']
        );

        return new WP_REST_Response(['success' => true], 200);
    }

    /**
     * Create a handler linking notification
     * Called when handlers are linked/unlinked
     */
    public static function create_link_notification($store_id, $linked_to_store_id, $is_main = false) {
        global $wpdb;

        // Get store language
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country, name, company_name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // Get the other party's info
        $other_store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $linked_to_store_id
        ));

        if (!$store || !$other_store) {
            return false;
        }

        $lang = strtoupper($store->country ?? 'DE');
        $other_name = $other_store->name ?: $other_store->company_name ?: $other_store->email;

        // Get translated title and message
        $translations = self::get_link_translations($lang);

        if ($is_main) {
            // This is the main handler - someone was linked TO them
            $title = $translations['main_title'];
            $message = sprintf($translations['main_message'], $other_name);
        } else {
            // This is the linked handler - they were linked to the main
            $title = $translations['linked_title'];
            $message = sprintf($translations['linked_message'], $other_name);
        }

        // Insert notification
        $result = $wpdb->insert(
            "{$wpdb->prefix}ppv_handler_notifications",
            [
                'store_id' => $store_id,
                'notification_type' => 'handler_linked',
                'title' => $title,
                'message' => $message,
                'data' => wp_json_encode([
                    'linked_store_id' => $linked_to_store_id,
                    'other_name' => $other_name,
                    'is_main' => $is_main
                ]),
                'seen' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        if ($result) {
            ppv_log("✅ [PPV_Handler_Notifications] Created link notification for store #{$store_id}");
        }

        return $result !== false;
    }

    /**
     * Create unlink notification
     */
    public static function create_unlink_notification($store_id, $was_linked_to_store_id) {
        global $wpdb;

        // Get store language
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // Get the other party's info
        $other_store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $was_linked_to_store_id
        ));

        if (!$store || !$other_store) {
            return false;
        }

        $lang = strtoupper($store->country ?? 'DE');
        $other_name = $other_store->name ?: $other_store->company_name ?: $other_store->email;

        $translations = self::get_unlink_translations($lang);
        $title = $translations['title'];
        $message = sprintf($translations['message'], $other_name);

        // Insert notification
        $result = $wpdb->insert(
            "{$wpdb->prefix}ppv_handler_notifications",
            [
                'store_id' => $store_id,
                'notification_type' => 'handler_unlinked',
                'title' => $title,
                'message' => $message,
                'data' => wp_json_encode([
                    'was_linked_to' => $was_linked_to_store_id,
                    'other_name' => $other_name
                ]),
                'seen' => 0,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );

        return $result !== false;
    }

    /**
     * Get translations for link notifications
     */
    private static function get_link_translations($lang) {
        $translations = [
            'DE' => [
                'main_title' => 'Neuer Handler verbunden',
                'main_message' => 'Der Handler "%s" wurde mit deinem Konto verbunden. Er kann jetzt dein Dashboard und deine Statistiken sehen.',
                'linked_title' => 'Konto verbunden',
                'linked_message' => 'Dein Konto wurde mit "%s" verbunden. Du siehst jetzt das Dashboard und die Statistiken dieses Kontos.'
            ],
            'HU' => [
                'main_title' => 'Új händler csatlakozott',
                'main_message' => '"%s" händler csatlakozott a fiókodhoz. Mostantól látja a dashboardodat és statisztikáidat.',
                'linked_title' => 'Fiók összekapcsolva',
                'linked_message' => 'A fiókod össze lett kapcsolva ezzel: "%s". Mostantól az ő dashboardját és statisztikáit látod.'
            ],
            'RO' => [
                'main_title' => 'Handler nou conectat',
                'main_message' => 'Handler-ul "%s" a fost conectat la contul tău. Acum poate vedea dashboard-ul și statisticile tale.',
                'linked_title' => 'Cont conectat',
                'linked_message' => 'Contul tău a fost conectat cu "%s". Acum vezi dashboard-ul și statisticile acestui cont.'
            ]
        ];

        return $translations[$lang] ?? $translations['DE'];
    }

    /**
     * Get translations for unlink notifications
     */
    private static function get_unlink_translations($lang) {
        $translations = [
            'DE' => [
                'title' => 'Konto getrennt',
                'message' => 'Die Verbindung zu "%s" wurde getrennt. Du siehst jetzt wieder dein eigenes Dashboard.'
            ],
            'HU' => [
                'title' => 'Fiók leválasztva',
                'message' => 'A kapcsolat "%s" felhasználóval megszűnt. Mostantól újra a saját dashboardodat látod.'
            ],
            'RO' => [
                'title' => 'Cont deconectat',
                'message' => 'Conexiunea cu "%s" a fost întreruptă. Acum vezi din nou propriul tău dashboard.'
            ]
        ];

        return $translations[$lang] ?? $translations['DE'];
    }

    /**
     * Render notification modal HTML in footer
     */
    public static function render_notification_modal() {
        // Only render on handler pages
        if (!self::is_handler_page()) {
            return;
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) {
            return;
        }
        ?>
        <!-- Handler Notification Modal -->
        <div id="ppv-handler-notification-modal" class="ppv-notification-modal" style="display:none;">
            <div class="ppv-notification-overlay"></div>
            <div class="ppv-notification-content">
                <div class="ppv-notification-icon">
                    <i class="ri-link"></i>
                </div>
                <h3 id="ppv-notification-title"></h3>
                <p id="ppv-notification-message"></p>
                <button type="button" id="ppv-notification-close" class="ppv-btn-primary">
                    <i class="ri-check-line"></i> OK
                </button>
            </div>
        </div>
        <style>
            .ppv-notification-modal {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                z-index: 99999;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .ppv-notification-overlay {
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(4px);
            }
            .ppv-notification-content {
                position: relative;
                background: linear-gradient(145deg, #1e293b 0%, #0f172a 100%);
                border: 1px solid rgba(56, 189, 248, 0.2);
                border-radius: 16px;
                padding: 30px;
                max-width: 400px;
                text-align: center;
                box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
                animation: ppvModalIn 0.3s ease-out;
            }
            @keyframes ppvModalIn {
                from { opacity: 0; transform: scale(0.9) translateY(-20px); }
                to { opacity: 1; transform: scale(1) translateY(0); }
            }
            .ppv-notification-icon {
                width: 64px;
                height: 64px;
                background: linear-gradient(135deg, #38bdf8, #818cf8);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 20px;
            }
            .ppv-notification-icon i {
                font-size: 28px;
                color: white;
            }
            .ppv-notification-content h3 {
                color: #f1f5f9;
                font-size: 18px;
                font-weight: 600;
                margin: 0 0 12px;
            }
            .ppv-notification-content p {
                color: #94a3b8;
                font-size: 14px;
                line-height: 1.6;
                margin: 0 0 24px;
            }
            .ppv-notification-content .ppv-btn-primary {
                background: linear-gradient(135deg, #38bdf8, #818cf8);
                border: none;
                color: white;
                padding: 12px 32px;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            .ppv-notification-content .ppv-btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(56, 189, 248, 0.3);
            }
        </style>
        <script>
        (function() {
            const modal = document.getElementById('ppv-handler-notification-modal');
            const titleEl = document.getElementById('ppv-notification-title');
            const messageEl = document.getElementById('ppv-notification-message');
            const closeBtn = document.getElementById('ppv-notification-close');

            if (!modal) return;

            let currentNotifications = [];
            let currentIndex = 0;

            // Check for notifications on page load
            async function checkNotifications() {
                try {
                    const res = await fetch('/wp-json/ppv/v1/handler/notifications', {
                        headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                    });
                    const data = await res.json();

                    if (data.success && data.notifications && data.notifications.length > 0) {
                        currentNotifications = data.notifications;
                        showNotification(0);
                    }
                } catch (err) {
                    console.error('[PPV] Notification check error:', err);
                }
            }

            function showNotification(index) {
                if (index >= currentNotifications.length) {
                    modal.style.display = 'none';
                    return;
                }

                const notif = currentNotifications[index];
                titleEl.textContent = notif.title;
                messageEl.textContent = notif.message;
                modal.style.display = 'flex';
                currentIndex = index;
            }

            async function markSeenAndNext() {
                const notif = currentNotifications[currentIndex];
                if (notif) {
                    try {
                        await fetch('/wp-json/ppv/v1/handler/notifications/' + notif.id + '/seen', {
                            method: 'POST',
                            headers: { 'X-WP-Nonce': '<?php echo wp_create_nonce('wp_rest'); ?>' }
                        });
                    } catch (err) {
                        console.error('[PPV] Mark seen error:', err);
                    }
                }

                // Show next or close
                showNotification(currentIndex + 1);
            }

            closeBtn?.addEventListener('click', markSeenAndNext);

            // Close on overlay click
            modal.querySelector('.ppv-notification-overlay')?.addEventListener('click', markSeenAndNext);

            // Check after small delay to ensure page is loaded
            setTimeout(checkNotifications, 1000);
        })();
        </script>
        <?php
    }

    /**
     * Check if current page is a handler page
     */
    private static function is_handler_page() {
        global $post;

        // Check for handler shortcodes
        $handler_shortcodes = ['ppv_qr', 'ppv_rewards', 'ppv_stats', 'ppv_onboarding', 'ppv_profile_form'];

        if (isset($post->post_content)) {
            foreach ($handler_shortcodes as $shortcode) {
                if (has_shortcode($post->post_content, $shortcode)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Get current store ID
     */
    private static function get_current_store_id() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        return 0;
    }
}

PPV_Handler_Notifications::hooks();
