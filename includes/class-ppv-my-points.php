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
            error_log("✅ [PPV_My_Points] Loaded {$lang} from: {$file}");
            return is_array($strings) ? $strings : [];
        }

        error_log("⚠️ [PPV_My_Points] Lang file not found: {$file}");
        return [];
    }

    /** ============================================================
     *  🔹 ENQUEUE SCRIPTS + INLINE STRINGS
     * ============================================================ */
    public static function enqueue_assets() {

        // Start session
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // ✅ GET ACTIVE LANGUAGE (SAFE)
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? '');
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_SESSION['ppv_lang'] ?? 'de');
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
        }

        // Save to session + cookie
        $_SESSION['ppv_lang'] = $lang;
        setcookie('ppv_lang', $lang, time() + 31536000, '/', '', false, true);

        error_log("🌍 [PPV_My_Points] Active language: {$lang}");

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
    'api_url' => rest_url('ppv/v1/user/points-detailed'),  // ✅ DETAILED!
    'lang'    => $lang,
];
        wp_add_inline_script(
            'ppv-my-points',
            'window.ppv_mypoints = ' . wp_json_encode($global_data) . ';',
            'before'
        );

        // ============================================================
        // 🌍 INLINE: LANGUAGE STRINGS
        // ============================================================
        $strings = self::load_lang_file($lang);
        
        wp_add_inline_script(
            'ppv-my-points',
            'window.ppv_lang = ' . wp_json_encode($strings) . ';',
            'before'
        );

        error_log("✅ [PPV_My_Points] Inline scripts added, lang={$lang}, strings=" . count($strings));
    }

    /** ============================================================
     *  🔹 RENDER HTML SHELL
     * ============================================================ */
    public static function render_shell() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Get active lang
        $lang = sanitize_text_field($_GET['lang'] ?? '');
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = sanitize_text_field($_COOKIE['ppv_lang'] ?? ($_SESSION['ppv_lang'] ?? 'de'));
        }
        if (!in_array($lang, ['de', 'hu', 'ro'], true)) {
            $lang = 'de';
        }

        // Load strings for error messages
        $strings = self::load_lang_file($lang);

        // Check user logged in
        $uid = get_current_user_id() ?: ($_SESSION['ppv_user_id'] ?? 0);
        if ($uid <= 0) {
            $msg = $strings['please_login'] ?? 'Bitte einloggen, um Punkte zu sehen.';
            return '<div class="ppv-notice" style="padding: 20px; text-align: center; color: #f55;">
                <strong>⚠️</strong> ' . esc_html($msg) . '
            </div>';
        }

        // Render shell
        $html = '<div id="ppv-my-points-app" data-lang="' . esc_attr($lang) . '"></div>';
        $html .= do_shortcode('[ppv_bottom_nav]');

        error_log("✅ [PPV_My_Points] Shell rendered, user={$uid}, lang={$lang}");

        return $html;
    }
}

// Initialize
PPV_My_Points::hooks();

error_log("✅ [PPV_My_Points] Class loaded");