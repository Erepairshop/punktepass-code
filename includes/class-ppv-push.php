<?php
/**
 * PPV Push Notifications
 * Firebase Cloud Messaging (FCM) integration for iOS, Android, and Web Push
 *
 * USE CASE: Händler (active stores) can send weekly promotional messages to their customers
 * LIMIT: 1 push notification per store per week
 *
 * Setup: Add these constants to wp-config.php:
 *   define('PPV_FCM_SERVER_KEY', 'your_firebase_server_key');
 *
 * @package PunktePass
 * @since 2.2
 */

if (!defined('ABSPATH')) exit;

class PPV_Push {

    /** @var string FCM Legacy API endpoint */
    private static $fcm_url = 'https://fcm.googleapis.com/fcm/send';

    /** @var int Weekly push limit per store */
    const WEEKLY_LIMIT = 1;

    /** ============================================================
     * Check if Push Notifications are enabled
     * ============================================================ */
    public static function is_enabled() {
        return defined('PPV_FCM_SERVER_KEY') && !empty(PPV_FCM_SERVER_KEY);
    }

    /** ============================================================
     * Register hooks
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     * Register REST API routes
     * ============================================================ */
    public static function register_routes() {
        // Register push token (for users/customers)
        register_rest_route('punktepass/v1', '/push/register', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_register_token'],
            'permission_callback' => [__CLASS__, 'verify_user_or_pos']
        ]);

