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
        add_shortcode('ppv_my_points', [__CLASS__, 'render_shell']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  🌍 LOAD LANGUAGE STRINGS FROM FILES
     * ============================================================ */
    private static function load_lang_file($lang = 'de') {
        // Validate
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
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

        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
            ppv_log("🔍 [PPV_My_Points] Lang from COOKIE: " . ($lang ?: 'EMPTY'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_SESSION['ppv_lang'] ?? 'de');
            ppv_log("🔍 [PPV_My_Points] Lang from SESSION: " . ($lang ?: 'de'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
        }

        // Save to session + cookie
        $_SESSION['ppv_lang'] = $lang;
        setcookie('ppv_lang', $lang, time() + 31536000, '/', '', false, true);

        ppv_log("🌍 [PPV_My_Points] Active language: {$lang}");

        // ============================================================
        // 📦 ENQUEUE SCRIPTS
        // ============================================================
        wp_enqueue_script('jquery');

        // Analytics
        wp_enqueue_script(
            'ppv-analytics',
            PPV_PLUGIN_URL . 'assets/js/ppv-analytics.js',
            ['jquery'],
            time(),
            true
        );

        // My Points
        wp_enqueue_script(
            'ppv-my-points',
            PPV_PLUGIN_URL . 'assets/js/ppv-my-points.js',
            ['jquery'],
            time(),
            true
        );

        // ============================================================
        // 🌍 INLINE: GLOBAL DATA
        // ============================================================
        $global_data = [
    'ajaxurl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('ppv_mypoints_nonce'),
    'api_url' => rest_url('ppv/v1/mypoints'),  // ✅ CORRECT ENDPOINT!
    'lang'    => $lang,
];
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

    /** ============================================================
     *  🔹 RENDER HTML SHELL
     * ============================================================ */
    public static function render_shell() {
        // Get active lang
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? ($_SESSION['ppv_lang'] ?? 'de'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
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