<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Device Fingerprint Management
 * Prevents multiple account creation from same device
 *
 * Features:
 * - Stores device fingerprints at registration
 * - Limits accounts per device (default: 2)
 * - Admin visibility for suspicious patterns
 *
 * @author Erik Borota / PunktePass
 */

class PPV_Device_Fingerprint {

    // Maximum accounts allowed per device
    const MAX_ACCOUNTS_PER_DEVICE = 2;

    /**
     * Register hooks
     */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Endpoint to check device limit before registration
        register_rest_route('punktepass/v1', '/device/check', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_check_device'],
            'permission_callback' => '__return_true'
        ]);

        // Endpoint to store fingerprint after successful registration
        register_rest_route('punktepass/v1', '/device/register', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_register_device'],
            'permission_callback' => '__return_true'
        ]);
    }

    /**
     * Check if device has reached account limit
     * Called BEFORE registration to show warning
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_check_device(WP_REST_Request $request) {
        $data = $request->get_json_params();
        $fingerprint = sanitize_text_field($data['fingerprint'] ?? '');

        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            // No fingerprint provided - allow registration (fallback)
            return new WP_REST_Response([
                'allowed' => true,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE
            ], 200);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $account_count = self::get_account_count($fingerprint_hash);

        $allowed = $account_count < self::MAX_ACCOUNTS_PER_DEVICE;

        ppv_log("📱 [Device Check] fingerprint_hash={$fingerprint_hash}, accounts={$account_count}, allowed=" . ($allowed ? 'YES' : 'NO'));

        return new WP_REST_Response([
            'allowed' => $allowed,
            'accounts' => $account_count,
            'limit' => self::MAX_ACCOUNTS_PER_DEVICE
        ], 200);
    }

    /**
     * Register device fingerprint after successful user registration
     *
     * @param WP_REST_Request $request
     * @return WP_REST_Response
     */
    public static function rest_register_device(WP_REST_Request $request) {
        global $wpdb;

        $data = $request->get_json_params();
        $fingerprint = sanitize_text_field($data['fingerprint'] ?? '');
        $user_id = intval($data['user_id'] ?? 0);
        $fingerprint_components = $data['components'] ?? null;

        if (empty($fingerprint) || $user_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing fingerprint or user_id'
            ], 400);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);

        // Get IP and user agent
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Store the fingerprint
        $result = $wpdb->insert(
            $wpdb->prefix . 'ppv_device_fingerprints',
            [
                'fingerprint_hash' => $fingerprint_hash,
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'fingerprint_data' => $fingerprint_components ? json_encode($fingerprint_components) : null,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        if ($result) {
            ppv_log("📱 [Device Register] SUCCESS: user_id={$user_id}, fingerprint_hash={$fingerprint_hash}, ip={$ip_address}");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Device registered'
            ], 200);
        } else {
            ppv_log("📱 [Device Register] FAILED: user_id={$user_id}, error=" . $wpdb->last_error);
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Failed to register device'
            ], 500);
        }
    }

    /**
     * Check device limit during registration (called from PHP)
     *
     * @param string $fingerprint Raw fingerprint string
     * @return array ['allowed' => bool, 'accounts' => int]
     */
    public static function check_device_limit($fingerprint) {
        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            return ['allowed' => true, 'accounts' => 0, 'limit' => self::MAX_ACCOUNTS_PER_DEVICE];
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $account_count = self::get_account_count($fingerprint_hash);

        return [
            'allowed' => $account_count < self::MAX_ACCOUNTS_PER_DEVICE,
            'accounts' => $account_count,
            'limit' => self::MAX_ACCOUNTS_PER_DEVICE
        ];
    }

    /**
     * Store fingerprint for a user (called from PHP after registration)
     *
     * @param int $user_id
     * @param string $fingerprint
     * @param array|null $components Optional fingerprint components
     * @return bool Success
     */
    public static function store_fingerprint($user_id, $fingerprint, $components = null) {
        global $wpdb;

        if (empty($fingerprint) || $user_id <= 0) {
            return false;
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $result = $wpdb->insert(
            $wpdb->prefix . 'ppv_device_fingerprints',
            [
                'fingerprint_hash' => $fingerprint_hash,
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'fingerprint_data' => $components ? json_encode($components) : null,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        ppv_log("📱 [Store Fingerprint] user_id={$user_id}, hash={$fingerprint_hash}, result=" . ($result ? 'OK' : 'FAIL'));

        return (bool) $result;
    }

    /**
     * Get number of accounts registered from this device
     *
     * @param string $fingerprint_hash
     * @return int
     */
    private static function get_account_count($fingerprint_hash) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ppv_device_fingerprints WHERE fingerprint_hash = %s",
            $fingerprint_hash
        ));
    }

    /**
     * Hash the fingerprint for storage
     *
     * @param string $fingerprint
     * @return string SHA256 hash
     */
    private static function hash_fingerprint($fingerprint) {
        return hash('sha256', $fingerprint);
    }

    /**
     * Get client IP address
     *
     * @return string
     */
    private static function get_client_ip() {
        $headers = [
            'HTTP_CF_CONNECTING_IP',     // Cloudflare
            'HTTP_X_FORWARDED_FOR',      // Proxy
            'HTTP_X_REAL_IP',            // Nginx
            'REMOTE_ADDR'                // Direct
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ip = $_SERVER[$header];
                // Handle comma-separated IPs (X-Forwarded-For)
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Get all accounts for a fingerprint (admin use)
     *
     * @param string $fingerprint_hash
     * @return array
     */
    public static function get_accounts_for_device($fingerprint_hash) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                df.user_id,
                df.ip_address,
                df.created_at,
                u.email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_device_fingerprints df
            LEFT JOIN {$wpdb->prefix}ppv_users u ON df.user_id = u.id
            WHERE df.fingerprint_hash = %s
            ORDER BY df.created_at DESC
        ", $fingerprint_hash));
    }

    /**
     * Get suspicious devices (more than 1 account)
     *
     * @return array
     */
    public static function get_suspicious_devices() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT
                fingerprint_hash,
                COUNT(DISTINCT user_id) as account_count,
                MIN(created_at) as first_seen,
                MAX(created_at) as last_seen,
                GROUP_CONCAT(DISTINCT user_id) as user_ids
            FROM {$wpdb->prefix}ppv_device_fingerprints
            GROUP BY fingerprint_hash
            HAVING account_count > 1
            ORDER BY account_count DESC, last_seen DESC
            LIMIT 100
        ");
    }
}
