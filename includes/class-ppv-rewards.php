<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Einlösungen Admin Dashboard v2.0
 * Modern design - Dashboard + Napló + Bizonylatok
 *
 * Shortcode: [ppv_rewards]
 *
 * Features:
 * - Dashboard statisztikák (Heute/Woche/Monat/Wert)
 * - Beváltás napló kártyákkal
 * - Approve/Cancel funkciók
 * - Bizonylatok tab (havi generálás)
 * - Ably real-time support
 * - Filiale support
 */

class PPV_Rewards {

    public static function hooks() {
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
        add_shortcode('ppv_rewards', [__CLASS__, 'render_page']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
    }

    /** ============================================================
     *  REST ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // Dashboard stats
        register_rest_route('ppv/v1', '/einloesungen/stats', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_stats'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Einlösungen lista (pending + approved)
        register_rest_route('ppv/v1', '/einloesungen/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_einloesungen'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Update status (approve/cancel)
        register_rest_route('ppv/v1', '/einloesungen/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // Recent logs
        register_rest_route('ppv/v1', '/einloesungen/log', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Bizonylatok lista
        register_rest_route('ppv/v1', '/einloesungen/receipts', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_receipts'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Havi bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // Egyedi bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/generate-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_single_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // Dátum szerinti bizonylat generálás
        register_rest_route('ppv/v1', '/einloesungen/date-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_date_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // === LEGACY ENDPOINTS (backward compatibility) ===
        register_rest_route('ppv/v1', '/redeem/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_einloesungen'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/redeem/update', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_update_status'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        register_rest_route('ppv/v1', '/redeem/log', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_logs'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/redeem/monthly-receipt', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_generate_monthly_receipt'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);
    }

    /** ============================================================
     *  GET STORE ID (Filiale support)
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // Session - base store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Vendor store fallback
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // Logged in user
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                $_SESSION['ppv_store_id'] = $store_id;
                return intval($store_id);
            }
        }

        return 0;
    }

    /** ============================================================
     *  GET HANDLER FILIALEN
     * ============================================================ */
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

    // ============================================================
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ============================================================

    /** Intercept /rewards URL at init and render standalone page */
    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/rewards') return;

        ppv_disable_wp_optimization();

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

    /** Render complete standalone HTML page for Rewards */
    private static function render_standalone_page() {
        global $wpdb;

        $plugin_url = PPV_PLUGIN_URL;
        $version    = PPV_Core::asset_version();
        $site_url   = get_site_url();

        // ─── Language ───
        if (class_exists('PPV_Lang')) {
            $lang    = PPV_Lang::$active ?: 'de';
            $strings = PPV_Lang::$strings ?: [];
        } else {
            $lang    = 'de';
            $strings = [];
        }

        // ─── Theme ───
        $theme_cookie = $_COOKIE['ppv_theme'] ?? ($_COOKIE['ppv_handler_theme'] ?? 'light');
        $is_dark = ($theme_cookie === 'dark');

        // ─── Page content (render_page returns HTML string) ───
        $page_html = self::render_page();

        // ─── Ably config (render_page inline script doesn't include it) ───
        $ably_enabled = class_exists('PPV_Ably') && PPV_Ably::is_enabled();
        $ably_patch = '';
        if ($ably_enabled) {
            $store_id = self::get_store_id();
            $ably_patch = 'if(window.ppv_rewards_config){window.ppv_rewards_config.ably=' . wp_json_encode([
                'key'     => PPV_Ably::get_key(),
                'channel' => 'store-' . $store_id,
            ]) . ';}';
        }

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
    <?php ppv_standalone_cleanup_head(); ?>
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title>Einlösungen - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-core.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-components.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-global-header.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-handler.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-rewards.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
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
<?php if (!empty($ably_patch)): ?>
<script><?php echo $ably_patch; ?></script>
<?php endif; ?>
<?php if ($ably_enabled): ?>
<script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-ably-manager.js?v=<?php echo esc_attr($version); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-rewards.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
    }

    /** ============================================================
     *  ASSETS
     * ============================================================ */
    public static function enqueue_assets() {
        global $post;
        if (!isset($post->post_content) || !has_shortcode($post->post_content, 'ppv_rewards')) {
            return;
        }

        $plugin_url = defined('PPV_PLUGIN_URL') ? PPV_PLUGIN_URL : plugin_dir_url(dirname(__FILE__));
        $store_id = self::get_store_id();

        // Theme loader
        wp_enqueue_script('ppv-theme-loader', $plugin_url . 'assets/js/ppv-theme-loader.js', [], time(), false);

        // Fonts - Google Fonts removed for performance (using system fonts)
        // wp_enqueue_style('google-fonts-inter', 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap', [], null);

        // Ably (if enabled) - use shared manager
        $dependencies = [];
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::enqueue_scripts();
            $dependencies[] = 'ppv-ably-manager';
        }

        // Main JS
        wp_enqueue_script('ppv-rewards', $plugin_url . 'assets/js/ppv-rewards.js', $dependencies, time(), true);

        // Config
        $config = [
            'base' => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_id' => $store_id,
            'plugin_url' => esc_url($plugin_url),
        ];

        // Ably config
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            $config['ably'] = [
                'key' => PPV_Ably::get_key(),
                'channel' => 'store-' . $store_id,
            ];
        }

