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

    // Device deletion log table
    const DEVICE_DELETION_LOG_TABLE = 'ppv_device_deletion_log';

    // Maximum devices per user (store owner) - BASE value
    const MAX_DEVICES_PER_USER = 2;

    /**
     * 🏢 Get dynamic max devices for store (base + 1 per filiale)
     * Base: 2 devices
     * Each filiale adds +1 device slot
     *
     * @param int $store_id Store ID (can be parent or child)
     * @return int Maximum allowed devices
     */
    public static function get_max_devices_for_store($store_id) {
        global $wpdb;

        if ($store_id <= 0) {
            return self::MAX_DEVICES_PER_USER;
        }

        // Get parent store ID
        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        if (!$parent_id) {
            return self::MAX_DEVICES_PER_USER;
        }

        // Count filialen (children of parent)
        $filiale_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = %d",
            $parent_id
        ));

        // Base (2) + 1 per filiale
        return self::MAX_DEVICES_PER_USER + intval($filiale_count);
    }

    // Fingerprint validation constants
    const FINGERPRINT_MIN_LENGTH = 16;
    const FINGERPRINT_MAX_LENGTH = 64;

    /**
     * Validate device fingerprint format
     * FingerprintJS v4 generates 20-character alphanumeric visitorId
     *
     * @param string $fingerprint The fingerprint to validate
     * @param bool $allow_empty Whether to allow empty fingerprints (for optional cases)
     * @return array ['valid' => bool, 'error' => string|null, 'sanitized' => string|null]
     */
    public static function validate_fingerprint($fingerprint, $allow_empty = false) {
        // Sanitize first
        $fingerprint = sanitize_text_field($fingerprint ?? '');

        // Check if empty
        if (empty($fingerprint)) {
            if ($allow_empty) {
                return ['valid' => true, 'error' => null, 'sanitized' => '', 'skipped' => true];
            }
            return ['valid' => false, 'error' => 'empty_fingerprint', 'sanitized' => null];
        }

        // Check minimum length
        if (strlen($fingerprint) < self::FINGERPRINT_MIN_LENGTH) {
            ppv_log("[Security] ⚠️ Fingerprint too short: " . strlen($fingerprint) . " chars");
            return ['valid' => false, 'error' => 'fingerprint_too_short', 'sanitized' => null];
        }

        // Check maximum length (prevent DoS with huge strings)
        if (strlen($fingerprint) > self::FINGERPRINT_MAX_LENGTH) {
            ppv_log("[Security] ⚠️ Fingerprint too long: " . strlen($fingerprint) . " chars");
            return ['valid' => false, 'error' => 'fingerprint_too_long', 'sanitized' => null];
        }

        // Check for valid characters (alphanumeric only - XSS prevention)
        if (!preg_match('/^[a-zA-Z0-9]+$/', $fingerprint)) {
            ppv_log("[Security] ⚠️ Fingerprint contains invalid characters");
            return ['valid' => false, 'error' => 'fingerprint_invalid_chars', 'sanitized' => null];
        }

        return ['valid' => true, 'error' => null, 'sanitized' => $fingerprint];
    }

    /**
     * Quick fingerprint validation (returns bool only)
     * Use this for simple checks where you don't need detailed error info
     */
    public static function is_valid_fingerprint($fingerprint, $allow_empty = false) {
        $result = self::validate_fingerprint($fingerprint, $allow_empty);
        return $result['valid'];
    }

    /**
     * Register hooks
     */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('admin_init', [__CLASS__, 'run_migrations']);
        add_action('init', [__CLASS__, 'run_migrations']); // Also run on init for standalone admin
    }

    /**
     * Run database migrations for new tables
     */
    public static function run_migrations() {
        global $wpdb;

        // Prevent running multiple times in same request
        static $already_run = false;
        if ($already_run) return;
        $already_run = true;

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
            $migration_version = '1.5';
        }

        // Migration 1.6: Add mobile_scanner column to user_devices (per-device mobile scanner)
        if (version_compare($migration_version, '1.6', '<')) {
            $table_user_devices = $wpdb->prefix . self::USER_DEVICES_TABLE;
            $table_requests = $wpdb->prefix . self::DEVICE_REQUESTS_TABLE;

            // Add mobile_scanner column to user_devices table (per-device setting)
            $wpdb->query("ALTER TABLE {$table_user_devices} ADD COLUMN mobile_scanner TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=device is mobile scanner' AFTER status");

            // Add device_id column to device_requests table (for device-specific mobile scanner requests)
            $wpdb->query("ALTER TABLE {$table_requests} ADD COLUMN device_id BIGINT(20) UNSIGNED NULL COMMENT 'Target device ID for mobile_scanner requests' AFTER store_id");

            ppv_log("✅ [PPV_Device_Fingerprint] Added mobile_scanner column (per-device) and device_id to requests");
            update_option('ppv_device_migration_version', '1.6');
            $migration_version = '1.6';
        }

        // Migration 1.7: Add device deletion log table
        if (version_compare($migration_version, '1.7', '<')) {
            $table_deletion_log = $wpdb->prefix . self::DEVICE_DELETION_LOG_TABLE;

            $sql = "CREATE TABLE IF NOT EXISTS {$table_deletion_log} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                device_id BIGINT(20) UNSIGNED NOT NULL,
                device_name VARCHAR(255) NULL,
                fingerprint_hash VARCHAR(64) NOT NULL,
                deleted_by_user_id BIGINT(20) UNSIGNED NOT NULL COMMENT 'PPV user ID who deleted',
                deleted_by_user_type ENUM('handler', 'scanner') NOT NULL COMMENT 'User type who deleted',
                deleted_by_user_email VARCHAR(255) NULL,
                ip_address VARCHAR(45) NULL,
                deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_store (store_id),
                KEY idx_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            ppv_log("✅ [PPV_Device_Fingerprint] Device deletion log table created");
            update_option('ppv_device_migration_version', '1.7');
            $migration_version = '1.7';
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

        // Request device removal (needs admin approval) - LEGACY, kept for backwards compatibility
        register_rest_route('punktepass/v1', '/user-devices/request-remove', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_device_removal'],
            'permission_callback' => '__return_true'
        ]);

        // Direct device deletion (no admin approval needed)
        register_rest_route('punktepass/v1', '/user-devices/delete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_delete_device_direct'],
            'permission_callback' => '__return_true'
        ]);

        // Request adding new device (when limit reached)
        register_rest_route('punktepass/v1', '/user-devices/request-add', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_device_add'],
            'permission_callback' => '__return_true'
        ]);

        // Request new device slot (for already registered users)
        register_rest_route('punktepass/v1', '/user-devices/request-new-slot', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_new_device_slot'],
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

        // Validate fingerprint (allow empty for fallback)
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '', true);
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'allowed' => false,
                'blocked' => false,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE,
                'error' => $fp_validation['error']
            ], 400);
        }

        // If fingerprint was empty/skipped, allow registration (fallback)
        if (!empty($fp_validation['skipped'])) {
            return new WP_REST_Response([
                'allowed' => true,
                'blocked' => false,
                'accounts' => 0,
                'limit' => self::MAX_ACCOUNTS_PER_DEVICE
            ], 200);
        }

        $fingerprint = $fp_validation['sanitized'];
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
        $user_id = intval($data['user_id'] ?? 0);
        $fingerprint_components = $data['components'] ?? null;

        // Validate fingerprint
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '');
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid fingerprint: ' . $fp_validation['error']
            ], 400);
        }

        if ($user_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Missing user_id'
            ], 400);
        }

        $fingerprint = $fp_validation['sanitized'];
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
        global $wpdb;

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

        // Fallback: get store_id from database if user_id is set
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
            $user_data = $wpdb->get_row($wpdb->prepare(
                "SELECT user_type, vendor_store_id FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                $user_id
            ));

            if ($user_data && !empty($user_data->vendor_store_id)) {
                // Cache in session for future calls
                $_SESSION['ppv_store_id'] = intval($user_data->vendor_store_id);
                if (!empty($user_data->user_type)) {
                    $_SESSION['ppv_user_type'] = $user_data->user_type;
                }
                ppv_log("📱 [Device] get_session_store_id fallback: user_id={$user_id}, store_id={$user_data->vendor_store_id}, type={$user_data->user_type}");
                return intval($user_data->vendor_store_id);
            }
        }

        // ✅ NEW: Token-based auth fallback (for scanner users via REST API)
        $token = null;
        if (!empty($_COOKIE['ppv_user_token'])) {
            $token = sanitize_text_field($_COOKIE['ppv_user_token']);
        }

        if ($token) {
            // Try to find user by login_token
            $user_data = $wpdb->get_row($wpdb->prepare(
                "SELECT id, user_type, vendor_store_id FROM {$wpdb->prefix}ppv_users WHERE login_token = %s AND active = 1 LIMIT 1",
                $token
            ));

            if ($user_data && !empty($user_data->vendor_store_id)) {
                // Cache in session for future calls
                $_SESSION['ppv_user_id'] = intval($user_data->id);
                $_SESSION['ppv_store_id'] = intval($user_data->vendor_store_id);
                if (!empty($user_data->user_type)) {
                    $_SESSION['ppv_user_type'] = $user_data->user_type;
                }
                ppv_log("📱 [Device] get_session_store_id TOKEN fallback: user_id={$user_data->id}, store_id={$user_data->vendor_store_id}, type={$user_data->user_type}");
                return intval($user_data->vendor_store_id);
            }
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
     * Calculate similarity score between two fingerprint component sets
     * Returns 0-100 (percentage of matching components)
     *
     * @param array $current_components Current device components
     * @param array|string $stored_components Stored device_info JSON or array
     * @return int Similarity percentage (0-100)
     */
    public static function calculate_fingerprint_similarity($current_components, $stored_components) {
        if (empty($current_components) || empty($stored_components)) {
            return 0;
        }

        // Parse stored components if JSON string
        if (is_string($stored_components)) {
            $stored_components = json_decode($stored_components, true);
        }

        if (!is_array($current_components) || !is_array($stored_components)) {
            return 0;
        }

        // Components to compare (with weights - higher weight = more important for device identity)
        $components_weights = [
            'platform' => 15,           // OS platform - very stable
            'timezone' => 10,           // Timezone - stable unless traveling
            'languages' => 5,           // Browser languages - stable
            'colorDepth' => 5,          // Screen color depth - stable
            'deviceMemory' => 10,       // RAM - stable
            'hardwareConcurrency' => 10, // CPU cores - stable
            'screenResolution' => 10,   // Screen size - stable per device
            'vendor' => 8,              // Browser vendor - stable
            'vendorFlavors' => 5,       // Browser flavor - can change with updates
            'cookiesEnabled' => 2,      // Cookies - usually stable
            'colorGamut' => 5,          // Display color gamut - stable
            'audio' => 8,               // Audio fingerprint - fairly stable
            'canvas' => 5,              // Canvas fingerprint - can change with GPU driver updates
            'webGlBasics' => 2,         // WebGL info - can change with driver updates
        ];

        $total_weight = 0;
        $matched_weight = 0;

        foreach ($components_weights as $key => $weight) {
            $total_weight += $weight;

            $current_value = $current_components[$key] ?? null;
            $stored_value = $stored_components[$key] ?? null;

            if ($current_value === null || $stored_value === null) {
                continue; // Skip if component missing from either
            }

            // Normalize for comparison
            $current_normalized = self::normalize_component_value($current_value);
            $stored_normalized = self::normalize_component_value($stored_value);

            if ($current_normalized === $stored_normalized) {
                $matched_weight += $weight;
            }
        }

        if ($total_weight === 0) {
            return 0;
        }

        return intval(round(($matched_weight / $total_weight) * 100));
    }

    /**
     * Normalize component value for comparison
     */
    private static function normalize_component_value($value) {
        if (is_array($value)) {
            // Sort arrays for consistent comparison
            sort($value);
            return json_encode($value);
        }
        if (is_float($value)) {
            return round($value, 2);
        }
        return $value;
    }

    /**
     * Find best matching device by similarity
     *
     * @param int $store_id Parent store ID
     * @param array $current_components Current device components
     * @param int $threshold Minimum similarity threshold (default 80%)
     * @return array|null Best matching device with similarity score, or null
     */
    public static function find_similar_device($store_id, $current_components, $threshold = 80) {
        global $wpdb;

        if (empty($current_components) || $store_id <= 0) {
            return null;
        }

        // Get all registered devices with device_info
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, fingerprint_hash, device_name, device_info, mobile_scanner
             FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . "
             WHERE store_id = %d AND status = 'active' AND device_info IS NOT NULL",
            $store_id
        ));

        if (empty($devices)) {
            return null;
        }

        $best_match = null;
        $best_score = 0;

        foreach ($devices as $device) {
            $score = self::calculate_fingerprint_similarity($current_components, $device->device_info);

            ppv_log("📱 [Similarity] Device #{$device->id} ({$device->device_name}): {$score}%");

            if ($score >= $threshold && $score > $best_score) {
                $best_score = $score;
                $best_match = [
                    'device_id' => intval($device->id),
                    'fingerprint_hash' => $device->fingerprint_hash,
                    'device_name' => $device->device_name,
                    'mobile_scanner' => !empty($device->mobile_scanner),
                    'similarity' => $score
                ];
            }
        }

        return $best_match;
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

        $max_devices = self::get_max_devices_for_store($parent_store_id);

        return new WP_REST_Response([
            'success' => true,
            'devices' => $devices,
            'max_devices' => $max_devices,
            'can_add_more' => count($devices) < $max_devices
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
        $device_name = sanitize_text_field($data['device_name'] ?? 'Unbenanntes Gerät');
        $device_info = $data['device_info'] ?? null; // FingerprintJS components data

        // Validate fingerprint
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '');
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint: ' . $fp_validation['error']
            ], 400);
        }

        $fingerprint = $fp_validation['sanitized'];
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

        // Check device limit (dynamic: base + 1 per filiale)
        $device_count = self::get_user_device_count($parent_store_id);
        $max_devices = self::get_max_devices_for_store($parent_store_id);
        if ($device_count >= $max_devices) {
            // Check if there's an available slot to claim
            $available_slot = self::get_available_device_slot($parent_store_id);
            if (!$available_slot) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Gerätelimit erreicht. Bitte Admin-Genehmigung anfordern.',
                    'limit_reached' => true,
                    'device_count' => $device_count,
                    'max_devices' => $max_devices
                ], 200);
            }

            // Claim the available slot - update it with the new device's info
            ppv_log("📱 [Device Slot Claim] Claiming slot ID={$available_slot->id} for new device, store_id={$parent_store_id}");

            $ip_address = self::get_client_ip();
            $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

            $update_data = [
                'fingerprint_hash' => $fingerprint_hash,
                'device_name' => $device_name,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'registered_at' => current_time('mysql'),
                'last_used_at' => current_time('mysql'),
                'status' => 'active'
            ];
            $update_format = ['%s', '%s', '%s', '%s', '%s', '%s', '%s'];

            // Add device_info if available
            if ($device_info_json && self::column_exists('device_info')) {
                $update_data['device_info'] = $device_info_json;
                $update_format[] = '%s';
            }

            $wpdb->update(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                $update_data,
                ['id' => $available_slot->id],
                $update_format,
                ['%d']
            );

            ppv_log("📱 [Device Registered via Slot] store_id={$parent_store_id}, hash=" . substr($fingerprint_hash, 0, 16) . "..., name={$device_name}");

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Gerät erfolgreich registriert (genehmigter Platz verwendet)!',
                'slot_used' => true
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

        // Validate fingerprint (allow empty for response with 'can_use_scanner' = false)
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '', true);
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid fingerprint: ' . $fp_validation['error'],
                'gps' => $gps_data
            ], 400);
        }

        // If fingerprint was empty/skipped
        if (!empty($fp_validation['skipped'])) {
            return new WP_REST_Response([
                'success' => true,
                'is_registered' => false,
                'can_use_scanner' => false, // Block scanner - require device registration
                'device_count' => 0,
                'max_devices' => self::get_max_devices_for_store($store_id),
                'message' => 'Kein Fingerprint',
                'gps' => $gps_data
            ], 200);
        }

        $fingerprint = $fp_validation['sanitized'];
        $fingerprint_hash = self::hash_fingerprint($fingerprint);
        $parent_store_id = self::get_parent_store_id($store_id);

        // Get components for similarity matching (auto-update feature)
        $components = $data['components'] ?? null;

        // Debug: Log the check
        ppv_log("📱 [Device Check] store_id={$store_id}, parent_store_id={$parent_store_id}, fp_hash=" . substr($fingerprint_hash, 0, 16) . "..., has_components=" . (!empty($components) ? 'YES' : 'NO'));

        // Get all registered hashes for comparison
        $registered_hashes = $wpdb->get_col($wpdb->prepare(
            "SELECT fingerprint_hash FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND status = 'active'",
            $parent_store_id
        ));

        ppv_log("📱 [Device Check] Registered hashes: " . json_encode(array_map(fn($h) => substr($h, 0, 16) . '...', $registered_hashes)));

        $is_registered = self::is_device_registered_for_user($parent_store_id, $fingerprint_hash);
        $device_count = self::get_user_device_count($parent_store_id);
        $auto_updated = false;
        $similarity_score = null;

        ppv_log("📱 [Device Check] is_registered=" . ($is_registered ? 'YES' : 'NO') . ", device_count={$device_count}");

        // ========================================
        // 🔄 AUTO FINGERPRINT UPDATE (Similarity Matching)
        // ========================================
        // If device not registered but we have components, try similarity matching
        if (!$is_registered && !empty($components) && is_array($components)) {
            ppv_log("📱 [Device Check] Exact match failed - trying similarity matching...");

            $similar_device = self::find_similar_device($parent_store_id, $components, 80);

            if ($similar_device) {
                // Found a similar device - auto-update the fingerprint hash
                $similarity_score = $similar_device['similarity'];
                $old_fingerprint_hash = $similar_device['fingerprint_hash'];
                ppv_log("📱 [Auto-Update] Found similar device #{$similar_device['device_id']} ({$similar_device['device_name']}) with {$similarity_score}% similarity");

                // Update the fingerprint hash for this device
                $updated = $wpdb->update(
                    $wpdb->prefix . self::USER_DEVICES_TABLE,
                    [
                        'fingerprint_hash' => $fingerprint_hash,
                        'device_info' => wp_json_encode($components),
                        'last_used_at' => current_time('mysql')
                    ],
                    ['id' => $similar_device['device_id']],
                    ['%s', '%s', '%s'],
                    ['%d']
                );

                if ($updated !== false) {
                    // Also update old scan records in ppv_points to maintain statistics continuity
                    // NOTE: ppv_points stores RAW fingerprint, not hash
                    // We need to match by hash (SHA2) and update to new RAW fingerprint
                    $points_updated = $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->prefix}ppv_points
                         SET device_fingerprint = %s
                         WHERE SHA2(device_fingerprint, 256) = %s
                           AND store_id = %d",
                        $fingerprint, // NEW raw fingerprint
                        $old_fingerprint_hash, // OLD hash to match
                        $parent_store_id
                    ));

                    ppv_log("✅ [Auto-Update] Fingerprint auto-updated for device #{$similar_device['device_id']}, points_records_updated={$points_updated}");
                    $is_registered = true;
                    $auto_updated = true;
                } else {
                    ppv_log("❌ [Auto-Update] Failed to update fingerprint: " . $wpdb->last_error);
                }
            } else {
                ppv_log("📱 [Device Check] No similar device found (threshold: 80%)");
            }
        }

        // Allow scanner only if device is registered (including auto-updated)
        $can_use_scanner = $is_registered;

        // Check device-level mobile scanner status
        $device_mobile_scanner = false;
        $device_id = null;

        // Update last_used_at if registered and get mobile_scanner status
        if ($is_registered) {
            // Get device info including mobile_scanner status
            $device = $wpdb->get_row($wpdb->prepare(
                "SELECT id, mobile_scanner FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . "
                 WHERE store_id = %d AND fingerprint_hash = %s AND status = 'active'",
                $parent_store_id, $fingerprint_hash
            ));

            if ($device) {
                $device_id = intval($device->id);
                $device_mobile_scanner = !empty($device->mobile_scanner);

                // Update last_used_at (skip if auto_updated since we already updated)
                if (!$auto_updated) {
                    $wpdb->update(
                        $wpdb->prefix . self::USER_DEVICES_TABLE,
                        ['last_used_at' => current_time('mysql')],
                        ['id' => $device->id],
                        ['%s'],
                        ['%d']
                    );
                }
            }
        }

        // Also check legacy store-level mobile scanner for backwards compatibility
        $store_scanner_type = $wpdb->get_var($wpdb->prepare(
            "SELECT scanner_type FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $current_store_id
        ));
        $store_is_mobile = ($store_scanner_type === 'mobile');

        // Device is mobile scanner if either device-level OR store-level is enabled
        $is_mobile_scanner = $device_mobile_scanner || $store_is_mobile;

        // Check for available slots (pre-approved by admin)
        $available_slots = self::get_available_slot_count($parent_store_id);

        return new WP_REST_Response([
            'success' => true,
            'is_registered' => $is_registered,
            'can_use_scanner' => $can_use_scanner,
            'device_count' => $device_count,
            'max_devices' => self::get_max_devices_for_store($parent_store_id),
            'available_slots' => $available_slots, // Pre-approved slots ready to claim
            // Device info
            'device_id' => $device_id,
            // Auto fingerprint update info
            'auto_updated' => $auto_updated,
            'similarity_score' => $similarity_score,
            // Mobile scanner status (per-device)
            'is_mobile_scanner' => $is_mobile_scanner,
            'device_mobile_scanner' => $device_mobile_scanner,
            'store_mobile_scanner' => $store_is_mobile, // Legacy store-level
            // GPS geofencing data (only relevant if NOT mobile scanner)
            'gps' => $gps_data,
            // Debug info
            'debug' => [
                'current_hash' => substr($fingerprint_hash, 0, 16) . '...',
                'registered_hashes' => array_map(fn($h) => substr($h, 0, 16) . '...', $registered_hashes),
                'store_id' => $parent_store_id,
                'auto_updated' => $auto_updated,
                'similarity' => $similarity_score
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
        $device_info = $data['device_info'] ?? null; // FingerprintJS components data

        if ($device_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültige Geräte-ID'
            ], 400);
        }

        // Validate fingerprint
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '');
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint: ' . $fp_validation['error']
            ], 400);
        }

        $new_fingerprint = $fp_validation['sanitized'];
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
            $old_fingerprint_hash = $device->fingerprint_hash;

            // Also update old scan records in ppv_points to maintain statistics continuity
            // NOTE: ppv_points stores RAW fingerprint, not hash
            // We need to match by hash (SHA2) and update to new RAW fingerprint
            $points_updated = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->prefix}ppv_points
                 SET device_fingerprint = %s
                 WHERE SHA2(device_fingerprint, 256) = %s
                   AND store_id = %d",
                $new_fingerprint, // NEW raw fingerprint
                $old_fingerprint_hash, // OLD hash to match
                $parent_store_id
            ));

            ppv_log("📱 [Device Update] Fingerprint updated: device_id={$device_id}, old_hash=" . substr($old_fingerprint_hash, 0, 16) . "..., new_hash=" . substr($new_fingerprint_hash, 0, 16) . "..., points_records_updated={$points_updated}");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Fingerprint erfolgreich aktualisiert',
                'points_updated' => $points_updated
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Fehler beim Aktualisieren'
        ], 500);
    }

    /**
     * REST: Request device removal (needs admin approval) - LEGACY
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
     * REST: Direct device deletion (no admin approval needed)
     * Shop owners and scanner users can delete devices directly
     */
    public static function rest_delete_device_direct(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        // Get current user info for logging
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        $user_type = sanitize_text_field($_SESSION['ppv_user_type'] ?? 'handler');

        // Get user email
        $user_email = '';
        if ($user_id > 0) {
            $user_email = $wpdb->get_var($wpdb->prepare(
                "SELECT email FROM {$wpdb->prefix}ppv_users WHERE id = %d",
                $user_id
            ));
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

        // Get device info before deletion (for logging)
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

        // Log the deletion BEFORE deleting
        $ip_address = self::get_client_ip();
        $wpdb->insert(
            $wpdb->prefix . self::DEVICE_DELETION_LOG_TABLE,
            [
                'store_id' => $parent_store_id,
                'device_id' => $device_id,
                'device_name' => $device->device_name,
                'fingerprint_hash' => $device->fingerprint_hash,
                'deleted_by_user_id' => $user_id,
                'deleted_by_user_type' => ($user_type === 'scanner') ? 'scanner' : 'handler',
                'deleted_by_user_email' => $user_email,
                'ip_address' => $ip_address,
                'deleted_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%d', '%s', '%s', '%s', '%s']
        );

        ppv_log("📱 [Device Delete] Logged: device_id={$device_id}, device_name={$device->device_name}, deleted_by={$user_email} ({$user_type})");

        // Delete the device
        $result = $wpdb->delete(
            $wpdb->prefix . self::USER_DEVICES_TABLE,
            ['id' => $device_id, 'store_id' => $parent_store_id],
            ['%d', '%d']
        );

        if ($result) {
            ppv_log("📱 [Device Delete] SUCCESS: device_id={$device_id}, store_id={$parent_store_id}");
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Gerät erfolgreich gelöscht'
            ], 200);
        }

        return new WP_REST_Response([
            'success' => false,
            'message' => 'Fehler beim Löschen des Geräts'
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
        $device_name = sanitize_text_field($data['device_name'] ?? 'Neues Gerät');

        // Validate fingerprint
        $fp_validation = self::validate_fingerprint($data['fingerprint'] ?? '');
        if (!$fp_validation['valid']) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Ungültiger Fingerprint: ' . $fp_validation['error']
            ], 400);
        }

        $fingerprint = $fp_validation['sanitized'];
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
     * REST: Request a new device slot (for already registered users at limit)
     * Creates a request without specific fingerprint - user will register from new device later
     */
    public static function rest_request_new_device_slot(WP_REST_Request $request) {
        $store_id = self::get_session_store_id();

        if ($store_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Nicht authentifiziert'
            ], 401);
        }

        $data = $request->get_json_params();
        $device_name = sanitize_text_field($data['device_name'] ?? 'Neues Gerät');

        // Use a placeholder fingerprint - will be replaced when device registers
        $placeholder_hash = 'SLOT_PENDING_' . time() . '_' . wp_generate_password(8, false);
        $parent_store_id = self::get_parent_store_id($store_id);

        // Create approval request with type 'new_slot'
        $result = self::create_device_request($parent_store_id, $placeholder_hash, 'new_slot', $device_name);

        if ($result['success']) {
            return new WP_REST_Response([
                'success' => true,
                'message' => 'Anfrage für weiteres Gerät gesendet. Admin-Genehmigung erforderlich.'
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
            // Enable mobile scanner for this specific device (per-device setting)
            if (!empty($req->device_id)) {
                $wpdb->update(
                    $wpdb->prefix . self::USER_DEVICES_TABLE,
                    ['mobile_scanner' => 1],
                    ['id' => $req->device_id],
                    ['%d'],
                    ['%d']
                );
                ppv_log("📱 [Mobile Scanner Approved] device_id={$req->device_id}, store_id={$req->store_id}");
                $action_text = 'Mobile Scanner für Gerät erfolgreich aktiviert!';
            } else {
                // Legacy: store-level mobile scanner (for older requests without device_id)
                $wpdb->update(
                    $wpdb->prefix . 'ppv_stores',
                    ['scanner_type' => 'mobile'],
                    ['id' => $req->store_id],
                    ['%s'],
                    ['%d']
                );
                ppv_log("📱 [Mobile Scanner Approved - Legacy Store] store_id={$req->store_id}");
                $action_text = 'Mobile Scanner erfolgreich aktiviert!';
            }
        } elseif ($req->request_type === 'new_slot') {
            // Approve new device slot - create a placeholder entry with status='slot'
            // User can claim this slot when they register from a new device
            $wpdb->insert(
                $wpdb->prefix . self::USER_DEVICES_TABLE,
                [
                    'store_id' => $req->store_id,
                    'fingerprint_hash' => $req->fingerprint_hash, // Placeholder hash
                    'device_name' => $req->device_name . ' (reserviert)',
                    'user_agent' => 'Slot für neues Gerät genehmigt',
                    'ip_address' => null,
                    'registered_at' => current_time('mysql'),
                    'status' => 'slot' // Special status - can be claimed
                ],
                ['%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
            ppv_log("📱 [Device Slot Approved] NEW_SLOT: store_id={$req->store_id}, name={$req->device_name}");
            $action_text = 'Zusätzlicher Geräteplatz genehmigt! Der Benutzer kann jetzt ein neues Gerät registrieren.';
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
            SELECT id, device_name, fingerprint_hash, user_agent, device_info, ip_address, registered_at, last_used_at, status, mobile_scanner
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
     * Get an available device slot (pre-approved by admin)
     * Returns the first slot with status='slot' that can be claimed
     */
    public static function get_available_device_slot($store_id) {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare(
            "SELECT id, device_name FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . "
             WHERE store_id = %d AND status = 'slot'
             ORDER BY registered_at ASC
             LIMIT 1",
            $store_id
        ));
    }

    /**
     * Count available device slots for a store
     */
    public static function get_available_slot_count($store_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE store_id = %d AND status = 'slot'",
            $store_id
        ));
    }

    /**
     * Create device approval request and send email
     */
    // ═══════════════════════════════════════════════════════════════
    // DEVICE REQUEST COOLDOWN - 1 day limit (Fázis 1 - 2025-12)
    // ═══════════════════════════════════════════════════════════════
    const DEVICE_REQUEST_COOLDOWN_HOURS = 24; // 1 day cooldown

    private static function create_device_request($store_id, $fingerprint_hash, $type, $device_name) {
        global $wpdb;

        // ═══════════════════════════════════════════════════════════════
        // COOLDOWN CHECK - Prevent spam requests (max 1 request per 24 hours)
        // ═══════════════════════════════════════════════════════════════
        $cooldown_hours = self::DEVICE_REQUEST_COOLDOWN_HOURS;
        $recent_request = $wpdb->get_row($wpdb->prepare(
            "SELECT id, requested_at, status FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE store_id = %d AND fingerprint_hash = %s
             AND requested_at >= DATE_SUB(NOW(), INTERVAL %d HOUR)
             ORDER BY requested_at DESC LIMIT 1",
            $store_id, $fingerprint_hash, $cooldown_hours
        ));

        if ($recent_request) {
            $time_since = strtotime(current_time('mysql')) - strtotime($recent_request->requested_at);
            $hours_remaining = ceil(($cooldown_hours * 3600 - $time_since) / 3600);

            ppv_log("🚫 [Device Cooldown] Request blocked: store_id={$store_id}, hours_remaining={$hours_remaining}");

            return [
                'success' => false,
                'message' => "Bitte warten Sie noch {$hours_remaining} Stunde(n) bevor Sie eine neue Geräteanfrage stellen können.",
                'cooldown' => true,
                'hours_remaining' => $hours_remaining
            ];
        }

        // Check for existing pending request (legacy check)
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

        // Set type text based on request type
        if ($type === 'add') {
            $type_text = 'Neues Gerät hinzufügen';
        } elseif ($type === 'new_slot') {
            $type_text = 'Zusätzlichen Geräteplatz anfordern';
        } elseif ($type === 'remove') {
            $type_text = 'Gerät entfernen';
        } elseif ($type === 'mobile_scanner') {
            $type_text = 'Mobile Scanner aktivieren';
        } else {
            $type_text = 'Unbekannte Anfrage';
        }
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
     * REST: Get mobile scanner status for devices (per-device system)
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

        $parent_store_id = self::get_parent_store_id($store_id);

        // Get all devices with mobile_scanner status
        $devices = $wpdb->get_results($wpdb->prepare(
            "SELECT id, device_name, mobile_scanner FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . "
             WHERE store_id = %d AND status = 'active'
             ORDER BY registered_at DESC",
            $parent_store_id
        ));

        // Get pending mobile scanner requests for this store's devices
        $pending_requests = $wpdb->get_results($wpdb->prepare(
            "SELECT device_id, requested_at FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE store_id = %d AND request_type = 'mobile_scanner' AND status = 'pending'",
            $parent_store_id
        ));

        // Build pending map by device_id and collect pending device IDs
        $pending_by_device = [];
        $pending_device_ids = [];
        foreach ($pending_requests as $pr) {
            if ($pr->device_id) {
                $pending_by_device[$pr->device_id] = $pr->requested_at;
                $pending_device_ids[] = intval($pr->device_id);
            }
        }

        // Build devices response with mobile scanner info
        $devices_info = [];
        foreach ($devices as $device) {
            $devices_info[] = [
                'id' => intval($device->id),
                'device_name' => $device->device_name,
                'mobile_scanner' => !empty($device->mobile_scanner),
                'pending_request' => isset($pending_by_device[$device->id]) ? $pending_by_device[$device->id] : null
            ];
        }

        // Legacy: also return store-level scanner type for backwards compatibility
        $scanner_type = $wpdb->get_var($wpdb->prepare(
            "SELECT scanner_type FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        return new WP_REST_Response([
            'success' => true,
            'devices' => $devices_info,
            'pending_device_ids' => $pending_device_ids, // Simple array for frontend
            // Legacy store-level info
            'store_scanner_type' => $scanner_type ?: 'fixed',
            'store_is_mobile' => ($scanner_type === 'mobile')
        ], 200);
    }

    /**
     * REST: Request mobile scanner mode for a specific device (needs admin approval)
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

        $data = $request->get_json_params();
        $device_id = intval($data['device_id'] ?? 0);

        if ($device_id <= 0) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Geräte-ID erforderlich'
            ], 400);
        }

        $parent_store_id = self::get_parent_store_id($store_id);

        // Check if device exists and belongs to this store
        $device = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}" . self::USER_DEVICES_TABLE . " WHERE id = %d AND store_id = %d AND status = 'active'",
            $device_id, $parent_store_id
        ));

        if (!$device) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Gerät nicht gefunden'
            ], 404);
        }

        // Check if device already has mobile scanner
        if (!empty($device->mobile_scanner)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Dieses Gerät hat bereits Mobile Scanner aktiviert'
            ], 200);
        }

        // Check for existing pending request for this device
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}" . self::DEVICE_REQUESTS_TABLE . "
             WHERE device_id = %d AND request_type = 'mobile_scanner' AND status = 'pending'",
            $device_id
        ));

        if ($existing) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Es gibt bereits eine ausstehende Anfrage für dieses Gerät'
            ], 200);
        }

        // Create the request
        $token = wp_generate_password(32, false);
        $ip_address = self::get_client_ip();
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $result = $wpdb->insert(
            $wpdb->prefix . self::DEVICE_REQUESTS_TABLE,
            [
                'store_id' => $parent_store_id,
                'device_id' => $device_id,
                'fingerprint_hash' => $device->fingerprint_hash,
                'request_type' => 'mobile_scanner',
                'device_name' => $device->device_name,
                'user_agent' => $user_agent,
                'ip_address' => $ip_address,
                'approval_token' => $token,
                'status' => 'pending',
                'requested_at' => current_time('mysql')
            ],
            ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        if (!$result) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Datenbankfehler'
            ], 500);
        }

        // Send email to admin
        self::send_mobile_scanner_request_email($parent_store_id, $token, $device);

        ppv_log("📱 [Mobile Scanner] Request created: store_id={$parent_store_id}, device_id={$device_id}, device={$device->device_name}, token={$token}");

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Anfrage gesendet! Der Admin wird per E-Mail benachrichtigt.'
        ], 200);
    }

    /**
     * Send mobile scanner request email to admin (per-device)
     */
    private static function send_mobile_scanner_request_email($store_id, $token, $device = null) {
        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, email, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));

        $store_name = $store->name ?? "Store #{$store_id}";
        $store_city = $store->city ?? '';
        $device_name = $device->device_name ?? 'Unbekannt';
        $device_id = $device->id ?? 0;

        // Admin email - use same as other notifications
        $admin_email = 'info@punktepass.de';
        $site_url = site_url();

        $approve_url = "{$site_url}/wp-json/punktepass/v1/user-devices/approve/{$token}";
        $reject_url = "{$site_url}/wp-json/punktepass/v1/user-devices/reject/{$token}";

        $subject = "[PunktePass] Mobile Scanner für Gerät - {$store_name}";

        $message = "
        <html>
        <head><style>
            body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
            .card { background: #fff; padding: 30px; border-radius: 10px; max-width: 500px; margin: 0 auto; }
            h2 { color: #333; margin-top: 0; }
            .info { background: #f0f0f0; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .device-info { background: #e3f2fd; border: 1px solid #2196f3; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .warning { background: #fff3e0; border: 1px solid #ff9800; padding: 15px; border-radius: 8px; margin: 20px 0; }
            .btn { display: inline-block; padding: 12px 25px; border-radius: 8px; text-decoration: none; color: #fff; margin-right: 10px; }
            .btn-approve { background: #4caf50; }
            .btn-reject { background: #f44336; }
        </style></head>
        <body>
        <div class='card'>
            <h2>📱 Mobile Scanner Anfrage (Gerätspezifisch)</h2>
            <p>Ein Geschäft möchte den <strong>Mobile Scanner</strong> für ein bestimmtes Gerät aktivieren.</p>
            <div class='info'>
                <p><strong>Geschäft:</strong> {$store_name}</p>
                <p><strong>Stadt:</strong> {$store_city}</p>
                <p><strong>Store ID:</strong> {$store_id}</p>
                <p><strong>Zeitpunkt:</strong> " . current_time('d.m.Y H:i') . "</p>
            </div>
            <div class='device-info'>
                <p><strong>📱 Gerät:</strong> {$device_name}</p>
                <p><strong>Geräte-ID:</strong> #{$device_id}</p>
            </div>
            <div class='warning'>
                <p><strong>⚠️ Hinweis:</strong> Bei Mobile Scanner wird die GPS-Prüfung für dieses Gerät deaktiviert. Es kann von überall scannen.</p>
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
        ppv_log("📧 [Mobile Scanner Email] Result: " . ($sent ? 'SUCCESS' : 'FAILED') . " - to={$admin_email}, store_id={$store_id}, device_id={$device_id}");
    }
}
