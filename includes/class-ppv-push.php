<?php
/**
 * PPV Push Notifications
 * Firebase Cloud Messaging (FCM) integration for iOS, Android, and Web Push
 *
 * Setup: Add these constants to wp-config.php:
 *   define('PPV_FCM_SERVER_KEY', 'your_firebase_server_key');
 *   define('PPV_FCM_PROJECT_ID', 'your_firebase_project_id'); // Optional for FCM v1 API
 *
 * @package PunktePass
 * @since 2.2
 */

if (!defined('ABSPATH')) exit;

class PPV_Push {

    /** @var string FCM Legacy API endpoint */
    private static $fcm_url = 'https://fcm.googleapis.com/fcm/send';

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
        // Register push token
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

        // Test push notification (admin only)
        register_rest_route('punktepass/v1', '/push/test', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_test_push'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            }
        ]);

        // Send push to user (admin or store owner)
        register_rest_route('punktepass/v1', '/push/send', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'api_send_push'],
            'permission_callback' => [__CLASS__, 'verify_admin_or_store']
        ]);
    }

    /** ============================================================
     * Permission: User or POS token
     * ============================================================ */
    public static function verify_user_or_pos($request = null) {
        // Logged in WP user
        if (is_user_logged_in()) return true;

        // Check for PPV user token in header
        $token = $request ? $request->get_header('Authorization') : null;
        if ($token && strpos($token, 'Bearer ') === 0) {
            $bearer = substr($token, 7);
            // Verify via PPV_Permissions if available
            if (class_exists('PPV_Permissions')) {
                return PPV_Permissions::verify_user_token($bearer);
            }
        }

        // Check for POS token
        if (class_exists('PPV_API')) {
            return PPV_API::verify_pos_or_user($request);
        }

        return false;
    }

    /** ============================================================
     * Permission: Admin or Store Owner
     * ============================================================ */
    public static function verify_admin_or_store($request = null) {
        if (current_user_can('manage_options')) return true;

        // Check if user owns a store
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
        $store_id     = intval($params['store_id'] ?? 0) ?: null;
        $language     = sanitize_text_field($params['language'] ?? 'de');

        if (empty($device_token)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token is required'
            ], 400);
        }

        // Validate platform
        if (!in_array($platform, ['ios', 'android', 'web'])) {
            $platform = 'web';
        }

        // Get user_id from session if not provided
        if (!$user_id && is_user_logged_in()) {
            global $wpdb;
            $wp_user_id = get_current_user_id();
            $user_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE wp_user_id = %d",
                $wp_user_id
            ));
        }

        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'User ID is required'
            ], 400);
        }

        $result = self::subscribe($user_id, $device_token, $platform, $device_name, $store_id, $language);

        if ($result) {
            ppv_log("[PPV_Push] Token registered for user {$user_id} ({$platform})");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Push subscription registered'
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Failed to register subscription'
        ], 500);
    }

    /** ============================================================
     * API: Unregister push token
     * ============================================================ */
    public static function api_unregister_token($request) {
        $params = $request->get_json_params();
        $device_token = sanitize_text_field($params['token'] ?? '');

        if (empty($device_token)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Token is required'
            ], 400);
        }

        $result = self::unsubscribe($device_token);

        return new WP_REST_Response([
            'success' => $result,
            'message' => $result ? 'Unsubscribed' : 'Token not found'
        ], $result ? 200 : 404);
    }

    /** ============================================================
     * API: Test push notification
     * ============================================================ */
    public static function api_test_push($request) {
        $params = $request->get_json_params();
        $user_id = intval($params['user_id'] ?? 0);
        $token   = sanitize_text_field($params['token'] ?? '');

        if ($token) {
            // Send to specific token
            $result = self::send_to_token($token, [
                'title' => 'PunktePass Test',
                'body'  => 'Push notification works!',
                'data'  => ['type' => 'test', 'timestamp' => time()]
            ]);
        } elseif ($user_id) {
            // Send to user
            $result = self::send_to_user($user_id, [
                'title' => 'PunktePass Test',
                'body'  => 'Push notification works!',
                'data'  => ['type' => 'test', 'timestamp' => time()]
            ]);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'user_id or token required'
            ], 400);
        }

        return new WP_REST_Response([
            'success' => $result['success'],
            'message' => $result['message'] ?? ($result['success'] ? 'Sent' : 'Failed'),
            'details' => $result
        ], $result['success'] ? 200 : 500);
    }

    /** ============================================================
     * API: Send push to user(s)
     * ============================================================ */
    public static function api_send_push($request) {
        $params = $request->get_json_params();

        $user_ids = $params['user_ids'] ?? [];
        $store_id = intval($params['store_id'] ?? 0);
        $title    = sanitize_text_field($params['title'] ?? '');
        $body     = sanitize_text_field($params['body'] ?? '');
        $data     = $params['data'] ?? [];

        if (empty($title) || empty($body)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Title and body are required'
            ], 400);
        }

        $payload = [
            'title' => $title,
            'body'  => $body,
            'data'  => $data
        ];

        $results = [];

        if (!empty($user_ids)) {
            foreach ($user_ids as $uid) {
                $results[$uid] = self::send_to_user(intval($uid), $payload);
            }
        } elseif ($store_id) {
            // Send to all users who visited this store
            $results = self::send_to_store_customers($store_id, $payload);
        } else {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'user_ids or store_id required'
            ], 400);
        }

        return new WP_REST_Response([
            'success' => true,
            'results' => $results
        ], 200);
    }

    /** ============================================================
     * Subscribe user to push notifications
     * ============================================================ */
    public static function subscribe($user_id, $device_token, $platform = 'web', $device_name = '', $store_id = null, $language = 'de') {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        // Check if token already exists
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, user_id FROM {$table} WHERE device_token = %s",
            $device_token
        ));

        if ($existing) {
            // Update existing subscription
            return $wpdb->update(
                $table,
                [
                    'user_id'     => $user_id,
                    'store_id'    => $store_id,
                    'platform'    => $platform,
                    'device_name' => $device_name,
                    'language'    => $language,
                    'is_active'   => 1,
                    'updated_at'  => current_time('mysql')
                ],
                ['id' => $existing->id],
                ['%d', '%d', '%s', '%s', '%s', '%d', '%s'],
                ['%d']
            );
        }

        // Insert new subscription
        return $wpdb->insert(
            $table,
            [
                'user_id'      => $user_id,
                'store_id'     => $store_id,
                'device_token' => $device_token,
                'platform'     => $platform,
                'device_name'  => $device_name,
                'language'     => $language,
                'is_active'    => 1,
                'created_at'   => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%d', '%s']
        );
    }

    /** ============================================================
     * Unsubscribe device from push notifications
     * ============================================================ */
    public static function unsubscribe($device_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        // Soft delete - set inactive
        return $wpdb->update(
            $table,
            ['is_active' => 0],
            ['device_token' => $device_token],
            ['%d'],
            ['%s']
        );
    }

    /** ============================================================
     * Get all active subscriptions for a user
     * ============================================================ */
    public static function get_user_subscriptions($user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$table} WHERE user_id = %d AND is_active = 1",
            $user_id
        ));
    }

    /** ============================================================
     * Send push notification to a specific token
     * ============================================================ */
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

        // Add iOS specific options
        $message['apns'] = [
            'payload' => [
                'aps' => [
                    'sound' => 'default',
                    'badge' => 1
                ]
            ]
        ];

        return self::send_fcm_request($message);
    }

    /** ============================================================
     * Send push notification to all user devices
     * ============================================================ */
    public static function send_to_user($user_id, $payload) {
        $subscriptions = self::get_user_subscriptions($user_id);

        if (empty($subscriptions)) {
            return ['success' => false, 'message' => 'No active subscriptions', 'sent' => 0];
        }

        $sent = 0;
        $failed = 0;
        $invalid_tokens = [];

        foreach ($subscriptions as $sub) {
            $result = self::send_to_token($sub->device_token, $payload);

            if ($result['success']) {
                $sent++;
                // Update last_used_at
                self::update_last_used($sub->device_token);
            } else {
                $failed++;
                // Check if token is invalid and should be removed
                if (isset($result['invalid_token']) && $result['invalid_token']) {
                    $invalid_tokens[] = $sub->device_token;
                }
            }
        }

        // Cleanup invalid tokens
        foreach ($invalid_tokens as $token) {
            self::unsubscribe($token);
            ppv_log("[PPV_Push] Removed invalid token for user {$user_id}");
        }

        return [
            'success' => $sent > 0,
            'sent'    => $sent,
            'failed'  => $failed,
            'total'   => count($subscriptions)
        ];
    }

    /** ============================================================
     * Send push to all customers of a store
     * ============================================================ */
    public static function send_to_store_customers($store_id, $payload) {
        global $wpdb;

        // Get all users who have points at this store
        $user_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}ppv_points WHERE store_id = %d",
            $store_id
        ));

        $results = [
            'total_users' => count($user_ids),
            'sent' => 0,
            'failed' => 0
        ];

        foreach ($user_ids as $uid) {
            $result = self::send_to_user($uid, $payload);
            if ($result['success']) {
                $results['sent'] += $result['sent'];
            }
            $results['failed'] += $result['failed'] ?? 0;
        }

        return $results;
    }

    /** ============================================================
     * Send FCM HTTP request
     * ============================================================ */
    private static function send_fcm_request($message) {
        $headers = [
            'Authorization' => 'key=' . PPV_FCM_SERVER_KEY,
            'Content-Type'  => 'application/json'
        ];

        $response = wp_remote_post(self::$fcm_url, [
            'method'    => 'POST',
            'timeout'   => 10,
            'headers'   => $headers,
            'body'      => json_encode($message)
        ]);

        if (is_wp_error($response)) {
            ppv_log('[PPV_Push] FCM Error: ' . $response->get_error_message());
            return ['success' => false, 'message' => $response->get_error_message()];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $http_code = wp_remote_retrieve_response_code($response);

        if ($http_code !== 200) {
            ppv_log('[PPV_Push] FCM HTTP Error: ' . $http_code);
            return ['success' => false, 'message' => 'HTTP ' . $http_code];
        }

        // Check for success
        if (isset($body['success']) && $body['success'] > 0) {
            return ['success' => true, 'message_id' => $body['results'][0]['message_id'] ?? null];
        }

        // Check for invalid token
        $error = $body['results'][0]['error'] ?? null;
        if (in_array($error, ['NotRegistered', 'InvalidRegistration'])) {
            return ['success' => false, 'message' => $error, 'invalid_token' => true];
        }

        return ['success' => false, 'message' => $error ?? 'Unknown error', 'response' => $body];
    }

    /** ============================================================
     * Update last_used_at timestamp
     * ============================================================ */
    private static function update_last_used($device_token) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        $wpdb->update(
            $table,
            ['last_used_at' => current_time('mysql')],
            ['device_token' => $device_token],
            ['%s'],
            ['%s']
        );
    }

    /** ============================================================
     * CONVENIENCE METHODS FOR COMMON NOTIFICATIONS
     * ============================================================ */

    /**
     * Notify user about points received
     */
    public static function notify_points_received($user_id, $points, $store_name, $total_points) {
        return self::send_to_user($user_id, [
            'title' => "+" . $points . " Punkte erhalten!",
            'body'  => "Bei {$store_name}. Gesamt: {$total_points} Punkte",
            'data'  => [
                'type'   => 'points_received',
                'points' => $points,
                'total'  => $total_points,
                'store'  => $store_name
            ]
        ]);
    }

    /**
     * Notify user about reward approval
     */
    public static function notify_reward_approved($user_id, $reward_name, $store_name) {
        return self::send_to_user($user_id, [
            'title' => "Belohnung eingelöst!",
            'body'  => "{$reward_name} bei {$store_name} wurde genehmigt",
            'data'  => [
                'type'   => 'reward_approved',
                'reward' => $reward_name,
                'store'  => $store_name
            ]
        ]);
    }

    /**
     * Notify store about new scan
     */
    public static function notify_store_new_scan($store_id, $user_name, $points) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        // Get store device subscriptions
        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT device_token FROM {$table} WHERE store_id = %d AND is_active = 1",
            $store_id
        ));

        $sent = 0;
        foreach ($tokens as $token) {
            $result = self::send_to_token($token, [
                'title' => "Neuer Scan!",
                'body'  => "{$user_name} - {$points} Punkte vergeben",
                'data'  => [
                    'type' => 'new_scan',
                    'user' => $user_name,
                    'points' => $points
                ]
            ]);
            if ($result['success']) $sent++;
        }

        return ['sent' => $sent, 'total' => count($tokens)];
    }

    /**
     * Notify store about reward request
     */
    public static function notify_store_reward_request($store_id, $user_name, $reward_name) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_push_subscriptions';

        $tokens = $wpdb->get_col($wpdb->prepare(
            "SELECT device_token FROM {$table} WHERE store_id = %d AND is_active = 1",
            $store_id
        ));

        $sent = 0;
        foreach ($tokens as $token) {
            $result = self::send_to_token($token, [
                'title' => "Einlösungsanfrage!",
                'body'  => "{$user_name} möchte {$reward_name} einlösen",
                'data'  => [
                    'type'   => 'reward_request',
                    'user'   => $user_name,
                    'reward' => $reward_name
                ]
            ]);
            if ($result['success']) $sent++;
        }

        return ['sent' => $sent, 'total' => count($tokens)];
    }

    /**
     * Send promotional notification to store customers
     */
    public static function notify_store_promotion($store_id, $title, $message, $data = []) {
        return self::send_to_store_customers($store_id, [
            'title' => $title,
            'body'  => $message,
            'data'  => array_merge(['type' => 'promotion', 'store_id' => $store_id], $data)
        ]);
    }
}
