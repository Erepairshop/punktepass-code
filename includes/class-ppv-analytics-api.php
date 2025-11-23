<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Analytics API (v1.2 - FIXED COLUMN NAME)
 * REST Endpoint: /wp-json/ppv/v1/analytics
 * ✅ Fixed: created_at → created (correct column name!)
 */

class PPV_Analytics_API {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_endpoints']);
    }

    public static function register_endpoints() {
        
        // Main analytics endpoint
        register_rest_route('ppv/v1', '/analytics', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_analytics'],
            'permission_callback' => [__CLASS__, 'check_permission'],
            'args' => [
                'range' => [
                    'type' => 'string',
                    'enum' => ['7', '30', '90', '365'],
                    'default' => '30',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['daily', 'weekly', 'monthly'],
                    'default' => 'daily',
                ],
                'store_id' => [
                    'type' => 'integer',
                    'default' => 0,
                ],
            ],
        ]);

        // Trend endpoint
        register_rest_route('ppv/v1', '/analytics/trend', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_trend'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        // Store breakdown endpoint
        register_rest_route('ppv/v1', '/analytics/stores', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_store_breakdown'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        // Summary stats endpoint
        register_rest_route('ppv/v1', '/analytics/summary', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'get_summary'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);
        
        // Debug endpoint
        register_rest_route('ppv/v1', '/analytics/debug', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'debug_points'],
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
        ]);
        
        ppv_log("✅ [PPV_Analytics] REST endpoints registered");
    }

    public static function check_permission() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $user_id = get_current_user_id();
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }
        
        return $user_id > 0;
    }

    public static function get_analytics($request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $user_id = get_current_user_id();
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }
        
        if ($user_id <= 0) {
            return rest_ensure_response([
                'success' => false,
                'message' => 'Not authenticated'
            ]);
        }
        
        $range = intval($request->get_param('range')) ?: 30;
        $type = $request->get_param('type') ?: 'daily';
        $store_id = intval($request->get_param('store_id')) ?: 0;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$range} days"));

        // ✅ FIXED: created_at → created
        $query = $wpdb->prepare(
            "SELECT 
                DATE(created) as date,
                SUM(points) as total_points,
                COUNT(*) as transactions,
                AVG(points) as avg_points,
                MIN(points) as min_points,
                MAX(points) as max_points,
                store_id
            FROM {$prefix}ppv_points
            WHERE user_id = %d
            AND created BETWEEN %s AND %s
            " . ($store_id ? $wpdb->prepare("AND store_id = %d", $store_id) : "") . "
            GROUP BY DATE(created)
            ORDER BY created ASC",
            $user_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        $results = $wpdb->get_results($query);

        $data = [];
        $total = 0;
        $count = 0;

        foreach ((array)$results as $row) {
            $total += (int)$row->total_points;
            $count += (int)$row->transactions;
            
            $data[] = [
                'date' => $row->date,
                'formatted_date' => self::format_date($row->date, $type),
                'points' => (int)$row->total_points,
                'transactions' => (int)$row->transactions,
                'avg_points' => round($row->avg_points, 2),
                'store_id' => (int)$row->store_id,
            ];
        }

        $avg_daily = count($data) > 0 ? round($total / count($data), 2) : 0;
        $peak_day = count($data) > 0 ? max(array_map(function($d) { return $d['points']; }, $data)) : 0;

        return rest_ensure_response([
            'success' => true,
            'data' => $data,
            'stats' => [
                'total_points' => $total,
                'total_transactions' => $count,
                'avg_daily_points' => $avg_daily,
                'peak_day_points' => $peak_day,
                'days_active' => count($data),
                'range_days' => $range,
            ],
            'range' => $range,
            'type' => $type,
            'period' => [
                'start' => $start_date,
                'end' => $end_date,
            ],
        ]);
    }

    public static function get_trend($request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $user_id = get_current_user_id();
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }
        
        if ($user_id <= 0) {
            return rest_ensure_response(['success' => false]);
        }
        
        $range = intval($request->get_param('range')) ?: 30;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$range} days"));

        // ✅ FIXED: created_at → created
        $query = $wpdb->prepare(
            "SELECT 
                DATE(created) as date,
                SUM(points) as points,
                COUNT(*) as count
            FROM {$prefix}ppv_points
            WHERE user_id = %d
            AND created BETWEEN %s AND %s
            GROUP BY DATE(created)
            ORDER BY created ASC",
            $user_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        $results = $wpdb->get_results($query);

        $trend = [];
        foreach ((array)$results as $row) {
            $trend[] = [
                'date' => $row->date,
                'day' => date('D', strtotime($row->date)),
                'short_date' => date('M d', strtotime($row->date)),
                'points' => (int)$row->points,
                'count' => (int)$row->count,
            ];
        }

        return rest_ensure_response([
            'success' => true,
            'trend' => $trend,
            'range' => $range,
        ]);
    }

    public static function get_store_breakdown($request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $user_id = get_current_user_id();
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }
        
        if ($user_id <= 0) {
            return rest_ensure_response(['success' => false]);
        }
        
        $range = intval($request->get_param('range')) ?: 30;

        global $wpdb;
        $prefix = $wpdb->prefix;

        $end_date = current_time('Y-m-d');
        $start_date = date('Y-m-d', strtotime("-{$range} days"));

        // ✅ FIXED: created_at → created
        $query = $wpdb->prepare(
            "SELECT 
                pp.store_id,
                s.company_name as store_name,
                SUM(pp.points) as total_points,
                COUNT(pp.id) as visits,
                AVG(pp.points) as avg_points
            FROM {$prefix}ppv_points pp
            LEFT JOIN {$prefix}ppv_stores s ON pp.store_id = s.id
            WHERE pp.user_id = %d
            AND pp.created BETWEEN %s AND %s
            GROUP BY pp.store_id
            ORDER BY total_points DESC",
            $user_id,
            $start_date . ' 00:00:00',
            $end_date . ' 23:59:59'
        );

        $results = $wpdb->get_results($query);

        $stores = [];
        $total_points = 0;

        foreach ((array)$results as $row) {
            $total_points += (int)$row->total_points;
            $stores[] = [
                'store_id' => (int)$row->store_id,
                'name' => $row->store_name ?: 'Unknown Store',
                'points' => (int)$row->total_points,
                'visits' => (int)$row->visits,
                'avg_points' => round($row->avg_points, 2),
                'percentage' => 0,
            ];
        }

        foreach ($stores as &$store) {
            $store['percentage'] = $total_points > 0 ? round(($store['points'] / $total_points) * 100, 1) : 0;
        }

        return rest_ensure_response([
            'success' => true,
            'stores' => $stores,
            'total_points' => $total_points,
            'total_stores' => count($stores),
            'range' => $range,
        ]);
    }

    public static function get_summary($request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        $user_id = get_current_user_id();
        if (!$user_id && !empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }
        
        if ($user_id <= 0) {
            return rest_ensure_response(['success' => false]);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // ✅ FIXED: created_at → created
        $week_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)",
            $user_id
        ));

        $month_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)",
            $user_id
        ));

        $year_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 365 DAY)",
            $user_id
        ));

        $best = $wpdb->get_row($wpdb->prepare(
            "SELECT DATE(created) as date, SUM(points) as points
            FROM {$prefix}ppv_points
            WHERE user_id = %d
            GROUP BY DATE(created)
            ORDER BY points DESC
            LIMIT 1",
            $user_id
        ));

        // Calculate streak
        $streak = 0;
        $dates = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT DATE(created) as d FROM {$prefix}ppv_points
            WHERE user_id = %d ORDER BY d DESC",
            $user_id
        ));

        if (!empty($dates)) {
            $streak = 1;
            for ($i = 1; $i < count($dates); $i++) {
                $prev = strtotime($dates[$i - 1]);
                $curr = strtotime($dates[$i]);
                $diff = ($prev - $curr) / 86400;
                
                if ($diff == 1) {
                    $streak++;
                } else {
                    break;
                }
            }
        }

        return rest_ensure_response([
            'success' => true,
            'summary' => [
                'week_points' => $week_points,
                'month_points' => $month_points,
                'year_points' => $year_points,
                'best_day' => $best ? [
                    'date' => $best->date,
                    'points' => (int)$best->points,
                ] : null,
                'current_streak' => $streak,
            ],
        ]);
    }

    public static function debug_points($request) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        
        global $wpdb;
        $prefix = $wpdb->prefix;
        
        $wp_user_id = get_current_user_id();
        $session_user_id = $_SESSION['ppv_user_id'] ?? null;
        
        $final_user_id = $wp_user_id ?: intval($session_user_id);
        
        // ✅ FIXED: created_at → created
        $all_points = $wpdb->get_results($wpdb->prepare("
            SELECT 
                id,
                user_id,
                store_id,
                points,
                created,
                DATEDIFF(NOW(), created) as days_ago
            FROM {$prefix}ppv_points
            WHERE user_id = %d
            ORDER BY created DESC
            LIMIT 10
        ", $final_user_id));
        
        $total_all_time = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points WHERE user_id = %d
        ", $final_user_id));
        
        $total_7_days = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ", $final_user_id));
        
        $total_30_days = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ", $final_user_id));
        
        $total_365_days = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points
            WHERE user_id = %d AND created >= DATE_SUB(NOW(), INTERVAL 365 DAY)
        ", $final_user_id));
        
        $trend_data = $wpdb->get_results($wpdb->prepare("
            SELECT 
                DATE(created) as date,
                SUM(points) as points,
                COUNT(*) as count,
                DATEDIFF(NOW(), created) as days_ago
            FROM {$prefix}ppv_points
            WHERE user_id = %d
            AND created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            GROUP BY DATE(created)
            ORDER BY created DESC
        ", $final_user_id));
        
        return rest_ensure_response([
            'success' => true,
            'user_id' => $final_user_id,
            'totals' => [
                'all_time' => $total_all_time,
                'last_7_days' => $total_7_days,
                'last_30_days' => $total_30_days,
                'last_365_days' => $total_365_days,
            ],
            'recent_points' => $all_points,
            'trend_last_30_days' => $trend_data,
            'table_name' => $prefix . 'ppv_points',
        ]);
    }

    private static function format_date($date, $type = 'daily') {
        switch ($type) {
            case 'weekly':
                return 'Week ' . date('W', strtotime($date));
            case 'monthly':
                return date('M Y', strtotime($date));
            case 'daily':
            default:
                return date('M d', strtotime($date));
        }
    }
}

PPV_Analytics_API::hooks();