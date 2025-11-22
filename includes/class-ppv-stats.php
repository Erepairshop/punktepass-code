<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Complete Stats Dashboard (v3.1)
 * ✅ 1-3: Basic Stats (Daily, Top5, Peak Hours)
 * ✅ 4-7: Advanced Stats (Trend, Spending, Conversion, Export)
 * ✅ PPV_Lang translations integration
 * ✅ JAVÍTÁS 3: Fordítások a render_stats_dashboard() függvényben
 * Author: PunktePass
 */

class PPV_Stats {

    // ========================================
    // 🔧 HOOKS
    // ========================================
    public static function hooks() {
        add_shortcode('ppv_stats_dashboard', [__CLASS__, 'render_stats_dashboard']);
        add_action('rest_api_init', [__CLASS__, 'register_rest']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 1);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets'], 1);
        error_log("✅ [PPV_Stats] Hooks registered");
    }

    // ========================================
    // 🌐 GET USER LANGUAGE
    // ========================================
    private static function get_user_lang() {
        static $lang = null;
        if ($lang !== null) return $lang;

        if (isset($_COOKIE['ppv_lang'])) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang']);
        } elseif (isset($_GET['lang'])) {
            $lang = sanitize_text_field($_GET['lang']);
        } else {
            $lang = substr(get_locale(), 0, 2);
        }