        // Unregister push token
        register_rest_route('punktepass/v1', '/push/unregister', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_unregister_token'],
            'permission_callback' => [__CLASS__, 'verify_user_or_pos']
        ]);

        // Send promotional push (store owner only)
        register_rest_route('punktepass/v1', '/push/promotion', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_send_promotion'],
            'permission_callback' => [__CLASS__, 'verify_store_owner']
        ]);

        // Check remaining push quota for store
        register_rest_route('punktepass/v1', '/push/quota', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'api_get_quota'],
            'permission_callback' => [__CLASS__, 'verify_store_owner']
        ]);

        // Test push notification (admin only)
        register_rest_route('punktepass/v1', '/push/test', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_test_push'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        // Admin: Send push (bypass limits)
        register_rest_route('punktepass/v1', '/push/send', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_admin_send'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);
    }

    /** ============================================================
     * Permission: User or POS token
     * ============================================================ */
    public static function verify_user_or_pos($request = null) {
        if (is_user_logged_in()) return true;

        $token = $request ? $request->get_header('Authorization') : null;
        if ($token && strpos($token, 'Bearer ') === 0) {
            $bearer = substr($token, 7);
            if (class_exists('PPV_Permissions')) {
                return PPV_Permissions::verify_user_token($bearer);
            }
        }

        if (class_exists('PPV_API')) {
            return PPV_API::verify_pos_or_user($request);
        }

        return false;
    }

    /** ============================================================
     * Permission: Store Owner (Händler)
     * ============================================================ */
    public static function verify_store_owner($request = null) {
        if (current_user_can('manage_options')) return true;

        // Check session for store owner
        if (!empty($_SESSION['ppv_user_type']) && $_SESSION['ppv_user_type'] === 'store') {
            return true;
        }

        // Check if logged in user owns a store
        if (is_user_logged_in()) {
            global $wpdb;
            $user_id = get_current_user_id();
            $has_store = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d",
                $user_id
            ));
            return $has_store > 0;
        }

        return false;
    }

    /** ============================================================
     * API: Register push token
     * ============================================================ */
    public static function api_register_token($request) {
        $params = $request->get_json_params();

        $device_token = sanitize_text_field($params['token'] ?? '');
        $platform     = sanitize_text_field($params['platform'] ?? 'web');
        $device_name  = sanitize_text_field($params['device_name'] ?? '');
        $user_id      = intval($params['user_id'] ?? 0);
        $language     = sanitize_text_field($params['language'] ?? 'de');

        if (empty($device_token)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Token is required'], 400);
        }

        if (!in_array($platform, ['ios', 'android', 'web'])) {
            $platform = 'web';
        }

        // Get user_id from session if not provided
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }

        if (!$user_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'User ID is required'], 400);
        }

        $result = self::subscribe($user_id, $device_token, $platform, $device_name, $language);

        if ($result) {
            ppv_log("[PPV_Push] Token registered for user {$user_id} ({$platform})");
            return new WP_REST_Response(['success' => true, 'message' => 'Push subscription registered'], 200);
        }

        return new WP_REST_Response(['success' => false, 'message' => 'Failed to register'], 500);
    }

    /** ============================================================
     * API: Unregister push token
     * ============================================================ */
    public static function api_unregister_token($request) {
        $params = $request->get_json_params();
        $device_token = sanitize_text_field($params['token'] ?? '');

        if (empty($device_token)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Token is required'], 400);
        }

        $result = self::unsubscribe($device_token);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'Unsubscribed' : 'Token not found'
        ], $result ? 200 : 404);
    }

    /** ============================================================
     * API: Send promotional push (Händler)
     * WITH WEEKLY LIMIT CHECK
     * ============================================================ */
    public static function api_send_promotion($request) {
        $params = $request->get_json_params();

        $store_id = intval($params['store_id'] ?? $_SESSION['ppv_store_id'] ?? 0);
        $title    = sanitize_text_field($params['title'] ?? '');
        $body     = sanitize_text_field($params['body'] ?? '');

        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Store ID fehlt'], 400);
        }

        if (empty($title) || empty($body)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Titel und Nachricht erforderlich'], 400);
        }

        if (!self::is_enabled()) {
            return new WP_REST_Response(['success' => false, 'message' => 'Push nicht konfiguriert'], 500);
        }

        // Check weekly limit
        $remaining = self::get_weekly_remaining($store_id);
        if ($remaining <= 0) {
            $next_available = self::get_next_available_date($store_id);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Wöchentliches Limit erreicht',
                'next_available' => $next_available
            ], 429);
        }

        // Get store info for logging
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT company_name FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // Send to all store customers
        $result = self::send_to_store_customers($store_id, [
            'title' => $title,
            'body'  => $body,
            'data'  => [
                'type'     => 'promotion',
                'store_id' => $store_id,
                'store'    => $store->company_name ?? ''
            ]
        ]);

        // Log the send
        if ($result['sent'] > 0) {
            self::log_store_push($store_id, $title, $result['sent']);
        }

        return new WP_REST_Response([
            'success' => $result['sent'] > 0,
            'sent'    => $result['sent'],
            'total_customers' => $result['total_users'],
            'remaining_this_week' => $remaining - 1,
            'message' => $result['sent'] > 0
                ? "Push an {$result['sent']} Kunden gesendet!"
                : 'Keine Kunden mit Push-Abo gefunden'
        ], 200);
    }

    /** ============================================================
     * API: Get push quota for store
     * ============================================================ */
    public static function api_get_quota($request) {
        $store_id = intval($request->get_param('store_id') ?? $_SESSION['ppv_store_id'] ?? 0);

        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Store ID fehlt'], 400);
        }

        $remaining = self::get_weekly_remaining($store_id);
        $last_sent = self::get_last_send_date($store_id);
        $next_available = $remaining > 0 ? 'jetzt' : self::get_next_available_date($store_id);
        $customer_count = self::get_customer_subscription_count($store_id);

        return new WP_REST_Response([
            'success' => true,
            'weekly_limit' => self::WEEKLY_LIMIT,
            'remaining' => $remaining,
            'last_sent' => $last_sent,
            'next_available' => $next_available,
            'customer_subscriptions' => $customer_count
        ], 200);
    }

    /** ============================================================
     * API: Test push notification (Admin)
     * ============================================================ */
    public static function api_test_push($request) {
        $params = $request->get_json_params();
        $token = sanitize_text_field($params['token'] ?? '');
        $user_id = intval($params['user_id'] ?? 0);

        if (!self::is_enabled()) {
            return new WP_REST_Response(['success' => false, 'message' => 'FCM nicht konfiguriert'], 500);
        }

        $payload = [
            'title' => 'PunktePass Test',
            'body'  => 'Test-Benachrichtigung erfolgreich!',
            'data'  => ['type' => 'test', 'timestamp' => time()]
        ];

        if ($token) {
            $result = self::send_to_token($token, $payload);
        } elseif ($user_id) {
            $result = self::send_to_user($user_id, $payload);
        } else {
            return new WP_REST_Response(['success' => false, 'message' => 'Token oder User ID erforderlich'], 400);
        }

        return new WP_REST_Response($result, $result['success'] ? 200 : 500);
    }

    /** ============================================================
     * API: Admin send (bypass limits)
     * ============================================================ */
    public static function api_admin_send($request) {
        $params = $request->get_json_params();

        $store_id = intval($params['store_id'] ?? 0);
        $user_ids = $params['user_ids'] ?? [];
        $title    = sanitize_text_field($params['title'] ?? '');
        $body     = sanitize_text_field($params['body'] ?? '');
        $target   = sanitize_text_field($params['target'] ?? 'store');

        if (empty($title) || empty($body)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Titel und Nachricht erforderlich'], 400);
        }

        $payload = [
            'title' => $title,
            'body'  => $body,
            'data'  => ['type' => 'admin_message', 'timestamp' => time()]
        ];

        $results = ['sent' => 0, 'failed' => 0];

        if ($target === 'all') {
            global $wpdb;
            $all_user_ids = $wpdb->get_col("SELECT DISTINCT user_id FROM {$wpdb->prefix}ppv_push_subscriptions WHERE is_active = 1");
            foreach ($all_user_ids as $uid) {
                $r = self::send_to_user($uid, $payload);
                $results['sent'] += $r['sent'] ?? ($r['success'] ? 1 : 0);
            }
        } elseif ($store_id) {
            $r = self::send_to_store_customers($store_id, $payload);
            $results = $r;
        } elseif (!empty($user_ids)) {
            foreach ($user_ids as $uid) {
                $r = self::send_to_user(intval($uid), $payload);
                $results['sent'] += $r['sent'] ?? ($r['success'] ? 1 : 0);
            }
        }

        return new WP_REST_Response([
            'success' => $results['sent'] > 0,
            'sent' => $results['sent']
        ], 200);
    }

    /** ============================================================
     * WEEKLY LIMIT METHODS
     * ============================================================ */

    /**
     * Get remaining pushes for this week
     */
    public static function get_weekly_remaining($store_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_log';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return self::WEEKLY_LIMIT;
        }

        $week_start = date('Y-m-d', strtotime('monday this week'));

        $sent_this_week = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE store_id = %d AND sent_at >= %s",
            $store_id, $week_start
        ));

        return max(0, self::WEEKLY_LIMIT - $sent_this_week);
    }

    /**
     * Get last send date for store
     */
    public static function get_last_send_date($store_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return null;
        }

        return $wpdb->get_var($wpdb->prepare(
            "SELECT sent_at FROM {$table} WHERE store_id = %d ORDER BY sent_at DESC LIMIT 1",
            $store_id
        ));
    }

    /**
     * Get next available date (next Monday)
     */
    public static function get_next_available_date($store_id) {
        return date('d.m.Y', strtotime('monday next week'));
    }

    /**
     * Log store push send
     */
    public static function log_store_push($store_id, $title, $recipients) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_log';

        // Create table if not exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            $wpdb->query("CREATE TABLE {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                title VARCHAR(255) NOT NULL,
                recipients INT DEFAULT 0,
                sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_store (store_id),
                KEY idx_sent (sent_at)
            ) {$wpdb->get_charset_collate()}");
        }

        $wpdb->insert($table, [
            'store_id'   => $store_id,
            'title'      => $title,
            'recipients' => $recipients,
            'sent_at'    => current_time('mysql')
        ], ['%d', '%s', '%d', '%s']);

        ppv_log("[PPV_Push] Store {$store_id} sent promotion: '{$title}' to {$recipients} customers");
    }

    /**
     * Get customer subscription count for store
     */
    public static function get_customer_subscription_count($store_id) {
        global $wpdb;

        // Count users who have points at this store AND have push subscriptions
        return (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT ps.user_id)
            FROM {$wpdb->prefix}ppv_push_subscriptions ps
            INNER JOIN {$wpdb->prefix}ppv_points p ON ps.user_id = p.user_id
            WHERE p.store_id = %d AND ps.is_active = 1
        ", $store_id));
    }

    /** ============================================================
     * SUBSCRIPTION METHODS
     * ============================================================ */

    /**
     * Subscribe user to push notifications
     */
    public static function subscribe($user_id, $device_token, $platform = 'web', $device_name = '', $language = 'de') {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        // Check if token already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$table} WHERE device_token = %s",
            $device_token
        ));

        if ($existing) {
            return $wpdb->update(
                $table,
                [
                    'user_id'     => $user_id,
                    'platform'    => $platform,
                    'device_name' => $device_name,
                    'language'    => $language,
                    'is_active'   => 1,
                    'updated_at'  => current_time('mysql')
                ],
                ['id' => $existing->id]
            );
        }

        return $wpdb->insert($table, [
            'user_id'      => $user_id,
            'device_token' => $device_token,
            'platform'     => $platform,
            'device_name'  => $device_name,
            'language'     => $language,
            'is_active'    => 1,
            'created_at'   => current_time('mysql')
        ]);
    }

    /**
     * Unsubscribe device
     */
    public static function unsubscribe($device_token) {
        global $wpdb;
        return $wpdb->update(
            $wpdb->prefix . 'ppv_push_subscriptions',
            ['is_active' => 0],
            ['device_token' => $device_token]
        );
    }

    /**
     * Get user subscriptions
     */
    public static function get_user_subscriptions($user_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_push_subscriptions WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
    }

    /** ============================================================
     * SEND METHODS
     * ============================================================ */

    /**
     * Send to specific token
     */
    public static function send_to_token($token, $payload) {
        if (!self::is_enabled()) {
            return ['success' => false, 'message' => 'FCM not configured'];
        }

        $message = [
            'to' => $token,
            'notification' => [
                'title' => $payload['title'] ?? 'PunktePass',
                'body'  => $payload['body'] ?? '',
                'sound' => 'default',
                'badge' => 1
            ],
            'data' => $payload['data'] ?? [],
            'priority' => 'high',
            'content_available' => true
        ];

        return self::send_fcm_request($message);
    }

    /**
     * Send to all user devices
     */
    public static function send_to_user($user_id, $payload) {
        $subscriptions = self::get_user_subscriptions($user_id);

        if (empty($subscriptions)) {
            return ['success' => false, 'sent' => 0];
        }

        $sent = 0;
        $invalid_tokens = [];

        foreach ($subscriptions as $sub) {
            $result = self::send_to_token($sub->device_token, $payload);
            if ($result['success']) {
                $sent++;
                self::update_last_used($sub->device_token);
            } elseif (!empty($result['invalid_token'])) {
                $invalid_tokens[] = $sub->device_token;
            }
        }

        // Cleanup invalid tokens
        foreach ($invalid_tokens as $token) {
            self::unsubscribe($token);
        }

        return ['success' => $sent > 0, 'sent' => $sent, 'total' => count($subscriptions)];
    }

    /**
     * Send to all customers of a store
     */
    public static function send_to_store_customers($store_id, $payload) {
        global $wpdb;

        // Get users who have points at this store AND have push subscriptions
        $user_ids = $wpdb->get_col($wpdb->prepare("
            SELECT DISTINCT ps.user_id
            FROM {$wpdb->prefix}ppv_push_subscriptions ps
            INNER JOIN {$wpdb->prefix}ppv_points p ON ps.user_id = p.user_id
            WHERE p.store_id = %d AND ps.is_active = 1
        ", $store_id));

        $results = ['total_users' => count($user_ids), 'sent' => 0, 'failed' => 0];

        foreach ($user_ids as $uid) {
            $result = self::send_to_user($uid, $payload);
            $results['sent'] += $result['sent'] ?? 0;
        }

        return $results;
    }

    /**
     * Send FCM request
     */
    private static function send_fcm_request($message) {
        $response = wp_remote_post(self::$fcm_url, [
            'method'  => 'POST',
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'key=' . PPV_FCM_SERVER_KEY,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode($message)
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return ['success' => false, 'message' => 'HTTP ' . $code];
        }

        if (isset($body['success']) && $body['success'] > 0) {
            return ['success' => true];
        }

        $error = $body['results'][0]['error'] ?? 'Unknown';
        return [
            'success' => false,
            'message' => $error,
            'invalid_token' => in_array($error, ['NotRegistered', 'InvalidRegistration'])
        ];
    }

    /**
     * Update last used timestamp
     */
    private static function update_last_used($device_token) {
        global $wpdb;
        $wpdb->update(
            $wpdb->prefix . 'ppv_push_subscriptions',
            ['last_used_at' => current_time('mysql')],
            ['device_token' => $device_token]
        );
    }
}
