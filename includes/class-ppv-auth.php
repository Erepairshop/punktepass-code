<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Unified Auth System v5.1
 * ✅ Egységes token-kezelés böngésző, POS és Dock számára
 * ✅ Namespace: punktepass/v1 + ppv/v1
 * ✅ Biztonságos REST permission_callback
 * ✅ Kompatibilis POS és QR modulokkal
 */

// 🔓 REST engedélyezés POS és Dock számára (mindkét namespace)
add_filter('rest_authentication_errors', function ($result) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/punktepass/v1/') !== false || strpos($uri, '/ppv/v1/') !== false) {
        return null; // engedélyezett minden PunktePass és POS API hívás
    }
    return $result;
});

class PPV_Auth {

    /** ============================================================
     * 🔹 Inicializálás
     * ============================================================ */
    public static function hooks() {
        add_action('init', [__CLASS__, 'create_token_table']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     * 🔹 Token tábla létrehozása
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
            INDEX(user_id)
        ) $charset;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
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
            'permission_callback' => '__return_true'
        ]);

        register_rest_route('punktepass/v1', '/auth/check', [
            'methods'             => ['GET'],
            'callback'            => [__CLASS__, 'rest_check_token'],
            'permission_callback' => '__return_true'
        ]);
    }

    /** ============================================================
     * 🔹 REST – token létrehozása
     * ============================================================ */
    public static function rest_create_token($request) {
        $user_id = get_current_user_id() ?: intval($request->get_param('user_id'));
        if (!$user_id) {
            return new WP_REST_Response(['error' => 'no_user'], 401);
        }

        $token = self::create_token($user_id);
        return new WP_REST_Response([
            'token'   => $token,
            'user_id' => $user_id
        ]);
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
}

PPV_Auth::hooks();
