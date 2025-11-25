<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Main REST API (User + POS Unified)
 * Version: 4.3 Stable
 * Author: Erik Borota / PunktePass
 */

class PPV_API {

    /** ============================================================
     * 🔹 Hook registration
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     * 🔐 Permission check (WP user or POS token)
     * ============================================================ */
    public static function verify_pos_or_user($request = null) {
        global $wpdb;

        // Admin → mindig engedélyezett
        if (is_user_logged_in() && current_user_can('manage_options')) return true;

        // Token headerből / GET / cookie
        $token = '';
        if ($request) $token = $request->get_header('ppv-pos-token');
        if (empty($token) && isset($_GET['pos_token'])) $token = sanitize_text_field($_GET['pos_token']);
        if (empty($token) && isset($_COOKIE['ppv_pos_token'])) $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
        if (empty($token)) return false;

        $exists = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token=%s AND pos_enabled=1",
            $token
        ));
        return $exists > 0;
    }

    /** ============================================================
     * 🔹 Register REST routes
     * ============================================================ */
    public static function register_routes() {

        register_rest_route('punktepass/v1', '/check-user', [
            'methods' => ['POST', 'GET'],
            'callback' => [__CLASS__, 'check_user'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);

        register_rest_route('punktepass/v1', '/add-points', [
            'methods' => ['POST', 'GET'],
            'callback' => [__CLASS__, 'add_points'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);

        register_rest_route('punktepass/v1', '/mypoints', [
            'methods' => ['GET'],
            'callback' => [__CLASS__, 'get_mypoints'],
            'permission_callback' => [__CLASS__, 'verify_pos_or_user']
        ]);
    }

    /** ============================================================
     * 🔹 1️⃣ Check user points
     * ============================================================ */
    public static function check_user($req) {
        global $wpdb;
        $params = $req->get_json_params();
        $email   = sanitize_text_field($params['email'] ?? ($_GET['email'] ?? ''));
        $user_id = intval($params['user_id'] ?? ($_GET['user_id'] ?? 0));

        if ($email && !$user_id) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1", $email
            ));
        }
        if (!$user_id) return ['status'=>'error','message'=>'User not found'];

        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        return [
            'status'=>'ok',
            'user_id'=>$user_id,
            'email'=>$email,
            'total_points'=>$total_points
        ];
    }

    /** ============================================================
     * 🔹 2️⃣ Add points (POS or user)
     * ============================================================ */
    public static function add_points($req) {
        global $wpdb;
        $params = $req->get_json_params();
        if (empty($params)) $params = $req->get_params();

        $email    = sanitize_text_field($params['email'] ?? ($_GET['email'] ?? ''));
        $user_id  = intval($params['user_id'] ?? ($_GET['user_id'] ?? 0));
        $store_id = intval($params['store_id'] ?? ($_GET['store_id'] ?? 0));
        $points   = intval($params['points'] ?? ($_GET['points'] ?? 0));
        $store_key = sanitize_text_field($params['store_key'] ?? ($_GET['store_key'] ?? ''));

        /** 🩵 Auto session restore via PPV_Session */
        if (class_exists('PPV_Session')) {
            $store = PPV_Session::current_store();
            if (!$store && $store_key) {
                $store = PPV_Session::get_store_by_key($store_key);
                if ($store) {
                    $store_id = intval($store->id);
                    $_SESSION['ppv_active_store'] = $store_id;
                    $_SESSION['ppv_is_pos'] = true;
                    $GLOBALS['ppv_active_store'] = $store;
                    $GLOBALS['ppv_is_pos'] = true;
                }
            }
        }

        if ($email && !$user_id) {
            $user_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_users WHERE email=%s LIMIT 1", $email
            ));
        }

        if (!$user_id || !$points) {
            return ['status'=>'error','message'=>'Missing user or points'];
        }

        $wpdb->insert("{$wpdb->prefix}ppv_points", [
            'user_id'=>$user_id,
            'store_id'=>$store_id,
            'points'=>$points,
            'created'=>current_time('mysql')
        ]);

        // Update lifetime_points for VIP level calculation (only for positive points)
        if (class_exists('PPV_User_Level') && $points > 0) {
            PPV_User_Level::add_lifetime_points($user_id, $points);
        }

        $new_total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        return [
            'status'=>'ok',
            'user_id'=>$user_id,
            'email'=>$email,
            'points_added'=>$points,
            'total_points'=>$new_total,
            'store_id'=>$store_id
        ];
    }

    /** ============================================================
     * 🔹 3️⃣ MyPoints data + translations
     * ============================================================ */
    public static function get_mypoints($req) {
        global $wpdb;
        if (class_exists('PPV_Lang')) PPV_Lang::boot();

        $user_id = intval($req->get_param('user_id'));
        if (!$user_id && is_user_logged_in()) $user_id = get_current_user_id();
        if (!$user_id) return ['status'=>'error','message'=>PPV_Lang::t('please_login')];

        $points_total = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        $avg_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT ROUND(AVG(points)) FROM {$wpdb->prefix}ppv_points WHERE user_id=%d", $user_id
        ));

        $best_day = $wpdb->get_row($wpdb->prepare(
            "SELECT DATE(created) as day, SUM(points) as total 
             FROM {$wpdb->prefix}ppv_points 
             WHERE user_id=%d GROUP BY DATE(created)
             ORDER BY total DESC LIMIT 1", $user_id
        ));

        $top_store = $wpdb->get_row($wpdb->prepare(
            "SELECT s.company_name as store_name, SUM(p.points) as total 
             FROM {$wpdb->prefix}ppv_points p
             LEFT JOIN {$wpdb->prefix}ppv_stores s ON p.store_id=s.id
             WHERE p.user_id=%d GROUP BY p.store_id
             ORDER BY total DESC LIMIT 1", $user_id
        ));

        $labels = [
            'title'=>PPV_Lang::t('title'),
            'total'=>PPV_Lang::t('total'),
            'avg'=>PPV_Lang::t('avg'),
            'best_day'=>PPV_Lang::t('best_day'),
            'top_store'=>PPV_Lang::t('top_store'),
            'next_reward'=>PPV_Lang::t('next_reward'),
            'remaining'=>PPV_Lang::t('remaining'),
            'top3'=>PPV_Lang::t('top3'),
            'recent'=>PPV_Lang::t('recent'),
            'motivation'=>PPV_Lang::t('motivation'),
        ];

        return [
            'status'=>'ok',
            'labels'=>$labels,
            'data'=>[
                'total'=>$points_total,
                'avg'=>$avg_points,
                'top_day'=>$best_day,
                'top_store'=>$top_store,
            ]
        ];
    }
}

// ============================================================
// 🔹 Init
// ============================================================
add_action('plugins_loaded', function() {
    if (class_exists('PPV_API')) PPV_API::hooks();
});
