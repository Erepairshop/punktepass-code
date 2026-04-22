<?php
if (!defined('ABSPATH')) exit;

// 🔹 Biztonságos session-indítás a fájl legelején
if (session_status() === PHP_SESSION_NONE) {
    ppv_maybe_start_session();
}

/**
 * PunktePass – Bridge v2
 * PWA és REST közötti stabil kommunikációs híd
 * Verzió: 2.0 (session + lang + cache control)
 */

class PPV_Bridge {

    public static function hooks() {
        add_action('init', [__CLASS__, 'init_session']);
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'inject_bridge']);
    }

    /** 🔹 Session + language betöltés */
    public static function init_session() {
        if (!class_exists('PPV_Lang') && file_exists(PPV_PLUGIN_DIR . 'includes/class-ppv-lang.php')) {
            require_once PPV_PLUGIN_DIR . 'includes/class-ppv-lang.php';
            PPV_Lang::hooks();
        }
    }

    /** 🔹 REST Bridge tesztútvonal */
    public static function register_routes() {
        register_rest_route('ppv/v1', '/bridge', [
            'methods' => 'GET',
            'permission_callback' => ['PPV_Permissions', 'allow_anonymous'],
            'callback' => function() {
                $lang = isset($_COOKIE['ppv_lang']) ? sanitize_text_field($_COOKIE['ppv_lang']) : 'de';
                return [
                    'status'  => 'ok',
                    'session' => isset($_SESSION['ppv_user_id']) ? 'active' : 'none',
                    'lang'    => $lang,
                    'time'    => date('H:i:s')
                ];
            }
        ]);
    }

    /** 🔹 Bridge JS betöltése minden oldalra */
    public static function inject_bridge() {
        wp_enqueue_script(
            'ppv-bridge',
            PPV_PLUGIN_URL . 'assets/js/ppv-bridge.js',
            [],
            time(),
            true
        );

        $__data = is_array([
            'rest'  => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest')
        ] ?? null) ? [
            'rest'  => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest')
        ] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-bridge', "window.ppv_bridge = {$__json};", 'before');
        $user_id = get_current_user_id();
$__data = is_array([
    'id' => $user_id,
    'is_logged' => is_user_logged_in(),
] ?? null) ? [
    'id' => $user_id,
    'is_logged' => is_user_logged_in(),
] : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('ppv-bridge', "window.ppv_bridge_user = {$__json};", 'before');

        
                // 🔹 Token hozzáadása, ha létezik vagy új kell
global $wpdb;
$table = $wpdb->prefix . 'ppv_tokens';
$user_id = get_current_user_id();

if (!$user_id && isset($_SESSION['ppv_user_id'])) {
    $user_id = intval($_SESSION['ppv_user_id']);
}

$token = '';
if ($user_id > 0 && class_exists('PPV_Auth')) {
    $token = $wpdb->get_var($wpdb->prepare(
        "SELECT token FROM $table WHERE user_id=%d AND (expires_at > NOW() OR expires_at IS NULL) ORDER BY id DESC LIMIT 1",
        $user_id
    ));
    if (!$token) {
        $token = PPV_Auth::create_token($user_id);
        ppv_log("🟢 [PPV_BRIDGE] Új token létrehozva user={$user_id}");
    } else {
        ppv_log("✅ [PPV_BRIDGE] Meglévő token betöltve user={$user_id}");
    }
}

wp_add_inline_script('ppv-bridge', "
    window.ppvAuthToken = " . json_encode($token) . ";
    console.log('🌉 PPV Bridge aktiv – Token=' + (window.ppvAuthToken ? window.ppvAuthToken.substring(0,10)+'…' : '(none)'));
    const oldFetch = window.fetch;
    window.fetch = function(input, init = {}) {
        try {
            if (typeof input === 'string' && input.includes('/wp-json/ppv/v1/')) {
                init.headers = init.headers || {};
                if (window.ppvAuthToken)
                    init.headers['Authorization'] = 'Bearer ' + window.ppvAuthToken;
            }
        } catch(e){}
        return oldFetch(input, init);
    };
");

    }
    
}

PPV_Bridge::hooks();

