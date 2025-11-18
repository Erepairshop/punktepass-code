<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass POS Stats API
 * – POS Dashboard statisztikákhoz
 */

class PPV_POS_STATS_API {

    /** ============================================================
     *  🔹 Hook regisztrálása
     * ============================================================ */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /** ============================================================
     *  🔹 REST route regisztráció
     * ============================================================ */
    public static function register_routes() {

        $routes = rest_get_server()->get_routes();
        if (isset($routes['/ppv/v1/pos/stats'])) {
            // Már regisztrálva másik modulban – kihagyjuk
            return;
        }

        register_rest_route('ppv/v1', '/pos/stats', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_stats'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
    }

    /** ============================================================
     *  🔹 Jogosultság-ellenőrzés
     * ============================================================ */
    public static function check_permission($request) {
        if (current_user_can('manage_options')) return true;

        $token = $request->get_header('ppv-pos-token') ?: $request->get_param('pos_token');
        if (empty($token)) return false;

        global $wpdb;
        $valid = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s AND pos_enabled = 1",
            $token
        ));

        return ($valid > 0);
    }

    /** ============================================================
     *  🔹 Statisztika lekérdezés
     * ============================================================ */
    public static function get_stats($req) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $store_id = intval($req['store_id'] ?? 0);

        if (!$store_id) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing store_id']);
        }

        $today = date('Y-m-d');
        $start = "$today 00:00:00";
        $end   = "$today 23:59:59";

        $today_scans = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_points WHERE store_id=%d AND created BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $today_points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$prefix}ppv_points WHERE store_id=%d AND created BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $today_rewards = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_rewards WHERE store_id=%d AND redeemed=1 AND redeemed_at BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $active_campaigns = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_campaigns WHERE store_id=%d AND status='active'",
            $store_id
        ));

        $today_sales = (float) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points) FROM {$prefix}ppv_points WHERE store_id=%d AND type='sale' AND created BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $last_scan = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(created) FROM {$prefix}ppv_points WHERE store_id=%d",
            $store_id
        ));

        $chart_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as day, SUM(points) as total 
             FROM {$prefix}ppv_points 
             WHERE store_id=%d 
             GROUP BY DATE(created)
             ORDER BY day DESC 
             LIMIT 7",
            $store_id
        ));

        $chart = [];
        foreach (array_reverse($chart_data) as $row) {
            $chart[] = [
                'day' => date('d.m', strtotime($row->day)),
                'points' => (int) $row->total,
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'stats' => [
                'today_scans'      => $today_scans,
                'today_points'     => $today_points,
                'today_rewards'    => $today_rewards,
                'active_campaigns' => $active_campaigns,
                'today_sales'      => round($today_sales, 2),
                'last_scan'        => $last_scan ?: '—',
                'chart'            => $chart,
            ]
        ]);
    }
}

// ============================================================
// 🔹 Plugin init
// ============================================================
add_action('plugins_loaded', ['PPV_POS_STATS_API', 'hooks']);
