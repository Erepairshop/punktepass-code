<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – My Points (Production Version)
 * ✅ Language strings from lang files
 * ✅ Auto-translate on language change
 * ✅ Safe + Secure
 * Version: 2.0
 */

class PPV_My_Points {

    private static $shortcode_used = false;

    public static function hooks() {
        add_action('init', [__CLASS__, 'maybe_render_standalone'], 1);
        add_shortcode('ppv_my_points', [__CLASS__, 'render_shell']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  🌍 LOAD LANGUAGE STRINGS FROM FILES
     * ============================================================ */
    private static function load_lang_file($lang = 'en') {
        // Validate
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = 'en';
        }

        // Try to load lang file
        $file = PPV_PLUGIN_DIR . "languages/lang-{$lang}-MY-POINTS-ONLY.php";
        
        if (file_exists($file)) {
            $strings = include($file);
            ppv_log("✅ [PPV_My_Points] Loaded {$lang} from: {$file}");
            return is_array($strings) ? $strings : [];
        }

        ppv_log("⚠️ [PPV_My_Points] Lang file not found: {$file}");
        return [];
    }

    /** ============================================================
     *  🔹 ENQUEUE SCRIPTS + INLINE STRINGS
     * ============================================================ */
    public static function enqueue_assets() {
        ppv_log("🔍 [PPV_My_Points::enqueue_assets] ========== START ==========");
        ppv_log("🔍 [PPV_My_Points] Current URL: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        ppv_log("🔍 [PPV_My_Points] User Agent: " . ($_SERVER['HTTP_USER_AGENT'] ?? 'N/A'));

        // ✅ REMOVED shortcode check - load on all pages like user-dashboard
        // This fixes issues with Elementor/page builders where $post->post_content
        // doesn't contain the shortcode

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            ppv_log("🔍 [PPV_My_Points] Session started");
        } else {
            ppv_log("🔍 [PPV_My_Points] Session already active");
        }

        // ✅ GET ACTIVE LANGUAGE (SAFE)
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        ppv_log("🔍 [PPV_My_Points] Lang from GET: " . ($lang ?: 'EMPTY'));

        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
            ppv_log("🔍 [PPV_My_Points] Lang from COOKIE: " . ($lang ?: 'EMPTY'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = sanitize_text_field($_SESSION['ppv_lang'] ?? 'en');
            ppv_log("🔍 [PPV_My_Points] Lang from SESSION: " . ($lang ?: 'en'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = 'en';
        }

        // Save to session + cookie
        $_SESSION['ppv_lang'] = $lang;
        setcookie('ppv_lang', $lang, time() + 31536000, '/', '', false, true);

        ppv_log("🌍 [PPV_My_Points] Active language: {$lang}");

        // ============================================================
        // 📦 ENQUEUE SCRIPTS
        // ============================================================
        wp_enqueue_script('jquery');

        // 📡 ABLY: Load JS library + shared manager if configured
        $js_deps = ['jquery'];
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
            PPV_Ably::enqueue_scripts();
            $js_deps[] = 'ppv-ably-manager';
        }

        // My Points
        wp_enqueue_script(
            'ppv-my-points',
            PPV_PLUGIN_URL . 'assets/js/ppv-my-points.js',
            $js_deps,
            time(),
            true
        );

        // ============================================================
        // 🌍 INLINE: GLOBAL DATA
        // ============================================================

        // Get user ID for Ably channel subscription
        $user_id = 0;
        if (!empty($_SESSION['ppv_user_id'])) {
            $user_id = intval($_SESSION['ppv_user_id']);
        }

        $global_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_mypoints_nonce'),
            'api_url' => rest_url('ppv/v1/mypoints'),
            'lang'    => $lang,
            'uid'     => $user_id,
        ];

        // 📡 ABLY: Add config for real-time updates
        if (class_exists('PPV_Ably') && PPV_Ably::is_enabled() && $user_id > 0) {
            $global_data['ably'] = [
                'key' => PPV_Ably::get_key(),
            ];
        }
        ppv_log("🔍 [PPV_My_Points] Global data prepared:");
        ppv_log("    - ajaxurl: " . $global_data['ajaxurl']);
        ppv_log("    - api_url: " . $global_data['api_url']);
        ppv_log("    - lang: " . $global_data['lang']);
        ppv_log("    - nonce: " . substr($global_data['nonce'], 0, 10) . "...");

        wp_add_inline_script(
            'ppv-my-points',
            'window.ppv_mypoints = ' . wp_json_encode($global_data) . '; console.log("🔍 [PHP→JS] window.ppv_mypoints set:", window.ppv_mypoints);',
            'before'
        );

        // ============================================================
        // 🌍 INLINE: LANGUAGE STRINGS
        // ============================================================
        $strings = self::load_lang_file($lang);
        ppv_log("🔍 [PPV_My_Points] Language strings loaded: " . count($strings) . " keys");

        wp_add_inline_script(
            'ppv-my-points',
            'window.ppv_lang = ' . wp_json_encode($strings) . '; console.log("🔍 [PHP→JS] window.ppv_lang set:", Object.keys(window.ppv_lang || {}).length + " keys");',
            'before'
        );

        ppv_log("✅ [PPV_My_Points] Inline scripts added, lang={$lang}, strings=" . count($strings));
        ppv_log("🔍 [PPV_My_Points::enqueue_assets] ========== END ==========");
    }

    // ============================================================
    // 🚀 STANDALONE RENDERING (bypasses WordPress theme)
    // ============================================================

    public static function maybe_render_standalone() {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        $path = rtrim($path, '/');
        if ($path !== '/meine-punkte') return;

        ppv_disable_wp_optimization();

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Customer auth
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);
        if ($user_id <= 0) {
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
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? ($_SESSION['ppv_lang'] ?? 'en'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = 'en';
        }
        $_SESSION['ppv_lang'] = $lang;

        $strings = self::load_lang_file($lang);

        // Also load global PPV_Lang strings for bottom nav etc.
        $global_strings = [];
        if (class_exists('PPV_Lang')) {
            $global_strings = PPV_Lang::$strings ?: [];
        }

        // ─── Theme ───
        $theme_cookie = $_COOKIE['ppv_theme'] ?? 'light';
        $is_dark = ($theme_cookie === 'dark');

        // ─── User ID ───
        $user_id = intval($_SESSION['ppv_user_id'] ?? 0);

        // ─── Mypoints config ───
        $mypoints_data = [
            'ajaxurl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('ppv_mypoints_nonce'),
            'api_url' => rest_url('ppv/v1/mypoints'),
            'lang'    => $lang,
            'uid'     => $user_id,
        ];

        // Ably
        $ably_enabled = class_exists('PPV_Ably') && PPV_Ably::is_enabled();
        if ($ably_enabled && $user_id > 0) {
            $mypoints_data['ably'] = [
                'key' => PPV_Ably::get_key(),
            ];
        }

        // ─── Page content ───
        $page_html = self::render_shell();

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
        $body_classes = ['ppv-standalone', 'ppv-app-mode'];
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
    <meta name="turbo-cache-control" content="no-cache">
    <title><?php echo esc_html(PPV_Lang::t('title') ?: 'My Points'); ?> - PunktePass</title>
    <link rel="manifest" href="<?php echo esc_url($site_url); ?>/manifest.json">
    <link rel="icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png" type="image/png">
    <link rel="apple-touch-icon" href="<?php echo esc_url($plugin_url); ?>assets/img/icon-192.png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-core.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-layout.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-components.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/handler-light.css?v=<?php echo esc_attr($version); ?>">
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-bottom-nav.css?v=<?php echo esc_attr($version); ?>">
<?php if ($is_dark): ?>
    <link rel="stylesheet" href="<?php echo esc_url($plugin_url); ?>assets/css/ppv-theme-dark-colors.css?v=<?php echo esc_attr($version); ?>">
<?php endif; ?>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
    <script>
    var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
    window.ppv_mypoints = <?php echo wp_json_encode($mypoints_data); ?>;
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
<?php if ($ably_enabled): ?>
<script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-ably-manager.js?v=<?php echo esc_attr($version); ?>"></script>
<?php endif; ?>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-my-points.js?v=<?php echo esc_attr($version); ?>"></script>
<script src="<?php echo esc_url($plugin_url); ?>assets/js/ppv-theme-loader.js?v=<?php echo esc_attr($version); ?>"></script>
<?php if (class_exists('PPV_Bottom_Nav')): ?>
<script><?php echo PPV_Bottom_Nav::inline_js(); ?></script>
<?php endif; ?>
</body>
</html>
<?php
    }

    /** ============================================================
     *  🔹 RENDER HTML SHELL
     * ============================================================ */
    public static function render_shell() {
        // Get active lang
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? ($_SESSION['ppv_lang'] ?? 'en'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'], true)) {
            $lang = 'en';
        }

        // ✅ SAME AS USER DASHBOARD - No user check here, let JS/REST API handle it!
        // This fixes Google/Facebook/TikTok login where session might not be ready yet
        $html = '<div id="ppv-my-points-app" data-lang="' . esc_attr($lang) . '"></div>';
        $html .= do_shortcode('[ppv_bottom_nav]');

        ppv_log("✅ [PPV_My_Points] Shell rendered, lang={$lang}");

        return $html;
    }
}

// Initialize
PPV_My_Points::hooks();

ppv_log("✅ [PPV_My_Points] Class loaded");