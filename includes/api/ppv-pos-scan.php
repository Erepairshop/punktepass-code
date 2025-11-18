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

        /** 2) QR -> User ID extrakció
         *    FONTOS: csak USER token engedett
         */
        $user_id = 0;

        if (strpos($qr, 'PPU') === 0) {
            $payload  = substr($qr, 3);
            $uid_part = substr($payload, 0, -6);
            $token6   = substr($payload, -6);
            $uid      = intval($uid_part);

            // Biztonság: valóban USER token?
            $valid = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_tokens
                WHERE entity_type='user' AND entity_id=%d
                AND token LIKE %s
            ", $uid, $token6 . '%'));

            if ($valid) {
                $user_id = $uid;
            }
        }

        if ($user_id <= 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => '🚫 Ungültiger QR-Code (kein User)'
            ]);
        }

        /** 3) USER létezik-e */
        $active_user = $wpdb->get_var($wpdb->prepare("
            SELECT active FROM {$wpdb->prefix}ppv_users WHERE id=%d LIMIT 1
        ", $user_id));

        if ($active_user === '0') {
            return rest_ensure_response(['success' => false, 'message' => '🚫 Benutzer gesperrt']);
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
                'message' => '⚠️ Heute bereits gescannt'
            ]);
        }

        /** 5) Duplikált scan (2 perc) */
        $recent = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$wpdb->prefix}ppv_pos_log
            WHERE user_id=%d AND store_id=%d
            AND created_at >= (NOW() - INTERVAL 2 MINUTE)
        ", $user_id, $store_id));

        if ($recent) {
            return rest_ensure_response(['success' => false, 'message' => '⚠️ Bereits gescannt']);
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
        self::log_event($store_id, 'qr_scan', "✅ +{$points_add} Punkte (User {$user_id})");

        /** 10) Üzenet */
        $msg = [
            'hu' => "✅ +{$points_add} pont hozzáadva",
            'ro' => "✅ +{$points_add} puncte adăugate",
            'de' => "✅ +{$points_add} Punkte hinzugefügt",
        ][$lang] ?? "✅ +{$points_add} Punkte";

        return rest_ensure_response([
            'success'  => true,
            'message'  => $msg,
            'user_id'  => $user_id,
            'store_id' => $store_id,
            'points'   => $points_add,
            'total'    => $total_points
        ]);
    }

    private static function log_event($store_id, $action, $message, $status = 'ok') {
        global $wpdb;
        $wpdb->insert("{$wpdb->prefix}ppv_pos_log", [
            'store_id'   => intval($store_id),
            'action'     => sanitize_text_field($action),
            'message'    => sanitize_textarea_field($message),
            'status'     => sanitize_text_field($status),
            'created_at' => current_time('mysql'),
        ]);
    }
}

add_action('plugins_loaded', ['PPV_POS_SCAN', 'hooks'], 5);
