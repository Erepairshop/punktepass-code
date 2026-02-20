<?php
/**
 * PunktePass Standalone Shell (Lightweight)
 *
 * Intercepts known PunktePass page URLs at template_redirect
 * and renders them in a minimal HTML shell WITHOUT the WordPress theme.
 * This skips theme loading, sidebars, menus, and most plugin output,
 * resulting in significantly faster page loads.
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Shell {

    /**
     * Route map: URL path → [shortcode_callback, page_title, page_type]
     * page_type: 'handler', 'customer', 'auth', 'public'
     */
    private static function get_routes() {
        return [
            // Handler pages
            '/qr-center'    => [['PPV_QR', 'render_qr_center'],         'QR Center',       'handler'],
            '/rewards'       => [['PPV_Rewards', 'render_page'],          'Rewards',         'handler'],
            '/mein-profil'   => [['PPV_Profile_Lite', 'render_form'],     'Profil',          'handler'],
            '/statistik'     => [['PPV_Stats', 'render_stats_dashboard'], 'Statistik',       'handler'],
            '/bonus-tage'    => [['PPV_Bonus_Days', 'render_bonus_page'], 'Bonus-Tage',      'handler'],
            '/vip-settings'  => [['PPV_VIP_Settings', 'render_settings_page'], 'VIP',        'handler'],
            '/rewards-management' => [['PPV_Rewards_Management', 'render_management_page'], 'Belohnungen verwalten', 'handler'],
            '/belege'        => [['PPV_Receipts', 'render_receipts_page'], 'Belege',         'handler'],
            '/einloesungen'  => [['PPV_Redeem_Admin', 'render_redeem_admin'], 'Einlösungen', 'handler'],

            // Customer pages
            '/user_dashboard' => [['PPV_User_Dashboard', 'render_dashboard'], 'Dashboard',   'customer'],
            '/meine-punkte'   => [['PPV_My_Points', 'render_shell'],          'Meine Punkte','customer'],
            '/belohnungen'    => [['PPV_Belohnungen', 'render_rewards_page'],  'Belohnungen', 'customer'],
            '/einstellungen'  => [['PPV_User_Settings', 'render_settings_page'], 'Einstellungen', 'customer'],
            '/mein-qr'        => [['PPV_User_QR', 'render_qr'],               'Mein QR-Code','customer'],

            // Auth pages
            '/login'          => [['PPV_Login', 'render_landing_page'],   'Login',          'auth'],
            '/anmelden'       => [['PPV_Login', 'render_landing_page'],   'Anmelden',       'auth'],
            '/signup'         => [['PPV_Signup', 'render_signup_page'],   'Registrierung',  'auth'],
            '/registrierung'  => [['PPV_Signup', 'render_signup_page'],   'Registrierung',  'auth'],
        ];
    }

    /** Register hook */
    public static function hooks() {
        add_action('template_redirect', [__CLASS__, 'maybe_render_standalone'], 1);
    }

    /** Check if current URL matches a known route and render standalone */
    public static function maybe_render_standalone() {
        // DISABLED: Needs more work before production use.
        // The shortcodes depend on wp_enqueue_script/style + wp_head/wp_footer
        // which don't work properly in standalone mode.
        return;

        $path = rtrim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
        if (empty($path)) $path = '/';

        $routes = self::get_routes();
        if (!isset($routes[$path])) return;

        $route = $routes[$path];
        $callback   = $route[0];
        $page_title = $route[1];
        $page_type  = $route[2];

        // Verify callback class exists
        if (is_array($callback) && !class_exists($callback[0])) return;

        // Session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Auth check for non-auth pages
        if ($page_type !== 'auth') {
            $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
            $store_id = intval($_SESSION['ppv_vendor_store_id'] ?? ($_SESSION['ppv_store_id'] ?? 0));

            if ($user_id <= 0 && $store_id <= 0) {
                // Not logged in → redirect to login
                wp_redirect(home_url('/login'));
                exit;
            }
        }

        // Determine user type for navigation
        $is_handler = !empty($_SESSION['ppv_user_type']) &&
            in_array($_SESSION['ppv_user_type'], ['store', 'handler', 'vendor', 'admin', 'scanner']);
        if (!$is_handler && !empty($_SESSION['ppv_vendor_store_id'])) {
            $is_handler = true;
        }

        // Get content from shortcode callback
        ob_start();
        if (is_callable($callback)) {
            echo call_user_func($callback);
        }
        $page_content = ob_get_clean();

        // Get global header
        ob_start();
        if (class_exists('PPV_User_Dashboard') && $page_type !== 'auth') {
            PPV_User_Dashboard::render_global_header();
        }
        $global_header = ob_get_clean();

        // Get bottom navigation
        $bottom_nav = '';
        if ($page_type !== 'auth' && class_exists('PPV_Bottom_Nav')) {
            $bottom_nav = PPV_Bottom_Nav::render_nav();
            // Also get the context script
            ob_start();
            PPV_Bottom_Nav::inject_context();
            $bottom_nav_context = ob_get_clean();
        }

        // Dark mode check
        $is_dark = isset($_COOKIE['ppv_handler_theme']) && $_COOKIE['ppv_handler_theme'] === 'dark';

        // Render the standalone HTML
        self::render_shell($page_title, $page_content, $global_header, $bottom_nav, $bottom_nav_context ?? '', $page_type, $is_handler, $is_dark);
        exit;
    }

    /** Render minimal HTML shell */
    private static function render_shell($title, $content, $header, $bottom_nav, $nav_context, $page_type, $is_handler, $is_dark) {
        $plugin_url = PPV_PLUGIN_URL;
        $version = PPV_VERSION;
        $site_url = get_site_url();
        $body_classes = ['ppv-standalone', 'ppv-app-mode'];
        if ($is_handler) $body_classes[] = 'ppv-handler-mode';
        if ($is_dark) $body_classes[] = 'ppv-dark-mode';
        $body_class = implode(' ', $body_classes);

        // Page-specific CSS
        $extra_css = '';
        if ($page_type === 'auth') {
            $extra_css = '<link rel="stylesheet" href="' . $plugin_url . 'assets/css/ppv-login-light.css?v=' . $version . '">';
        } elseif ($page_type === 'customer') {
            $extra_css = '<link rel="stylesheet" href="' . $plugin_url . 'assets/css/ppv-user-settings.css?v=' . $version . '">';
        }

        header('Content-Type: text/html; charset=utf-8');
        ?>
<!DOCTYPE html>
<html lang="<?php echo esc_attr(class_exists('PPV_Lang') ? PPV_Lang::current() : 'de'); ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, maximum-scale=1, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?php echo esc_html($title); ?> - PunktePass</title>
    <link rel="manifest" href="<?php echo $site_url; ?>/manifest.json">
    <link rel="icon" href="<?php echo $plugin_url; ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo $plugin_url; ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-core.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-layout.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-components.css?v=<?php echo $version; ?>">
    <?php if ($is_handler): ?>
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-handler.css?v=<?php echo $version; ?>">
    <?php else: ?>
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-theme-light.css?v=<?php echo $version; ?>">
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/handler-light.css?v=<?php echo $version; ?>">
    <?php endif; ?>
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-bottom-nav.css?v=<?php echo $version; ?>">
    <?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo $plugin_url; ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo $version; ?>">
    <?php endif; ?>
    <?php echo $extra_css; ?>
    <?php
    // Fire wp_head to let shortcodes that enqueued styles get them output
    // But only print styles, not all of wp_head (which would include theme stuff)
    wp_print_styles();
    ?>
    <style>
    /* Standalone shell base */
    html,body{margin:0;padding:0;min-height:100vh;background:var(--pp-bg,#f5f5f7)}
    .ppv-standalone-wrap{max-width:768px;margin:0 auto;padding:0 0 90px 0;min-height:100vh}
    /* Ensure safe area on iOS */
    .ppv-standalone-wrap{padding-top:env(safe-area-inset-top,0)}
    </style>
</head>
<body class="<?php echo esc_attr($body_class); ?>">
<?php echo $header; ?>
<div class="ppv-standalone-wrap">
<?php echo $content; ?>
</div>
<?php echo $bottom_nav; ?>
<?php echo $nav_context; ?>
<?php
// Print scripts enqueued by shortcodes (REST API localization etc.)
wp_print_scripts();
?>
</body>
</html>
<?php
    }
}
