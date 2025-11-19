<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS Scan API v4.7 (Ultra Fixed)
 * ✅ entity_type=user filter
 * ✅ Händler QR kizárva
 * ✅ POS token safe
 * ✅ Nincs több 3836 keveredés
 * ✅ Napi limit + duplikált scan
 */

class PPV_POS_SCAN {

    public static function hooks() {
        add_filter('rest_authentication_errors', [__CLASS__, 'allow_pos_requests'], 5);
        add_action('rest_api_init', [__CLASS__, 'register_routes'], 10);
    }

    public static function allow_pos_requests($result) {
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($uri, '/ppv/v1/pos/scan') !== false) {
            return true;
        }
        return $result;
    }

    public static function register_routes() {
        register_rest_route('ppv/v1', '/pos/scan', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'handle_scan'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        register_rest_route('ppv/v1', '/pos/recent-scans', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'get_recent_scans'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);
    }

    public static function handle_scan(WP_REST_Request $req) {
        global $wpdb;

        $p = $req->get_json_params() ?: [];
        $qr        = sanitize_text_field($p['qr'] ?? '');
        $store_key = sanitize_text_field($p['store_key'] ?? '');
        $lang      = sanitize_text_field($p['lang'] ?? 'de');

        if ($qr === '') {
            return rest_ensure_response(['success' => false, 'message' => '❌ Kein QR-Code empfangen']);
        }

        /** 1) POS Store detektálás */
        $store = null;

        // POS session cookie
        if (!empty($_COOKIE['ppv_pos_token'])) {
            $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores
                WHERE pos_token=%s AND pos_enabled=1
                LIMIT 1
            ", $token));
        }

        // REST paraméter
        if (!$store && !empty($store_key)) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores
                WHERE store_key=%s OR pos_token=%s
                LIMIT 1
            ", $store_key, $store_key));
        }

        if (!$store) {
            return rest_ensure_response([
                'success' => false,
                'message' => '❌ Kein aktiver POS-Store'
            ]);
        }

        $store_id = intval($store->id);

        /** 2) IP Rate Limiting (10 scans per minute) */
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);

        $recent_scans_from_ip = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_pos_log
            WHERE ip_address=%s AND created_at >= (NOW() - INTERVAL 1 MINUTE)
        ", $ip_address));

        if ($recent_scans_from_ip >= 10) {
            self::log_event($store_id, 'rate_limit', "🚫 Rate limit exceeded from IP {$ip_address}", 'blocked');
            return rest_ensure_response([
                'success' => false,
                'message' => '🚫 Zu viele Anfragen. Bitte warten Sie.'
            ]);
        }

        /** 3) QR -> User ID extraction with security
         *    IMPORTANT: Only USER tokens allowed
         *    Format: PPU{user_id}{16-char-token}
         */
        $user_id = 0;

        if (strpos($qr, 'PPU') === 0) {
            $payload = substr($qr, 3);

            // Find where user_id ends (first non-digit)
            if (preg_match('/^(\d+)(.+)$/', $payload, $matches)) {
                $uid = intval($matches[1]);
                $token_from_qr = $matches[2];

                // Security: Verify token + active status in single JOIN query
                $user_check = $wpdb->get_row($wpdb->prepare("
                    SELECT u.id, u.active
                    FROM {$wpdb->prefix}ppv_users u
                    INNER JOIN {$wpdb->prefix}ppv_tokens t
                        ON t.entity_type='user' AND t.entity_id=u.id
                    WHERE u.id=%d
                        AND t.token=%s
                        AND t.expires_at > NOW()
                    LIMIT 1
                ", $uid, $token_from_qr));

                if ($user_check && $user_check->active == 1) {
                    $user_id = intval($user_check->id);
                } elseif ($user_check && $user_check->active == 0) {
                    self::log_scan_attempt($store_id, $uid, $ip_address, 'blocked', 'User inactive');
                    return rest_ensure_response([
                        'success' => false,
                        'message' => '🚫 Benutzer gesperrt',
                        'store_name' => $store->name ?? 'PunktePass',
                        'error_type' => 'user_blocked'
                    ]);
                }
            }
        }

        if ($user_id <= 0) {
            self::log_scan_attempt($store_id, 0, $ip_address, 'invalid', 'Invalid QR code');
            return rest_ensure_response([
                'success' => false,
                'message' => '🚫 Ungültiger QR-Code (kein User)',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'invalid_qr'
            ]);
        }

        /** 4) Napi limit */
        $already_today = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points
            WHERE user_id=%d AND store_id=%d
            AND DATE(created)=CURDATE()
            AND type='qr_scan'
        ", $user_id, $store_id));

        if ($already_today > 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => '⚠️ Heute bereits gescannt',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'already_scanned_today'
            ]);
        }

        /** 5) Duplikált scan (2 perc) */
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_pos_log
            WHERE user_id=%d AND store_id=%d
            AND created_at >= (NOW() - INTERVAL 2 MINUTE)
        ", $user_id, $store_id));

        if ($recent) {
            return rest_ensure_response([
                'success' => false,
                'message' => '⚠️ Bereits gescannt',
                'store_name' => $store->name ?? 'PunktePass',
                'error_type' => 'duplicate_scan'
            ]);
        }

        /** 6) Bónusz nap */
        $points_add = 1;

        $bonus = $wpdb->get_row($wpdb->prepare("
            SELECT multiplier, extra_points FROM {$wpdb->prefix}ppv_bonus_days
            WHERE store_id=%d AND date=%s AND active=1
        ", $store_id, date('Y-m-d')));

        if ($bonus) {
            $points_add = (int)round(($points_add * (float)$bonus->multiplier) + (int)$bonus->extra_points);
        }

        /** 7) Mentés */
        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'   => $user_id,
            'store_id'  => $store_id,
            'points'    => $points_add,
            'type'      => 'qr_scan',
            'reference' => 'POS Scan',
            'created'   => current_time('mysql'),
        ]);

        /** 8) Teljes pont */
        $total_points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d
        ", $user_id));

        /** 9) Log */
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        self::log_scan_attempt($store_id, $user_id, $ip_address, 'ok', "✅ +{$points_add} Punkte");

        /** 10) Üzenet */
        $msg = [
            'hu' => "✅ +{$points_add} pont hozzáadva",
            'ro' => "✅ +{$points_add} puncte adăugate",
            'de' => "✅ +{$points_add} Punkte hinzugefügt",
        ][$lang] ?? "✅ +{$points_add} Punkte";

        return rest_ensure_response([
            'success'    => true,
            'message'    => $msg,
            'user_id'    => $user_id,
            'store_id'   => $store_id,
            'store_name' => $store->name ?? 'PunktePass',
            'points'     => $points_add,
            'total'      => $total_points
        ]);
    }

    private static function log_event($store_id, $action, $message, $status = 'ok') {
        global $wpdb;
        $ip_address = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
        $ip_address = sanitize_text_field(explode(',', $ip_address)[0]);
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'message'    => sanitize_textarea_field($message),
            'status'     => sanitize_text_field($status),
            'ip_address' => $ip_address,
            'user_agent' => $user_agent,
            'created_at' => current_time('mysql'),
        ]);
    }

    private static function log_scan_attempt($store_id, $user_id, $ip_address, $status, $reason) {
        global $wpdb;
        $user_agent = sanitize_text_field($_SERVER['HTTP_USER_AGENT'] ?? '');

        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'user_id'    => intval($user_id),
            'message'    => sanitize_text_field($reason),
            'status'     => sanitize_text_field($status),
            'ip_address' => sanitize_text_field($ip_address),
            'user_agent' => $user_agent,
            'created_at' => current_time('mysql'),
        ]);
    }

    /** ============================================================
     * 📋 Get Recent Scans (for live table refresh)
     * ============================================================ */
    public static function get_recent_scans(WP_REST_Request $req) {
        global $wpdb;

        // Get store_id from session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $store_id = intval($_SESSION['ppv_store_id'] ?? 0);

        if ($store_id === 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'No store_id in session'
            ]);
        }

        // Get last 12 scans for this store
        $scans = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.created,
                p.points,
                u.email,
                CONCAT(u.first_name, ' ', u.last_name) as name
            FROM {$wpdb->prefix}ppv_points p
            LEFT JOIN {$wpdb->prefix}ppv_users u ON p.user_id = u.id
            WHERE p.store_id = %d AND p.type = 'qr_scan'
            ORDER BY p.created DESC
            LIMIT 12
        ", $store_id));

        $formatted = [];
        foreach ($scans as $scan) {
            $time = date('H:i:s', strtotime($scan->created));
            $user_display = !empty(trim($scan->name)) ? $scan->name : $scan->email;
            $status = "✅ +{$scan->points}";

            $formatted[] = [
                'time' => $time,
                'user' => $user_display,
                'status' => $status
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'scans' => $formatted
        ]);
    }
}

add_action('plugins_loaded', ['PPV_POS_SCAN', 'hooks'], 5);