        return $lang ?: 'de';
    }

    // ========================================
    // 🔍 HELPER: Get Store ID (with FILIALE support)
    // ========================================
    public static function get_handler_store_id() {
        error_log("🔍 [Stats] get_handler_store_id() START");

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $sid = intval($_SESSION['ppv_current_filiale_id']);
            error_log("✅ [Stats] Store from SESSION (FILIALE): {$sid}");
            return $sid;
        }

        // 1️⃣ GLOBALS
        if (!empty($GLOBALS['ppv_active_store_id'])) {
            $sid = intval($GLOBALS['ppv_active_store_id']);
            error_log("✅ [Stats] Store from GLOBALS: {$sid}");
            return $sid;
        }

        // 2️⃣ SESSION - Direct (base store)
        if (!empty($_SESSION['ppv_store_id'])) {
            $sid = intval($_SESSION['ppv_store_id']);
            error_log("✅ [Stats] Store from SESSION (store): {$sid}");
            return $sid;
        }

        // 3️⃣ SESSION - Vendor
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            $sid = intval($_SESSION['ppv_vendor_store_id']);
            error_log("✅ [Stats] Store from SESSION (vendor): {$sid}");
            return $sid;
        }

        // 4️⃣ SESSION - Active
        if (!empty($_SESSION['ppv_active_store'])) {
            $sid = intval($_SESSION['ppv_active_store']);
            error_log("✅ [Stats] Store from SESSION (active): {$sid}");
            return $sid;
        }

        // 5️⃣ DB FALLBACK
        global $wpdb;
        $uid = get_current_user_id();
        if ($uid > 0) {
            error_log("📊 [Stats] Checking DB for WP user: {$uid}");
            $sid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($sid) {
                $sid = intval($sid);
                error_log("✅ [Stats] Store from DB: {$sid}");
                return $sid;
            }
        }

        error_log("❌ [Stats] NO STORE FOUND!");
        return null;
    }

    // ========================================
    // 🔐 PERMISSION CHECK
    // ========================================
    public static function check_handler_permission($request = null) {
        $store_id = self::get_handler_store_id();
        
        if (!$store_id) {
            error_log("🚫 [Stats Perm] DENIED - no store");
            return new WP_Error('unauthorized', 'Not authenticated', ['status' => 403]);
        }
        
        error_log("✅ [Stats Perm] OK - store={$store_id}");
        return true;
    }

    // ========================================
    // 📡 REGISTER REST ROUTES
    // ========================================
    public static function register_rest() {
        register_rest_route('punktepass/v1', '/stats', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_stats'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/export', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_export_csv'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/trend', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_trend'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/spending', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_spending'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/conversion', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_conversion'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/export-advanced', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_export_advanced'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        error_log("✅ [PPV_Stats] ALL REST routes OK");
    }

    // ========================================
    // 📦 ENQUEUE ASSETS + TRANSLATIONS
    // ========================================
    public static function enqueue_assets() {
        error_log('📈 [Stats] enqueue_assets() called');

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart.js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

        wp_enqueue_script('ppv-stats', PPV_PLUGIN_URL . 'assets/js/ppv-stats.js', ['jquery', 'chart.js'], time(), true);

        $store_id = self::get_handler_store_id();
        $lang = self::get_user_lang();

        // ✅ JAVÍTÁS 1: Initialize translations array
        $translations = [];
        if (class_exists('PPV_Lang') && !empty(PPV_Lang::$strings)) {
            $translations = PPV_Lang::$strings;
            error_log("🌐 [Stats] Translations loaded from PPV_Lang: " . count($translations) . " strings");
        } else {
            error_log("⚠️ [Stats] PPV_Lang not available, using fallback");
        }

        $data = [
            'ajax_url' => esc_url(rest_url('punktepass/v1/stats')),
            'export_url' => esc_url(rest_url('punktepass/v1/stats/export')),
            'trend_url' => esc_url(rest_url('punktepass/v1/stats/trend')),
            'spending_url' => esc_url(rest_url('punktepass/v1/stats/spending')),
            'conversion_url' => esc_url(rest_url('punktepass/v1/stats/conversion')),
            'export_adv_url' => esc_url(rest_url('punktepass/v1/stats/export-advanced')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_id' => intval($store_id ?? 0),
            'lang' => $lang,
            'translations' => $translations,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ];

        error_log("📊 [Stats] JS Data: store_id=" . $data['store_id'] . ", lang=" . $lang . ", translations=" . count($translations));
        wp_add_inline_script('ppv-stats', "window.ppvStats = " . wp_json_encode($data) . ";", 'before');
    }

    // ========================================
    // 📊 REST: BASIC STATS
    // ========================================
    public static function rest_stats($req) {
        global $wpdb;

        error_log("📊 [REST] stats() called");

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Main stats
        $daily = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id=%d AND DATE(created)=%s",
            $store_id, $today
        ));
        $weekly = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id=%d AND DATE(created) >= %s",
            $store_id, $week_start
        ));
        $monthly = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id=%d AND DATE(created) >= %s",
            $store_id, $month_start
        ));
        $all_time = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id=%d",
            $store_id
        ));
        $unique = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id=%d",
            $store_id
        ));

        // Chart (7-day)
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($today)));
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_points WHERE store_id=%d AND DATE(created)=%s",
                $store_id, $date
            ));
            $chart[] = ['date' => $date, 'count' => $count];
        }

        // Top 5 Users
        $top5 = $wpdb->get_results($wpdb->prepare("
            SELECT 
                p.user_id, 
                COUNT(*) as purchases, 
                SUM(p.points) as total_points,
                pu.email,
                pu.first_name,
                pu.last_name
            FROM $table_points p
            LEFT JOIN {$wpdb->prefix}ppv_users pu ON p.user_id = pu.id
            WHERE p.store_id=%d
            GROUP BY p.user_id
            ORDER BY total_points DESC
            LIMIT 5
        ", $store_id));

        $top5_formatted = [];
        foreach ($top5 as $user) {
            $name = (!empty($user->first_name) || !empty($user->last_name)) 
                ? trim($user->first_name . ' ' . $user->last_name)
                : 'User #' . $user->user_id;
            
            $top5_formatted[] = [
                'user_id' => intval($user->user_id),
                'name' => $name,
                'email' => $user->email ?: 'N/A',
                'purchases' => intval($user->purchases),
                'total_points' => intval($user->total_points)
            ];
        }

        // Rewards Stats
        $rewards_total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id=%d",
            $store_id
        ));
        $rewards_approved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id=%d AND status IN ('approved', 'bestätigt')",
            $store_id
        ));
        $rewards_pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id=%d AND status IN ('pending', 'offen')",
            $store_id
        ));
        $rewards_spent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND status IN ('approved', 'bestätigt')",
            $store_id
        )) ?? 0;

        // Peak Hours
        $peak_hours = $wpdb->get_results($wpdb->prepare("
            SELECT HOUR(created) as hour, COUNT(*) as count
            FROM $table_points
            WHERE store_id=%d AND DATE(created)=%s
            GROUP BY HOUR(created)
            ORDER BY count DESC
            LIMIT 3
        ", $store_id, $today));

        $peak_formatted = [];
        foreach ($peak_hours as $peak) {
            $peak_formatted[] = [
                'hour' => intval($peak->hour),
                'time' => str_pad($peak->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'count' => intval($peak->count)
            ];
        }

        error_log("✅ [REST] stats() complete");

        return new WP_REST_Response([
            'success' => true,
            'store_id' => $store_id,
            'daily' => $daily,
            'weekly' => $weekly,
            'monthly' => $monthly,
            'all_time' => $all_time,
            'unique' => $unique,
            'chart' => $chart,
            'top5_users' => $top5_formatted,
            'rewards' => [
                'total' => $rewards_total,
                'approved' => $rewards_approved,
                'pending' => $rewards_pending,
                'points_spent' => $rewards_spent
            ],
            'peak_hours' => $peak_formatted
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    // ========================================
    // 📥 REST: CSV EXPORT
    // ========================================
    public static function rest_export_csv($req) {
        global $wpdb;

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $range = sanitize_text_field($req->get_param('range') ?? 'all');
        $today = current_time('Y-m-d');
        $table = $wpdb->prefix . 'ppv_points';
        
        $where = $wpdb->prepare("WHERE store_id=%d", $store_id);

        if ($range === 'day') {
            $where .= $wpdb->prepare(" AND DATE(created)=%s", $today);
        } elseif ($range === 'week') {
            $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
            $where .= $wpdb->prepare(" AND DATE(created) >= %s", $week_start);
        } elseif ($range === 'month') {
            $month_start = date('Y-m-01', strtotime($today));
            $where .= $wpdb->prepare(" AND DATE(created) >= %s", $month_start);
        }

        $rows = $wpdb->get_results("SELECT user_id, points, created FROM $table $where ORDER BY created DESC");

        if (empty($rows)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No data'], 404);
        }

        $csv = "User ID,Points,Created\n";
        foreach ($rows as $row) {
            $csv .= "{$row->user_id},{$row->points},{$row->created}\n";
        }

        error_log("✅ [Export] Generated: " . count($rows) . " rows");

        return new WP_REST_Response([
            'success' => true,
            'csv' => $csv,
            'filename' => 'stats_' . $store_id . '_' . $today . '.csv'
        ], 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    // ========================================
    // 📈 REST: TREND
    // ========================================
    public static function rest_trend($req) {
        global $wpdb;

        error_log("📈 [Trend] Start");

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $table = $wpdb->prefix . 'ppv_points';
        $today = current_time('Y-m-d');

        // Week comparison
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($today)));
        $prev_week_start = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
        $prev_week_end = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));

        $current_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id=%d AND DATE(created) BETWEEN %s AND %s",
            $store_id, $week_start, $week_end
        ));
        $previous_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id=%d AND DATE(created) BETWEEN %s AND %s",
            $store_id, $prev_week_start, $prev_week_end
        ));
        $week_trend = $previous_week > 0 ? (($current_week - $previous_week) / $previous_week) * 100 : 0;

        // Month comparison
        $month_start = date('Y-m-01', strtotime($today));
        $month_end = date('Y-m-t', strtotime($today));
        $prev_month_start = date('Y-m-01', strtotime('-1 month', strtotime($today)));
        $prev_month_end = date('Y-m-t', strtotime('-1 month', strtotime($today)));

        $current_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id=%d AND DATE(created) BETWEEN %s AND %s",
            $store_id, $month_start, $month_end
        ));
        $previous_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id=%d AND DATE(created) BETWEEN %s AND %s",
            $store_id, $prev_month_start, $prev_month_end
        ));
        $month_trend = $previous_month > 0 ? (($current_month - $previous_month) / $previous_month) * 100 : 0;

        // Daily breakdown
        $daily_week = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($week_start . " +$i days"));
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table WHERE store_id=%d AND DATE(created)=%s",
                $store_id, $date
            ));
            $daily_week[] = [
                'date' => $date,
                'day' => date('D', strtotime($date)),
                'count' => $count
            ];
        }

        error_log("✅ [Trend] Complete");

        return new WP_REST_Response([
            'success' => true,
            'week' => [
                'current' => $current_week,
                'previous' => $previous_week,
                'trend' => round($week_trend, 1),
                'trend_up' => $week_trend >= 0
            ],
            'month' => [
                'current' => $current_month,
                'previous' => $previous_month,
                'trend' => round($month_trend, 1),
                'trend_up' => $month_trend >= 0
            ],
            'daily_breakdown' => $daily_week
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // 💰 REST: SPENDING
    // ========================================
    public static function rest_spending($req) {
        global $wpdb;

        error_log("💰 [Spending] Start");

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Spending by period
        $daily_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND DATE(redeemed_at)=%s AND status IN ('approved', 'bestätigt')",
            $store_id, $today
        )) ?? 0;

        $weekly_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND DATE(redeemed_at) >= %s AND status IN ('approved', 'bestätigt')",
            $store_id, $week_start
        )) ?? 0;

        $monthly_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND DATE(redeemed_at) >= %s AND status IN ('approved', 'bestätigt')",
            $store_id, $month_start
        )) ?? 0;

        // Average reward
        $avg_reward = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(points_spent) FROM $table_redeemed WHERE store_id=%d AND status IN ('approved', 'bestätigt')",
            $store_id
        )) ?? 0;

        // Top rewards
        $top_rewards = $wpdb->get_results($wpdb->prepare(
            "SELECT reward_id, SUM(points_spent) as total, COUNT(*) as count
             FROM $table_redeemed 
             WHERE store_id=%d AND status IN ('approved', 'bestätigt')
             GROUP BY reward_id
             ORDER BY total DESC
             LIMIT 5",
            $store_id
        ));

        $top_rewards_formatted = [];
        foreach ($top_rewards as $reward) {
            $top_rewards_formatted[] = [
                'reward_id' => intval($reward->reward_id),
                'total_spent' => intval($reward->total),
                'redeemed_count' => intval($reward->count)
            ];
        }

        // By status
        $pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND status IN ('pending', 'offen')",
            $store_id
        )) ?? 0;

        $rejected = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND status IN ('rejected', 'abgelehnt')",
            $store_id
        )) ?? 0;

        $approved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id=%d AND status IN ('approved', 'bestätigt')",
            $store_id
        )) ?? 0;

        error_log("✅ [Spending] Complete");

        return new WP_REST_Response([
            'success' => true,
            'spending' => [
                'daily' => $daily_spending,
                'weekly' => $weekly_spending,
                'monthly' => $monthly_spending
            ],
            'average_reward_value' => $avg_reward,
            'top_rewards' => $top_rewards_formatted,
            'by_status' => [
                'approved' => $approved,
                'pending' => $pending,
                'rejected' => $rejected
            ]
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // 📊 REST: CONVERSION
    // ========================================
    public static function rest_conversion($req) {
        global $wpdb;

        error_log("📊 [Conversion] Start");

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Users
        $total_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id=%d",
            $store_id
        ));

        $redeemed_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_redeemed WHERE store_id=%d",
            $store_id
        ));

        $conversion_rate = $total_users > 0 ? ($redeemed_users / $total_users) * 100 : 0;

        // Averages
        $avg_points_per_user = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(total) FROM (
                SELECT user_id, SUM(points) as total 
                FROM $table_points 
                WHERE store_id=%d 
                GROUP BY user_id
            ) t",
            $store_id
        )) ?? 0;

        $avg_redemptions_per_user = $redeemed_users > 0 
            ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) / %d FROM $table_redeemed WHERE store_id=%d",
                $redeemed_users, $store_id
            )) ?? 0
            : 0;

        // Repeat customers
        $repeat_customers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points 
             WHERE store_id=%d 
             GROUP BY user_id 
             HAVING COUNT(*) > 1",
            $store_id
        ));

        $repeat_rate = $total_users > 0 ? ($repeat_customers / $total_users) * 100 : 0;

        error_log("✅ [Conversion] Complete");

        return new WP_REST_Response([
            'success' => true,
            'total_users' => $total_users,
            'redeemed_users' => $redeemed_users,
            'conversion_rate' => round($conversion_rate, 1),
            'repeat_customers' => $repeat_customers,
            'repeat_rate' => round($repeat_rate, 1),
            'average_points_per_user' => $avg_points_per_user,
            'average_redemptions_per_user' => round($avg_redemptions_per_user, 1)
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // 📥 REST: ADVANCED EXPORT
    // ========================================
    public static function rest_export_advanced($req) {
        global $wpdb;

        error_log("📥 [Export Advanced] Start");

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $format = sanitize_text_field($req->get_param('format') ?? 'detailed');
        $table_points = $wpdb->prefix . 'ppv_points';

        if ($format === 'summary') {
            // Summary
            $csv = "Store ID,Date,Daily Points,Daily Redemptions,Unique Users\n";
            
            $today = current_time('Y-m-d');
            for ($i = 6; $i >= 0; $i--) {
                $date = date('Y-m-d', strtotime("-$i days", strtotime($today)));
                $points = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table_points WHERE store_id=%d AND DATE(created)=%s",
                    $store_id, $date
                ));
                $unique = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id=%d AND DATE(created)=%s",
                    $store_id, $date
                ));
                
                $csv .= "$store_id,$date,$points,0,$unique\n";
            }

            $filename = 'stats_summary_' . $store_id . '_' . date('Y-m-d') . '.csv';
        } else {
            // Detailed
            $csv = "User ID,Email,Name,Total Points,Purchases,Redemptions,Points Spent\n";
            
            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT 
                    p.user_id,
                    pu.email,
                    CONCAT(COALESCE(pu.first_name, ''), ' ', COALESCE(pu.last_name, '')) as name,
                    SUM(p.points) as total_points,
                    COUNT(DISTINCT p.id) as purchases,
                    COUNT(DISTINCT pr.id) as redemptions,
                    COALESCE(SUM(pr.points_spent), 0) as points_spent
                FROM {$wpdb->prefix}ppv_points p
                LEFT JOIN {$wpdb->prefix}ppv_users pu ON p.user_id = pu.id
                LEFT JOIN {$wpdb->prefix}ppv_rewards_redeemed pr ON p.user_id = pr.user_id AND pr.store_id = p.store_id
                WHERE p.store_id = %d
                GROUP BY p.user_id, pu.email
                ORDER BY total_points DESC
            ", $store_id));

            foreach ($rows as $row) {
                $name = trim($row->name);
                $email = $row->email ?? 'N/A';
                $csv .= "{$row->user_id},\"{$email}\",\"{$name}\",{$row->total_points},{$row->purchases},{$row->redemptions},{$row->points_spent}\n";
            }

            $filename = 'stats_detailed_' . $store_id . '_' . date('Y-m-d') . '.csv';
        }

        error_log("✅ [Export Advanced] Generated: $filename");

        return new WP_REST_Response([
            'success' => true,
            'csv' => $csv,
            'filename' => $filename
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // 🎨 RENDER DASHBOARD
    // ✅ JAVÍTÁS 2: Removed get_translations() call
    // ✅ JAVÍTÁS 3: Translations integration
    // ========================================
    public static function render_stats_dashboard() {
        $store_id = self::get_handler_store_id();

        if (!$store_id) {
            $msg = isset(PPV_Lang::$strings['not_authorized']) 
                ? PPV_Lang::$strings['not_authorized'] 
                : '⚠️ Not authorized';
            return '<div class="ppv-notice-error">' . esc_html($msg) . '</div>';
        }

        // ✅ JAVÍTÁS 3: Fordítások betöltése
        $T = PPV_Lang::$strings ?? [];

        ob_start(); ?>
        
        <div class="ppv-stats-wrapper">
            <h2 class="ppv-stats-title" style="font-size: 18px; margin-bottom: 16px;"><i class="ri-bar-chart-box-line"></i> <?php echo esc_html($T['statistics'] ?? 'Statistics'); ?></h2>

            <!-- BASIC STATS SECTION -->
            <div class="ppv-stats-loading" id="ppv-stats-loading" style="display:none;">
                <div class="ppv-spinner"></div>
                <p><?php echo esc_html($T['loading_data'] ?? 'Loading data...'); ?></p>
            </div>

            <div class="ppv-stats-error" id="ppv-stats-error" style="display:none;">
                <p>❌ <?php echo esc_html($T['error_loading_data'] ?? 'Error loading data'); ?></p>
            </div>

            <div class="ppv-stats-content">
                <div class="ppv-stats-controls">
                    <div class="ppv-stats-filters">
                        <select id="ppv-stats-range">
                            <option value="day"><?php echo esc_html($T['today'] ?? 'Today'); ?></option>
                            <option value="week" selected><?php echo esc_html($T['this_week'] ?? 'This Week'); ?></option>
                            <option value="month"><?php echo esc_html($T['this_month'] ?? 'This Month'); ?></option>
                            <option value="all"><?php echo esc_html($T['all_time'] ?? 'All Time'); ?></option>
                        </select>
                    </div>
                    <button id="ppv-export-csv" class="ppv-export-btn">
                        <i class="ri-download-line"></i> <?php echo esc_html($T['export_csv'] ?? 'Export CSV'); ?>
                    </button>
                </div>

                <div class="ppv-stats-cards">
                    <div class="ppv-stat-card">
                        <span class="ppv-stat-label"><?php echo esc_html($T['today'] ?? 'Today'); ?></span>
                        <span class="ppv-stat-value" id="ppv-stat-daily">0</span>
                    </div>
                    <div class="ppv-stat-card">
                        <span class="ppv-stat-label"><?php echo esc_html($T['weekly'] ?? 'Weekly'); ?></span>
                        <span class="ppv-stat-value" id="ppv-stat-weekly">0</span>
                    </div>
                    <div class="ppv-stat-card">
                        <span class="ppv-stat-label"><?php echo esc_html($T['monthly'] ?? 'Monthly'); ?></span>
                        <span class="ppv-stat-value" id="ppv-stat-monthly">0</span>
                    </div>
                    <div class="ppv-stat-card">
                        <span class="ppv-stat-label"><?php echo esc_html($T['total'] ?? 'Total'); ?></span>
                        <span class="ppv-stat-value" id="ppv-stat-all-time">0</span>
                    </div>
                    <div class="ppv-stat-card">
                        <span class="ppv-stat-label"><?php echo esc_html($T['unique'] ?? 'Unique'); ?></span>
                        <span class="ppv-stat-value" id="ppv-stat-unique">0</span>
                    </div>
                </div>

                <div class="ppv-stats-chart">
                    <canvas id="ppv-stats-canvas"></canvas>
                </div>

                <!-- TOP 5 USERS -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title"><i class="ri-trophy-line"></i> <?php echo esc_html($T['top_5_users'] ?? 'Top 5 Users'); ?></h3>
                    <div class="ppv-top5-list" id="ppv-top5-users">
                        <p class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></p>
                    </div>
                </div>

                <!-- REWARDS STATS -->
                <div class="ppv-rewards-stats">
                    <div class="ppv-reward-stat-card">
                        <span class="ppv-label"><?php echo esc_html($T['total_redeemed'] ?? 'Total Redeemed'); ?></span>
                        <span class="ppv-value" id="ppv-rewards-total">0</span>
                    </div>
                    <div class="ppv-reward-stat-card">
                        <span class="ppv-label"><?php echo esc_html($T['approved'] ?? 'Approved'); ?></span>
                        <span class="ppv-value ppv-approved" id="ppv-rewards-approved">0</span>
                    </div>
                    <div class="ppv-reward-stat-card">
                        <span class="ppv-label"><?php echo esc_html($T['pending'] ?? 'Pending'); ?></span>
                        <span class="ppv-value ppv-pending" id="ppv-rewards-pending">0</span>
                    </div>
                    <div class="ppv-reward-stat-card">
                        <span class="ppv-label"><?php echo esc_html($T['points_spent'] ?? 'Points Spent'); ?></span>
                        <span class="ppv-value" id="ppv-rewards-spent">0</span>
                    </div>
                </div>

                <!-- PEAK HOURS -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title"><i class="ri-time-line"></i> <?php echo esc_html($T['peak_hours_today'] ?? 'Peak Hours Today'); ?></h3>
                    <div class="ppv-peak-hours" id="ppv-peak-hours">
                        <p class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></p>
                    </div>
                </div>
            </div>

            <!-- ADVANCED STATS SECTION -->
            <hr style="margin: 2rem 0; opacity: 0.2;">

            <h2 class="ppv-stats-title" style="font-size: 18px; margin-bottom: 16px;"><i class="ri-line-chart-line"></i> <?php echo esc_html($T['advanced_statistics'] ?? 'Advanced Statistics'); ?></h2>

            <!-- TREND -->
            <div class="ppv-stats-section">
                <h3 class="ppv-section-title"><i class="ri-bar-chart-line"></i> <?php echo esc_html($T['trend'] ?? 'Trend'); ?></h3>
                <div id="ppv-trend" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
            </div>

            <!-- SPENDING -->
            <div class="ppv-stats-section">
                <h3 class="ppv-section-title"><i class="ri-money-euro-circle-line"></i> <?php echo esc_html($T['rewards_spending'] ?? 'Rewards Spending'); ?></h3>
                <div id="ppv-spending" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
            </div>

            <!-- CONVERSION -->
            <div class="ppv-stats-section">
                <h3 class="ppv-section-title"><i class="ri-percent-line"></i> <?php echo esc_html($T['conversion_rate'] ?? 'Conversion Rate'); ?></h3>
                <div id="ppv-conversion" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
            </div>

            <!-- ADVANCED EXPORT -->
            <div class="ppv-stats-section">
                <h3 class="ppv-section-title"><i class="ri-download-2-line"></i> <?php echo esc_html($T['advanced_export'] ?? 'Advanced Export'); ?></h3>
                <div class="ppv-export-advanced-controls">
                    <select id="ppv-export-format">
                        <option value="detailed"><?php echo esc_html($T['detailed_user_email'] ?? 'Detailed (User + Email)'); ?></option>
                        <option value="summary"><?php echo esc_html($T['summary_daily'] ?? 'Summary (Daily)'); ?></option>
                    </select>
                    <button id="ppv-export-advanced" class="ppv-export-btn">
                        <i class="ri-download-line"></i> <?php echo esc_html($T['download'] ?? 'Download'); ?>
                    </button>
                </div>
            </div>
        </div>

        <?php
        $content = ob_get_clean();
        $content .= do_shortcode('[ppv_bottom_nav]');
        return $content;
    }
}

PPV_Stats::hooks();