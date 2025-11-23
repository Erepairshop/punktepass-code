<?php
/**
 * PPV Pusher Integration
 * Lightweight Pusher client using cURL (no external dependencies)
 *
 * Setup: Add these constants to wp-config.php:
 *   define('PPV_PUSHER_APP_ID', 'your_app_id');
 *   define('PPV_PUSHER_KEY', 'your_key');
 *   define('PPV_PUSHER_SECRET', 'your_secret');
 *   define('PPV_PUSHER_CLUSTER', 'eu'); // or your cluster
 */
class PPV_Pusher {

    private static $app_id;
    private static $key;
    private static $secret;
    private static $cluster;

    /**
     * Initialize Pusher credentials from wp-config constants
     */
    private static function init() {
        if (self::$app_id !== null) return true;

        // Check if Pusher is configured
        if (!defined('PPV_PUSHER_APP_ID') || !defined('PPV_PUSHER_KEY') ||
            !defined('PPV_PUSHER_SECRET') || !defined('PPV_PUSHER_CLUSTER')) {
            return false;
        }

        self::$app_id = PPV_PUSHER_APP_ID;
        self::$key = PPV_PUSHER_KEY;
        self::$secret = PPV_PUSHER_SECRET;
        self::$cluster = PPV_PUSHER_CLUSTER;

        return true;
    }

    /**
     * Check if Pusher is configured
     */
    public static function is_enabled() {
        return self::init();
    }

    /**
     * Get Pusher key for frontend
     */
    public static function get_key() {
        return self::init() ? self::$key : null;
    }

    /**
     * Get Pusher cluster for frontend
     */
    public static function get_cluster() {
        return self::init() ? self::$cluster : null;
    }

    /**
     * Trigger an event on a channel
     *
     * @param string $channel Channel name (e.g., 'store_123')
     * @param string $event Event name (e.g., 'new-scan')
     * @param array $data Event data
     * @return bool Success
     */
    public static function trigger($channel, $event, $data = []) {
        if (!self::init()) {
            error_log('[PPV_Pusher] Not configured - skipping trigger');
            return false;
        }

        $payload = json_encode([
            'name' => $event,
            'channel' => $channel,
            'data' => json_encode($data)
        ]);

        $path = '/apps/' . self::$app_id . '/events';
        $timestamp = time();

        // Build query string for auth
        $query_params = [
            'auth_key' => self::$key,
            'auth_timestamp' => $timestamp,
            'auth_version' => '1.0',
            'body_md5' => md5($payload)
        ];

        ksort($query_params);
        $query_string = http_build_query($query_params);

        // Create signature
        $string_to_sign = "POST\n{$path}\n{$query_string}";
        $signature = hash_hmac('sha256', $string_to_sign, self::$secret);

        // Build URL
        $url = 'https://api-' . self::$cluster . '.pusher.com' . $path . '?' . $query_string . '&auth_signature=' . $signature;

        // Send request (non-blocking via async)
        $result = self::send_async($url, $payload);

        if ($result) {
            error_log("[PPV_Pusher] Event triggered: {$channel}/{$event}");
        }

        return $result;
    }

    /**
     * Send async HTTP request (non-blocking)
     */
    private static function send_async($url, $payload) {
        // Try wp_remote_post first (WordPress way)
        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($url, [
                'method' => 'POST',
                'timeout' => 2, // Short timeout to not block
                'blocking' => false, // Non-blocking!
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'body' => $payload
            ]);
            return !is_wp_error($response);
        }

        // Fallback to cURL
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => 2000, // 2 second timeout
                CURLOPT_CONNECTTIMEOUT_MS => 1000,
            ]);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code >= 200 && $http_code < 300;
        }

        error_log('[PPV_Pusher] No HTTP client available');
        return false;
    }

    /**
     * Trigger a scan event for a store
     * Convenience method for scan notifications
     *
     * @param int $store_id Store ID
     * @param array $scan_data Scan data to broadcast
     */
    public static function trigger_scan($store_id, $scan_data) {
        $channel = 'private-store-' . intval($store_id);
        return self::trigger($channel, 'new-scan', $scan_data);
    }

    /**
     * Trigger points update for a user
     * Used to notify user's dashboard of point changes
     *
     * @param int $user_id User ID
     * @param array $data Points data (points, store, message, etc.)
     */
    public static function trigger_user_points($user_id, $data) {
        $channel = 'private-user-' . intval($user_id);
        return self::trigger($channel, 'points-update', $data);
    }

    /**
     * Trigger reward request notification to POS
     * Used when a user requests to redeem a reward
     *
     * @param int $store_id Store ID
     * @param array $data Reward request data
     */
    public static function trigger_reward_request($store_id, $data) {
        $channel = 'private-store-' . intval($store_id);
        return self::trigger($channel, 'reward-request', $data);
    }

    /**
     * Trigger reward approved notification to user
     * Used when POS approves a reward redemption
     *
     * @param int $user_id User ID
     * @param array $data Reward approval data
     */
    public static function trigger_reward_approved($user_id, $data) {
        $channel = 'private-user-' . intval($user_id);
        return self::trigger($channel, 'reward-approved', $data);
    }

    /**
     * Generate auth signature for private channels
     * Used by frontend to authenticate with Pusher
     *
     * @param string $channel_name Channel name
     * @param string $socket_id Socket ID from Pusher
     * @return string|false Auth signature or false on failure
     */
    public static function auth($channel_name, $socket_id) {
        if (!self::init()) {
            return false;
        }

        $string_to_sign = $socket_id . ':' . $channel_name;
        $signature = hash_hmac('sha256', $string_to_sign, self::$secret);

        return [
            'auth' => self::$key . ':' . $signature
        ];
    }
}
