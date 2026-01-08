<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - Scan Monitoring & Fraud Detection
 * Enhanced: Device fingerprint + GPS distance + Email alerts
 *
 * Features:
 * - Device fingerprint validation (first scan = trusted device)
 * - GPS distance validation (scanner vs store location)
 * - 10km+ distance: scan goes to PENDING (not blocked)
 * - Email alerts for suspicious scans (info@punktepass.de)
 * - Mobile scanner support (no fixed location)
 * - Admin notifications for suspicious scans
 */
class PPV_Scan_Monitoring {

    // Maximum allowed distance in meters (default: 500m)
    const DEFAULT_MAX_DISTANCE = 500;

    // Distance threshold for PENDING status (10km)
    const PENDING_DISTANCE_THRESHOLD = 10000;

    // Admin email for fraud alerts
    const FRAUD_ALERT_EMAIL = 'info@punktepass.de';

    // ═══════════════════════════════════════════════════════════════
    // GPS ZONE CONSTANTS (Fázis 1 - 2025-12)
    // ═══════════════════════════════════════════════════════════════
    // Zone 1: OK - scan allowed without logging
    const GPS_ZONE_OK = 100;        // < 100m = OK

    // Zone 2: LOG - scan allowed but logged as suspicious
    const GPS_ZONE_LOG = 200;       // 100-200m = LOG only

    // Zone 3: BLOCK - scan blocked (> 200m)
    // Anything above GPS_ZONE_LOG = BLOCKED

    // GPS Spoof Detection: Maximum realistic travel speed (km/h)
    // Above this = impossible travel (GPS spoofing suspected)
    const MAX_TRAVEL_SPEED_KMH = 300; // 300 km/h (high-speed train)

    /**
     * Initialize hooks
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'ensure_db_columns'], 5);

        // Hook into filiale creation to auto-enable monitoring
        add_action('ppv_filiale_created', [__CLASS__, 'auto_enable_monitoring'], 10, 1);
    }

    /**
     * Ensure required database columns exist
     */
    public static function ensure_db_columns() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_stores';

