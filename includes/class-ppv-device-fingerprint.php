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

    // Login tracking table name suffix
    const LOGIN_TABLE = 'ppv_device_logins';

    // Blocked devices table name suffix
    const BLOCKED_TABLE = 'ppv_blocked_devices';

    // User registered devices table
    const USER_DEVICES_TABLE = 'ppv_user_devices';

    // Device approval requests table
    const DEVICE_REQUESTS_TABLE = 'ppv_device_requests';

    // Maximum devices per user (store owner)
    const MAX_DEVICES_PER_USER = 2;

    /**
     * Register hooks
     */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('admin_init', [__CLASS__, 'run_migrations']);
    }

    /**
     * Run database migrations for new tables
     */
    public static function run_migrations() {
        global $wpdb;

        $migration_version = get_option('ppv_device_migration_version', '0');

        // Migration 1.0: Add login tracking table
        if (version_compare($migration_version, '1.0', '<')) {
            $table_logins = $wpdb->prefix . self::LOGIN_TABLE;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_logins} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                fingerprint_hash VARCHAR(64) NOT NULL,
                user_id BIGINT(20) UNSIGNED NOT NULL,
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                login_type ENUM('password', 'google', 'cookie') DEFAULT 'password',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_fingerprint (fingerprint_hash),
                KEY idx_user (user_id),
                KEY idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Device_Fingerprint] Login tracking table created");
            update_option('ppv_device_migration_version', '1.0');
            $migration_version = '1.0';
        }

        // Migration 1.1: Add blocked devices table
        if (version_compare($migration_version, '1.1', '<')) {
            $table_blocked = $wpdb->prefix . self::BLOCKED_TABLE;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_blocked} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                fingerprint_hash VARCHAR(64) NOT NULL,
                reason TEXT NULL,
                blocked_by BIGINT(20) UNSIGNED NULL COMMENT 'WP user ID who blocked',
                blocked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                UNIQUE KEY idx_fingerprint (fingerprint_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Device_Fingerprint] Blocked devices table created");
            update_option('ppv_device_migration_version', '1.1');
            $migration_version = '1.1';
        }

        // Migration 1.2: Add user devices table (for scanner device registration)
        if (version_compare($migration_version, '1.2', '<')) {
            $table_user_devices = $wpdb->prefix . self::USER_DEVICES_TABLE;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_user_devices} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'Store owner ID',
                fingerprint_hash VARCHAR(64) NOT NULL,
                device_name VARCHAR(255) NULL COMMENT 'User-friendly device name',
                user_agent TEXT NULL,
                ip_address VARCHAR(45) NULL,
                registered_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                last_used_at DATETIME NULL,
                status ENUM('active', 'pending_removal') DEFAULT 'active',
                PRIMARY KEY (id),
                UNIQUE KEY idx_store_fingerprint (store_id, fingerprint_hash),
                KEY idx_store (store_id),
                KEY idx_fingerprint (fingerprint_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Device_Fingerprint] User devices table created");
            update_option('ppv_device_migration_version', '1.2');
            $migration_version = '1.2';
        }

        // Migration 1.3: Add device requests table (for admin approval)
        if (version_compare($migration_version, '1.3', '<')) {
            $table_requests = $wpdb->prefix . self::DEVICE_REQUESTS_TABLE;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_requests} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                fingerprint_hash VARCHAR(64) NOT NULL,
                request_type ENUM('add', 'remove') NOT NULL,
                device_name VARCHAR(255) NULL,
                user_agent TEXT NULL,
                ip_address VARCHAR(45) NULL,
                approval_token VARCHAR(64) NOT NULL,
                status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
                requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                processed_at DATETIME NULL,
                processed_by VARCHAR(100) NULL,
                PRIMARY KEY (id),
                UNIQUE KEY idx_token (approval_token),
                KEY idx_store (store_id),
                KEY idx_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Device_Fingerprint] Device requests table created");
            update_option('ppv_device_migration_version', '1.3');
        }

        // Migration 1.4: Extend request_type to include mobile_scanner
        if (version_compare($migration_version, '1.4', '<')) {
            $table_requests = $wpdb->prefix . self::DEVICE_REQUESTS_TABLE;

            // Alter the ENUM to include mobile_scanner
            $wpdb->query("ALTER TABLE {$table_requests} MODIFY COLUMN request_type ENUM('add', 'remove', 'mobile_scanner') NOT NULL");

            ppv_log("✅ [PPV_Device_Fingerprint] Added mobile_scanner request type");
            update_option('ppv_device_migration_version', '1.4');
            $migration_version = '1.4';
        }

        // Migration 1.5: Add device_info column for storing device details (memory, screen, etc.)
        if (version_compare($migration_version, '1.5', '<')) {
            $table_user_devices = $wpdb->prefix . self::USER_DEVICES_TABLE;
            $table_requests = $wpdb->prefix . self::DEVICE_REQUESTS_TABLE;

            // Add device_info column to user_devices table
            $wpdb->query("ALTER TABLE {$table_user_devices} ADD COLUMN device_info TEXT NULL COMMENT 'JSON: device details from FingerprintJS' AFTER user_agent");

            // Add device_info column to device_requests table
            $wpdb->query("ALTER TABLE {$table_requests} ADD COLUMN device_info TEXT NULL COMMENT 'JSON: device details from FingerprintJS' AFTER user_agent");

            ppv_log("✅ [PPV_Device_Fingerprint] Added device_info column to tables");
            update_option('ppv_device_migration_version', '1.5');
        }
    }

    /**
     * Check if a column exists in the user_devices table
     */
    private static function column_exists($column_name) {
        global $wpdb;
        static $columns_cache = null;

        if ($columns_cache === null) {
            $table = $wpdb->prefix . self::USER_DEVICES_TABLE;
            $columns = $wpdb->get_col("SHOW COLUMNS FROM {$table}");
            $columns_cache = array_flip($columns);
        }

        return isset($columns_cache[$column_name]);
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

        // ========================================
        // 📱 USER DEVICE MANAGEMENT ROUTES
        // ========================================

        // Get user's registered devices
        register_rest_route('punktepass/v1', '/user-devices/list', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_user_devices'],
            'permission_callback' => '__return_true'
        ]);

        // Register current device for user
        register_rest_route('punktepass/v1', '/user-devices/register', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_register_user_device'],
            'permission_callback' => '__return_true'
        ]);

        // Check if current device is registered for user
        register_rest_route('punktepass/v1', '/user-devices/check', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_check_user_device'],
            'permission_callback' => '__return_true'
        ]);

        // Update device fingerprint (when fingerprint changed)
        register_rest_route('punktepass/v1', '/user-devices/update-fingerprint', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_update_device_fingerprint'],
            'permission_callback' => '__return_true'
        ]);

        // Request device removal (needs admin approval)
        register_rest_route('punktepass/v1', '/user-devices/request-remove', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_device_removal'],
            'permission_callback' => '__return_true'
        ]);

        // Request adding new device (when limit reached)
        register_rest_route('punktepass/v1', '/user-devices/request-add', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_device_add'],
            'permission_callback' => '__return_true'
        ]);

        // Admin approval endpoint (via email link)
        register_rest_route('punktepass/v1', '/user-devices/approve/(?P<token>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_approve_device_request'],
            'permission_callback' => '__return_true'
        ]);

        // Admin rejection endpoint (via email link)
        register_rest_route('punktepass/v1', '/user-devices/reject/(?P<token>[a-zA-Z0-9]+)', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_reject_device_request'],
            'permission_callback' => '__return_true'
        ]);

        // ========================================
        // 📱 MOBILE SCANNER REQUEST
        // ========================================

        // Request mobile scanner mode (needs admin approval)
        register_rest_route('punktepass/v1', '/user-devices/request-mobile-scanner', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_mobile_scanner'],
            'permission_callback' => '__return_true'
        ]);

        // Check mobile scanner status
        register_rest_route('punktepass/v1', '/user-devices/mobile-scanner-status', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_mobile_scanner_status'],
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
                'blocked' => false,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE
            ], 200);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);

        // 🚫 Check if device is blocked
        if (self::is_device_blocked($fingerprint_hash)) {
            ppv_log("🚫 [Device Check] BLOCKED device attempted registration: fingerprint_hash={$fingerprint_hash}");
            return new WP_REST_Response([
                'allowed' => false,
                'blocked' => true,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE,
                'message' => 'Dieses Gerät wurde gesperrt.'
            ], 200);
        }

        $account_count = self::get_account_count($fingerprint_hash);
        $allowed = $account_count < self::MAX_ACCOUNTS_PER_DEVICE;

        ppv_log("📱 [Device Check] fingerprint_hash={$fingerprint_hash}, accounts={$account_count}, allowed=" . ($allowed ? 'YES' : 'NO'));

        return new WP_REST_Response([
            'allowed' => $allowed,
            'blocked' => false,
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
     * @return array ['allowed' => bool, 'accounts' => int, 'blocked' => bool]
     */
    public static function check_device_limit($fingerprint) {
        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            return ['allowed' => true, 'accounts' => 0, 'limit' => self::MAX_ACCOUNTS_PER_DEVICE, 'blocked' => false];
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);

        // 🚫 Check if device is blocked
        if (self::is_device_blocked($fingerprint_hash)) {
            return [
                'allowed' => false,
                'blocked' => true,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE
            ];
        }

        $account_count = self::get_account_count($fingerprint_hash);

        return [
            'allowed' => $account_count < self::MAX_ACCOUNTS_PER_DEVICE,
            'blocked' => false,
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

    // ========================================
    // 📱 LOGIN TRACKING METHODS
    // ========================================

    /**
     * Track a login event for a user
     *
     * @param int $user_id
     * @param string $fingerprint Raw fingerprint
     * @param string $login_type 'password', 'google', or 'cookie'
     * @return bool Success
     */
    public static function track_login($user_id, $fingerprint, $login_type = 'password') {
        global $wpdb;

        if (empty($fingerprint) || $user_id <= 0) {
            return false;
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $result = $wpdb->insert(
            $wpdb->prefix . self::LOGIN_TABLE,
            [
                'fingerprint_hash' => $fingerprint_hash,
                'user_id' => $user_id,
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'login_type' => $login_type,
                'created_at' => current_time('mysql')
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s']
        );

        ppv_log("📱 [Login Track] user_id={$user_id}, type={$login_type}, hash={$fingerprint_hash}, ip={$ip_address}");

        return (bool) $result;
    }

    /**
     * Get login history for a user
     *
     * @param int $user_id
     * @param int $limit
     * @return array
     */
    public static function get_user_login_history($user_id, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}" . self::LOGIN_TABLE . "
            WHERE user_id = %d
            ORDER BY created_at DESC
            LIMIT %d
        ", $user_id, $limit));
    }

    /**
     * Get login history for a device (fingerprint)
     *
     * @param string $fingerprint_hash
     * @param int $limit
     * @return array
     */
    public static function get_device_login_history($fingerprint_hash, $limit = 50) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.email, u.first_name, u.last_name
            FROM {$wpdb->prefix}" . self::LOGIN_TABLE . " l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            WHERE l.fingerprint_hash = %s
            ORDER BY l.created_at DESC
            LIMIT %d
        ", $fingerprint_hash, $limit));
    }

    /**
     * Get recent logins across all devices (admin dashboard)
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent_logins($limit = 100) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT l.*, u.email, u.first_name, u.last_name
            FROM {$wpdb->prefix}" . self::LOGIN_TABLE . " l
            LEFT JOIN {$wpdb->prefix}ppv_users u ON l.user_id = u.id
            ORDER BY l.created_at DESC
            LIMIT %d
        ", $limit));
    }

    // ========================================
    // 🚫 DEVICE BLOCKING METHODS
    // ========================================

    /**
     * Check if a device is blocked
     *
     * @param string $fingerprint_hash SHA256 hash of fingerprint
     * @return bool
     */
    public static function is_device_blocked($fingerprint_hash) {
        global $wpdb;

        $table = $wpdb->prefix . self::BLOCKED_TABLE;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return false;
        }

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE fingerprint_hash = %s",
            $fingerprint_hash
        ));
    }

    /**
     * Check if a raw fingerprint is blocked
     *
     * @param string $fingerprint Raw fingerprint
     * @return bool
     */
    public static function is_fingerprint_blocked($fingerprint) {
        if (empty($fingerprint)) {
            return false;
        }
        return self::is_device_blocked(self::hash_fingerprint($fingerprint));
    }

    /**
     * Block a device
     *
     * @param string $fingerprint_hash SHA256 hash
     * @param string $reason Optional reason
     * @param int $blocked_by WP user ID who blocked (optional)
     * @return bool Success
     */
    public static function block_device($fingerprint_hash, $reason = '', $blocked_by = null) {
        global $wpdb;

        // Check if already blocked
        if (self::is_device_blocked($fingerprint_hash)) {
            ppv_log("🚫 [Block Device] Already blocked: {$fingerprint_hash}");
            return true;
        }

        $result = $wpdb->insert(
            $wpdb->prefix . self::BLOCKED_TABLE,
            [
                'fingerprint_hash' => $fingerprint_hash,
                'reason' => $reason,
                'blocked_by' => $blocked_by ?: get_current_user_id(),
                'blocked_at' => current_time('mysql')
            ],
            ['%s', '%s', '%d', '%s']
        );

        ppv_log("🚫 [Block Device] " . ($result ? 'SUCCESS' : 'FAILED') . ": hash={$fingerprint_hash}, reason={$reason}");

        return (bool) $result;
    }

    /**
     * Unblock a device
     *
     * @param string $fingerprint_hash SHA256 hash
     * @return bool Success
     */
    public static function unblock_device($fingerprint_hash) {
        global $wpdb;

        $result = $wpdb->delete(
            $wpdb->prefix . self::BLOCKED_TABLE,
            ['fingerprint_hash' => $fingerprint_hash],
            ['%s']
        );

        ppv_log("✅ [Unblock Device] " . ($result ? 'SUCCESS' : 'NOT FOUND') . ": hash={$fingerprint_hash}");

        return (bool) $result;
    }

    /**
     * Get all blocked devices (admin use)
     *
     * @return array
     */
    public static function get_blocked_devices() {
        global $wpdb;

        $table = $wpdb->prefix . self::BLOCKED_TABLE;

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") !== $table) {
            return [];
        }

        return $wpdb->get_results("
            SELECT
                bd.*,
                wu.display_name as blocked_by_name,
                (SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}ppv_device_fingerprints WHERE fingerprint_hash = bd.fingerprint_hash) as account_count,
                (SELECT GROUP_CONCAT(DISTINCT user_id) FROM {$wpdb->prefix}ppv_device_fingerprints WHERE fingerprint_hash = bd.fingerprint_hash) as user_ids
            FROM {$table} bd
            LEFT JOIN {$wpdb->users} wu ON bd.blocked_by = wu.ID
            ORDER BY bd.blocked_at DESC
        ");
    }

    /**
     * Get block info for a device
     *
     * @param string $fingerprint_hash
     * @return object|null
     */
    public static function get_block_info($fingerprint_hash) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("
            SELECT bd.*, wu.display_name as blocked_by_name
            FROM {$wpdb->prefix}" . self::BLOCKED_TABLE . " bd
            LEFT JOIN {$wpdb->users} wu ON bd.blocked_by = wu.ID
            WHERE bd.fingerprint_hash = %s
        ", $fingerprint_hash));
    }

    // ========================================
    // 📱 USER DEVICE MANAGEMENT METHODS
    // ========================================

    /**
     * Get store ID from session (helper)
     */
    private static function get_session_store_id() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Check for filiale first
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        return 0;
    }

    /**
     * Get parent store ID (for device registration - always use parent)
     */
    private static function get_parent_store_id($store_id) {
        global $wpdb;

        if ($store_id <= 0) return 0;

        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        return $parent_id ? intval($parent_id) : $store_id;
    }

    /**
     * REST: Get user's registered devices
     */
    public static function rest_get_user_devices(WP_REST_Request $request) {
        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        // Always use parent store for device management
        $parent_store_id = self::get_parent_store_id($store_id);
        $devices = self::get_user_devices($parent_store_id);

        return new WP_REST_Response([
            'success' => true,
            'devices' => $devices,
            'max_devices' => self::MAX_DEVICES_PER_USER,
            'can_add_more' => count($devices) < self::MAX_DEVICES_PER_USER
        ], 200);
    }

    /**
     * REST: Register current device for user
     */
    public static function rest_register_user_device(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $fingerprint = sanitize_text_field($data['fingerprint'] ?? '');
        $device_name = sanitize_text_field($data['device_name'] ?? 'Unbenanntes Gerät');
        $device_info = $data['device_info'] ?? null; // FingerprintJS components data

        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint'
            ], 400);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $parent_store_id = self::get_parent_store_id($store_id);

        // Prepare device_info JSON
        $device_info_json = null;
        if (!empty($device_info) && is_array($device_info)) {
            $device_info_json = wp_json_encode($device_info);
        }

        // Check if already registered
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND fingerprint_hash = %s",
            $parent_store_id, $fingerprint_hash
        ));

        if ($existing) {
            // Update last_used_at and device_info if provided
            $update_data = ['last_used_at' => current_time('mysql')];
            $update_format = ['%s'];

            // Only add device_info if we have data AND column exists
            if ($device_info_json && self::column_exists('device_info')) {
                $update_data['device_info'] = $device_info_json;
                $update_format[] = '%s';
            }

            $wpdb->update(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                $update_data,
                ['id' => $existing],
                $update_format,
                ['%d']
            );

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Gerät bereits registriert',
                'already_registered' => true
            ], 200);
        }

        // Check device limit
        $device_count = self::get_user_device_count($parent_store_id);
        if ($device_count >= self::MAX_DEVICES_PER_USER) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gerätelimit erreicht. Bitte Admin-Genehmigung anfordern.',
                'limit_reached' => true,
                'device_count' => $device_count,
                'max_devices' => self::MAX_DEVICES_PER_USER
            ], 200);
        }

        // Register the device
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Base insert data (without device_info - column might not exist yet)
        $insert_data = [
            'store_id' => $parent_store_id,
            'fingerprint_hash' => $fingerprint_hash,
            'device_name' => $device_name,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'registered_at' => current_time('mysql'),
            'last_used_at' => current_time('mysql'),
            'status' => 'active'
        ];
        $insert_format = ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s'];

        // Only add device_info if we have data AND column exists
        if ($device_info_json && self::column_exists('device_info')) {
            $insert_data['device_info'] = $device_info_json;
            $insert_format[] = '%s';
        }

        $result = $wpdb->insert(
            $wpdb->prefix . self::USER_DEVICES_TABLE,
            $insert_data,
            $insert_format
        );

        if ($result) {
            ppv_log("📱 [User Device] Registered: store_id={$parent_store_id}, hash={$fingerprint_hash}");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Gerät erfolgreich registriert',
                'device_id' => $wpdb->insert_id
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Fehler bei der Registrierung'
        ], 500);
    }

    /**
     * REST: Check if current device is registered
     */
    public static function rest_check_user_device(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $fingerprint = sanitize_text_field($data['fingerprint'] ?? '');

        // Get store location for GPS geofencing
        $current_store_id = $store_id; // Use current store (filiale) for GPS, not parent
        $store_location = $wpdb->get_row($wpdb->prepare(
            "SELECT latitude, longitude, max_scan_distance FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $current_store_id
        ));

        // Build GPS data for response
        $gps_data = [
            'store_lat' => $store_location->latitude ?? null,
            'store_lng' => $store_location->longitude ?? null,
            'max_distance' => intval($store_location->max_scan_distance ?? 500) ?: 500
        ];

        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            return new WP_REST_Response([
                'success' => true,
                'is_registered' => false,
                'can_use_scanner' => false, // Block scanner - require device registration
                'device_count' => 0,
                'max_devices' => self::MAX_DEVICES_PER_USER,
                'message' => 'Kein Fingerprint',
                'gps' => $gps_data
            ], 200);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $parent_store_id = self::get_parent_store_id($store_id);

        // Debug: Log the check
        ppv_log("📱 [Device Check] store_id={$store_id}, parent_store_id={$parent_store_id}, fp_hash=" . substr($fingerprint_hash, 0, 16) . "...");

        // Get all registered hashes for comparison
        $registered_hashes = $wpdb->get_col($wpdb->prepare(
            "SELECT fingerprint_hash FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND status = 'active'",
            $parent_store_id
        ));

        ppv_log("📱 [Device Check] Registered hashes: " . json_encode(array_map(fn($h) => substr($h, 0, 16) . '...', $registered_hashes)));

        $is_registered = self::is_device_registered_for_user($parent_store_id, $fingerprint_hash);
        $device_count = self::get_user_device_count($parent_store_id);

        ppv_log("📱 [Device Check] is_registered=" . ($is_registered ? 'YES' : 'NO') . ", device_count={$device_count}");

        // Allow scanner only if device is registered
        $can_use_scanner = $is_registered;

        // Update last_used_at if registered
        if ($is_registered) {
            $wpdb->update(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                ['last_used_at' => current_time('mysql')],
                ['store_id' => $parent_store_id, 'fingerprint_hash' => $fingerprint_hash],
                ['%s'],
                ['%d', '%s']
            );
        }

        return new WP_REST_Response([
            'success' => true,
            'is_registered' => $is_registered,
            'can_use_scanner' => $can_use_scanner,
            'device_count' => $device_count,
            'max_devices' => self::MAX_DEVICES_PER_USER,
            // GPS geofencing data
            'gps' => $gps_data,
            // Debug info
            'debug' => [
                'current_hash' => substr($fingerprint_hash, 0, 16) . '...',
                'registered_hashes' => array_map(fn($h) => substr($h, 0, 16) . '...', $registered_hashes),
                'store_id' => $parent_store_id
            ]
        ], 200);
    }

    /**
     * REST: Update device fingerprint (when browser fingerprint changed)
     * This allows updating the fingerprint of an existing device without admin approval
     */
    public static function rest_update_device_fingerprint(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $device_id = intval($data['device_id'] ?? 0);
        $new_fingerprint = sanitize_text_field($data['fingerprint'] ?? '');
        $device_info = $data['device_info'] ?? null; // FingerprintJS components data

        if ($device_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültige Geräte-ID'
            ], 400);
        }

        if (empty($new_fingerprint) || strlen($new_fingerprint) < 16) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint'
            ], 400);
        }

        $parent_store_id = self::get_parent_store_id($store_id);
        $new_fingerprint_hash = self::hash_fingerprint($new_fingerprint);

        // Prepare device_info JSON
        $device_info_json = null;
        if (!empty($device_info) && is_array($device_info)) {
            $device_info_json = wp_json_encode($device_info);
        }

        // Check if device exists for this store
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE id = %d AND store_id = %d",
            $device_id, $parent_store_id
        ));

        if (!$device) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gerät nicht gefunden'
            ], 404);
        }

        // Check if new fingerprint is already registered to another device
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND fingerprint_hash = %s AND id != %d",
            $parent_store_id, $new_fingerprint_hash, $device_id
        ));

        if ($existing) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Fingerprint bereits für ein anderes Gerät registriert'
            ], 400);
        }

        // Update the fingerprint
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $update_data = [
            'fingerprint_hash' => $new_fingerprint_hash,
            'user_agent' => $user_agent,
            'ip_address' => $ip_address,
            'last_used_at' => current_time('mysql')
        ];
        $update_format = ['%s', '%s', '%s', '%s'];

        // Only add device_info if we have data AND column exists
        if ($device_info_json && self::column_exists('device_info')) {
            $update_data['device_info'] = $device_info_json;
            $update_format[] = '%s';
        }

        $result = $wpdb->update(
            $wpdb->prefix . self::USER_DEVICES_TABLE,
            $update_data,
            ['id' => $device_id],
            $update_format,
            ['%d']
        );

        if ($result !== false) {
            ppv_log("📱 [Device Update] Fingerprint updated: device_id={$device_id}, old_hash=" . substr($device->fingerprint_hash, 0, 16) . "..., new_hash=" . substr($new_fingerprint_hash, 0, 16) . "...");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Fingerprint erfolgreich aktualisiert'
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Fehler beim Aktualisieren'
        ], 500);
    }

    /**
     * REST: Request device removal (needs admin approval)
     */
    public static function rest_request_device_removal(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $device_id = intval($data['device_id'] ?? 0);

        if ($device_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültige Geräte-ID'
            ], 400);
        }

        $parent_store_id = self::get_parent_store_id($store_id);

        // Get device info
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE id = %d AND store_id = %d",
            $device_id, $parent_store_id
        ));

        if (!$device) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gerät nicht gefunden'
            ], 404);
        }

        // Create approval request
        $result = self::create_device_request($parent_store_id, $device->fingerprint_hash, 'remove', $device->device_name);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Löschungsanfrage gesendet. Admin-Genehmigung erforderlich.'
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message'] ?? 'Fehler beim Erstellen der Anfrage'
        ], 500);
    }

    /**
     * REST: Request adding new device (when limit reached)
     */
    public static function rest_request_device_add(WP_REST_Request $request) {
        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $fingerprint = sanitize_text_field($data['fingerprint'] ?? '');
        $device_name = sanitize_text_field($data['device_name'] ?? 'Neues Gerät');

        if (empty($fingerprint) || strlen($fingerprint) < 16) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint'
            ], 400);
        }

        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $parent_store_id = self::get_parent_store_id($store_id);

        // Create approval request
        $result = self::create_device_request($parent_store_id, $fingerprint_hash, 'add', $device_name);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Anfrage für neues Gerät gesendet. Admin-Genehmigung erforderlich.'
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => $result['message'] ?? 'Fehler beim Erstellen der Anfrage'
        ], 500);
    }

    /**
     * REST: Approve device request (via email link)
     */
    public static function rest_approve_device_request(WP_REST_Request $request) {
        global $wpdb;

        $token = sanitize_text_field($request->get_param('token'));

        if (empty($token)) {
            return self::render_approval_page('error', 'Ungültiger Token');
        }

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . " WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            return self::render_approval_page('error', 'Anfrage nicht gefunden oder bereits verarbeitet');
        }

        // Process the request based on type
        if ($req->request_type === 'add') {
            // Add the device
            $ip_address = self::get_client_ip();
            $wpdb->insert(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                [
                    'store_id' => $req->store_id,
                    'fingerprint_hash' => $req->fingerprint_hash,
                    'device_name' => $req->device_name,
                    'user_agent' => $req->user_agent,
                    'ip_address' => $ip_address,
                    'registered_at' => current_time('mysql'),
                    'status' => 'active'
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            ppv_log("📱 [Device Approved] ADD: store_id={$req->store_id}, hash={$req->fingerprint_hash}");
            $action_text = 'Gerät erfolgreich hinzugefügt!';
        } elseif ($req->request_type === 'remove') {
            // Remove the device
            $wpdb->delete(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                ['store_id' => $req->store_id, 'fingerprint_hash' => $req->fingerprint_hash],
                ['%d', '%s']
            );
            ppv_log("📱 [Device Approved] REMOVE: store_id={$req->store_id}, hash={$req->fingerprint_hash}");
            $action_text = 'Gerät erfolgreich entfernt!';
        } elseif ($req->request_type === 'mobile_scanner') {
            // Enable mobile scanner for this store
            $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['scanner_type' => 'mobile'],
                ['id' => $req->store_id],
                ['%s'],
                ['%d']
            );
            ppv_log("📱 [Mobile Scanner Approved] store_id={$req->store_id}");
            $action_text = 'Mobile Scanner erfolgreich aktiviert!';
        } else {
            return self::render_approval_page('error', 'Unbekannter Anfragetyp');
        }

        // Mark request as approved
        $wpdb->update(
            $wpdb->prefix . self::DEVICE_REQUESTS_TABLE,
            [
                'status' => 'approved',
                'processed_at' => current_time('mysql'),
                'processed_by' => 'admin_link'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        return self::render_approval_page('success', $action_text);
    }

    /**
     * REST: Reject device request (via email link)
     */
    public static function rest_reject_device_request(WP_REST_Request $request) {
        global $wpdb;

        $token = sanitize_text_field($request->get_param('token'));

        if (empty($token)) {
            return self::render_approval_page('error', 'Ungültiger Token');
        }

        $req = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . " WHERE approval_token = %s AND status = 'pending'",
            $token
        ));

        if (!$req) {
            return self::render_approval_page('error', 'Anfrage nicht gefunden oder bereits verarbeitet');
        }

        // Mark request as rejected
        $wpdb->update(
            $wpdb->prefix . self::DEVICE_REQUESTS_TABLE,
            [
                'status' => 'rejected',
                'processed_at' => current_time('mysql'),
                'processed_by' => 'admin_link'
            ],
            ['id' => $req->id],
            ['%s', '%s', '%s'],
            ['%d']
        );

        ppv_log("📱 [Device Rejected] type={$req->request_type}, store_id={$req->store_id}");

        return self::render_approval_page('info', 'Anfrage wurde abgelehnt.');
    }

    /**
     * Render approval result page
     */
    private static function render_approval_page($type, $message) {
        $colors = [
            'success' => '#4caf50',
            'error' => '#f44336',
            'info' => '#2196f3'
        ];
        $icons = [
            'success' => '✅',
            'error' => '❌',
            'info' => 'ℹ️'
        ];

        $color = $colors[$type] ?? '#999';
        $icon = $icons[$type] ?? '📱';

        $html = "<!DOCTYPE html><html><head><meta charset='UTF-8'><meta name='viewport' content='width=device-width,initial-scale=1'>
        <title>PunktePass - Geräteverwaltung</title>
        <style>body{font-family:Arial,sans-serif;background:#0f0f1e;color:#fff;display:flex;justify-content:center;align-items:center;min-height:100vh;margin:0;}
        .card{background:#1a1a2e;padding:40px;border-radius:15px;text-align:center;max-width:400px;box-shadow:0 10px 40px rgba(0,0,0,0.5);}
        .icon{font-size:48px;margin-bottom:20px;}
        .message{font-size:18px;color:{$color};margin-bottom:20px;}
        .close-btn{background:{$color};color:#fff;border:none;padding:12px 30px;border-radius:8px;cursor:pointer;font-size:16px;}
        </style></head><body>
        <div class='card'>
            <div class='icon'>{$icon}</div>
            <div class='message'>{$message}</div>
            <button class='close-btn' onclick='window.close()'>Schließen</button>
        </div>
        </body></html>";

        return new WP_REST_Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    // ========================================
    // 📱 USER DEVICE HELPER METHODS
    // ========================================

    /**
     * Get all registered devices for a store
     */
    public static function get_user_devices($store_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT id, device_name, fingerprint_hash, user_agent, device_info, ip_address, registered_at, last_used_at, status
            FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . "
            WHERE store_id = %d AND status = 'active'
            ORDER BY registered_at DESC
        ", $store_id));
    }

    /**
     * Get device count for a store
     */
    public static function get_user_device_count($store_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND status = 'active'",
            $store_id
        ));
    }

    /**
     * Check if a device is registered for a user
     */
    public static function is_device_registered_for_user($store_id, $fingerprint_hash) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND fingerprint_hash = %s AND status = 'active'",
            $store_id, $fingerprint_hash
        ));
    }

    /**
     * Create device approval request and send email
     */
    private static function create_device_request($store_id, $fingerprint_hash, $type, $device_name) {
        global $wpdb;

        // Check for existing pending request
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE store_id = %d AND fingerprint_hash = %s AND request_type = %s AND status = 'pending'",
            $store_id, $fingerprint_hash, $type
        ));

        if ($existing) {
            return [
                'success' => false,
                'message' => 'Es gibt bereits eine ausstehende Anfrage für dieses Gerät'
            ];
        }

        // Generate approval token
        $token = wp_generate_password(32, false);
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        // Insert request
        $result = $wpdb->insert(
            $wpdb->prefix . self::DEVICE_REQUESTS_TABLE,
            [
                'store_id' => $store_id,
                'fingerprint_hash' => $fingerprint_hash,
                'request_type' => $type,
                'device_name' => $device_name,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'approval_token' => $token,
                'status' => 'pending',
                'requested_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return ['success' => false, 'message' => 'Datenbankfehler'];
        }

        // Send email to admin
        self::send_device_request_email($store_id, $type, $device_name, $token);

        ppv_log("📱 [Device Request] Created: type={$type}, store_id={$store_id}, token={$token}");

        return ['success' => true, 'token' => $token];
    }

    /**
     * Send device request email to admin
     */
    private static function send_device_request_email($store_id, $type, $device_name, $token) {
        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        $store_name = $store->name ?? "Store #{$store_id}";
        $store_email = $store->email ?? '';

        // Admin email - use same as other notifications
        $admin_email = 'info@punktepass.de';

        $type_text = $type === 'add' ? 'Neues Gerät hinzufügen' : 'Gerät entfernen';
        $site_url = site_url();

        $approve_url = "{$site_url}/wp-json/punktepass/v1/user-devices/approve/{$token}";
        $reject_url = "{$site_url}/wp-json/punktepass/v1/user-devices/reject/{$token}";

        $subject = "[PunktePass] Geräte-Anfrage: {$type_text} - {$store_name}";

        $message = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .card { background: #fff; padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; }
            h2 { color: #333; margin-top: 0; }
            .info { background: #f0f0f0; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 25px; border-radius: 8px; text-decoration: none; color: #fff; margin-right: 10px; }
            .btn-approve { background: #4caf50; }
            .btn-reject { background: #f44336; }
        </style></head>
        <body>
        <div class='card'>
            <h2>📱 Geräte-Anfrage</h2>
            <p><strong>Aktion:</strong> {$type_text}</p>
            <div class='info'>
                <p><strong>Geschäft:</strong> {$store_name}</p>
                <p><strong>Gerätename:</strong> {$device_name}</p>
                <p><strong>Zeitpunkt:</strong> " . current_time('d.m.Y H:i') . "</p>
            </div>
            <p>Bitte bestätigen oder ablehnen:</p>
            <a href='{$approve_url}' class='btn btn-approve'>✅ Genehmigen</a>
            <a href='{$reject_url}' class='btn btn-reject'>❌ Ablehnen</a>
        </div>
        </body>
        </html>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>'
        ];

        ppv_log("📧 [Device Email] Attempting to send to: {$admin_email}");
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        ppv_log("📧 [Device Email] Result: " . ($sent ? 'SUCCESS' : 'FAILED') . " - to={$admin_email}, store_id={$store_id}");
    }

    // ========================================
    // 📱 MOBILE SCANNER REQUEST METHODS
    // ========================================

    /**
     * REST: Get mobile scanner status for current store
     */
    public static function rest_get_mobile_scanner_status(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        // Get current scanner type
        $scanner_type = $wpdb->get_var($wpdb->prepare(
            "SELECT scanner_type FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // Check for pending request
        $pending_request = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE store_id = %d AND request_type = 'mobile_scanner' AND status = 'pending'
             ORDER BY requested_at DESC LIMIT 1",
            $store_id
        ));

        return new WP_REST_Response([
            'success' => true,
            'scanner_type' => $scanner_type ?: 'fixed',
            'is_mobile' => ($scanner_type === 'mobile'),
            'has_pending_request' => !empty($pending_request),
            'pending_request' => $pending_request ? [
                'id' => $pending_request->id,
                'requested_at' => $pending_request->requested_at
            ] : null
        ], 200);
    }

    /**
     * REST: Request mobile scanner mode (needs admin approval)
     */
    public static function rest_request_mobile_scanner(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        // Check if already mobile
        $scanner_type = $wpdb->get_var($wpdb->prepare(
            "SELECT scanner_type FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if ($scanner_type === 'mobile') {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Mobile Scanner ist bereits aktiviert'
            ], 200);
        }

        // Check for existing pending request
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE store_id = %d AND request_type = 'mobile_scanner' AND status = 'pending'",
            $store_id
        ));

        if ($existing) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Es gibt bereits eine ausstehende Anfrage'
            ], 200);
        }

        // Create the request
        $token = wp_generate_password(32, false);
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $result = $wpdb->insert(
            $wpdb->prefix . self::DEVICE_REQUESTS_TABLE,
            [
                'store_id' => $store_id,
                'fingerprint_hash' => 'mobile_scanner_request',
                'request_type' => 'mobile_scanner',
                'device_name' => 'Mobile Scanner Anfrage',
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'approval_token' => $token,
                'status' => 'pending',
                'requested_at' => current_time('mysql')
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Datenbankfehler'
            ], 500);
        }

        // Send email to admin
        self::send_mobile_scanner_request_email($store_id, $token);

        ppv_log("📱 [Mobile Scanner] Request created: store_id={$store_id}, token={$token}");

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Anfrage gesendet! Der Admin wird per E-Mail benachrichtigt.'
        ], 200);
    }

    /**
     * Send mobile scanner request email to admin
     */
    private static function send_mobile_scanner_request_email($store_id, $token) {
        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, email, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        $store_name = $store->name ?? "Store #{$store_id}";
        $store_city = $store->city ?? '';

        // Admin email - use same as other notifications
        $admin_email = 'info@punktepass.de';
        $site_url = site_url();

        $approve_url = "{$site_url}/wp-json/punktepass/v1/user-devices/approve/{$token}";
        $reject_url = "{$site_url}/wp-json/punktepass/v1/user-devices/reject/{$token}";

        $subject = "[PunktePass] Mobile Scanner Anfrage - {$store_name}";

        $message = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .card { background: #fff; padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; }
            h2 { color: #333; margin-top: 0; }
            .info { background: #f0f0f0; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .warning { background: #fff3e0; border: 1px solid #ff9800; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 25px; border-radius: 8px; text-decoration: none; color: #fff; margin-right: 10px; }
            .btn-approve { background: #4caf50; }
            .btn-reject { background: #f44336; }
        </style></head>
        <body>
        <div class='card'>
            <h2>📱 Mobile Scanner Anfrage</h2>
            <p>Ein Geschäft möchte den <strong>Mobile Scanner</strong> Modus aktivieren.</p>
            <div class='info'>
                <p><strong>Geschäft:</strong> {$store_name}</p>
                <p><strong>Stadt:</strong> {$store_city}</p>
                <p><strong>Store ID:</strong> {$store_id}</p>
                <p><strong>Zeitpunkt:</strong> " . current_time('d.m.Y H:i') . "</p>
            </div>
            <div class='warning'>
                <p><strong>⚠️ Hinweis:</strong> Bei Mobile Scanner wird die GPS-Prüfung deaktiviert. Das Gerät kann von überall scannen.</p>
            </div>
            <p>Bitte bestätigen oder ablehnen:</p>
            <a href='{$approve_url}' class='btn btn-approve'>✅ Genehmigen</a>
            <a href='{$reject_url}' class='btn btn-reject'>❌ Ablehnen</a>
        </div>
        </body>
        </html>";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>'
        ];

        ppv_log("📧 [Mobile Scanner Email] Attempting to send to: {$admin_email}");
        $sent = wp_mail($admin_email, $subject, $message, $headers);
        ppv_log("📧 [Mobile Scanner Email] Result: " . ($sent ? 'SUCCESS' : 'FAILED') . " - to={$admin_email}, store_id={$store_id}");
    }
}
