<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Rewards API (v2.1 Unified)
 * POS + User kompatibilis REST rendszer
 * ------------------------------------------------------------
 * ✅ Engedélyezés: WP user vagy POS token
 * ✅ Egységes logika: check / redeem / save / list
 * ✅ Reward log + pontlevonás integráltan
 */

class PPV_Rewards_API {

    /** ============================================================
     *  🔹 Hook inicializálás
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     *  🔹 Engedélyezés – WP user vagy POS token
     * ============================================================ */
    public static function verify_access() {
        if (is_user_logged_in()) return true;

        if (!empty($_COOKIE['ppv_pos_token'])) {
            global $wpdb;
            $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token=%s AND pos_enabled=1",
                $token
            ));
            if ($exists > 0) return true;
        }
        return false;
    }

    /** ============================================================
     *  🔐 GET STORE ID (with FILIALE support)
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        // 🔐 Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Fallback: vendor store
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Fallback: WordPress user (rare case)
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                return intval($store_id);
            }
        }

        return 0;
    }

    /** ============================================================
     *  🔹 Route regisztráció
     * ============================================================ */
    public static function register_routes() {

        register_rest_route('punktepass/v1', '/rewards/check', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'check_rewards'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/rewards/redeem', [
            'methods' => ['GET', 'POST'],
            'callback' => [__CLASS__, 'redeem_reward'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/rewards/save', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'save_reward'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);

        register_rest_route('punktepass/v1', '/rewards/list', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'list_rewards'],
            'permission_callback' => [__CLASS__, 'verify_access']
        ]);
    }

    /** ============================================================
     *  1️⃣ Reward jogosultság lekérdezés
     * ============================================================ */
    public static function check_rewards($req) {
        global $wpdb;

        $email = sanitize_text_field($req->get_param('email'));
        $user_id = intval($req->get_param('user_id') ?? 0);

        if (!$user_id && $email) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s", $email
            ));
        }

        if (!$user_id) {
            return rest_ensure_response(['success' => false, 'message' => 'User not found']);
        }

        $points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        $rewards = $wpdb->get_results("
            SELECT id, title, required_points, action_type, action_value
            FROM {$wpdb->prefix}ppv_rewards
            WHERE active=1
            ORDER BY required_points ASC
        ");

        foreach ($rewards as &$r) {
            $r->eligible = ($points >= $r->required_points);
        }

        return rest_ensure_response([
            'success' => true,
            'user_id' => $user_id,
            'current_points' => $points,
            'rewards' => $rewards
        ]);
    }

    /** ============================================================
     *  2️⃣ Reward beváltás (User vagy POS)
     * ============================================================ */
    public static function redeem_reward($req) {
        global $wpdb;

        $params = array_merge($req->get_json_params() ?: [], $req->get_query_params() ?: []);
        $email  = sanitize_text_field($params['email'] ?? '');
        $reward_code = sanitize_text_field($params['reward_code'] ?? '');
        $user_id  = intval($params['user_id'] ?? 0);

        // 🏪 FILIALE SUPPORT: Use session-aware store ID with proper priority
        $store_id = intval($params['store_id'] ?? 0);
        if (!$store_id) {
            $store_id = self::get_store_id();
        }

        // 🔹 Fallback – emailből user ID
        if (!$user_id && $email) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s", $email
            ));
        }

        if (!$user_id || !$reward_code) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing user or reward code']);
        }

        // 🔹 Reward keresés (title / value alapján)
        $reward = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id=%d AND (title LIKE %s OR action_value LIKE %s)
            LIMIT 1
        ", $store_id, '%' . $wpdb->esc_like($reward_code) . '%', '%' . $wpdb->esc_like($reward_code) . '%'));

        if (!$reward) {
            return rest_ensure_response(['success' => false, 'message' => 'Reward not found']);
        }

        // 🔹 Pont ellenőrzés
        $current_points = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d
        ", $user_id));

        if ($current_points < $reward->required_points) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Not enough points',
                'needed'  => (int)$reward->required_points,
                'current' => $current_points
            ]);
        }

        // 🔹 Tranzakciós pontlevonás + log
        $wpdb->query('START TRANSACTION');
        try {
            $wpdb->insert("{$wpdb->prefix}ppv_points", [
                'user_id' => $user_id,
                'store_id' => $store_id,
                'points' => -abs($reward->required_points),
                'type' => 'redeem',
                'reference' => $reward->title,
                'created' => current_time('mysql')
            ]);

            $wpdb->insert("{$wpdb->prefix}ppv_reward_requests", [
                'user_id' => $user_id,
                'store_id' => $store_id,
                'reward_id' => $reward->id,
                'status' => 'approved',
                'created_at' => current_time('mysql')
            ]);

            $wpdb->query('COMMIT');
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return rest_ensure_response(['success' => false, 'message' => 'DB transaction failed']);
        }

        $remaining = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d
        ", $user_id));

        return rest_ensure_response([
            'success' => true,
            'message' => "{$reward->title} eingelöst",
            'data' => [
                'user_id' => $user_id,
                'store_id' => $store_id,
                'points_deducted' => (int)$reward->required_points,
                'remaining_points' => $remaining,
                'reward_type' => $reward->action_type,
                'reward_value' => $reward->action_value
            ]
        ]);
    }

    /** ============================================================
     *  3️⃣ Reward mentés (Händler oldalon)
     * ============================================================ */
    public static function save_reward($req) {
        global $wpdb;
        $params = $req->get_json_params();

        // 🏪 FILIALE SUPPORT: Use session-aware store ID with proper priority
        $store_id = intval($params['store_id'] ?? 0);
        if (!$store_id) {
            $store_id = self::get_store_id();
        }

        $title = sanitize_text_field($params['title'] ?? '');
        $required_points = intval($params['required_points'] ?? 0);
        $description = sanitize_textarea_field($params['description'] ?? '');
        $action_type = sanitize_text_field($params['action_type'] ?? 'info');
        $action_value = sanitize_text_field($params['action_value'] ?? '');

        if (!$title || !$required_points) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing data']);
        }

        $wpdb->insert("{$wpdb->prefix}ppv_rewards", [
            'store_id' => $store_id,
            'title' => $title,
            'required_points' => $required_points,
            'description' => $description,
            'action_type' => $action_type,
            'action_value' => $action_value,
            'active' => 1,
            'created_at' => current_time('mysql')
        ]);

        return rest_ensure_response([
            'success' => true,
            'message' => 'Reward gespeichert',
            'data' => [
                'title' => $title,
                'required_points' => $required_points,
                'type' => $action_type,
                'value' => $action_value
            ]
        ]);
    }

    /** ============================================================
     *  4️⃣ Reward lista lekérdezés
     * ============================================================ */
    public static function list_rewards($req) {
        global $wpdb;

        // 🏪 FILIALE SUPPORT: Use session-aware store ID with proper priority
        $store_id = intval($req->get_param('store_id') ?? 0);
        if (!$store_id) {
            $store_id = self::get_store_id();
        }

        if (!$store_id) {
            return rest_ensure_response(['success' => false, 'message' => 'Store ID missing']);
        }

        $rewards = $wpdb->get_results($wpdb->prepare("
            SELECT id, title, required_points, description, action_type, action_value, active
            FROM {$wpdb->prefix}ppv_rewards
            WHERE store_id=%d
            ORDER BY id DESC
        ", $store_id));

        return rest_ensure_response(['success' => true, 'rewards' => $rewards]);
    }
}

add_action('plugins_loaded', ['PPV_Rewards_API', 'hooks']);
