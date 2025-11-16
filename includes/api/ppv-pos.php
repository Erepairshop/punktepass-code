<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – POS API (Sync Bridge)
 * Version: 4.2 Stable
 * Features:
 * ✅ POS-token authentication (header / GET / cookie)
 * ✅ Auto Session Restore via PPV_Session
 * ✅ Safe point + reward sync from cash register
 * ✅ Unified log + error handling
 */

class PPV_POS_API {

    /** ============================================================
     * 🔹 Hook registration
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     * 🔐 Permission check (POS token or admin)
     * ============================================================ */
    public static function verify_pos_or_user($request = null) {
        global $wpdb;

        // ✅ WP-admin mindig engedélyezett
        if (is_user_logged_in() && current_user_can('manage_options')) return true;

        // 🔹 Token from header / GET / cookie
        $token = '';
        if ($request) $token = $request->get_header('ppv-pos-token');
        if (empty($token) && isset($_GET['pos_token'])) $token = sanitize_text_field($_GET['pos_token']);
        if (empty($token) && isset($_COOKIE['ppv_pos_token'])) $token = sanitize_text_field($_COOKIE['ppv_pos_token']);

        if (empty($token)) {
            error_log("❌ [PPV_POS_API] Missing POS token");
            return false;
        }

        // 🔹 Ellenőrzés adatbázisban
        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token=%s AND pos_enabled=1",
            $token
        ));

        if ($exists > 0) {
            error_log("✅ [PPV_POS_API] Token accepted ({$token})");
            return true;
        }

        error_log("🚫 [PPV_POS_API] Invalid POS token ({$token})");
        return false;
    }

    /** ============================================================
     * 🔹 REST route registration
     * ============================================================ */
    public static function register_routes() {
        register_rest_route('punktepass/v1', '/pos/sync', [
            'methods'  => ['POST', 'GET'],
            'callback' => [__CLASS__, 'sync_transaction'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);
    }

    /** ============================================================
     * 🔹 Main transaction sync handler
     * ============================================================ */
    public static function sync_transaction($req) {
        global $wpdb;

        $params = $req->get_json_params();
        if (empty($params)) $params = $req->get_params();

        $store_id       = intval($params['store_id'] ?? 0);
        $store_key      = sanitize_text_field($params['store_key'] ?? '');
        $email          = sanitize_text_field($params['email'] ?? '');
        $points         = intval($params['points'] ?? 0);
        $reward_code    = sanitize_text_field($params['reward_code'] ?? '');
        $transaction_id = sanitize_text_field($params['transaction_id'] ?? '');
        $amount         = floatval($params['amount'] ?? 0);

        error_log("🧠 [PPV_POS_API] Incoming POS sync | store_id={$store_id} | key={$store_key} | email={$email}");

        /** =========================================================
         * 🩵 Auto Session Restore (POS-token or key)
         * ========================================================= */
        if (class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();

            // Fallback: ha nincs aktív session, próbáljuk a kulcsot
            if (!$store && !empty($store_key)) {
                $store = PPV_Session::get_store_by_key($store_key);
                if ($store) {
                    $_SESSION['ppv_active_store'] = intval($store->id);
                    $_SESSION['ppv_is_pos'] = true;
                    $GLOBALS['ppv_active_store'] = $store;
                    $GLOBALS['ppv_is_pos'] = true;
                    $store_id = intval($store->id);
                    error_log("✅ [PPV_POS_API] Store auto-restored via key={$store_id}");
                }
            }
        }

        /** =========================================================
         * ❌ Validáció
         * ========================================================= */
        if (!$store_id || (empty($email) && empty($transaction_id))) {
            return rest_ensure_response([
                'status'  => 'error',
                'message' => '❌ store_id or email missing (no valid session or key)',
                'debug'   => $params
            ]);
        }

        /** =========================================================
         * 👤 User identification
         * ========================================================= */
        $user_id = 0;
        if (!empty($email)) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1",
                $email
            ));
        }

        /** =========================================================
         * ➕ Point insertion
         * ========================================================= */
        if ($points > 0 && $user_id > 0) {
            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id'  => $user_id,
                'store_id' => $store_id,
                'points'   => $points,
                'created'  => current_time('mysql'),
                'note'     => 'POS Sync #' . esc_sql($transaction_id)
            ]);
            error_log("✅ [PPV_POS_API] {$points} Punkte hinzugefügt | user={$user_id} | store={$store_id}");
        }

        /** =========================================================
         * 🎁 Reward redemption (if provided)
         * ========================================================= */
        if (!empty($reward_code) && $user_id > 0) {
            do_action('ppv_redeem_reward_from_pos', [
                'user_id'     => $user_id,
                'store_id'    => $store_id,
                'reward_code' => $reward_code
            ]);
            error_log("🎁 [PPV_POS_API] Reward redeem via POS | user={$user_id} | code={$reward_code}");
        }

        /** =========================================================
         * 📊 Calculate new total points
         * ========================================================= */
        $new_points = 0;
        if ($user_id > 0) {
            $new_points = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT SUM(points) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d",
                $user_id
            ));
        }

        /** =========================================================
         * ✅ Response
         * ========================================================= */
        return rest_ensure_response([
            'status'         => 'ok',
            'message'        => 'POS sync success',
            'user_points'    => $new_points,
            'store_id'       => $store_id,
            'transaction_id' => $transaction_id,
        ]);
    }
}

// ============================================================
// 🔹 Init
// ============================================================
add_action('init', function() {
    if (class_exists('PPV_POS_API')) PPV_POS_API::hooks();
});
