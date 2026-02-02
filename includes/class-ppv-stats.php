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
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
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

        return $lang ?: 'ro'; // Default Romanian
    }

    // ========================================
    // ⚡ CACHE HELPER (5 min transient)
    // ========================================
    private static function get_cached($cache_key, $callback, $ttl = 300) {
        // Try to get cached data
        $cached = get_transient($cache_key);
        if ($cached !== false) {
            ppv_log("⚡ [Stats Cache] HIT: {$cache_key}");
            return $cached;
        }

        // Cache miss - run callback to get fresh data
        ppv_log("🔄 [Stats Cache] MISS: {$cache_key} - fetching fresh data");
        $data = $callback();

        // Store in cache
        set_transient($cache_key, $data, $ttl);
        return $data;
    }

    private static function build_cache_key($endpoint, $store_ids) {
        sort($store_ids); // Normalize order
        $stores_hash = md5(implode('_', $store_ids));
        $date = current_time('Y-m-d');
        return "ppv_stats_{$endpoint}_{$stores_hash}_{$date}";
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

        // 🔗 CHECK IF HANDLER IS LINKED TO A MAIN HANDLER
        // If linked_to_store_id is set, show the MAIN handler's stores instead
        $linked_to = $wpdb->get_var($wpdb->prepare(
            "SELECT linked_to_store_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d AND linked_to_store_id IS NOT NULL",
            $base_store_id
        ));

        if ($linked_to) {
            // This handler is linked to a main handler - use main handler's store
            $effective_store_id = intval($linked_to);
            ppv_log("🔗 [Stats] Handler #{$base_store_id} linked to main handler #{$effective_store_id} - showing main's stores");
        } else {
            // Not linked, use own store
            $effective_store_id = $base_store_id;
        }

        // Get all stores: main store + children (filialen)
        $filialen = $wpdb->get_results($wpdb->prepare("
            SELECT id, name, company_name, address, city, plz
            FROM {$wpdb->prefix}ppv_stores
            WHERE id = %d OR parent_store_id = %d
            ORDER BY (id = %d) DESC, name ASC
        ", $effective_store_id, $effective_store_id, $effective_store_id));

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
        // ✅ Use centralized permission check (supports token-based auth for scanner users)
        if (class_exists('PPV_Permissions')) {
            $result = PPV_Permissions::check_handler();
            if (is_wp_error($result)) {
                ppv_log("🚫 [Stats Perm] DENIED by PPV_Permissions: " . $result->get_error_message());
                return $result;
            }
        }

        $store_id = self::get_handler_store_id();

        if (!$store_id) {
            ppv_log("🚫 [Stats Perm] DENIED - no store");
            return new WP_Error('unauthorized', 'Not authenticated', ['status' => 403]);
        }

        ppv_log("✅ [Stats Perm] OK - store={$store_id}");
        return true;
    }

    /**
     * Permission check with CSRF nonce validation
     * Use this for POST endpoints
     */
    public static function check_handler_permission_with_nonce($request = null) {
        // First check standard permission
        $perm_check = self::check_handler_permission($request);
        if (is_wp_error($perm_check)) {
            return $perm_check;
        }

        // 🔒 CSRF: Verify nonce
        if (class_exists('PPV_Permissions')) {
            return PPV_Permissions::verify_nonce($request);
        }

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

        // 🔒 CSRF protected
        register_rest_route('punktepass/v1', '/stats/request-review', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_request_review'],
            'permission_callback' => [__CLASS__, 'check_handler_permission_with_nonce']
        ]);

        // 📱 Device Activity Dashboard - utolsó 7 nap scan-jei eszközönként
        register_rest_route('punktepass/v1', '/stats/device-activity', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_device_activity'],
            'permission_callback' => [__CLASS__, 'check_handler_permission']
        ]);

        ppv_log("✅ [PPV_Stats] ALL REST routes OK");
    }

    // ========================================
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ========================================

    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/statistik') return;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        if (!class_exists('PPV_Permissions')) {
            header('Location: /login');
            exit;
        }

        $auth_check = PPV_Permissions::check_handler();
        if (is_wp_error($auth_check)) {
            header('Location: /login');
            exit;
        }

        self::render_standalone_page();
        exit;
    }

    private static function render_standalone_page() {
        $plugin_url = PPV_PLUGIN_URL;
        $version    = PPV_VERSION;
        $site_url   = get_site_url();

        // ─── Language ───
        if (class_exists('PPV_Lang')) {
            $lang    = PPV_Lang::$active ?: 'de';
            $strings = PPV_Lang::$strings ?: [];
        } else {
            $lang    = self::get_user_lang();
            $strings = [];
        }

        // ─── Theme ───
        $theme_cookie = $_COOKIE['ppv_theme'] ?? ($_COOKIE['ppv_handler_theme'] ?? 'light');
        $is_dark = ($theme_cookie === 'dark');

        // ─── Page content (render_stats_dashboard returns HTML) ───
        $page_html = self::render_stats_dashboard();

        // ─── Localized data for ppv-stats.js ───
        $store_id = self::get_handler_store_id();
        $filialen = self::get_handler_filialen();

        $stats_data = [
            'ajax_url'          => esc_url(rest_url('punktepass/v1/stats')),
            'export_url'        => esc_url(rest_url('punktepass/v1/stats/export')),
            'trend_url'         => esc_url(rest_url('punktepass/v1/stats/trend')),
            'spending_url'      => esc_url(rest_url('punktepass/v1/stats/spending')),
            'conversion_url'    => esc_url(rest_url('punktepass/v1/stats/conversion')),
            'export_adv_url'    => esc_url(rest_url('punktepass/v1/stats/export-advanced')),
            'scanner_url'       => esc_url(rest_url('punktepass/v1/stats/scanners')),
            'suspicious_url'    => esc_url(rest_url('punktepass/v1/stats/suspicious')),
            'device_activity_url' => esc_url(rest_url('punktepass/v1/stats/device-activity')),
            'nonce'             => wp_create_nonce('wp_rest'),
            'store_id'          => intval($store_id ?? 0),
            'filialen'          => $filialen,
            'lang'              => $lang,
            'translations'      => $strings,
            'debug'             => defined('WP_DEBUG') && WP_DEBUG,
        ];

        // ─── Global header ───
        $global_header = '';
        if (class_exists('PPV_User_Dashboard')) {
            ob_start();
            PPV_User_Dashboard::render_global_header();
            $global_header = ob_get_clean();
        }

        // ─── Bottom nav context ───
        $bottom_nav_context = '';
        if (class_exists('PPV_Bottom_Nav')) {
            ob_start();
            PPV_Bottom_Nav::inject_context();
            $bottom_nav_context = ob_get_clean();
        }

        // ─── Body classes ───
        $body_classes = ['ppv-standalone', 'ppv-app-mode', 'ppv-handler-mode'];
        $body_classes[] = $is_dark ? 'ppv-dark' : 'ppv-light';
        $body_class = implode(' ', $body_classes);

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr($lang); ?>" data-theme="<?php echo $is_dark ? 'dark' : 'light'; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Statistik - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/handler-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    window.ppvStats = <?php echo wp_json_encode($stats_data); ?>;
    window.ppv_lang = <?php echo wp_json_encode($strings); ?>;
    </script>
    <style>
    html,body{margin:0;padding:0;min-height:100vh;background:var(--pp-bg,#f5f5f7);overflow-y:auto!important;overflow-x:hidden!important;height:auto!important}
    .ppv-standalone-wrap{max-width:768px;margin:0 auto;padding:0 0 90px 0;min-height:100vh}
    .ppv-standalone-wrap{padding-top:env(safe-area-inset-top,0)}
    </style>
</head>
<body class="<?php echo esc_attr($body_class); ?>">
<?php echo $global_header; ?>
<div class="ppv-standalone-wrap">
<?php echo $page_html; ?>
</div>
<?php echo $bottom_nav_context; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-debug.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-global.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-stats.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
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
            'device_activity_url' => esc_url(rest_url('punktepass/v1/stats/device-activity')),
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
    // 📊 REST: BASIC STATS (cached 5 min)
    // ========================================
    public static function rest_stats($req) {
        ppv_log("📊 [REST] stats() called");

        // Get filiale parameter from request
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // ⚡ Use cache (5 min TTL)
        $cache_key = self::build_cache_key('stats', $store_ids);
        $data = self::get_cached($cache_key, function() use ($store_ids, $filiale_param) {
            return self::fetch_stats_data($store_ids, $filiale_param);
        });

        return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store, no-cache, must-revalidate']);
    }

    private static function fetch_stats_data($store_ids, $filiale_param) {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

        // Main stats
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

        // Chart (7-day)
        $week_ago = date('Y-m-d', strtotime("-6 days", strtotime($today)));
        $chart_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM $table_points
             WHERE store_id IN ($placeholders) AND DATE(created) >= %s
             GROUP BY DATE(created)",
            array_merge($store_ids, [$week_ago])
        ));

        $chart_map = [];
        foreach ($chart_results as $row) {
            $chart_map[$row->date] = (int) $row->count;
        }

        $chart = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-$i days", strtotime($today)));
            $chart[] = ['date' => $date, 'count' => $chart_map[$date] ?? 0];
        }

        // Top 5 Users
        // 🔧 FIX: Include display_name and username for complete name fallback
        $top5 = $wpdb->get_results($wpdb->prepare("
            SELECT
                p.user_id,
                COUNT(*) as purchases,
                SUM(p.points) as total_points,
                pu.email,
                pu.display_name,
                pu.username,
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
            // Priority: display_name > username > first_name+last_name > User #ID
            if (!empty($user->display_name)) {
                $name = $user->display_name;
            } elseif (!empty($user->username)) {
                $name = $user->username;
            } elseif (!empty($user->first_name) || !empty($user->last_name)) {
                $name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
            } else {
                $name = 'User #' . $user->user_id;
            }

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

        ppv_log("✅ [REST] stats() data fetched");

        return [
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
        ];
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
    // 📈 REST: TREND (cached 5 min)
    // ========================================
    public static function rest_trend($req) {
        ppv_log("📈 [Trend] Start");

        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // ⚡ Use cache (5 min TTL)
        $cache_key = self::build_cache_key('trend', $store_ids);
        $data = self::get_cached($cache_key, function() use ($store_ids) {
            return self::fetch_trend_data($store_ids);
        });

        return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store']);
    }

    private static function fetch_trend_data($store_ids) {
        global $wpdb;

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

        // Daily breakdown
        $daily_results = $wpdb->get_results($wpdb->prepare(
            "SELECT DATE(created) as date, COUNT(*) as count
             FROM $table
             WHERE store_id IN ($placeholders) AND DATE(created) BETWEEN %s AND %s
             GROUP BY DATE(created)",
            array_merge($store_ids, [$week_start, $week_end])
        ));

        $daily_map = [];
        foreach ($daily_results as $row) {
            $daily_map[$row->date] = (int) $row->count;
        }

        $daily_week = [];
        for ($i = 0; $i < 7; $i++) {
            $date = date('Y-m-d', strtotime($week_start . " +$i days"));
            $daily_week[] = [
                'date' => $date,
                'day' => date('D', strtotime($date)),
                'count' => $daily_map[$date] ?? 0
            ];
        }

        ppv_log("✅ [Trend] data fetched");

        return [
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
        ];
    }

    // ========================================
    // 💰 REST: SPENDING (cached 5 min)
    // ========================================
    public static function rest_spending($req) {
        ppv_log("💰 [Spending] Start");

        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // ⚡ Use cache (5 min TTL)
        $cache_key = self::build_cache_key('spending', $store_ids);
        $data = self::get_cached($cache_key, function() use ($store_ids) {
            return self::fetch_spending_data($store_ids);
        });

        return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store']);
    }

    private static function fetch_spending_data($store_ids) {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';
        $today = current_time('Y-m-d');
        $week_start = date('Y-m-d', strtotime('monday this week', strtotime($today)));
        $month_start = date('Y-m-01', strtotime($today));

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

        $avg_reward = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT AVG(points_spent) FROM $table_redeemed WHERE store_id IN ($placeholders) AND status IN ('approved', 'bestätigt')",
            $store_ids
        )) ?? 0;

        // 🔧 FIX: Join with ppv_rewards table to get reward title (not just ID)
        $table_rewards = $wpdb->prefix . 'ppv_rewards';
        $top_rewards = $wpdb->get_results($wpdb->prepare(
            "SELECT r.reward_id, rw.title as reward_title, SUM(r.points_spent) as total, COUNT(*) as count
             FROM $table_redeemed r
             LEFT JOIN $table_rewards rw ON r.reward_id = rw.id
             WHERE r.store_id IN ($placeholders) AND r.status IN ('approved', 'bestätigt')
             GROUP BY r.reward_id, rw.title
             ORDER BY total DESC
             LIMIT 5",
            $store_ids
        ));

        $top_rewards_formatted = [];
        foreach ($top_rewards as $reward) {
            $top_rewards_formatted[] = [
                'reward_id' => intval($reward->reward_id),
                'reward_title' => $reward->reward_title ?: ('Reward #' . $reward->reward_id),
                'total_spent' => intval($reward->total),
                'redeemed_count' => intval($reward->count)
            ];
        }

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

        ppv_log("✅ [Spending] data fetched");

        return [
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
        ];
    }

    // ========================================
    // 📊 REST: CONVERSION (cached 5 min)
    // ========================================
    public static function rest_conversion($req) {
        ppv_log("📊 [Conversion] Start");

        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // ⚡ Use cache (5 min TTL)
        $cache_key = self::build_cache_key('conversion', $store_ids);
        $data = self::get_cached($cache_key, function() use ($store_ids) {
            return self::fetch_conversion_data($store_ids);
        });

        return new WP_REST_Response($data, 200, ['Cache-Control' => 'no-store']);
    }

    private static function fetch_conversion_data($store_ids) {
        global $wpdb;

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_points = $wpdb->prefix . 'ppv_points';
        $table_redeemed = $wpdb->prefix . 'ppv_rewards_redeemed';

        $total_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_points WHERE store_id IN ($placeholders)",
            $store_ids
        ));

        $redeemed_users = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM $table_redeemed WHERE store_id IN ($placeholders)",
            $store_ids
        ));

        $conversion_rate = $total_users > 0 ? ($redeemed_users / $total_users) * 100 : 0;

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

        ppv_log("✅ [Conversion] data fetched");

        return [
            'success' => true,
            'total_users' => $total_users,
            'redeemed_users' => $redeemed_users,
            'conversion_rate' => round($conversion_rate, 1),
            'repeat_customers' => $repeat_customers,
            'repeat_rate' => round($repeat_rate, 1),
            'average_points_per_user' => $avg_points_per_user,
            'average_redemptions_per_user' => round($avg_redemptions_per_user, 1)
        ];
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

        // 🌐 Get handler's language for CSV headers
        $lang = self::get_user_lang();
        $headers = self::get_export_headers($lang);

        if ($format === 'summary') {
            // Summary - use translated headers
            $csv = implode(',', [
                $headers['store_id'],
                $headers['date'],
                $headers['daily_points'],
                $headers['daily_redemptions'],
                $headers['unique_users']
            ]) . "\n";

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
            // Detailed - use translated headers
            $csv = implode(',', [
                $headers['user_id'],
                $headers['email'],
                $headers['name'],
                $headers['total_points'],
                $headers['purchases'],
                $headers['redemptions'],
                $headers['points_spent']
            ]) . "\n";

            $rows = $wpdb->get_results($wpdb->prepare("
                SELECT
                    p.user_id,
                    pu.email,
                    COALESCE(pu.display_name, CONCAT(COALESCE(pu.first_name, ''), ' ', COALESCE(pu.last_name, ''))) as name,
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

        ppv_log("✅ [Export Advanced] Generated: $filename (lang: $lang)");

        return new WP_REST_Response([
            'success' => true,
            'csv' => $csv,
            'filename' => $filename
        ], 200, ['Cache-Control' => 'no-store']);
    }

    /**
     * 🌐 Get translated CSV export headers based on language
     */
    private static function get_export_headers($lang) {
        $headers = [
            'de' => [
                'store_id' => 'Filiale ID',
                'date' => 'Datum',
                'daily_points' => 'Tägliche Punkte',
                'daily_redemptions' => 'Tägliche Einlösungen',
                'unique_users' => 'Einzigartige Nutzer',
                'user_id' => 'Benutzer ID',
                'email' => 'E-Mail',
                'name' => 'Name',
                'total_points' => 'Gesamtpunkte',
                'purchases' => 'Käufe',
                'redemptions' => 'Einlösungen',
                'points_spent' => 'Punkte ausgegeben'
            ],
            'hu' => [
                'store_id' => 'Üzlet ID',
                'date' => 'Dátum',
                'daily_points' => 'Napi pontok',
                'daily_redemptions' => 'Napi beváltások',
                'unique_users' => 'Egyedi felhasználók',
                'user_id' => 'Felhasználó ID',
                'email' => 'E-mail',
                'name' => 'Név',
                'total_points' => 'Összes pont',
                'purchases' => 'Vásárlások',
                'redemptions' => 'Beváltások',
                'points_spent' => 'Elköltött pontok'
            ],
            'ro' => [
                'store_id' => 'ID Magazin',
                'date' => 'Data',
                'daily_points' => 'Puncte zilnice',
                'daily_redemptions' => 'Răscumpărări zilnice',
                'unique_users' => 'Utilizatori unici',
                'user_id' => 'ID Utilizator',
                'email' => 'E-mail',
                'name' => 'Nume',
                'total_points' => 'Total puncte',
                'purchases' => 'Achiziții',
                'redemptions' => 'Răscumpărări',
                'points_spent' => 'Puncte cheltuite'
            ]
        ];

        return $headers[$lang] ?? $headers['de'];
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
        // 🔧 FIX: Group ONLY by scanner_id (not scanner_name) to prevent duplicate counting
        // 🔧 FIX: Properly exclude NULL and 'null' string values to prevent overlap with untracked
        $scanner_stats = $wpdb->get_results($wpdb->prepare("
            SELECT
                JSON_UNQUOTE(JSON_EXTRACT(l.metadata, '$.scanner_id')) as scanner_id,
                COUNT(*) as total_scans,
                SUM(CASE WHEN DATE(l.created_at) = %s THEN 1 ELSE 0 END) as today_scans,
                SUM(CASE WHEN DATE(l.created_at) >= %s THEN 1 ELSE 0 END) as week_scans,
                SUM(CASE WHEN DATE(l.created_at) >= %s THEN 1 ELSE 0 END) as month_scans,
                COALESCE(SUM(l.points_change), 0) as total_points,
                COALESCE(SUM(CASE WHEN DATE(l.created_at) = %s THEN l.points_change ELSE 0 END), 0) as today_points,
                COALESCE(SUM(CASE WHEN DATE(l.created_at) >= %s THEN l.points_change ELSE 0 END), 0) as week_points,
                COALESCE(SUM(CASE WHEN DATE(l.created_at) >= %s THEN l.points_change ELSE 0 END), 0) as month_points,
                MIN(l.created_at) as first_scan,
                MAX(l.created_at) as last_scan
            FROM {$table_log} l
            WHERE l.store_id IN ({$placeholders})
              AND l.type = 'qr_scan'
              AND JSON_EXTRACT(l.metadata, '$.scanner_id') IS NOT NULL
              AND JSON_UNQUOTE(JSON_EXTRACT(l.metadata, '$.scanner_id')) NOT IN ('null', '')
            GROUP BY scanner_id
            ORDER BY total_scans DESC
        ", array_merge([$today, $week_start, $month_start, $today, $week_start, $month_start], $store_ids)));

        // Format results
        $scanners_formatted = [];
        foreach ($scanner_stats as $scanner) {
            // Note: SQL already filters out null/empty scanner_ids, but double-check just in case
            if (empty($scanner->scanner_id)) {
                continue;
            }

            $scanner_id = intval($scanner->scanner_id);

            // 🔧 FIX: ALWAYS fetch scanner_name from ppv_users table (not from log metadata)
            // The log metadata might have old/incorrect names (like "Scanner" instead of "Adrian")
            // Scanner users are created with 'username' field, NOT display_name!
            $scanner_name = null;
            $user_data = $wpdb->get_row($wpdb->prepare(
                "SELECT display_name, username, email, first_name, last_name
                 FROM {$table_users} WHERE id = %d LIMIT 1",
                $scanner_id
            ));

            if ($user_data) {
                // Priority: display_name > username > first_name + last_name > email
                if (!empty($user_data->display_name)) {
                    $scanner_name = $user_data->display_name;
                } elseif (!empty($user_data->username)) {
                    $scanner_name = $user_data->username;
                } elseif (!empty($user_data->first_name) || !empty($user_data->last_name)) {
                    $scanner_name = trim(($user_data->first_name ?? '') . ' ' . ($user_data->last_name ?? ''));
                } elseif (!empty($user_data->email)) {
                    $scanner_name = $user_data->email;
                }
            }

            // Final fallback
            if (empty($scanner_name)) {
                $scanner_name = 'Scanner #' . $scanner_id;
            }

            $scanners_formatted[] = [
                'scanner_id' => $scanner_id,
                'scanner_name' => $scanner_name,
                'total_scans' => intval($scanner->total_scans),
                'today_scans' => intval($scanner->today_scans),
                'week_scans' => intval($scanner->week_scans),
                'month_scans' => intval($scanner->month_scans),
                'total_points' => intval($scanner->total_points ?? 0),
                'today_points' => intval($scanner->today_points ?? 0),
                'week_points' => intval($scanner->week_points ?? 0),
                'month_points' => intval($scanner->month_points ?? 0),
                'first_scan' => $scanner->first_scan,
                'last_scan' => $scanner->last_scan,
            ];
        }

        // Also get scans without scanner_id (legacy/untracked)
        // 🔧 FIX: Use JSON_UNQUOTE for proper comparison, matching the tracked query logic
        $untracked_scans = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_scans,
                SUM(CASE WHEN DATE(created_at) = %s THEN 1 ELSE 0 END) as today_scans,
                SUM(CASE WHEN DATE(created_at) >= %s THEN 1 ELSE 0 END) as week_scans
            FROM {$table_log}
            WHERE store_id IN ({$placeholders})
              AND type = 'qr_scan'
              AND (JSON_EXTRACT(metadata, '$.scanner_id') IS NULL
                   OR JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.scanner_id')) IN ('null', ''))
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
                ss.distance_meters,
                ss.scan_latitude,
                ss.scan_longitude,
                ss.store_latitude,
                ss.store_longitude,
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
                'distance_km' => round(floatval($scan->distance_meters) / 1000, 2),
                'status' => $scan->status,
                'created_at' => $scan->created_at,
                'maps_link' => "https://www.google.com/maps?q={$scan->scan_latitude},{$scan->scan_longitude}"
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
    // 📧 REST: REQUEST ADMIN REVIEW (sends email)
    // ========================================
    public static function rest_request_review($req) {
        global $wpdb;

        ppv_log("📧 [Request Review] Start");

        $body = json_decode($req->get_body(), true);
        $scan_id = intval($body['scan_id'] ?? 0);

        if (!$scan_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'Missing scan_id'], 400);
        }

        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        // Get scan details
        $table_suspicious = $wpdb->prefix . 'ppv_suspicious_scans';
        $table_users = $wpdb->prefix . 'ppv_users';
        $table_stores = $wpdb->prefix . 'ppv_stores';

        $scan = $wpdb->get_row($wpdb->prepare("
            SELECT
                ss.*,
                u.first_name, u.last_name, u.email as user_email,
                s.company_name as store_name
            FROM {$table_suspicious} ss
            LEFT JOIN {$table_users} u ON ss.user_id = u.id
            LEFT JOIN {$table_stores} s ON ss.store_id = s.id
            WHERE ss.id = %d
        ", $scan_id));

        if (!$scan) {
            return new WP_REST_Response(['success' => false, 'error' => 'Scan not found'], 404);
        }

        // Get requesting store info
        $requesting_store = $wpdb->get_row($wpdb->prepare(
            "SELECT company_name, email FROM {$table_stores} WHERE id = %d",
            $store_id
        ));

        // Build email
        $user_name = trim(($scan->first_name ?? '') . ' ' . ($scan->last_name ?? '')) ?: 'User #' . $scan->user_id;
        $distance_km = round($scan->distance_meters / 1000, 2);
        $maps_link = "https://www.google.com/maps?q={$scan->scan_latitude},{$scan->scan_longitude}";
        $admin_link = admin_url("admin.php?page=ppv-suspicious&scan_id={$scan_id}");

        $subject = "🚨 PunktePass: Admin Überprüfung angefordert - {$user_name}";

        $message = "
<h2>🚨 Admin Überprüfung angefordert</h2>

<p><strong>Angefordert von:</strong> {$requesting_store->company_name}</p>

<h3>Verdächtiger Scan Details:</h3>
<table style='border-collapse: collapse; width: 100%;'>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Benutzer:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$user_name}</td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Email:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$scan->user_email}</td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Shop:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$scan->store_name}</td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Entfernung:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'><span style='color: red; font-weight: bold;'>{$distance_km} km</span></td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Status:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$scan->status}</td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Datum:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$scan->created_at}</td></tr>
    <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>IP Adresse:</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$scan->ip_address}</td></tr>
</table>

<p style='margin-top: 20px;'>
    <a href='{$maps_link}' style='background: #0073aa; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px; margin-right: 10px;'>📍 Auf Karte anzeigen</a>
    <a href='{$admin_link}' style='background: #d63638; color: white; padding: 10px 20px; text-decoration: none; border-radius: 4px;'>🔧 Im Admin prüfen</a>
</p>
";

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: PunktePass <noreply@punktepass.de>'
        ];

        // Send email to admin
        $sent = wp_mail('info@punktepass.de', $subject, $message, $headers);

        if ($sent) {
            // Update scan status to indicate review was requested
            $wpdb->update(
                $table_suspicious,
                ['admin_notes' => "Review angefordert von Store {$store_id} am " . current_time('mysql')],
                ['id' => $scan_id]
            );

            ppv_log("✅ [Request Review] Email sent for scan {$scan_id}");
            return new WP_REST_Response(['success' => true, 'message' => 'Email sent'], 200);
        } else {
            ppv_log("❌ [Request Review] Email failed for scan {$scan_id}");
            return new WP_REST_Response(['success' => false, 'error' => 'Email failed'], 500);
        }
    }

    // ========================================
    // 📱 REST: DEVICE ACTIVITY DASHBOARD
    // Shows ALL registered devices with scan activity over last 7 days
    // ========================================
    public static function rest_device_activity($req) {
        global $wpdb;

        ppv_log("📱 [Device Activity] Start");

        // Get store IDs
        $filiale_param = $req->get_param('filiale_id');
        $store_ids = self::get_store_ids_for_query($filiale_param);

        if (empty($store_ids)) {
            return new WP_REST_Response(['success' => false, 'error' => 'No store'], 403);
        }

        $placeholders = implode(',', array_fill(0, count($store_ids), '%d'));
        $table_points = $wpdb->prefix . 'ppv_points';
        $table_devices = $wpdb->prefix . 'ppv_user_devices';

        // Date range: last 7 days
        $dates = [];
        for ($i = 6; $i >= 0; $i--) {
            $dates[] = date('Y-m-d', strtotime("-{$i} days"));
        }
        $date_start = $dates[0];
        $date_end = $dates[6];

        // 1️⃣ Get ALL registered devices from ppv_user_devices
        // Note: fingerprint_hash is the correct column name (not device_fingerprint)
        // Note: browser/os info is in device_info JSON (not separate columns)
        $device_details = $wpdb->get_results($wpdb->prepare("
            SELECT
                d.id as device_id,
                d.fingerprint_hash,
                d.device_name,
                d.device_info,
                d.user_agent,
                d.mobile_scanner,
                d.last_used_at,
                d.registered_at,
                d.status
            FROM {$table_devices} d
            WHERE d.store_id IN ({$placeholders})
              AND d.status = 'active'
            ORDER BY d.last_used_at DESC
        ", $store_ids));

        ppv_log("📱 [Device Activity] Found " . count($device_details) . " registered devices");

        // 2️⃣ Get scan activity from ppv_points table (using device_fingerprint)
        // NOTE: ppv_points stores RAW fingerprint, ppv_user_devices stores SHA256 HASH
        // So we need to hash the fingerprint in SQL using SHA2() function
        $device_scans = $wpdb->get_results($wpdb->prepare("
            SELECT
                SHA2(p.device_fingerprint, 256) as fingerprint_hash,
                DATE(p.created) as scan_date,
                COUNT(*) as scan_count
            FROM {$table_points} p
            WHERE p.store_id IN ({$placeholders})
              AND p.type = 'qr_scan'
              AND DATE(p.created) >= %s
              AND DATE(p.created) <= %s
              AND p.device_fingerprint IS NOT NULL
              AND p.device_fingerprint != ''
            GROUP BY fingerprint_hash, scan_date
            ORDER BY fingerprint_hash, scan_date
        ", array_merge($store_ids, [$date_start, $date_end])));

        ppv_log("📱 [Device Activity] Found " . count($device_scans) . " scan records with device fingerprint");

        // Create scan lookup by fingerprint_hash (now hashed to match ppv_user_devices)
        $scan_lookup = [];
        foreach ($device_scans as $scan) {
            $fp = $scan->fingerprint_hash;
            if (empty($fp)) continue;

            if (!isset($scan_lookup[$fp])) {
                $scan_lookup[$fp] = [];
            }
            $scan_lookup[$fp][$scan->scan_date] = intval($scan->scan_count);
        }

        // 3️⃣ Build device data - show ALL registered devices
        $devices_data = [];
        foreach ($device_details as $d) {
            $fp = $d->fingerprint_hash;
            $device_id = $d->device_id;

            // Parse device_info JSON for browser/OS
            $device_info = json_decode($d->device_info ?? '{}', true) ?: [];
            $browser = $device_info['browserName'] ?? null;
            $os = $device_info['os'] ?? ($device_info['osName'] ?? null);

            // If no device_info, try to parse user_agent
            if (!$browser && !empty($d->user_agent)) {
                if (stripos($d->user_agent, 'Chrome') !== false) $browser = 'Chrome';
                elseif (stripos($d->user_agent, 'Firefox') !== false) $browser = 'Firefox';
                elseif (stripos($d->user_agent, 'Safari') !== false) $browser = 'Safari';
                elseif (stripos($d->user_agent, 'Edge') !== false) $browser = 'Edge';
            }
            if (!$os && !empty($d->user_agent)) {
                if (stripos($d->user_agent, 'Android') !== false) $os = 'Android';
                elseif (stripos($d->user_agent, 'iPhone') !== false || stripos($d->user_agent, 'iPad') !== false) $os = 'iOS';
                elseif (stripos($d->user_agent, 'Windows') !== false) $os = 'Windows';
                elseif (stripos($d->user_agent, 'Mac') !== false) $os = 'macOS';
                elseif (stripos($d->user_agent, 'Linux') !== false) $os = 'Linux';
            }

            // Get daily scans for this device (using fingerprint_hash to match)
            $daily_scans = array_fill_keys($dates, 0);
            $total_scans = 0;

            // Match by fingerprint_hash
            if (isset($scan_lookup[$fp])) {
                foreach ($scan_lookup[$fp] as $date => $count) {
                    if (isset($daily_scans[$date])) {
                        $daily_scans[$date] = $count;
                        $total_scans += $count;
                    }
                }
            }

            $devices_data[$fp] = [
                'fingerprint' => substr($fp, 0, 8) . '...',
                'full_fingerprint' => $fp,
                'device_id' => $device_id,
                'name' => $d->device_name ?: null,
                'browser' => $browser,
                'os' => $os,
                'is_mobile_scanner' => (bool)$d->mobile_scanner,
                'daily_scans' => $daily_scans,
                'total_scans' => $total_scans,
                'last_activity' => $d->last_used_at,
                'registered_at' => $d->registered_at,
            ];
        }

        // 4️⃣ Calculate suspicious indicators
        $formatted = [];
        $avg_total = count($devices_data) > 0 ? array_sum(array_column($devices_data, 'total_scans')) / count($devices_data) : 0;

        foreach ($devices_data as $fp => $device) {
            // Suspicious indicators
            $suspicious_reasons = [];

            // 1. High volume: More than 3x average (only if > 10 scans)
            if ($avg_total > 0 && $device['total_scans'] > 10 && $device['total_scans'] > $avg_total * 3) {
                $suspicious_reasons[] = 'high_volume';
            }

            // 2. Unusual activity spike: Any day has more than 50 scans
            foreach ($device['daily_scans'] as $count) {
                if ($count > 50) {
                    $suspicious_reasons[] = 'spike';
                    break;
                }
            }

            // 3. Burst: No activity for 5+ days but suddenly active with many scans
            $active_days = array_filter($device['daily_scans'], fn($c) => $c > 0);
            if (count($active_days) == 1 && $device['total_scans'] > 20) {
                $suspicious_reasons[] = 'burst';
            }

            $formatted[] = [
                'fingerprint' => $device['fingerprint'],
                'full_fingerprint' => $device['full_fingerprint'],
                'device_id' => $device['device_id'],
                'name' => $device['name'] ?: 'Unbekanntes Gerät',
                'browser' => $device['browser'],
                'os' => $device['os'],
                'is_mobile_scanner' => $device['is_mobile_scanner'],
                'daily_scans' => array_values($device['daily_scans']),
                'total_scans' => $device['total_scans'],
                'last_activity' => $device['last_activity'],
                'registered_at' => $device['registered_at'],
                'is_suspicious' => !empty($suspicious_reasons),
                'suspicious_reasons' => $suspicious_reasons,
            ];
        }

        // Sort: mobile scanners first, then by total scans descending
        usort($formatted, function($a, $b) {
            // Mobile scanners first
            if ($a['is_mobile_scanner'] !== $b['is_mobile_scanner']) {
                return $b['is_mobile_scanner'] - $a['is_mobile_scanner'];
            }
            // Then by total scans
            return $b['total_scans'] - $a['total_scans'];
        });

        // Summary
        $total_devices = count($formatted);
        $suspicious_count = count(array_filter($formatted, fn($d) => $d['is_suspicious']));
        $mobile_scanner_count = count(array_filter($formatted, fn($d) => $d['is_mobile_scanner']));

        ppv_log("✅ [Device Activity] Complete: {$total_devices} devices, {$suspicious_count} suspicious, {$mobile_scanner_count} mobile");

        return new WP_REST_Response([
            'success' => true,
            'devices' => $formatted,
            'dates' => $dates,
            'summary' => [
                'total_devices' => $total_devices,
                'suspicious_count' => $suspicious_count,
                'mobile_scanner_count' => $mobile_scanner_count,
                'avg_scans_per_device' => round($avg_total, 1),
            ]
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

        <!-- Mobile Stats CSS Fix + iOS Scroll Fix -->
        <style>
            /* iOS scroll fix - apply globally */
            .ppv-stats-wrapper {
                position: relative;
                overflow: visible !important;
                -webkit-overflow-scrolling: touch;
            }
            .ppv-stats-tab-content {
                overflow: visible !important;
                -webkit-overflow-scrolling: touch;
            }

            @media (max-width: 600px) {
                .ppv-stats-tabs {
                    flex-wrap: nowrap;
                    overflow-x: auto;
                    -webkit-overflow-scrolling: touch;
                    scrollbar-width: none;
                    -ms-overflow-style: none;
                    padding-bottom: 8px;
                }
                .ppv-stats-tabs::-webkit-scrollbar {
                    display: none;
                }
                .ppv-stats-tab {
                    flex: 0 0 auto;
                    min-width: auto;
                    padding: 10px 14px;
                    font-size: 12px;
                    white-space: nowrap;
                }
                .ppv-stats-tab i {
                    font-size: 14px;
                }
            }
        </style>

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
                <button class="ppv-stats-tab" data-tab="device-activity" id="ppv-tab-device-activity-btn">
                    <i class="ri-device-line"></i> <?php echo esc_html($T['device_activity'] ?? 'Geräte'); ?>
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
                                    <?php echo esc_html($fil->name ?: $fil->company_name ?: 'Filiale #' . $fil->id); ?>
                                    <?php if ($fil->city): ?> – <?php echo esc_html($fil->city); ?><?php endif; ?>
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

            <!-- ═══════════════════════════════════════════════════════════ -->
            <!-- TAB 5: DEVICE ACTIVITY -->
            <!-- ═══════════════════════════════════════════════════════════ -->
            <div class="ppv-stats-tab-content" id="ppv-tab-device-activity">
                <div class="ppv-stats-section">
                    <h3 class="ppv-section-title">📱 <?php echo esc_html($T['device_activity'] ?? 'Geräte-Aktivität'); ?></h3>
                    <p class="ppv-section-desc"><?php echo esc_html($T['device_activity_desc'] ?? 'Übersicht der Scan-Aktivitäten pro Gerät (letzte 7 Tage).'); ?></p>

                    <!-- Summary Stats -->
                    <div class="ppv-stats-summary-row" style="margin-bottom: 20px;">
                        <div class="ppv-stat-card">
                            <span class="ppv-stat-label"><?php echo esc_html($T['total_devices'] ?? 'Aktive Geräte'); ?></span>
                            <span class="ppv-stat-value" id="ppv-device-count">0</span>
                        </div>
                        <div class="ppv-stat-card">
                            <span class="ppv-stat-label"><?php echo esc_html($T['mobile_scanners'] ?? 'Mobile Scanner'); ?></span>
                            <span class="ppv-stat-value" id="ppv-mobile-scanner-count">0</span>
                        </div>
                        <div class="ppv-stat-card ppv-stat-card-warning">
                            <span class="ppv-stat-label"><?php echo esc_html($T['suspicious_devices'] ?? 'Verdächtige Geräte'); ?></span>
                            <span class="ppv-stat-value" id="ppv-suspicious-device-count">0</span>
                        </div>
                    </div>

                    <div id="ppv-device-loading" class="ppv-loading-small" style="display:none;">
                        <?php echo esc_html($T['loading'] ?? 'Loading...'); ?>
                    </div>

                    <!-- Device Activity Table -->
                    <div class="ppv-device-activity-table" id="ppv-device-activity-list">
                        <p class="ppv-no-data"><?php echo esc_html($T['no_device_data'] ?? 'Noch keine Gerätedaten vorhanden.'); ?></p>
                    </div>
                </div>
            </div><!-- END TAB 5: DEVICE ACTIVITY -->

        </div>

        <?php
        $content = ob_get_clean();
        $content .= do_shortcode('[ppv_bottom_nav]');
        return $content;
    }
}

PPV_Stats::hooks();