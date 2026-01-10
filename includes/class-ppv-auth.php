<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Unified Auth System v5.2 (Security Hardened)
 * ✅ Egységes token-kezelés böngésző, POS és Dock számára
 * ✅ Namespace: punktepass/v1 + ppv/v1
 * ✅ Biztonságos REST permission_callback
 * ✅ Kompatibilis POS és QR modulokkal
 * 🔒 SECURITY FIX: Removed authentication bypass filter
 * 🔒 SECURITY FIX: Token creation requires authentication
 * ✅ Rate limiting for brute force protection
 * ✅ Automatic expired token cleanup
 */

// ❌ SECURITY FIX: Removed rest_authentication_errors filter
// The filter was bypassing ALL authentication for /punktepass/v1/* and /ppv/v1/* endpoints
// Each endpoint now properly handles authentication via permission_callback

class PPV_Auth {

    /** ============================================================
     * 🔹 Inicializálás
     * ============================================================ */
    public static function hooks() {
        add_action('init', [__CLASS__, 'create_token_table']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // 🧹 Automatic token cleanup (daily WP-Cron)
        add_action('ppv_cleanup_expired_tokens', [__CLASS__, 'cleanup_expired_tokens']);
        if (!wp_next_scheduled('ppv_cleanup_expired_tokens')) {
            wp_schedule_event(time(), 'daily', 'ppv_cleanup_expired_tokens');
        }
    }

    /** ============================================================
     * 🔹 Token tábla létrehozása + Performance Indexes
     * ============================================================ */
    public static function create_token_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_tokens';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            id BIGINT(20) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            token VARCHAR(64) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME DEFAULT NULL,
            UNIQUE KEY token (token),
            INDEX idx_user_id (user_id),
            INDEX idx_expires_cleanup (expires_at, created_at),
            INDEX idx_token_validation (token, user_id, expires_at)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // 🚀 Add indexes to existing table (if they don't exist)
        // This handles upgrades from older versions
        $indexes_to_add = [
            "ALTER TABLE $table ADD INDEX idx_expires_cleanup (expires_at, created_at)",
            "ALTER TABLE $table ADD INDEX idx_token_validation (token, user_id, expires_at)"
        ];

        foreach ($indexes_to_add as $index_sql) {
            // Check if index already exists (suppress errors if it does)
            $wpdb->query($index_sql);
        }
    }

    /** ============================================================
     * 🔹 Token generálás
     * ============================================================ */
    public static function create_token($user_id, $hours = 720) {
        global $wpdb;
        if (!$user_id) return false;

        try {
            $token = bin2hex(random_bytes(32));
        } catch (Throwable $e) {
            $token = md5(uniqid(mt_rand(), true));
        }

        $expires = date('Y-m-d H:i:s', time() + $hours * 3600);
        $table = $wpdb->prefix . 'ppv_tokens';

        $wpdb->insert($table, [
            'user_id'    => $user_id,
            'token'      => $token,
            'expires_at' => $expires
        ]);

        return $token;
    }

