<?php
/**
 * PPV Ably Integration
 * Lightweight Ably client using cURL (no external dependencies)
 *
 * Setup: Add these constants to wp-config.php or punktepass.php:
 *   define('PPV_ABLY_API_KEY', 'your_api_key'); // Format: appId.keyId:keySecret
 */
class PPV_Ably {

    private static $api_key;

    /**
     * Initialize Ably credentials from constants
     */
    private static function init() {
        if (self::$api_key !== null) return true;

        // Check if Ably is configured
        if (!defined('PPV_ABLY_API_KEY') || empty(PPV_ABLY_API_KEY)) {
            return false;
        }

        self::$api_key = PPV_ABLY_API_KEY;
        return true;
    }

    /**
     * Check if Ably is configured
     */
    public static function is_enabled() {
        return self::init();
    }

    /**
     * Get Ably API key for frontend (public part only)
     * Returns the full key - Ably handles auth differently than Pusher
     */
    public static function get_key() {
        return self::init() ? self::$api_key : null;
    }

    /**
     * Publish a message to a channel
     *
     * @param string $channel Channel name (e.g., 'store-123')
     * @param string $event Event name (e.g., 'new-scan')
     * @param array $data Event data
     * @return bool Success
     */
    public static function publish($channel, $event, $data = []) {
        if (!self::init()) {
            ppv_log('[PPV_Ably] Not configured - skipping publish');
            return false;
        }

        $payload = json_encode([
            'name' => $event,
            'data' => $data
        ]);

        // Ably REST API endpoint
        $url = 'https://rest.ably.io/channels/' . urlencode($channel) . '/messages';

        // Send request
        $result = self::send_request($url, $payload);

        if ($result) {
            ppv_log("[PPV_Ably] Message published: {$channel}/{$event}");
        }

        return $result;
    }

    /**
     * Send HTTP request to Ably REST API
     */
    private static function send_request($url, $payload) {
        // Try wp_remote_post first (WordPress way)
        if (function_exists('wp_remote_post')) {
            $response = wp_remote_post($url, [
                'method' => 'POST',
                'timeout' => 3,
                'blocking' => true, // Must be blocking for Ably to receive message
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Basic ' . base64_encode(self::$api_key),
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
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Basic ' . base64_encode(self::$api_key),
                ],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT_MS => 5000,
                CURLOPT_CONNECTTIMEOUT_MS => 2000,
            ]);
            $result = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            return $http_code >= 200 && $http_code < 300;
        }

        ppv_log('[PPV_Ably] No HTTP client available');
        return false;
    }

    /**
     * Publish a scan event for a store
     * Convenience method for scan notifications
     *
     * @param int $store_id Store ID
     * @param array $scan_data Scan data to broadcast
     */
    public static function trigger_scan($store_id, $scan_data) {
        $channel = 'store-' . intval($store_id);
        return self::publish($channel, 'new-scan', $scan_data);
    }

    /**
     * Publish points update for a user
     * Used to notify user's dashboard of point changes
     *
     * @param int $user_id User ID
     * @param array $data Points data (points, store, message, etc.)
     */
    public static function trigger_user_points($user_id, $data) {
        $channel = 'user-' . intval($user_id);
        ppv_log("📡 [PPV_Ably] trigger_user_points: channel={$channel}, user_id={$user_id}, data=" . json_encode($data));
        return self::publish($channel, 'points-update', $data);
    }

    /**
     * Publish reward request notification to POS
     * Used when a user requests to redeem a reward
     *
     * @param int $store_id Store ID
     * @param array $data Reward request data
     */
    public static function trigger_reward_request($store_id, $data) {
        $channel = 'store-' . intval($store_id);
        return self::publish($channel, 'reward-request', $data);
    }

    /**
     * Publish reward approved notification to user
     * Used when POS approves a reward redemption
     *
     * @param int $user_id User ID
     * @param array $data Reward approval data
     */
    public static function trigger_reward_approved($user_id, $data) {
        $channel = 'user-' . intval($user_id);
        return self::publish($channel, 'reward-approved', $data);
    }

    /**
     * Publish campaign update notification to POS
     * Used when a campaign is created, updated, or deleted
     *
     * @param int $store_id Store ID
     * @param array $data Campaign data (action: created/updated/deleted, campaign info)
     */
    public static function trigger_campaign_update($store_id, $data) {
        $channel = 'store-' . intval($store_id);
        ppv_log("📡 [PPV_Ably] trigger_campaign_update: channel={$channel}, action={$data['action']}");
        return self::publish($channel, 'campaign-update', $data);
    }

    /**
     * Publish reward/prämien update notification to POS
     * Used when a reward is created, updated, or deleted
     *
     * @param int $store_id Store ID
     * @param array $data Reward data (action: created/updated/deleted, reward info)
     */
    public static function trigger_reward_update($store_id, $data) {
        $channel = 'store-' . intval($store_id);
        ppv_log("📡 [PPV_Ably] trigger_reward_update: channel={$channel}, action={$data['action']}");
        return self::publish($channel, 'reward-update', $data);
    }

    /**
     * Create a token request for frontend authentication
     * This is used for Ably's token-based auth (more secure than exposing API key)
     *
     * @param string $client_id Client ID for the token
     * @param string $capability JSON capability string
     * @return array|false Token request or false on failure
     */
    public static function create_token_request($client_id = null, $capability = null) {
        if (!self::init()) {
            return false;
        }

        // Parse the API key
        $key_parts = explode(':', self::$api_key);
        if (count($key_parts) !== 2) {
            return false;
        }

        $key_name = $key_parts[0]; // appId.keyId
        $key_secret = $key_parts[1];

        // Token request params
        $token_params = [
            'keyName' => $key_name,
            'timestamp' => time() * 1000, // milliseconds
            'nonce' => bin2hex(random_bytes(16)),
        ];

        if ($client_id) {
            $token_params['clientId'] = $client_id;
        }

        if ($capability) {
            $token_params['capability'] = $capability;
        }

        // Create signature
        $sign_text = implode("\n", [
            $token_params['keyName'],
            isset($token_params['ttl']) ? $token_params['ttl'] : '',
            isset($token_params['capability']) ? $token_params['capability'] : '',
            isset($token_params['clientId']) ? $token_params['clientId'] : '',
            $token_params['timestamp'],
            $token_params['nonce'],
        ]);

        $token_params['mac'] = base64_encode(hash_hmac('sha256', $sign_text, $key_secret, true));

        return $token_params;
    }
}