        // Check if scan_monitoring_enabled column exists
        $monitoring_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'scan_monitoring_enabled'",
            DB_NAME,
            $table_name
        ));

        if (empty($monitoring_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN scan_monitoring_enabled TINYINT(1) NOT NULL DEFAULT 1
                COMMENT 'GPS monitoring enabled (1=yes, 0=no)'
            ");
        }

        // Check if scanner_type column exists
        $scanner_type_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'scanner_type'",
            DB_NAME,
            $table_name
        ));

        if (empty($scanner_type_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN scanner_type ENUM('fixed', 'mobile') NOT NULL DEFAULT 'fixed'
                COMMENT 'fixed=check GPS, mobile=no GPS check'
            ");
        }

        // Check if max_scan_distance column exists
        $distance_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'max_scan_distance'",
            DB_NAME,
            $table_name
        ));

        if (empty($distance_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN max_scan_distance INT(11) NOT NULL DEFAULT 500
                COMMENT 'Max allowed scan distance in meters'
            ");
        }

        // Check if trusted_device_fingerprint column exists
        $fingerprint_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'trusted_device_fingerprint'",
            DB_NAME,
            $table_name
        ));

        if (empty($fingerprint_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN trusted_device_fingerprint VARCHAR(64) DEFAULT NULL
                COMMENT 'First scanner device fingerprint (trusted)'
            ");
        }

        // Create suspicious scans table if not exists
        self::create_suspicious_scans_table();

        // Create pending scans table if not exists
        self::create_pending_scans_table();
    }

    /**
     * Create pending scans table for 10km+ distance scans
     */
    private static function create_pending_scans_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_pending_scans';
        $charset_collate = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            points INT(11) NOT NULL DEFAULT 1,
            scan_latitude DECIMAL(10,8) DEFAULT NULL,
            scan_longitude DECIMAL(11,8) DEFAULT NULL,
            distance_meters INT(11) DEFAULT NULL,
            device_fingerprint VARCHAR(64) DEFAULT NULL,
            reason VARCHAR(50) NOT NULL DEFAULT 'distance_exceeded',
            status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
            admin_notes TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_store_id (store_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Create suspicious scans log table
     */
    private static function create_suspicious_scans_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_suspicious_scans';
        $charset_collate = $wpdb->get_charset_collate();

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            scan_latitude DECIMAL(10,8) DEFAULT NULL,
            scan_longitude DECIMAL(11,8) DEFAULT NULL,
            store_latitude DECIMAL(10,8) DEFAULT NULL,
            store_longitude DECIMAL(11,8) DEFAULT NULL,
            distance_meters INT(11) DEFAULT NULL,
            reason VARCHAR(50) NOT NULL DEFAULT 'gps_distance',
            status ENUM('new', 'reviewed', 'dismissed', 'blocked') NOT NULL DEFAULT 'new',
            admin_notes TEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            user_agent TEXT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            reviewed_at DATETIME DEFAULT NULL,
            reviewed_by BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY (id),
            KEY idx_store_id (store_id),
            KEY idx_user_id (user_id),
            KEY idx_status (status),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    /**
     * Auto-enable monitoring when a new filiale is created
     */
    public static function auto_enable_monitoring($store_id) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['scan_monitoring_enabled' => 1],
            ['id' => $store_id],
            ['%d'],
            ['%d']
        );

        ppv_log("[PPV_Scan_Monitoring] Auto-enabled monitoring for store #{$store_id}");
    }

    /**
     * Check if store has monitoring enabled
     */
    public static function is_monitoring_enabled($store_id) {
        global $wpdb;

        return (bool) $wpdb->get_var($wpdb->prepare(
            "SELECT scan_monitoring_enabled FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
    }

    /**
     * Get scanner type for a store
     *
     * @deprecated Use is_device_mobile_scanner() instead - per-device mobile_scanner flag
     * This method will be removed in a future version
     */
    public static function get_scanner_type($store_id) {
        global $wpdb;

        return $wpdb->get_var($wpdb->prepare(
            "SELECT scanner_type FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        )) ?: 'fixed';
    }

    /**
     * Check if device has mobile_scanner enabled (skips GPS check)
     * @param string $device_fingerprint The device fingerprint hash
     * @param int $store_id The store ID
     * @return bool True if device is a mobile scanner
     */
    public static function is_device_mobile_scanner($device_fingerprint, $store_id) {
        global $wpdb;

        if (empty($device_fingerprint)) {
            ppv_log("[GPS] ❌ Mobile scanner check FAILED: Empty device_fingerprint");
            return false;
        }

        // Get parent store ID (in case of filiale)
        $parent_store_id = $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(parent_store_id, id) FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        ppv_log("[GPS] 🔍 Checking mobile_scanner: store={$store_id}, parent={$parent_store_id}, fingerprint=" . substr($device_fingerprint, 0, 20) . "...");

        // Check if device has mobile_scanner flag
        $mobile_scanner = $wpdb->get_var($wpdb->prepare(
            "SELECT mobile_scanner FROM {$wpdb->prefix}ppv_user_devices
             WHERE store_id = %d AND fingerprint_hash = %s AND status = 'active'",
            $parent_store_id,
            $device_fingerprint
        ));

        ppv_log("[GPS] 🔍 Mobile scanner query result: " . ($mobile_scanner ? "mobile_scanner={$mobile_scanner}" : "NOT FOUND or NULL"));
        ppv_log("[GPS] 🔍 Last SQL: " . $wpdb->last_query);

        $is_mobile = !empty($mobile_scanner);
        ppv_log("[GPS] " . ($is_mobile ? "✅ Device IS mobile scanner" : "❌ Device is NOT mobile scanner"));

        return $is_mobile;
    }

    /**
     * Validate scan GPS location with 3-zone logic
     *
     * ZONES:
     * - Zone 1 (< 100m): OK - scan allowed
     * - Zone 2 (100-200m): LOG - scan allowed but logged as suspicious
     * - Zone 3 (> 200m): BLOCK - scan rejected
     *
     * EXCEPTIONS:
     * - Mobile scanner devices skip ALL GPS checks
     * - Store-level scanner_type='mobile' skips GPS checks
     *
     * @param int $store_id Store ID
     * @param float $scan_lat Scan latitude
     * @param float $scan_lng Scan longitude
     * @param string|null $device_fingerprint Device fingerprint for mobile scanner check
     * @return array ['valid' => bool, 'distance' => int|null, 'reason' => string|null, 'action' => string]
     */
    public static function validate_scan_location($store_id, $scan_lat, $scan_lng, $device_fingerprint = null) {
        global $wpdb;

        ppv_log("[GPS] 🚀 validate_scan_location called: store={$store_id}, lat={$scan_lat}, lng={$scan_lng}, fingerprint=" . ($device_fingerprint ? substr($device_fingerprint, 0, 20) . "..." : "NULL"));

        // ═══════════════════════════════════════════════════════════════
        // MOBILE SCANNER EXCEPTION - Skip GPS for mobile scanner devices
        // ═══════════════════════════════════════════════════════════════

        // Check device-level mobile scanner first (per-device setting)
        if ($device_fingerprint && self::is_device_mobile_scanner($device_fingerprint, $store_id)) {
            ppv_log("[GPS] 📱 Mobile scanner device detected - GPS check SKIPPED for store #{$store_id}");
            return [
                'valid' => true,
                'distance' => null,
                'reason' => null,
                'action' => 'none',
                'skipped' => 'mobile_scanner_device'
            ];
        }

        // ⚠️ DEPRECATED: Store-level scanner_type check
        // Use per-device mobile_scanner flag instead (set in ppv_user_devices table)
        // This fallback will be removed in a future version - migrate stores to device-level settings
        $scanner_type = self::get_scanner_type($store_id);
        if ($scanner_type === 'mobile') {
            ppv_log("[GPS] ⚠️ DEPRECATED: Using store-level scanner_type='mobile' for store #{$store_id}. Please migrate to per-device mobile_scanner flag.");
            return [
                'valid' => true,
                'distance' => null,
                'reason' => null,
                'action' => 'none',
                'skipped' => 'mobile_scanner_store',
                'deprecated' => true
            ];
        }

        // ═══════════════════════════════════════════════════════════════
        // GPS VALIDATION - Always enabled (admin controlled, not per-store)
        // ═══════════════════════════════════════════════════════════════

        // If no GPS provided from scan, LOG as suspicious (could be GPS spoofer hiding location)
        if (empty($scan_lat) || empty($scan_lng)) {
            ppv_log("[GPS] ⚠️ No GPS data provided for store #{$store_id} - logging as suspicious");
            return [
                'valid' => true,  // Allow scan but log
                'distance' => null,
                'reason' => 'no_gps_data',
                'action' => 'log',
                'skipped' => false
            ];
        }

        // Get store location
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT latitude, longitude, country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // If store has no GPS coordinates, try country-based check as fallback
        if (empty($store->latitude) || empty($store->longitude)) {
            // Check if store has country set - do country-level validation
            if (!empty($store->country)) {
                $country_check = self::validate_scan_country($scan_lat, $scan_lng, $store->country);
                if (!$country_check['valid']) {
                    ppv_log("[GPS] ❌ BLOCKED: Wrong country! Store={$store->country}, Scan={$country_check['detected_country']}");
                    return [
                        'valid' => false,
                        'distance' => null,
                        'reason' => 'wrong_country',
                        'action' => 'block',
                        'store_country' => $store->country,
                        'scan_country' => $country_check['detected_country']
                    ];
                }
            }
            return [
                'valid' => true,
                'distance' => null,
                'reason' => null,
                'action' => 'none',
                'skipped' => 'no_store_gps'
            ];
        }

        // Calculate distance using Haversine formula
        $distance = self::calculate_distance(
            $store->latitude,
            $store->longitude,
            $scan_lat,
            $scan_lng
        );

        // ═══════════════════════════════════════════════════════════════
        // 3-ZONE GPS VALIDATION
        // ═══════════════════════════════════════════════════════════════

        // ZONE 1: OK (< 100m) - Scan allowed, no logging
        if ($distance <= self::GPS_ZONE_OK) {
            return [
                'valid' => true,
                'distance' => $distance,
                'reason' => null,
                'action' => 'none',
                'zone' => 'ok'
            ];
        }

        // ZONE 2: LOG (100-200m) - Scan allowed but logged as suspicious
        if ($distance <= self::GPS_ZONE_LOG) {
            ppv_log("[GPS] ⚠️ Zone 2 (LOG): Distance {$distance}m for store #{$store_id} - logging as suspicious");
            return [
                'valid' => true,
                'distance' => $distance,
                'reason' => 'gps_zone_log',
                'action' => 'log',
                'zone' => 'log',
                'store_lat' => $store->latitude,
                'store_lng' => $store->longitude
            ];
        }

        // ZONE 3: BLOCK (> 200m) - Scan rejected
        ppv_log("[GPS] ❌ Zone 3 (BLOCK): Distance {$distance}m for store #{$store_id} - BLOCKING scan");
        return [
            'valid' => false,
            'distance' => $distance,
            'reason' => 'gps_distance_blocked',
            'action' => 'block',
            'zone' => 'block',
            'max_allowed' => self::GPS_ZONE_LOG,
            'store_lat' => $store->latitude,
            'store_lng' => $store->longitude
        ];
    }

    /**
     * Calculate distance between two GPS coordinates (Haversine formula)
     * Returns distance in meters
     */
    public static function calculate_distance($lat1, $lng1, $lat2, $lng2) {
        $earth_radius = 6371000; // meters

        $lat1_rad = deg2rad($lat1);
        $lat2_rad = deg2rad($lat2);
        $delta_lat = deg2rad($lat2 - $lat1);
        $delta_lng = deg2rad($lng2 - $lng1);

        $a = sin($delta_lat / 2) * sin($delta_lat / 2) +
             cos($lat1_rad) * cos($lat2_rad) *
             sin($delta_lng / 2) * sin($delta_lng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earth_radius * $c);
    }

    /**
     * Country bounding boxes (approximate)
     * Format: [min_lat, max_lat, min_lng, max_lng]
     */
    private static function get_country_bounds() {
        return [
            'DE' => [47.27, 55.10, 5.87, 15.04],   // Germany
            'HU' => [45.74, 48.59, 16.11, 22.90],  // Hungary
            'RO' => [43.62, 48.27, 20.26, 29.76],  // Romania
            'AT' => [46.37, 49.02, 9.53, 17.16],   // Austria
            'PL' => [49.00, 54.83, 14.12, 24.15],  // Poland
            'CZ' => [48.55, 51.06, 12.09, 18.86],  // Czech Republic
            'SK' => [47.73, 49.61, 16.84, 22.57],  // Slovakia
        ];
    }

    /**
     * Detect country from GPS coordinates using bounding boxes
     * Returns country code or null if not in any known country
     */
    public static function detect_country_from_gps($lat, $lng) {
        $bounds = self::get_country_bounds();

        foreach ($bounds as $country => $box) {
            list($min_lat, $max_lat, $min_lng, $max_lng) = $box;
            if ($lat >= $min_lat && $lat <= $max_lat && $lng >= $min_lng && $lng <= $max_lng) {
                return $country;
            }
        }

        return null; // Unknown/other country
    }

    /**
     * Validate if scan GPS is in the expected country
     * Returns: ['valid' => bool, 'detected_country' => string|null]
     */
    public static function validate_scan_country($scan_lat, $scan_lng, $expected_country) {
        $detected = self::detect_country_from_gps($scan_lat, $scan_lng);

        // If we can't detect country, allow the scan (benefit of the doubt)
        if ($detected === null) {
            ppv_log("[PPV_Scan_Monitoring] Country check: GPS ({$scan_lat}, {$scan_lng}) not in known country bounds, allowing scan");
            return ['valid' => true, 'detected_country' => 'UNKNOWN'];
        }

        // Check if detected country matches expected
        if ($detected === $expected_country) {
            return ['valid' => true, 'detected_country' => $detected];
        }

        // Country mismatch - suspicious!
        ppv_log("[PPV_Scan_Monitoring] COUNTRY MISMATCH: Store country={$expected_country}, Scan GPS in {$detected}");
        return ['valid' => false, 'detected_country' => $detected];
    }

    /**
     * Log a suspicious scan
     */
    public static function log_suspicious_scan($store_id, $user_id, $scan_lat, $scan_lng, $validation_result) {
        global $wpdb;

        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert(
            $wpdb->prefix . 'ppv_suspicious_scans',
            [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'scan_latitude' => $scan_lat,
                'scan_longitude' => $scan_lng,
                'store_latitude' => $validation_result['store_lat'] ?? null,
                'store_longitude' => $validation_result['store_lng'] ?? null,
                'distance_meters' => $validation_result['distance'] ?? null,
                'reason' => $validation_result['reason'] ?? 'gps_distance',
                'status' => 'new',
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s']
        );

        $suspicious_id = $wpdb->insert_id;

        ppv_log("[PPV_Scan_Monitoring] Suspicious scan logged: id={$suspicious_id}, store={$store_id}, user={$user_id}, distance={$validation_result['distance']}m");

        // Send admin notification
        self::notify_admin($store_id, $user_id, $validation_result);

        return $suspicious_id;
    }

    /**
     * Send admin notification about suspicious scan
     */
    private static function notify_admin($store_id, $user_id, $validation_result) {
        global $wpdb;

        // Get store and user info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        $store_name = $store->company_name ?: $store->name ?: "Store #{$store_id}";
        $user_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: "User #{$user_id}";
        $distance = $validation_result['distance'] ?? 'unknown';
        $max_distance = $validation_result['max_distance'] ?? self::DEFAULT_MAX_DISTANCE;

        // Log for now (could extend to email/push notifications)
        error_log("[PunktePass SUSPICIOUS SCAN] Store: {$store_name}, User: {$user_name}, Distance: {$distance}m (max: {$max_distance}m)");
    }

    /**
     * Get count of new suspicious scans (for admin badge)
     */
    public static function get_new_suspicious_count() {
        global $wpdb;

        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_suspicious_scans WHERE status = 'new'"
        );
    }

    /**
     * Get suspicious scans for admin list
     */
    public static function get_suspicious_scans($status = null, $limit = 50, $offset = 0) {
        global $wpdb;

        $where = '';
        if ($status) {
            $where = $wpdb->prepare(" WHERE ss.status = %s", $status);
        }

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                ss.*,
                s.name as store_name,
                s.company_name,
                s.city as store_city,
                u.first_name,
                u.last_name,
                u.email as user_email
            FROM {$wpdb->prefix}ppv_suspicious_scans ss
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON ss.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON ss.user_id = u.id
            {$where}
            ORDER BY ss.created_at DESC
            LIMIT %d OFFSET %d
        ", $limit, $offset));
    }

    /**
     * Update suspicious scan status
     */
    public static function update_scan_status($scan_id, $status, $admin_notes = null) {
        global $wpdb;

        $update_data = [
            'status' => $status,
            'reviewed_at' => current_time('mysql'),
            'reviewed_by' => get_current_user_id()
        ];

        if ($admin_notes !== null) {
            $update_data['admin_notes'] = $admin_notes;
        }

        return $wpdb->update(
            $wpdb->prefix . 'ppv_suspicious_scans',
            $update_data,
            ['id' => $scan_id],
            ['%s', '%s', '%d', '%s'],
            ['%d']
        );
    }

    /**
     * Toggle monitoring for a store
     */
    public static function toggle_monitoring($store_id, $enabled) {
        global $wpdb;

        return $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['scan_monitoring_enabled' => $enabled ? 1 : 0],
            ['id' => $store_id],
            ['%d'],
            ['%d']
        );
    }

    /**
     * Set scanner type for a store
     *
     * @deprecated Use per-device mobile_scanner flag instead (ppv_user_devices.mobile_scanner)
     * Set device as mobile scanner via REST API: POST /punktepass/v1/user-devices/request-mobile-scanner
     * This method will be removed in a future version
     */
    public static function set_scanner_type($store_id, $type) {
        global $wpdb;

        ppv_log("[Scanner] ⚠️ DEPRECATED: set_scanner_type() called for store #{$store_id}. Use per-device mobile_scanner flag instead.");

        if (!in_array($type, ['fixed', 'mobile'])) {
            $type = 'fixed';
        }

        return $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['scanner_type' => $type],
            ['id' => $store_id],
            ['%s'],
            ['%d']
        );
    }

    // ═══════════════════════════════════════════════════════════════
    // DEVICE FINGERPRINT VALIDATION
    // First scan from a device = trusted device for that store
    // Different device = NOTIFY via email (but allow scan)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Validate device fingerprint
     * Returns: ['valid' => bool, 'is_new' => bool, 'trusted_fp' => string|null]
     */
    public static function validate_device_fingerprint($store_id, $device_fingerprint) {
        global $wpdb;

        if (empty($device_fingerprint)) {
            return ['valid' => true, 'is_new' => false, 'trusted_fp' => null, 'skipped' => 'no_fingerprint'];
        }

        // Use central fingerprint validation if available
        if (class_exists('PPV_Device_Fingerprint') && method_exists('PPV_Device_Fingerprint', 'validate_fingerprint')) {
            $fp_validation = PPV_Device_Fingerprint::validate_fingerprint($device_fingerprint, true);
            if (!$fp_validation['valid']) {
                ppv_log("[PPV_Scan_Monitoring] ⚠️ Invalid fingerprint format rejected");
                return ['valid' => false, 'is_new' => false, 'trusted_fp' => null, 'error' => $fp_validation['error']];
            }
            $device_fingerprint = $fp_validation['sanitized'];
        }

        // Get current trusted fingerprint for this store
        $trusted_fp = $wpdb->get_var($wpdb->prepare(
            "SELECT trusted_device_fingerprint FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // First scan ever - register this device as trusted
        if (empty($trusted_fp)) {
            $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['trusted_device_fingerprint' => $device_fingerprint],
                ['id' => $store_id],
                ['%s'],
                ['%d']
            );
            ppv_log("[PPV_Scan_Monitoring] 📱 First device registered for store #{$store_id}: " . substr($device_fingerprint, 0, 16) . "...");
            return ['valid' => true, 'is_new' => true, 'trusted_fp' => $device_fingerprint];
        }

        // Check if fingerprint matches trusted device
        if ($trusted_fp === $device_fingerprint) {
            return ['valid' => true, 'is_new' => false, 'trusted_fp' => $trusted_fp];
        }

        // DIFFERENT DEVICE! Allow scan but return info for notification
        ppv_log("[PPV_Scan_Monitoring] ⚠️ DIFFERENT DEVICE detected for store #{$store_id}!");
        return [
            'valid' => false,
            'is_new' => false,
            'trusted_fp' => $trusted_fp,
            'current_fp' => $device_fingerprint,
            'reason' => 'different_device'
        ];
    }

    /**
     * Send email alert for suspicious activity
     */
    public static function send_fraud_alert_email($alert_type, $store_id, $user_id, $details = []) {
        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, city, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        // Get user info
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        $store_name = $store->company_name ?: $store->name ?: "Store #{$store_id}";
        $user_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: "User #{$user_id}";
        $user_email = $user->email ?? 'N/A';

        // Build email content based on alert type
        $subject = '';
        $body = '';

        switch ($alert_type) {
            case 'different_device':
                $subject = "⚠️ PunktePass: Új eszköz észlelve - {$store_name}";
                $body = "FIGYELEM: Új/más eszközről történt beolvasás!\n\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "🏪 Üzlet: {$store_name}\n";
                $body .= "📍 Város: " . ($store->city ?? 'N/A') . "\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "👤 Felhasználó: {$user_name}\n";
                $body .= "📧 Email: {$user_email}\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "📱 Új eszköz ID: " . substr($details['current_fp'] ?? 'N/A', 0, 16) . "...\n";
                $body .= "📱 Eredeti eszköz: " . substr($details['trusted_fp'] ?? 'N/A', 0, 16) . "...\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "⏰ Időpont: " . current_time('Y-m-d H:i:s') . "\n";
                $body .= "🌐 IP: " . ($details['ip_address'] ?? 'N/A') . "\n\n";
                $body .= "A beolvasás ENGEDÉLYEZVE lett - csak értesítés.\n";
                break;

            case 'distance_pending':
                $distance_km = round(($details['distance'] ?? 0) / 1000, 1);
                $subject = "🚨 PunktePass: Scan függőbe téve (10km+) - {$store_name}";
                $body = "FIGYELEM: Beolvasás FÜGGŐBE TÉVE nagy távolság miatt!\n\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "🏪 Üzlet: {$store_name}\n";
                $body .= "📍 Város: " . ($store->city ?? 'N/A') . "\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "👤 Felhasználó: {$user_name}\n";
                $body .= "📧 Email: {$user_email}\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "📏 Távolság: {$distance_km} km\n";
                $body .= "🎯 Megengedett: 10 km\n";
                $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
                $body .= "⏰ Időpont: " . current_time('Y-m-d H:i:s') . "\n";
                $body .= "🌐 IP: " . ($details['ip_address'] ?? 'N/A') . "\n\n";
                $body .= "⚡ TEENDŐ: Admin jóváhagyás szükséges!\n";
                $body .= "🔗 Admin: " . admin_url('admin.php?page=punktepass-pending-scans') . "\n";
                break;

            default:
                $subject = "⚠️ PunktePass: Gyanús tevékenység - {$store_name}";
                $body = "Gyanús scan észlelve.\n\n";
                $body .= "Store: {$store_name}\n";
                $body .= "User: {$user_name}\n";
                $body .= "Details: " . print_r($details, true) . "\n";
        }

        // Send email
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: PunktePass System <noreply@punktepass.de>'
        ];

        $sent = wp_mail(self::FRAUD_ALERT_EMAIL, $subject, $body, $headers);

        if ($sent) {
            ppv_log("[PPV_Scan_Monitoring] 📧 Fraud alert email sent: {$alert_type} for store #{$store_id}");
        } else {
            ppv_log("[PPV_Scan_Monitoring] ❌ Failed to send fraud alert email: {$alert_type}");
        }

        return $sent;
    }

    // ═══════════════════════════════════════════════════════════════
    // PENDING SCANS (10km+ distance)
    // ═══════════════════════════════════════════════════════════════

    /**
     * Create a pending scan (for 10km+ distance)
     */
    public static function create_pending_scan($store_id, $user_id, $points, $scan_lat, $scan_lng, $distance, $device_fingerprint = null) {
        global $wpdb;

        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert(
            $wpdb->prefix . 'ppv_pending_scans',
            [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'points' => $points,
                'scan_latitude' => $scan_lat,
                'scan_longitude' => $scan_lng,
                'distance_meters' => $distance,
                'device_fingerprint' => $device_fingerprint,
                'reason' => 'distance_exceeded',
                'status' => 'pending',
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
        );

        $pending_id = $wpdb->insert_id;

        ppv_log("[PPV_Scan_Monitoring] 📋 Pending scan created: id={$pending_id}, store={$store_id}, user={$user_id}, distance={$distance}m");

        // Send email alert
        self::send_fraud_alert_email('distance_pending', $store_id, $user_id, [
            'distance' => $distance,
            'ip_address' => $ip_address,
            'pending_id' => $pending_id
        ]);

        return $pending_id;
    }

    /**
     * Get pending scans count for admin badge
     */
    public static function get_pending_scans_count() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pending_scans WHERE status = 'pending'"
        );
    }

    /**
     * Get pending scans for admin list
     */
    public static function get_pending_scans($status = 'pending', $limit = 50, $offset = 0) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                ps.*,
                s.name as store_name,
                s.company_name,
                s.city as store_city,
                u.first_name,
                u.last_name,
                u.email as user_email
            FROM {$wpdb->prefix}ppv_pending_scans ps
            LEFT JOIN {$wpdb->prefix}ppv_stores s ON ps.store_id = s.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON ps.user_id = u.id
            WHERE ps.status = %s
            ORDER BY ps.created_at DESC
            LIMIT %d OFFSET %d
        ", $status, $limit, $offset));
    }

    /**
     * Approve a pending scan - add points to user
     */
    public static function approve_pending_scan($pending_id, $admin_notes = null) {
        global $wpdb;

        // Get pending scan data
        $pending = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_pending_scans WHERE id = %d AND status = 'pending'",
            $pending_id
        ));

        if (!$pending) {
            return ['success' => false, 'message' => 'Pending scan not found or already processed'];
        }

        // Add points to user
        $wpdb->insert(
            $wpdb->prefix . 'ppv_points',
            [
                'user_id' => $pending->user_id,
                'store_id' => $pending->store_id,
                'points' => $pending->points,
                'type' => 'admin_approved',
                'created' => current_time('mysql')
            ],
            ['%d', '%d', '%d', '%s', '%s']
        );

        // Update pending scan status
        $wpdb->update(
            $wpdb->prefix . 'ppv_pending_scans',
            [
                'status' => 'approved',
                'admin_notes' => $admin_notes,
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => get_current_user_id()
            ],
            ['id' => $pending_id],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );

        ppv_log("[PPV_Scan_Monitoring] ✅ Pending scan #{$pending_id} APPROVED - {$pending->points} points added to user #{$pending->user_id}");

        return ['success' => true, 'message' => 'Scan approved, points added'];
    }

    /**
     * Reject a pending scan
     */
    public static function reject_pending_scan($pending_id, $admin_notes = null) {
        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ppv_pending_scans',
            [
                'status' => 'rejected',
                'admin_notes' => $admin_notes,
                'reviewed_at' => current_time('mysql'),
                'reviewed_by' => get_current_user_id()
            ],
            ['id' => $pending_id],
            ['%s', '%s', '%s', '%d'],
            ['%d']
        );

        ppv_log("[PPV_Scan_Monitoring] ❌ Pending scan #{$pending_id} REJECTED");

        return ['success' => true, 'message' => 'Scan rejected'];
    }

    /**
     * Check if scan should be pending (10km+ distance)
     */
    public static function should_be_pending($distance) {
        return $distance !== null && $distance > self::PENDING_DISTANCE_THRESHOLD;
    }

    // ═══════════════════════════════════════════════════════════════
    // GPS SPOOF DETECTION - Impossible Travel
    // ═══════════════════════════════════════════════════════════════

    /**
     * Check for impossible travel (GPS spoofing detection)
     *
     * Compares current scan GPS with user's last scan GPS.
     * If the user "traveled" faster than MAX_TRAVEL_SPEED_KMH, it's suspicious.
     *
     * Example: User scanned in Budapest, 5 minutes later scans in Vienna = 250km in 5min = 3000km/h = IMPOSSIBLE
     *
     * @param int $user_id User ID
     * @param float $current_lat Current scan latitude
     * @param float $current_lng Current scan longitude
     * @return array ['suspicious' => bool, 'reason' => string|null, 'details' => array|null]
     */
    public static function check_impossible_travel($user_id, $current_lat, $current_lng) {
        global $wpdb;

        if (empty($current_lat) || empty($current_lng)) {
            return ['suspicious' => false, 'reason' => 'no_current_gps'];
        }

        // Get user's last scan with GPS data (from suspicious_scans or from a GPS log)
        // We need to track GPS per scan - let's check suspicious_scans first
        $last_scan = $wpdb->get_row($wpdb->prepare("
            SELECT scan_latitude, scan_longitude, created_at
            FROM {$wpdb->prefix}ppv_suspicious_scans
            WHERE user_id = %d
              AND scan_latitude IS NOT NULL
              AND scan_longitude IS NOT NULL
              AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ORDER BY created_at DESC
            LIMIT 1
        ", $user_id));

        // Also check the gps_scan_log table if it exists
        $gps_log_table = $wpdb->prefix . 'ppv_gps_scan_log';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$gps_log_table}'") === $gps_log_table;

        if ($table_exists) {
            $last_gps_log = $wpdb->get_row($wpdb->prepare("
                SELECT latitude, longitude, created_at
                FROM {$gps_log_table}
                WHERE user_id = %d
                  AND latitude IS NOT NULL
                  AND longitude IS NOT NULL
                  AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                ORDER BY created_at DESC
                LIMIT 1
            ", $user_id));

            // Use the more recent one
            if ($last_gps_log && (!$last_scan || strtotime($last_gps_log->created_at) > strtotime($last_scan->created_at))) {
                $last_scan = (object)[
                    'scan_latitude' => $last_gps_log->latitude,
                    'scan_longitude' => $last_gps_log->longitude,
                    'created_at' => $last_gps_log->created_at
                ];
            }
        }

        if (!$last_scan) {
            return ['suspicious' => false, 'reason' => 'no_previous_gps'];
        }

        // Calculate distance and time
        $distance_meters = self::calculate_distance(
            $last_scan->scan_latitude,
            $last_scan->scan_longitude,
            $current_lat,
            $current_lng
        );

        $time_diff_seconds = time() - strtotime($last_scan->created_at);

        // Minimum time difference to check (avoid division by zero, also ignore very recent scans)
        if ($time_diff_seconds < 60) {
            return ['suspicious' => false, 'reason' => 'too_recent'];
        }

        // Calculate speed in km/h
        $distance_km = $distance_meters / 1000;
        $time_hours = $time_diff_seconds / 3600;
        $speed_kmh = $time_hours > 0 ? $distance_km / $time_hours : 0;

        // Check if speed exceeds maximum realistic travel speed
        if ($speed_kmh > self::MAX_TRAVEL_SPEED_KMH) {
            ppv_log("[GPS SPOOF] 🚨 IMPOSSIBLE TRAVEL detected! User #{$user_id}: {$distance_km}km in " . round($time_diff_seconds / 60, 1) . " min = {$speed_kmh} km/h (max: " . self::MAX_TRAVEL_SPEED_KMH . " km/h)");

            return [
                'suspicious' => true,
                'reason' => 'impossible_travel',
                'details' => [
                    'distance_km' => round($distance_km, 2),
                    'time_minutes' => round($time_diff_seconds / 60, 1),
                    'speed_kmh' => round($speed_kmh, 1),
                    'max_speed_kmh' => self::MAX_TRAVEL_SPEED_KMH,
                    'last_lat' => $last_scan->scan_latitude,
                    'last_lng' => $last_scan->scan_longitude,
                    'last_time' => $last_scan->created_at,
                    'current_lat' => $current_lat,
                    'current_lng' => $current_lng
                ]
            ];
        }

        return ['suspicious' => false, 'speed_kmh' => round($speed_kmh, 1)];
    }

    /**
     * Log GPS data for impossible travel detection
     * Call this after every successful scan with GPS data
     *
     * @param int $user_id User ID
     * @param int $store_id Store ID
     * @param float $latitude Scan latitude
     * @param float $longitude Scan longitude
     */
    public static function log_gps_scan($user_id, $store_id, $latitude, $longitude) {
        global $wpdb;

        if (empty($latitude) || empty($longitude)) {
            return;
        }

        // Ensure table exists
        self::ensure_gps_log_table();

        $wpdb->insert(
            $wpdb->prefix . 'ppv_gps_scan_log',
            [
                'user_id' => $user_id,
                'store_id' => $store_id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'created_at' => current_time('mysql')
            ],
            ['%d', '%d', '%f', '%f', '%s']
        );

        // Clean up old entries (keep only last 24 hours per user)
        $wpdb->query($wpdb->prepare("
            DELETE FROM {$wpdb->prefix}ppv_gps_scan_log
            WHERE user_id = %d AND created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ", $user_id));
    }

    /**
     * Ensure GPS scan log table exists
     */
    private static function ensure_gps_log_table() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_gps_scan_log';

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") === $table_name) {
            return;
        }

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            store_id BIGINT(20) UNSIGNED NOT NULL,
            latitude DECIMAL(10,8) NOT NULL,
            longitude DECIMAL(11,8) NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user_id (user_id),
            KEY idx_created_at (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        ppv_log("[PPV_Scan_Monitoring] ✅ Created ppv_gps_scan_log table for impossible travel detection");
    }

    // ═══════════════════════════════════════════════════════════════
    // VELOCITY CHECKS - Fraud Detection
    // ═══════════════════════════════════════════════════════════════

    /**
     * Check for same IP with different users (potential fraud)
     * Returns suspicious if same IP used by multiple users within time window
     */
    public static function check_ip_velocity($store_id, $user_id, $ip_address, $time_window_minutes = 60) {
        global $wpdb;

        if (empty($ip_address)) {
            return ['suspicious' => false, 'reason' => 'no_ip'];
        }

        // Count different users from same IP in the time window
        $time_threshold = date('Y-m-d H:i:s', strtotime("-{$time_window_minutes} minutes"));

        $different_users = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->prefix}ppv_points
            WHERE store_id = %d
              AND created >= %s
              AND user_id != %d
        ", $store_id, $time_threshold, $user_id));

        // Also check in suspicious scans log for IP
        $ip_in_suspicious = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(DISTINCT user_id)
            FROM {$wpdb->prefix}ppv_suspicious_scans
            WHERE store_id = %d
              AND ip_address = %s
              AND created_at >= %s
              AND user_id != %d
        ", $store_id, $ip_address, $time_threshold, $user_id));

        $total_different_users = intval($different_users) + intval($ip_in_suspicious);

        if ($total_different_users >= 2) {
            ppv_log("[PPV_Scan_Monitoring] 🚨 IP VELOCITY: Same IP {$ip_address} used by {$total_different_users}+ different users at store #{$store_id}");
            return [
                'suspicious' => true,
                'reason' => 'same_ip_multiple_users',
                'ip_address' => $ip_address,
                'different_users_count' => $total_different_users,
                'time_window' => $time_window_minutes
            ];
        }

        return ['suspicious' => false];
    }

    /**
     * Check for too many scans in short time (scan flooding)
     * Returns suspicious if user has too many scans within time window
     */
    public static function check_scan_frequency($store_id, $user_id, $max_scans = 5, $time_window_minutes = 10) {
        global $wpdb;

        $time_threshold = date('Y-m-d H:i:s', strtotime("-{$time_window_minutes} minutes"));

        // Count scans by this user at this store in time window
        $scan_count = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*)
            FROM {$wpdb->prefix}ppv_points
            WHERE store_id = %d
              AND user_id = %d
              AND created >= %s
        ", $store_id, $user_id, $time_threshold));

        if (intval($scan_count) >= $max_scans) {
            ppv_log("[PPV_Scan_Monitoring] 🚨 SCAN FREQUENCY: User #{$user_id} has {$scan_count} scans in {$time_window_minutes} min at store #{$store_id}");
            return [
                'suspicious' => true,
                'reason' => 'too_many_scans',
                'scan_count' => intval($scan_count),
                'max_allowed' => $max_scans,
                'time_window' => $time_window_minutes
            ];
        }

        return ['suspicious' => false];
    }

    /**
     * Full velocity check - combines IP and frequency checks
     * Call this during scan processing
     */
    public static function perform_velocity_check($store_id, $user_id, $ip_address = null) {
        $results = [
            'passed' => true,
            'alerts' => []
        ];

        // Get IP if not provided
        if (empty($ip_address)) {
            $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
            $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        }

        // Check 1: Same IP, different users
        $ip_check = self::check_ip_velocity($store_id, $user_id, $ip_address);
        if ($ip_check['suspicious']) {
            $results['passed'] = false;
            $results['alerts'][] = $ip_check;
        }

        // Check 2: Scan frequency (too many scans)
        $freq_check = self::check_scan_frequency($store_id, $user_id);
        if ($freq_check['suspicious']) {
            $results['passed'] = false;
            $results['alerts'][] = $freq_check;
        }

        // If any alerts, log and notify
        if (!$results['passed']) {
            self::log_velocity_alert($store_id, $user_id, $ip_address, $results['alerts']);
        }

        return $results;
    }

    /**
     * Log velocity alert to suspicious scans table
     */
    private static function log_velocity_alert($store_id, $user_id, $ip_address, $alerts) {
        global $wpdb;

        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        foreach ($alerts as $alert) {
            $reason = $alert['reason'] ?? 'velocity_check';

            $wpdb->insert(
                $wpdb->prefix . 'ppv_suspicious_scans',
                [
                    'store_id' => $store_id,
                    'user_id' => $user_id,
                    'scan_latitude' => null,
                    'scan_longitude' => null,
                    'store_latitude' => null,
                    'store_longitude' => null,
                    'distance_meters' => null,
                    'reason' => $reason,
                    'status' => 'new',
                    'admin_notes' => json_encode($alert),
                    'ip_address' => $ip_address,
                    'user_agent' => $user_agent,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%d', '%f', '%f', '%f', '%f', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
            );
        }

        // Send email alert
        self::send_velocity_alert_email($store_id, $user_id, $ip_address, $alerts);
    }

    /**
     * Send email alert for velocity violations
     */
    private static function send_velocity_alert_email($store_id, $user_id, $ip_address, $alerts) {
        global $wpdb;

        // Get store and user info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT name, company_name, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT first_name, last_name, email FROM {$wpdb->prefix}ppv_users WHERE id = %d",
            $user_id
        ));

        $store_name = $store->company_name ?: $store->name ?: "Store #{$store_id}";
        $user_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: "User #{$user_id}";
        $user_email = $user->email ?? 'N/A';

        $subject = "🚨 PunktePass VELOCITY ALERT - {$store_name}";

        $body = "
<h2 style='color: #d63638;'>🚨 Velocity Alert - Verdächtige Aktivität!</h2>

<p><strong>Shop:</strong> {$store_name}</p>
<p><strong>Stadt:</strong> " . ($store->city ?? 'N/A') . "</p>

<hr>

<h3>Benutzer:</h3>
<p><strong>Name:</strong> {$user_name}</p>
<p><strong>Email:</strong> {$user_email}</p>
<p><strong>IP Adresse:</strong> {$ip_address}</p>

<hr>

<h3>Erkannte Probleme:</h3>
<ul>
";

        foreach ($alerts as $alert) {
            if ($alert['reason'] === 'same_ip_multiple_users') {
                $body .= "<li style='color: #d63638;'><strong>⚠️ Gleiche IP, verschiedene Benutzer:</strong> {$alert['different_users_count']} verschiedene Benutzer von derselben IP in {$alert['time_window']} Minuten</li>";
            }
            if ($alert['reason'] === 'too_many_scans') {
                $body .= "<li style='color: #d63638;'><strong>⚠️ Zu viele Scans:</strong> {$alert['scan_count']} Scans in {$alert['time_window']} Minuten (max: {$alert['max_allowed']})</li>";
            }
        }

        $body .= "
</ul>

<p style='margin-top: 20px;'>
    <a href='" . admin_url('admin.php?page=ppv-suspicious') . "' style='background: #d63638; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>🔧 Im Admin prüfen</a>
</p>

<p style='color: #666; font-size: 12px; margin-top: 30px;'>
    Zeitpunkt: " . current_time('Y-m-d H:i:s') . "
</p>
";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass System <noreply@punktepass.de>'
        ];

        $sent = wp_mail(self::FRAUD_ALERT_EMAIL, $subject, $body, $headers);

        if ($sent) {
            ppv_log("[PPV_Scan_Monitoring] 📧 Velocity alert email sent for store #{$store_id}");
        }

        return $sent;
    }
}

PPV_Scan_Monitoring::hooks();