    /** ============================================================
     * 🔹 Token érvényesítése REST kérésekben
     * ============================================================ */
    public static function get_user_from_token($request = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_tokens';
        $token = null;

        // 1️⃣ REST paraméterből
        if ($request instanceof WP_REST_Request) {
            $token = sanitize_text_field($request->get_param('token'));
        }

        // 2️⃣ Authorization headerből (Bearer)
        if (empty($token) && $request instanceof WP_REST_Request) {
            $auth = $request->get_header('authorization');
            if (!empty($auth) && stripos($auth, 'bearer ') === 0) {
                $token = trim(substr($auth, 7));
            }
        }

        // 3️⃣ PPV-POS-Token header fallback
        if (empty($token) && $request instanceof WP_REST_Request) {
            $pos = $request->get_header('ppv-pos-token');
            if (!empty($pos)) $token = sanitize_text_field($pos);
        }

        // 4️⃣ URL query
        if (empty($token) && isset($_GET['token'])) {
            $token = sanitize_text_field($_GET['token']);
        }

        // ❌ nincs token
        if (empty($token)) return 0;

        // 🔍 Token tábla lekérdezése
        $user_id = (int)$wpdb->get_var($wpdb->prepare("
            SELECT user_id FROM $table
            WHERE token=%s AND (expires_at IS NULL OR expires_at > NOW())
        ", $token));

        // 🔹 POS token fallback (ha a store-ban van eltárolva)
        if ($user_id === 0) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare("
                SELECT user_id FROM {$wpdb->prefix}ppv_stores
                WHERE pos_token=%s LIMIT 1
            ", $token));
        }

        return $user_id > 0 ? $user_id : 0;
    }

    /** ============================================================
     * 🔹 REST permission helper (minden endpointhoz)
     * ============================================================ */
    public static function check_rest_access($request) {
        $user_id = self::get_user_from_token($request);
        if ($user_id > 0 || current_user_can('manage_options')) {
            return true;
        }
        return new WP_Error('unauthorized', '❌ Unauthorized request', ['status' => 401]);
    }

    /** ============================================================
     * 🔹 REST útvonalak
     * ============================================================ */
    public static function register_rest_routes() {
        register_rest_route('punktepass/v1', '/auth/create', [
            'methods'             => ['GET', 'POST'],
            'callback'            => [__CLASS__, 'rest_create_token'],
            'permission_callback' => ['PPV_Permissions', 'check_authenticated'] // 🔒 SECURITY FIX: Require authentication
        ]);

        register_rest_route('punktepass/v1', '/auth/check', [
            'methods'             => ['GET'],
            'callback'            => [__CLASS__, 'rest_check_token'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous']
        ]);
    }

    /** ============================================================
     * 🔹 REST – token létrehozása (SECURITY HARDENED)
     * ============================================================ */
    public static function rest_create_token($request) {
        // 🔒 SECURITY FIX: Only authenticated users can create tokens for themselves
        // Removed ability to specify user_id - prevents token hijacking

        // 🛡️ Rate limiting to prevent brute force
        $rate_check = PPV_Permissions::check_rate_limit('token_create', 10, 3600);
        if (is_wp_error($rate_check)) {
            return $rate_check;
        }

        // Get user_id from current session/auth ONLY
        $user_id = PPV_Permissions::get_current_user_id();

        if (!$user_id) {
            PPV_Permissions::increment_rate_limit('token_create', 3600);
            return new WP_Error(
                'unauthorized',
                'Authentication required to create token',
                ['status' => 401]
            );
        }

        // Create token for authenticated user
        $token = self::create_token($user_id);

        if (!$token) {
            return new WP_Error(
                'token_creation_failed',
                'Failed to create token',
                ['status' => 500]
            );
        }

        // Reset rate limit on successful creation
        PPV_Permissions::reset_rate_limit('token_create');

        return new WP_REST_Response([
            'success' => true,
            'token'   => $token,
            'user_id' => $user_id,
            'expires_in_hours' => 720
        ], 200);
    }

    /** ============================================================
     * 🔹 REST – token ellenőrzése
     * ============================================================ */
    public static function rest_check_token($request) {
        $user_id = self::get_user_from_token($request);
        return new WP_REST_Response([
            'valid'   => $user_id > 0,
            'user_id' => $user_id
        ], $user_id > 0 ? 200 : 401);
    }

    /** ============================================================
     * 🧹 Lejárt tokenek automatikus törlése (WP-Cron)
     * ============================================================ */
    public static function cleanup_expired_tokens() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_tokens';

        // Delete tokens that are expired
        $deleted = $wpdb->query("
            DELETE FROM $table
            WHERE expires_at IS NOT NULL
            AND expires_at < NOW()
        ");

        // Also delete very old tokens (older than 60 days) regardless of expiry
        $wpdb->query("
            DELETE FROM $table
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log("[PPV_Auth] Cleaned up $deleted expired tokens");
        }

        return $deleted;
    }
}

PPV_Auth::hooks();
