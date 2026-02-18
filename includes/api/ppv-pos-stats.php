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
        add_action('admin_init', [__CLASS__, 'ensure_indexes'], 20);
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
        // WordPress admin
        if (current_user_can('manage_options')) return true;

        // Session-based handler auth (same as QR scanner endpoints)
        if (class_exists('PPV_Permissions') && PPV_Permissions::check_handler() === true) {
            return true;
        }

        // POS token (pos_token or store_key)
        $token = $request->get_header('ppv-pos-token') ?: $request->get_param('pos_token');
        if (!empty($token)) {
            global $wpdb;
            $valid = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE (pos_token = %s OR store_key = %s)",
                $token, $token
            ));
            if ($valid > 0) return true;
        }

        return false;
    }

    /** ============================================================
     *  🔹 Statisztika lekérdezés
     * ============================================================ */
    public static function get_stats($req) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // 🏪 FILIALE SUPPORT: Use session-aware store ID, ignore request parameter
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id = intval($_SESSION['ppv_vendor_store_id']);
        } else {
            // Fallback to request parameter only if no session
            $store_id = intval($req['store_id'] ?? 0);
        }

        if (!$store_id) {
            return rest_ensure_response(['success' => false, 'message' => 'Missing store_id']);
        }

        // Check transient cache (30 sec TTL)
        $cache_key = 'ppv_pos_stats_' . $store_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            return rest_ensure_response($cached);
        }

        $today = date('Y-m-d');
        $start = "$today 00:00:00";
        $end   = "$today 23:59:59";

        // Combine 3 ppv_points queries into 1
        $points_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(CASE WHEN points > 0 THEN 1 END) AS scan_count,
                    COALESCE(SUM(CASE WHEN points > 0 THEN points ELSE 0 END), 0) AS total_points,
                    COALESCE(SUM(CASE WHEN type='sale' THEN points ELSE 0 END), 0) AS sale_points,
                    MAX(created) AS last_scan
             FROM {$prefix}ppv_points
             WHERE store_id = %d AND created BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $today_scans  = (int) ($points_stats->scan_count ?? 0);
        $today_points = (int) ($points_stats->total_points ?? 0);
        $today_sales  = (float) ($points_stats->sale_points ?? 0);

        // Last scan: use today's max if available, otherwise query all-time
        $last_scan = $points_stats->last_scan;
        if (!$last_scan) {
            $last_scan = $wpdb->get_var($wpdb->prepare(
                "SELECT MAX(created) FROM {$prefix}ppv_points WHERE store_id=%d",
                $store_id
            ));
        }

        $today_rewards = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_rewards WHERE store_id=%d AND redeemed=1 AND redeemed_at BETWEEN %s AND %s",
            $store_id, $start, $end
        ));

        $active_campaigns = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_campaigns WHERE store_id=%d AND status='active'",
            $store_id
        ));

        $chart_data = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as day, SUM(points) as total
             FROM {$prefix}ppv_points
             WHERE store_id=%d AND created >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND points > 0
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

        $response = [
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
        ];

        // Cache for 30 seconds
        set_transient($cache_key, $response, 30);

        return rest_ensure_response($response);
    }

    /**
     * Ensure ppv_points has proper indexes for stats queries (one-time)
     */
    public static function ensure_indexes() {
        if (get_option('ppv_points_idx_v', '0') === '1') {
            return;
        }
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_points';

        // Index for store stats queries (store_id + created range)
        $idx = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND INDEX_NAME = 'idx_store_created'");
        if (!$idx) {
            $wpdb->query("ALTER TABLE $table ADD INDEX idx_store_created (store_id, created)");
        }

        // Index for user last_scan query (user_id + created DESC)
        $idx2 = $wpdb->get_var("SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table' AND INDEX_NAME = 'idx_user_created'");
        if (!$idx2) {
            $wpdb->query("ALTER TABLE $table ADD INDEX idx_user_created (user_id, created)");
        }

        update_option('ppv_points_idx_v', '1', true);
    }
}

// ============================================================
// 🔹 Plugin init
// ============================================================
add_action('plugins_loaded', ['PPV_POS_STATS_API', 'hooks']);
