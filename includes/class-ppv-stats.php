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
        ppv_log("✅ [PPV_Stats] Hooks registered");
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
    // 🏢 HELPER: Get All Filialen for Handler
    // ========================================
    public static function get_handler_filialen() {
        global $wpdb;

        // Get the base store ID (not filiale-specific)
        $base_store_id = null;

        if (!empty($_SESSION['ppv_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $base_store_id = intval($_SESSION['ppv_vendor_store_id']);
        } elseif (!empty($GLOBALS['ppv_active_store_id'])) {
            $base_store_id = intval($GLOBALS['ppv_active_store_id']);
        }

        if (!$base_store_id) {
            // Try DB fallback
            $uid = get_current_user_id();
            if ($uid > 0) {
                $base_store_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $uid
                ));
            }
        }

        if (!$base_store_id) {
            return [];
        }

        // Get all stores: parent + children
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $base_store_id, $base_store_id, $base_store_id));

        return $filialen ?: [];
    }

    // ========================================
    // 🔍 HELPER: Get Store ID (with FILIALE support)
    // ========================================
    public static function get_handler_store_id() {
        ppv_log("🔍 [Stats] get_handler_store_id() START");

        // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $sid = intval($_SESSION['ppv_current_filiale_id']);
            ppv_log("✅ [Stats] Store from SESSION (FILIALE): {$sid}");
            return $sid;
        }

        // 1️⃣ GLOBALS
        if (!empty($GLOBALS['ppv_active_store_id'])) {
            $sid = intval($GLOBALS['ppv_active_store_id']);
            ppv_log("✅ [Stats] Store from GLOBALS: {$sid}");
            return $sid;
        }

        // 2️⃣ SESSION - Direct (base store)
        if (!empty($_SESSION['ppv_store_id'])) {
            $sid = intval($_SESSION['ppv_store_id']);
            ppv_log("✅ [Stats] Store from SESSION (store): {$sid}");
            return $sid;
        }

        // 3️⃣ SESSION - Vendor
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            $sid = intval($_SESSION['ppv_vendor_store_id']);
            ppv_log("✅ [Stats] Store from SESSION (vendor): {$sid}");
            return $sid;
        }

        // 4️⃣ SESSION - Active
        if (!empty($_SESSION['ppv_active_store'])) {
            $sid = intval($_SESSION['ppv_active_store']);
            ppv_log("✅ [Stats] Store from SESSION (active): {$sid}");
            return $sid;
        }

        // 5️⃣ DB FALLBACK
        global $wpdb;
        $uid = get_current_user_id();
        if ($uid > 0) {
            ppv_log("📊 [Stats] Checking DB for WP user: {$uid}");
            $sid = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($sid) {
                $sid = intval($sid);
                ppv_log("✅ [Stats] Store from DB: {$sid}");
                return $sid;
            }
        }

        ppv_log("❌ [Stats] NO STORE FOUND!");
        return null;
    }

    // ========================================
    // 🔐 PERMISSION CHECK
    // ========================================
    public static function check_handler_permission($request = null) {
        $store_id = self::get_handler_store_id();
        
        if (!$store_id) {
            ppv_log("🚫 [Stats Perm] DENIED - no store");
            return new WP_Error('unauthorized', 'Not authenticated', ['status' => 403]);
        }
        
        ppv_log("✅ [Stats Perm] OK - store={$store_id}");
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

        register_rest_route('punktepass/v1', '/stats/scanners', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_scanner_stats'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        register_rest_route('punktepass/v1', '/stats/suspicious', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_suspicious_scans'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        ppv_log("✅ [PPV_Stats] ALL REST routes OK");
    }

    // ========================================
    // 📦 ENQUEUE ASSETS + TRANSLATIONS
    // ========================================
    public static function enqueue_assets() {
        ppv_log('📈 [Stats] enqueue_assets() called');

        wp_enqueue_script('jquery');
        wp_enqueue_script('chart.js', 'https://cdn.jsdelivr.net/npm/chart.js', [], null, true);

        wp_enqueue_script('ppv-stats', PPV_PLUGIN_URL . 'assets/js/ppv-stats.js', ['jquery', 'chart.js'], time(), true);

        $store_id = self::get_handler_store_id();
        $lang = self::get_user_lang();

        // ✅ JAVÍTÁS 1: Initialize translations array
        $translations = [];
        if (class_exists('PPV_Lang') && !empty(PPV_Lang::$strings)) {
            $translations = PPV_Lang::$strings;
            ppv_log("🌐 [Stats] Translations loaded from PPV_Lang: " . count($translations) . " strings");
        } else {
            ppv_log("⚠️ [Stats] PPV_Lang not available, using fallback");
        }

        // Get filialen for dropdown
        $filialen = self::get_handler_filialen();

        $data = [
            'ajax_url' => esc_url(rest_url('punktepass/v1/stats')),
            'export_url' => esc_url(rest_url('punktepass/v1/stats/export')),
            'trend_url' => esc_url(rest_url('punktepass/v1/stats/trend')),
            'spending_url' => esc_url(rest_url('punktepass/v1/stats/spending')),
            'conversion_url' => esc_url(rest_url('punktepass/v1/stats/conversion')),
            'export_adv_url' => esc_url(rest_url('punktepass/v1/stats/export-advanced')),
            'scanner_url' => esc_url(rest_url('punktepass/v1/stats/scanners')),
            'suspicious_url' => esc_url(rest_url('punktepass/v1/stats/suspicious')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_id' => intval($store_id ?? 0),
            'filialen' => $filialen,
            'lang' => $lang,
            'translations' => $translations,
            'debug' => defined('WP_DEBUG') && WP_DEBUG
        ];

        ppv_log("📊 [Stats] JS Data: store_id=" . $data['store_id'] . ", lang=" . $lang . ", translations=" . count($translations));
        wp_add_inline_script('ppv-stats', "window.ppvStats = " . wp_json_encode($data) . ";", 'before');
    }

    // ========================================
    // 🏢 HELPER: Get Store IDs for Query (handles "all" filialen)
    // ========================================
    private static function get_store_ids_for_query($filiale_param = null) {
        $filialen = self::get_handler_filialen();

        // If "all" or no param, return all filiale IDs
        if ($filiale_param === 'all' || $filiale_param === null || $filiale_param === '') {
            return array_map(function($f) { return intval($f->id); }, $filialen);
        }

        // Single filiale selected
        $filiale_id = intval($filiale_param);

        // Verify this filiale belongs to the handler
        $valid_ids = array_map(function($f) { return intval($f->id); }, $filialen);
        if (in_array($filiale_id, $valid_ids)) {
            return [$filiale_id];
        }

        // Fallback to current store
        $store_id = self::get_handler_store_id();
        return $store_id ? [$store_id] : [];
    }

    // ========================================
    // 📊 REST: BASIC STATS
    // ========================================
    public static function rest_stats($req) {
        global $wpdb;

        ppv_log("📊 [REST] stats() called");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // Build IN clause for multiple stores
        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));

        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Main stats - with IN clause for multiple stores
        $daily = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created)=%s",
            array_merge($store_ids, [$today])
        ));
        $weekly = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created) >= %s",
            array_merge($store_ids, [$week_start])
        ));
        $monthly = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders) AND DATE(created) >= %s",
            array_merge($store_ids, [$month_start])
        ));
        $all_time = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_points WHERE store_id IN ($placeholders)",
            $store_ids
        ));
        $unique = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id IN ($placeholders)",
            $store_ids
        ));

        // Chart (7-day) - OPTIMIZED: Single query with GROUP BY instead of 7 queries
        $week_ago = date('Y-m-d', strtotime("-6 days", strtotime($today)));
        $chart_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM $table_points
             WHERE store_id IN ($placeholders) AND DATE(created) >= %s
             GROUP BY DATE(created)",
            array_merge($store_ids, [$week_ago])
        ));

        // Build lookup map from results
        $chart_map = [];
        foreach ($chart_results as $row) {
            $chart_map[$row->date] = (int) $row->count;
        }

        // Fill in all 7 days (including zero-count days)
        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($today)));
            $chart[] = ['date' => $date, 'count' => $chart_map[$date] ?? 0];
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
            WHERE p.store_id IN ($placeholders)
            GROUP BY p.user_id
            ORDER BY total_points DESC
            LIMIT 5
        ", $store_ids));

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
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id IN ($placeholders)",
            $store_ids
        ));
        $rewards_approved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')",
            $store_ids
        ));
        $rewards_pending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('pending', 'offen')",
            $store_ids
        ));
        $rewards_spent = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')",
            $store_ids
        )) ?? 0;

        // Peak Hours
        $peak_hours = $wpdb->get_results($wpdb->prepare("
            SELECT HOUR(created) as hour, COUNT(*) as count
            FROM $table_points
            WHERE store_id IN ($placeholders) AND DATE(created)=%s
            GROUP BY HOUR(created)
            ORDER BY count DESC
            LIMIT 3
        ", array_merge($store_ids, [$today])));

        $peak_formatted = [];
        foreach ($peak_hours as $peak) {
            $peak_formatted[] = [
                'hour' => intval($peak->hour),
                'time' => str_pad($peak->hour, 2, '0', STR_PAD_LEFT) . ':00',
                'count' => intval($peak->count)
            ];
        }

        ppv_log("✅ [REST] stats() complete");

        return new WP_REST_Response([
            'success' => true,
            'store_ids' => $store_ids,
            'filiale_mode' => $filiale_param === 'all' ? 'all' : 'single',
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

        ppv_log("✅ [Export] Generated: " . count($rows) . " rows");

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

        ppv_log("📈 [Trend] Start");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table = $wpdb->prefix . 'ppv_points';
        $today = current_time('Y-m-d');

        // Week comparison
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $week_end = date('Y-m-d', strtotime('sunday this week', strtotime($today)));
        $prev_week_start = date('Y-m-d', strtotime('-1 week', strtotime($week_start)));
        $prev_week_end = date('Y-m-d', strtotime('-1 day', strtotime($week_start)));

        $current_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s",
            array_merge($store_ids, [$week_start, $week_end])
        ));
        $previous_week = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s",
            array_merge($store_ids, [$prev_week_start, $prev_week_end])
        ));
        $week_trend = $previous_week > 0 ? (($current_week - $previous_week) / $previous_week) * 100 : 0;

        // Month comparison
        $month_start = date('Y-m-01', strtotime($today));
        $month_end = date('Y-m-t', strtotime($today));
        $prev_month_start = date('Y-m-01', strtotime('-1 month', strtotime($today)));
        $prev_month_end = date('Y-m-t', strtotime('-1 month', strtotime($today)));

        $current_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s",
            array_merge($store_ids, [$month_start, $month_end])
        ));
        $previous_month = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s",
            array_merge($store_ids, [$prev_month_start, $prev_month_end])
        ));
        $month_trend = $previous_month > 0 ? (($current_month - $previous_month) / $previous_month) * 100 : 0;

        // Daily breakdown - OPTIMIZED: Single query with GROUP BY instead of 7 queries
        $daily_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM $table
             WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s
             GROUP BY DATE(created)",
            array_merge($store_ids, [$week_start, $week_end])
        ));

        // Build lookup map from results
        $daily_map = [];
        foreach ($daily_results as $row) {
            $daily_map[$row->date] = (int) $row->count;
        }

        // Fill in all 7 days (including zero-count days)
        $daily_week = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($week_start . " +$i days"));
            $daily_week[] = [
                'date' => $date,
                'day' => date('D', strtotime($date)),
                'count' => $daily_map[$date] ?? 0
            ];
        }

        ppv_log("✅ [Trend] Complete");

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

        ppv_log("💰 [Spending] Start");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Spending by period
        $daily_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND DATE(redeemed_at)=%s AND status IN ('approved', 'bestätigt')",
            array_merge($store_ids, [$today])
        )) ?? 0;

        $weekly_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND DATE(redeemed_at) >= %s AND status IN ('approved', 'bestätigt')",
            array_merge($store_ids, [$week_start])
        )) ?? 0;

        $monthly_spending = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND DATE(redeemed_at) >= %s AND status IN ('approved', 'bestätigt')",
            array_merge($store_ids, [$month_start])
        )) ?? 0;

        // Average reward
        $avg_reward = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')",
            $store_ids
        )) ?? 0;

        // Top rewards
        $top_rewards = $wpdb->get_results($wpdb->prepare(
            "SELECT reward_id, SUM(points_spent) as total, COUNT(*) as count
             FROM $table_redeemed
             WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')
             GROUP BY reward_id
             ORDER BY total DESC
             LIMIT 5",
            $store_ids
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
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('pending', 'offen')",
            $store_ids
        )) ?? 0;

        $rejected = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('rejected', 'abgelehnt')",
            $store_ids
        )) ?? 0;

        $approved = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')",
            $store_ids
        )) ?? 0;

        ppv_log("✅ [Spending] Complete");

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

        ppv_log("📊 [Conversion] Start");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Users
        $total_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id IN ($placeholders)",
            $store_ids
        ));

        $redeemed_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_redeemed WHERE store_id IN ($placeholders)",
            $store_ids
        ));

        $conversion_rate = $total_users > 0 ? ($redeemed_users / $total_users) * 100 : 0;

        // Averages
        $avg_points_per_user = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(total) FROM (
                SELECT user_id, SUM(points) as total
                FROM $table_points
                WHERE store_id IN ($placeholders)
                GROUP BY user_id
            ) t",
            $store_ids
        )) ?? 0;

        $avg_redemptions_per_user = $redeemed_users > 0
            ? (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) / %d FROM $table_redeemed WHERE store_id IN ($placeholders)",
                array_merge([$redeemed_users], $store_ids)
            )) ?? 0
            : 0;

        // Repeat customers - need to count users with >1 visit across selected stores
        $repeat_customers = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM (
                SELECT user_id FROM $table_points
                WHERE store_id IN ($placeholders)
                GROUP BY user_id
                HAVING COUNT(*) > 1
            ) t",
            $store_ids
        )) ?? 0;

        $repeat_rate = $total_users > 0 ? ($repeat_customers / $total_users) * 100 : 0;

        ppv_log("✅ [Conversion] Complete");

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

        ppv_log("📥 [Export Advanced] Start");

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

        ppv_log("✅ [Export Advanced] Generated: $filename");

        return new WP_REST_Response([
            'success' => true,
            'csv' => $csv,
            'filename' => $filename
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // 👤 REST: SCANNER STATS (Employee Scan Counts)
    // ========================================
    public static function rest_scanner_stats($req) {
        global $wpdb;

        ppv_log("👤 [Scanner Stats] Start");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_log = $wpdb->prefix . 'ppv_pos_log';
        $table_users = $wpdb->prefix . 'ppv_users';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Get all scans with scanner info from metadata (JSON)
        // We extract scanner_id from the JSON metadata column
        $scanner_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(l.metadata, '$.scanner_id')) as scanner_id,
                JSON_UNQUOTE(JSON_EXTRACT(l.metadata, '$.scanner_name')) as scanner_name,
                COUNT(*) as total_scans,
                SUM(CASE WHEN DATE(l.created_at) = %s THEN 1 ELSE 0 END) as today_scans,
                SUM(CASE WHEN DATE(l.created_at) >= %s THEN 1 ELSE 0 END) as week_scans,
                SUM(CASE WHEN DATE(l.created_at) >= %s THEN 1 ELSE 0 END) as month_scans,
                MIN(l.created_at) as first_scan,
                MAX(l.created_at) as last_scan
            FROM {$table_log} l
            WHERE l.store_id IN ({$placeholders})
              AND l.type = 'qr_scan'
              AND JSON_EXTRACT(l.metadata, '$.scanner_id') IS NOT NULL
            GROUP BY scanner_id, scanner_name
            ORDER BY total_scans DESC
        ", array_merge([$today, $week_start, $month_start], $store_ids)));

        // Format results
        $scanners_formatted = [];
        foreach ($scanner_stats as $scanner) {
            if (empty($scanner->scanner_id) || $scanner->scanner_id === 'null') {
                continue; // Skip entries without scanner_id
            }

            $scanners_formatted[] = [
                'scanner_id' => intval($scanner->scanner_id),
                'scanner_name' => $scanner->scanner_name ?: 'Scanner #' . $scanner->scanner_id,
                'total_scans' => intval($scanner->total_scans),
                'today_scans' => intval($scanner->today_scans),
                'week_scans' => intval($scanner->week_scans),
                'month_scans' => intval($scanner->month_scans),
                'first_scan' => $scanner->first_scan,
                'last_scan' => $scanner->last_scan,
            ];
        }

        // Also get scans without scanner_id (legacy/untracked)
        $untracked_scans = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_scans,
                SUM(CASE WHEN DATE(created_at) = %s THEN 1 ELSE 0 END) as today_scans,
                SUM(CASE WHEN DATE(created_at) >= %s THEN 1 ELSE 0 END) as week_scans
            FROM {$table_log}
            WHERE store_id IN ({$placeholders})
              AND type = 'qr_scan'
              AND (JSON_EXTRACT(metadata, '$.scanner_id') IS NULL
                   OR JSON_EXTRACT(metadata, '$.scanner_id') = 'null')
        ", array_merge([$today, $week_start], $store_ids)));

        // Summary totals
        $total_tracked = array_sum(array_column($scanners_formatted, 'total_scans'));
        $total_untracked = intval($untracked_scans->total_scans ?? 0);

        ppv_log("✅ [Scanner Stats] Complete: " . count($scanners_formatted) . " scanners found");

        return new WP_REST_Response([
            'success' => true,
            'scanners' => $scanners_formatted,
            'summary' => [
                'total_tracked' => $total_tracked,
                'total_untracked' => $total_untracked,
                'scanner_count' => count($scanners_formatted),
            ],
            'untracked' => [
                'total_scans' => $total_untracked,
                'today_scans' => intval($untracked_scans->today_scans ?? 0),
                'week_scans' => intval($untracked_scans->week_scans ?? 0),
            ]
        ], 200, ['Cache-Control' => 'no-store']);
    }

    // ========================================
    // ⚠️ REST: SUSPICIOUS SCANS (for store owners)
    // ========================================
    public static function rest_suspicious_scans($req) {
        global $wpdb;

        ppv_log("⚠️ [Suspicious Scans] Start");

        // Get store IDs for this handler
        $store_ids = self::get_store_ids_for_query(null);

        ppv_log("⚠️ [Suspicious Scans] Store IDs: " . json_encode($store_ids));

        if (empty($store_ids)) {
            // Fallback: try to get from session
            $handler_store_id = self::get_handler_store_id();
            ppv_log("⚠️ [Suspicious Scans] Fallback handler_store_id: " . $handler_store_id);

            if ($handler_store_id) {
                $store_ids = [$handler_store_id];
                // Also include filialen
                $filialen_ids = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE parent_store_id = %d",
                    $handler_store_id
                ));
                $store_ids = array_merge($store_ids, $filialen_ids);
                ppv_log("⚠️ [Suspicious Scans] With filialen: " . json_encode($store_ids));
            }
        }

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store', 'debug' => 'store_ids empty'], 403);
        }

        $status_filter = sanitize_text_field($req->get_param('status') ?? 'new');

        $table_suspicious = $wpdb->prefix . 'ppv_suspicious_scans';
        $table_users = $wpdb->prefix . 'ppv_users';
        $table_stores = $wpdb->prefix . 'ppv_stores';

        // Get suspicious scans for this store
        // Build the query with proper escaping
        $store_ids_escaped = implode(',', array_map('intval', $store_ids));

        $query = "
            SELECT
                ss.id,
                ss.user_id,
                ss.store_id,
                ss.distance_km,
                ss.user_lat,
                ss.user_lng,
                ss.store_lat,
                ss.store_lng,
                ss.status,
                ss.created_at,
                u.first_name,
                u.last_name,
                u.email as user_email,
                s.company_name as store_name
            FROM {$table_suspicious} ss
            LEFT JOIN {$table_users} u ON ss.user_id = u.id
            LEFT JOIN {$table_stores} s ON ss.store_id = s.id
            WHERE ss.store_id IN ({$store_ids_escaped})
        ";

        if ($status_filter !== 'all') {
            $query .= $wpdb->prepare(" AND ss.status = %s", $status_filter);
        }

        $query .= " ORDER BY ss.created_at DESC LIMIT 100";

        ppv_log("⚠️ [Suspicious Scans] Query: " . $query);

        $scans = $wpdb->get_results($query);

        ppv_log("⚠️ [Suspicious Scans] Found " . count($scans) . " scans");

        // Count by status
        $counts = [
            'new' => 0,
            'reviewed' => 0,
            'dismissed' => 0,
            'all' => 0
        ];

        $count_query = "
            SELECT status, COUNT(*) as cnt
            FROM {$table_suspicious}
            WHERE store_id IN ({$store_ids_escaped})
            GROUP BY status
        ";
        $count_results = $wpdb->get_results($count_query);

        foreach ($count_results as $row) {
            if (isset($counts[$row->status])) {
                $counts[$row->status] = intval($row->cnt);
            }
            $counts['all'] += intval($row->cnt);
        }

        // Format results
        $formatted = [];
        foreach ($scans as $scan) {
            $user_name = trim(($scan->first_name ?? '') . ' ' . ($scan->last_name ?? ''));
            if (empty($user_name)) {
                $user_name = 'User #' . $scan->user_id;
            }

            $formatted[] = [
                'id' => intval($scan->id),
                'user_id' => intval($scan->user_id),
                'user_name' => $user_name,
                'user_email' => $scan->user_email ?? '',
                'store_name' => $scan->store_name ?? 'Store #' . $scan->store_id,
                'distance_km' => round(floatval($scan->distance_km), 2),
                'status' => $scan->status,
                'created_at' => $scan->created_at,
                'maps_link' => "https://www.google.com/maps?q={$scan->user_lat},{$scan->user_lng}"
            ];
        }

        ppv_log("✅ [Suspicious Scans] Found " . count($formatted) . " scans");

        return new WP_REST_Response([
            'success' => true,
            'scans' => $formatted,
            'counts' => $counts
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

        // Get filialen for dropdown
        $filialen = self::get_handler_filialen();
        $has_multiple_filialen = count($filialen) > 1;

        ob_start(); ?>

        <div class="ppv-stats-wrapper">

            <!-- TABS NAVIGATION -->
            <div class="ppv-stats-tabs">
                <button class="ppv-stats-tab active" data-tab="overview">
                    <i class="ri-bar-chart-box-line"></i> <?php echo esc_html($T['overview'] ?? 'Übersicht'); ?>
                </button>
                <button class="ppv-stats-tab" data-tab="advanced">
                    <i class="ri-line-chart-line"></i> <?php echo esc_html($T['advanced'] ?? 'Erweitert'); ?>
                </button>
                <button class="ppv-stats-tab" data-tab="scanners">
                    <i class="ri-team-line"></i> <?php echo esc_html($T['scanner_stats'] ?? 'Mitarbeiter'); ?>
                </button>
                <button class="ppv-stats-tab" data-tab="suspicious" id="ppv-tab-suspicious-btn">
                    <i class="ri-alarm-warning-line"></i> <?php echo esc_html($T['suspicious_scans'] ?? 'Verdächtige Scans'); ?>
                    <span class="ppv-badge-count" id="ppv-suspicious-badge" style="display:none;"></span>
                </button>
            </div>

            <!-- BASIC STATS SECTION -->
            <div class="ppv-stats-loading" id="ppv-stats-loading" style="display:none;">
                <div class="ppv-spinner"></div>
                <p><?php echo esc_html($T['loading_data'] ?? 'Loading data...'); ?></p>
            </div>

            <div class="ppv-stats-error" id="ppv-stats-error" style="display:none;">
                <p>❌ <?php echo esc_html($T['error_loading_data'] ?? 'Error loading data'); ?></p>
            </div>

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- TAB 1: OVERVIEW -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="ppv-stats-tab-content active" id="ppv-tab-overview">
                <div class="ppv-stats-controls">
                    <div class="ppv-stats-filters">
                        <?php if ($has_multiple_filialen): ?>
                        <select id="ppv-stats-filiale" class="ppv-filiale-select">
                            <option value="all"><?php echo esc_html($T['all_branches'] ?? 'Összes filiale'); ?></option>
                            <?php foreach ($filialen as $fil): ?>
                                <option value="<?php echo intval($fil->id); ?>">
                                    <?php echo esc_html($fil->company_name ?: $fil->name ?: 'Filiale #' . $fil->id); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php endif; ?>
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
                    <h3 class="ppv-section-title">🏆 <?php echo esc_html($T['top_5_users'] ?? 'Top 5 Users'); ?></h3>
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
                    <h3 class="ppv-section-title">⏰ <?php echo esc_html($T['peak_hours_today'] ?? 'Peak Hours Today'); ?></h3>
                    <div class="ppv-peak-hours" id="ppv-peak-hours">
                        <p class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></p>
                    </div>
                </div>
            </div>
            </div><!-- END TAB 1: OVERVIEW -->

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- TAB 2: ADVANCED -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="ppv-stats-tab-content" id="ppv-tab-advanced">
                <!-- TREND -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">📊 <?php echo esc_html($T['trend'] ?? 'Trend'); ?></h3>
                    <div id="ppv-trend" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
                </div>

                <!-- SPENDING -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">💰 <?php echo esc_html($T['rewards_spending'] ?? 'Rewards Spending'); ?></h3>
                    <div id="ppv-spending" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
                </div>

                <!-- CONVERSION -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">📊 <?php echo esc_html($T['conversion_rate'] ?? 'Conversion Rate'); ?></h3>
                    <div id="ppv-conversion" class="ppv-loading-small"><?php echo esc_html($T['loading'] ?? 'Loading...'); ?></div>
                </div>

                <!-- ADVANCED EXPORT -->
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">📥 <?php echo esc_html($T['advanced_export'] ?? 'Advanced Export'); ?></h3>
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
            </div><!-- END TAB 2: ADVANCED -->

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- TAB 3: SCANNER STATS (Employee Performance) -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="ppv-stats-tab-content" id="ppv-tab-scanners">
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">👤 <?php echo esc_html($T['employee_scans'] ?? 'Mitarbeiter Scans'); ?></h3>
                    <p class="ppv-section-desc"><?php echo esc_html($T['employee_scans_desc'] ?? 'Übersicht welcher Mitarbeiter wie viele Scans durchgeführt hat.'); ?></p>

                    <div id="ppv-scanner-stats-loading" class="ppv-loading-small" style="display:none;">
                        <?php echo esc_html($T['loading'] ?? 'Loading...'); ?>
                    </div>

                    <!-- Scanner Summary Cards -->
                    <div class="ppv-scanner-summary" id="ppv-scanner-summary">
                        <div class="ppv-stat-card">
                            <span class="ppv-stat-label"><?php echo esc_html($T['total_scanners'] ?? 'Scanner gesamt'); ?></span>
                            <span class="ppv-stat-value" id="ppv-scanner-count">0</span>
                        </div>
                        <div class="ppv-stat-card">
                            <span class="ppv-stat-label"><?php echo esc_html($T['tracked_scans'] ?? 'Erfasste Scans'); ?></span>
                            <span class="ppv-stat-value" id="ppv-tracked-scans">0</span>
                        </div>
                        <div class="ppv-stat-card">
                            <span class="ppv-stat-label"><?php echo esc_html($T['untracked_scans'] ?? 'Ohne Scanner'); ?></span>
                            <span class="ppv-stat-value" id="ppv-untracked-scans">0</span>
                        </div>
                    </div>

                    <!-- Scanner List -->
                    <div class="ppv-scanner-list" id="ppv-scanner-list">
                        <p class="ppv-no-data"><?php echo esc_html($T['no_scanner_data'] ?? 'Noch keine Scanner-Daten vorhanden. Sobald Mitarbeiter Scans durchführen, erscheinen hier die Statistiken.'); ?></p>
                    </div>
                </div>
            </div><!-- END TAB 3: SCANNER STATS -->

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- TAB 4: SUSPICIOUS SCANS -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="ppv-stats-tab-content" id="ppv-tab-suspicious">
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">⚠️ <?php echo esc_html($T['suspicious_scans'] ?? 'Verdächtige Scans'); ?></h3>
                    <p class="ppv-section-desc"><?php echo esc_html($T['suspicious_desc'] ?? 'Scans die aus verdächtiger Entfernung durchgeführt wurden.'); ?></p>

                    <!-- Status filter -->
                    <div class="ppv-suspicious-filters" style="margin-bottom: 15px;">
                        <select id="ppv-suspicious-status" class="ppv-select">
                            <option value="new"><?php echo esc_html($T['status_new'] ?? 'Neu'); ?></option>
                            <option value="reviewed"><?php echo esc_html($T['status_reviewed'] ?? 'Überprüft'); ?></option>
                            <option value="dismissed"><?php echo esc_html($T['status_dismissed'] ?? 'Abgewiesen'); ?></option>
                            <option value="all"><?php echo esc_html($T['status_all'] ?? 'Alle'); ?></option>
                        </select>
                    </div>

                    <div id="ppv-suspicious-loading" class="ppv-loading-small" style="display:none;">
                        <?php echo esc_html($T['loading'] ?? 'Loading...'); ?>
                    </div>

                    <!-- Suspicious scans list -->
                    <div class="ppv-suspicious-list" id="ppv-suspicious-list">
                        <p class="ppv-no-data"><?php echo esc_html($T['no_suspicious_scans'] ?? 'Keine verdächtigen Scans vorhanden.'); ?></p>
                    </div>
                </div>
            </div><!-- END TAB 4: SUSPICIOUS SCANS -->

        </div>

        <?php
        $content = ob_get_clean();
        $content .= do_shortcode('[ppv_bottom_nav]');
        return $content;
    }
}

PPV_Stats::hooks();