        wp_add_inline_script('ppv-rewards', 'window.ppv_rewards_config = ' . wp_json_encode($config) . ';', 'before');

        // Translations
        if (class_exists('PPV_Lang')) {
            wp_add_inline_script('ppv-rewards', 'window.ppv_lang = ' . wp_json_encode(PPV_Lang::$strings) . ';', 'before');
        }
    }

    /** ============================================================
     *  RENDER PAGE
     * ============================================================ */
    public static function render_page() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        global $wpdb;
        $store_id = self::get_store_id();

        // Translation helper
        $t = function($key) {
            return class_exists('PPV_Lang') ? PPV_Lang::t($key) : $key;
        };

        if (!$store_id) {
            return '<div class="ppv-warning"><i class="ri-alert-line"></i> ' . esc_html($t('rewards_login_required')) . '</div>';
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, company_name, country FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
            $store_id
        ));

        if (!$store) {
            return '<div class="ppv-warning"><i class="ri-alert-line"></i> ' . esc_html($t('rewards_store_not_found')) . '</div>';
        }

        // Currency
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$store->country] ?? 'EUR';

        // Month names from translations
        $month_keys = ['month_january', 'month_february', 'month_march', 'month_april', 'month_may', 'month_june',
                       'month_july', 'month_august', 'month_september', 'month_october', 'month_november', 'month_december'];

        // Get filialen for selector
        $filialen = self::get_handler_filialen();
        $has_multiple_filialen = count($filialen) > 1;

        ob_start();
        ?>
        <script>
        window.PPV_STORE_ID = <?php echo intval($store->id); ?>;
        window.ppv_rewards_config = <?php echo wp_json_encode([
            'base' => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'store_id' => $store_id,
            'plugin_url' => defined('PPV_PLUGIN_URL') ? esc_url(PPV_PLUGIN_URL) : esc_url(plugin_dir_url(dirname(__FILE__))),
        ]); ?>;
        </script>

        <div class="ppv-einloesungen-admin" data-store-id="<?php echo esc_attr($store->id); ?>" data-currency="<?php echo esc_attr($currency); ?>">

            <!-- HEADER -->
            <div class="ppv-ea-header">
                <div class="ppv-ea-header-left">
                    <h1><i class="ri-trophy-line"></i> <?php echo esc_html($t('rewards_title')); ?></h1>
                    <span class="ppv-ea-store-name"><?php echo esc_html($store->company_name); ?></span>
                </div>
                <div class="ppv-ea-header-right">
                    <button class="ppv-ea-refresh-btn" id="ppv-ea-refresh">
                        <i class="ri-refresh-line"></i>
                    </button>
                </div>
            </div>

            <!-- DASHBOARD STATS -->
            <div class="ppv-ea-stats" id="ppv-ea-stats">
                <div class="ppv-ea-stat-card ppv-ea-stat-heute">
                    <div class="ppv-ea-stat-icon"><i class="ri-sun-line"></i></div>
                    <span class="ppv-ea-stat-value" id="stat-heute">-</span>
                    <span class="ppv-ea-stat-label"><?php echo esc_html($t('rewards_stat_today')); ?></span>
                </div>
                <div class="ppv-ea-stat-card ppv-ea-stat-woche">
                    <div class="ppv-ea-stat-icon"><i class="ri-calendar-todo-line"></i></div>
                    <span class="ppv-ea-stat-value" id="stat-woche">-</span>
                    <span class="ppv-ea-stat-label"><?php echo esc_html($t('rewards_stat_week')); ?></span>
                </div>
                <div class="ppv-ea-stat-card ppv-ea-stat-monat">
                    <div class="ppv-ea-stat-icon"><i class="ri-calendar-2-line"></i></div>
                    <span class="ppv-ea-stat-value" id="stat-monat">-</span>
                    <span class="ppv-ea-stat-label"><?php echo esc_html($t('rewards_stat_month')); ?></span>
                </div>
                <div class="ppv-ea-stat-card ppv-ea-stat-wert">
                    <div class="ppv-ea-stat-icon"><i class="ri-wallet-3-line"></i></div>
                    <span class="ppv-ea-stat-value" id="stat-wert">-</span>
                    <span class="ppv-ea-stat-label"><?php echo esc_html($t('rewards_stat_value')); ?></span>
                </div>
            </div>

            <!-- TABS -->
            <div class="ppv-ea-tabs">
                <button class="ppv-ea-tab active" data-tab="pending">
                    <i class="ri-hourglass-line"></i> <?php echo esc_html($t('rewards_tab_pending')); ?>
                    <span class="ppv-ea-tab-badge" id="pending-count">0</span>
                </button>
                <button class="ppv-ea-tab" data-tab="history">
                    <i class="ri-time-line"></i> <?php echo esc_html($t('rewards_tab_history')); ?>
                </button>
                <button class="ppv-ea-tab" data-tab="receipts">
                    <i class="ri-receipt-line"></i> <?php echo esc_html($t('rewards_tab_receipts')); ?>
                </button>
            </div>

            <!-- TAB CONTENT: PENDING -->
            <div class="ppv-ea-tab-content active" id="tab-pending">
                <div class="ppv-ea-list" id="ppv-ea-pending-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html($t('rewards_loading')); ?>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: HISTORY -->
            <div class="ppv-ea-tab-content" id="tab-history">
                <div class="ppv-ea-filter-bar">
                    <select id="ppv-ea-filter-status" class="ppv-ea-filter-select">
                        <option value="all"><?php echo esc_html($t('rewards_filter_all')); ?></option>
                        <option value="approved"><?php echo esc_html($t('rewards_filter_approved')); ?></option>
                        <option value="cancelled"><?php echo esc_html($t('rewards_filter_cancelled')); ?></option>
                    </select>
                    <input type="date" id="ppv-ea-filter-date" class="ppv-ea-filter-date">
                </div>
                <div class="ppv-ea-list" id="ppv-ea-history-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html($t('rewards_loading')); ?>
                    </div>
                </div>
            </div>

            <!-- TAB CONTENT: RECEIPTS -->
            <div class="ppv-ea-tab-content" id="tab-receipts">
                <?php if ($has_multiple_filialen): ?>
                <!-- Filiale Selector -->
                <div class="ppv-ea-filiale-selector" style="margin-bottom: 20px; padding: 15px; background: rgba(255,255,255,0.05); border-radius: 10px;">
                    <label style="display: flex; align-items: center; gap: 10px; color: #ccc; font-weight: 500;">
                        <i class="ri-store-2-line"></i>
                        <?php echo esc_html($t('rewards_select_filiale') ?: 'Filiale auswählen'); ?>
                    </label>
                    <select id="ppv-ea-receipt-filiale" class="ppv-ea-select" style="margin-top: 8px; width: 100%; max-width: 400px;">
                        <option value="all"><?php echo esc_html($t('rewards_all_filialen') ?: 'Alle Filialen (gruppiert)'); ?></option>
                        <?php foreach ($filialen as $filiale): ?>
                            <option value="<?php echo esc_attr($filiale->id); ?>">
                                <?php echo esc_html($filiale->name ?: $filiale->company_name); ?>
                                <?php if ($filiale->city): ?> – <?php echo esc_html($filiale->city); ?><?php endif; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <!-- Receipt Generators -->
                <div class="ppv-ea-receipt-generators">
                    <!-- Monthly Receipt Generator -->
                    <div class="ppv-ea-receipt-generator">
                        <h3><i class="ri-calendar-schedule-line"></i> <?php echo esc_html($t('rewards_monthly_report')); ?></h3>
                        <div class="ppv-ea-receipt-form">
                            <select id="ppv-ea-receipt-month" class="ppv-ea-select">
                                <?php
                                $current_month = (int)date('n');
                                for ($i = 1; $i <= 12; $i++):
                                    $selected = ($i === $current_month) ? 'selected' : '';
                                    $month_name = $t($month_keys[$i-1]);
                                ?>
                                    <option value="<?php echo $i; ?>" <?php echo $selected; ?>><?php echo esc_html($month_name); ?></option>
                                <?php endfor; ?>
                            </select>
                            <select id="ppv-ea-receipt-year" class="ppv-ea-select">
                                <?php
                                $current_year = (int)date('Y');
                                for ($y = $current_year; $y >= $current_year - 2; $y--):
                                ?>
                                    <option value="<?php echo $y; ?>"><?php echo $y; ?></option>
                                <?php endfor; ?>
                            </select>
                            <button id="ppv-ea-generate-receipt" class="ppv-ea-btn-primary">
                                <i class="ri-add-line"></i> <?php echo esc_html($t('rewards_btn_create')); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Date Range Receipt Generator -->
                    <div class="ppv-ea-receipt-generator">
                        <h3><i class="ri-calendar-event-line"></i> <?php echo esc_html($t('rewards_period_report')); ?></h3>
                        <div class="ppv-ea-receipt-form ppv-ea-date-range-form">
                            <div class="ppv-ea-date-range">
                                <input type="date" id="ppv-ea-receipt-date-from" class="ppv-ea-select" value="<?php echo date('Y-m-01'); ?>">
                                <span class="ppv-ea-date-separator"><?php echo esc_html($t('rewards_date_until')); ?></span>
                                <input type="date" id="ppv-ea-receipt-date-to" class="ppv-ea-select" value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <button id="ppv-ea-generate-date-receipt" class="ppv-ea-btn-primary">
                                <i class="ri-add-line"></i> <?php echo esc_html($t('rewards_btn_create')); ?>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Receipts List -->
                <div class="ppv-ea-receipts-list" id="ppv-ea-receipts-list">
                    <div class="ppv-ea-loading">
                        <i class="ri-loader-4-line ri-spin"></i> <?php echo esc_html($t('rewards_loading_receipts')); ?>
                    </div>
                </div>
            </div>

        </div>

        <?php
        // Bottom nav
        if (class_exists('PPV_Bottom_Nav')) {
            echo PPV_Bottom_Nav::render_nav();
        } else {
            echo do_shortcode('[ppv_bottom_nav]');
        }

        return ob_get_clean();
    }

    /** ============================================================
     *  REST: Get Dashboard Stats
     * ============================================================ */
    public static function rest_get_stats($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'No store ID'], 400);
        }

        $table = $wpdb->prefix . 'ppv_rewards_redeemed';

        // Today
        $heute = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND DATE(redeemed_at) = CURDATE()
        ", $store_id));

        // This week
        $woche = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND YEARWEEK(redeemed_at, 1) = YEARWEEK(CURDATE(), 1)
        ", $store_id));

        // This month
        $monat = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'approved' AND YEAR(redeemed_at) = YEAR(CURDATE()) AND MONTH(redeemed_at) = MONTH(CURDATE())
        ", $store_id));

        // Total value this month - calculated from reward
        // Priority: actual_amount → action_value → free_product_value
        $rewards_table = $wpdb->prefix . 'ppv_rewards';
        $wert = (float)$wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(
                CASE
                    WHEN r.actual_amount IS NOT NULL AND r.actual_amount > 0 THEN r.actual_amount
                    WHEN rw.action_value IS NOT NULL AND rw.action_value != '' AND rw.action_value != '0' THEN CAST(rw.action_value AS DECIMAL(10,2))
                    WHEN rw.free_product_value IS NOT NULL AND rw.free_product_value > 0 THEN rw.free_product_value
                    ELSE 0
                END
            ), 0)
            FROM {$table} r
            LEFT JOIN {$rewards_table} rw ON r.reward_id = rw.id
            WHERE r.store_id = %d AND r.status = 'approved'
            AND YEAR(r.redeemed_at) = YEAR(CURDATE())
            AND MONTH(r.redeemed_at) = MONTH(CURDATE())
        ", $store_id));

        // Pending count
        $pending = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$table}
            WHERE store_id = %d AND status = 'pending'
        ", $store_id));

        // Currency
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
        $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
        $currency = $currency_map[$store->country ?? 'DE'] ?? 'EUR';

        return new WP_REST_Response([
            'success' => true,
            'stats' => [
                'heute' => $heute,
                'woche' => $woche,
                'monat' => $monat,
                'wert' => $wert,
                'currency' => $currency,
                'pending' => $pending,
            ]
        ], 200);
    }

    /** ============================================================
     *  REST: List Einlösungen
     * ============================================================ */
    public static function rest_list_einloesungen($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'items' => []], 400);
        }

        $status = sanitize_text_field($request->get_param('status') ?? 'all');
        $date = sanitize_text_field($request->get_param('date') ?? '');
        $limit = intval($request->get_param('limit') ?? 50);

        $where = "r.store_id = %d";
        $params = [$store_id];

        if ($status === 'pending') {
            $where .= " AND r.status = 'pending'";
        } elseif ($status === 'approved') {
            $where .= " AND r.status = 'approved'";
        } elseif ($status === 'cancelled') {
            $where .= " AND r.status = 'cancelled'";
        } elseif ($status === 'history') {
            $where .= " AND r.status IN ('approved', 'cancelled')";
        }

        if ($date) {
            $where .= " AND DATE(r.redeemed_at) = %s";
            $params[] = $date;
        }

        $params[] = $limit;

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.user_id,
                r.store_id,
                r.reward_id,
                r.points_spent,
                r.actual_amount,
                r.status,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE {$where}
            ORDER BY r.redeemed_at DESC
            LIMIT %d
        ", ...$params));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'count' => count($items)
        ], 200);
    }

    /** ============================================================
     *  REST: Update Status (Approve/Cancel)
     * ============================================================ */
    public static function rest_update_status($request) {
        global $wpdb;

        $data = $request->get_json_params();
        $id = intval($data['id'] ?? 0);
        $status = sanitize_text_field($data['status'] ?? '');
        $store_id = self::get_store_id();

        if (!$id || !$status || !in_array($status, ['approved', 'cancelled'])) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid data'], 400);
        }

        // Check current status
        $current = $wpdb->get_var($wpdb->prepare(
            "SELECT status FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d AND store_id = %d",
            $id, $store_id
        ));

        if ($current === $status) {
            return new WP_REST_Response(['success' => true, 'message' => 'No change'], 200);
        }

        // Update status
        $wpdb->update(
            $wpdb->prefix . 'ppv_rewards_redeemed',
            ['status' => $status, 'redeemed_at' => current_time('mysql')],
            ['id' => $id, 'store_id' => $store_id],
            ['%s', '%s'],
            ['%d', '%d']
        );

        if ($wpdb->last_error) {
            return new WP_REST_Response(['success' => false, 'message' => 'DB error'], 500);
        }

        $receipt_path = null;
        $receipt_url = null;

        // If approved: deduct points + generate receipt
        if ($status === 'approved') {
            self::deduct_points($id, $store_id);

            // Generate receipt
            if (class_exists('PPV_Expense_Receipt')) {
                $receipt_path = PPV_Expense_Receipt::generate_for_redeem($id);
                if ($receipt_path) {
                    $receipt_url = PPV_Expense_Receipt::get_receipt_url($receipt_path);
                }
            }

            // Ably notification
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                $redeem = $wpdb->get_row($wpdb->prepare("
                    SELECT r.user_id, r.points_spent, rw.title AS reward_title
                    FROM {$wpdb->prefix}ppv_rewards_redeemed r
                    LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
                    WHERE r.id = %d
                ", $id));

                if ($redeem && $redeem->user_id) {
                    PPV_Ably::trigger_reward_approved($redeem->user_id, [
                        'redeem_id' => $id,
                        'status' => $status,
                        'reward_name' => $redeem->reward_title,
                        'points_spent' => $redeem->points_spent,
                    ]);
                }
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'message' => $status === 'approved' ? 'Bestätigt' : 'Abgelehnt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Get Logs (History)
     * ============================================================ */
    public static function rest_get_logs($request) {
        global $wpdb;

        $store_id = self::get_store_id();
        $limit = intval($request->get_param('limit') ?? 20);

        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.points_spent,
                r.actual_amount,
                r.status,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE r.store_id = %d AND r.status IN ('approved', 'cancelled')
            ORDER BY r.redeemed_at DESC
            LIMIT %d
        ", $store_id, $limit));

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: []
        ], 200);
    }

    /** ============================================================
     *  REST: Get Receipts List
     * ============================================================ */
    public static function rest_get_receipts($request) {
        global $wpdb;

        $store_id = self::get_store_id();

        // Show ALL approved redemptions, not just those with receipt_pdf_path
        $items = $wpdb->get_results($wpdb->prepare("
            SELECT
                r.id,
                r.points_spent,
                r.actual_amount,
                r.redeemed_at,
                r.receipt_pdf_path,
                rw.title AS reward_title,
                u.email AS user_email,
                u.first_name,
                u.last_name
            FROM {$wpdb->prefix}ppv_rewards_redeemed r
            LEFT JOIN {$wpdb->prefix}ppv_rewards rw ON r.reward_id = rw.id
            LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
            WHERE r.store_id = %d AND r.status = 'approved'
            ORDER BY r.redeemed_at DESC
            LIMIT 50
        ", $store_id));

        // Get upload URL for receipt links
        $upload = wp_upload_dir();
        $base_url = $upload['baseurl'];

        return new WP_REST_Response([
            'success' => true,
            'items' => $items ?: [],
            'base_url' => $base_url
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Monthly Receipt
     * ============================================================ */
    public static function rest_generate_monthly_receipt($request) {
        $data = $request->get_json_params();
        $store_id = self::get_store_id();
        $year = intval($data['year'] ?? date('Y'));
        $month = intval($data['month'] ?? date('m'));
        $filiale_id = sanitize_text_field($data['filiale_id'] ?? 'all');

        if (!$store_id || $month < 1 || $month > 12) {
            return new WP_REST_Response(['success' => false, 'message' => 'Invalid parameters'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        // Handle filiale selection
        $target_store_id = ($filiale_id !== 'all' && is_numeric($filiale_id)) ? intval($filiale_id) : $store_id;
        $group_by_filiale = ($filiale_id === 'all');

        $receipt_path = PPV_Expense_Receipt::generate_monthly_receipt($target_store_id, $year, $month, $group_by_filiale);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Einlösungen für diesen Zeitraum'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Monatsbericht erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Single Receipt
     * ============================================================ */
    public static function rest_generate_single_receipt($request) {
        $data = $request->get_json_params();
        $redeem_id = intval($data['redeem_id'] ?? 0);

        if (!$redeem_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Redeem-ID'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        $receipt_path = PPV_Expense_Receipt::generate_for_redeem($redeem_id);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Beleg konnte nicht erstellt werden'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Beleg erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  REST: Generate Receipt for date range (Zeitraumbericht)
     * ============================================================ */
    public static function rest_generate_date_receipt($request) {
        $data = $request->get_json_params();
        $store_id = self::get_store_id();
        $date_from = sanitize_text_field($data['date_from'] ?? '');
        $date_to = sanitize_text_field($data['date_to'] ?? '');
        $filiale_id = sanitize_text_field($data['filiale_id'] ?? 'all');

        if (!$store_id || !$date_from || !$date_to) {
            return new WP_REST_Response(['success' => false, 'message' => 'Ungültige Parameter'], 400);
        }

        // Validate date format
        if (!strtotime($date_from) || !strtotime($date_to)) {
            return new WP_REST_Response(['success' => false, 'message' => 'Ungültiges Datumsformat'], 400);
        }

        if (!class_exists('PPV_Expense_Receipt')) {
            $file = PPV_PLUGIN_DIR . 'includes/class-ppv-expense-receipt.php';
            if (file_exists($file)) {
                require_once $file;
            } else {
                return new WP_REST_Response(['success' => false, 'message' => 'Receipt class not found'], 500);
            }
        }

        // Handle filiale selection
        $target_store_id = ($filiale_id !== 'all' && is_numeric($filiale_id)) ? intval($filiale_id) : $store_id;
        $group_by_filiale = ($filiale_id === 'all');

        $receipt_path = PPV_Expense_Receipt::generate_date_range_receipt($target_store_id, $date_from, $date_to, $group_by_filiale);

        if (!$receipt_path) {
            return new WP_REST_Response(['success' => false, 'message' => 'Keine Einlösungen für diesen Zeitraum'], 400);
        }

        $upload = wp_upload_dir();
        $receipt_url = $upload['baseurl'] . '/' . $receipt_path;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Zeitraumbericht erstellt',
            'receipt_url' => $receipt_url,
        ], 200);
    }

    /** ============================================================
     *  PRIVATE: Deduct Points
     * ============================================================ */
    private static function deduct_points($redeem_id, $store_id) {
        global $wpdb;

        $redeem = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, points_spent FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d",
            $redeem_id
        ), ARRAY_A);

        if (!$redeem) return false;

        $user_id = intval($redeem['user_id']);
        $points = intval($redeem['points_spent']);

        // Check if already deducted
        $existing = $wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$wpdb->prefix}ppv_points WHERE reference = %s
        ", 'REDEEM-' . $redeem_id));

        if ($existing > 0) return false;

        // Deduct
        $wpdb->insert($wpdb->prefix . 'ppv_points', [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => -abs($points),
            'type' => 'redeem',
            'reference' => 'REDEEM-' . $redeem_id,
            'created' => current_time('mysql')
        ], ['%d', '%d', '%d', '%s', '%s', '%s']);

        return true;
    }
}

// Initialize
PPV_Rewards::hooks();
