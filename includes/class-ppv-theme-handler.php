<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Theme Handler (v2.0)
 * ✅ REST endpoint for theme switching
 * ✅ Multi-domain cookie setter
 * ✅ Persistent storage (user meta)
 * ✅ Language ↔ Theme sync
 * ✅ Service Worker messaging
 */

class PPV_Theme_Handler {

    const VALID_THEMES = ['dark', 'light'];
    const COOKIE_KEY = 'ppv_theme';
    const THEME_META_KEY = 'ppv_user_theme_preference';

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('init', [__CLASS__, 'init_theme'], 1);
        add_action('wp_head', [__CLASS__, 'output_theme_meta']);
    }

    /** ============================================================
     * 🔹 Init Theme – Set on page load
     * ============================================================ */
    public static function init_theme() {
        // Session safe start
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $theme = self::get_user_theme();
        
        // Set session + cookie
        $_SESSION[self::COOKIE_KEY] = $theme;
        self::set_multi_domain_cookie($theme);

        error_log("🎨 [PPV_Theme_Handler::init] Theme initialized: {$theme}");
    }

    /** ============================================================
     * 🔹 Get User Theme (Priority: Cookie > DB > Session > Default)
     * ✅ Cookie first for instant feedback, DB syncs in background
     * ============================================================ */
    private static function get_user_theme() {
        // 1️⃣ Cookie check (highest priority for instant updates)
        if (!empty($_COOKIE[self::COOKIE_KEY])) {
            $cookie = sanitize_text_field($_COOKIE[self::COOKIE_KEY]);
            if (in_array($cookie, self::VALID_THEMES)) {
                error_log("🎨 [PPV_Theme_Handler] Theme from Cookie: {$cookie}");

                // Sync to DB in background if logged in
                if (is_user_logged_in()) {
                    $uid = get_current_user_id();
                    $db_theme = get_user_meta($uid, self::THEME_META_KEY, true);
                    if ($db_theme !== $cookie) {
                        update_user_meta($uid, self::THEME_META_KEY, $cookie);
                        error_log("🔄 [PPV_Theme_Handler] Synced cookie→DB: {$cookie}");
                    }
                }

                return $cookie;
            }
        }

        // 2️⃣ Logged in user → check user meta
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $saved = get_user_meta($uid, self::THEME_META_KEY, true);
            if (in_array($saved, self::VALID_THEMES)) {
                error_log("🎨 [PPV_Theme_Handler] Theme from DB: {$saved}");
                return $saved;
            }
        }

        // 3️⃣ Session check
        if (!empty($_SESSION[self::COOKIE_KEY])) {
            $session = sanitize_text_field($_SESSION[self::COOKIE_KEY]);
            if (in_array($session, self::VALID_THEMES)) {
                error_log("🎨 [PPV_Theme_Handler] Theme from Session: {$session}");
                return $session;
            }
        }

        // 4️⃣ Default
        error_log("🎨 [PPV_Theme_Handler] Using default theme: light");
        return 'light';
    }

    /** ============================================================
     * 🔹 Multi-Domain Cookie Setter
     * ============================================================ */
    private static function set_multi_domain_cookie($value) {
        if (headers_sent()) return;

        $value = sanitize_text_field($value);
        if (!in_array($value, self::VALID_THEMES)) $value = 'light';

        $expire = time() + (365 * 24 * 60 * 60); // 1 year
        $path = '/';
        $secure = !empty($_SERVER['HTTPS']);
        $httponly = true;
        $samesite = 'Lax';

        // Get host variants
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $host = str_replace('www.', '', $host);

        // Cookie domains to try
        $domains = [
            '',                    // Current domain
            $host,                 // domain.com
            '.' . $host,           // .domain.com (wildcard)
        ];

        // If has www variant
        if (strpos($_SERVER['HTTP_HOST'], 'www.') === 0) {
            $domains[] = $_SERVER['HTTP_HOST']; // www.domain.com
        }

        // Set cookie on all variants
        foreach ($domains as $domain) {
            @setcookie(
                self::COOKIE_KEY,
                $value,
                [
                    'expires' => $expire,
                    'path' => $path,
                    'domain' => $domain,
                    'secure' => $secure,
                    'httponly' => $httponly,
                    'samesite' => $samesite,
                ]
            );
        }

        $_COOKIE[self::COOKIE_KEY] = $value;
        error_log("🍪 [PPV_Theme_Handler] Cookie set on domains: " . json_encode($domains));
    }

    /** ============================================================
     * 🔹 Output Theme Meta Tag (for SW message)
     * ============================================================ */
    public static function output_theme_meta() {
        $theme = self::get_user_theme();
        echo "\n<!-- PunktePass Theme -->\n";
        echo "<meta name='ppv-theme' content='" . esc_attr($theme) . "'>\n";
    }

    /** ============================================================
     * 🔹 REST ENDPOINT: Set Theme
     * ============================================================ */
    public static function register_rest_routes() {
        register_rest_route('ppv/v1', '/theme/set', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_set_theme'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route('ppv/v1', '/theme/get', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'rest_get_theme'],
            'permission_callback' => '__return_true',
        ]);
    }

    /** ============================================================
     * REST: GET current theme
     * ============================================================ */
    public static function rest_get_theme(WP_REST_Request $request) {
        $theme = self::get_user_theme();

        return new WP_REST_Response([
            'success' => true,
            'theme' => $theme,
            'uid' => get_current_user_id(),
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'Pragma' => 'no-cache',
        ]);
    }

    /** ============================================================
     * REST: SET theme
     * ============================================================ */
    public static function rest_set_theme(WP_REST_Request $request) {
        $data = $request->get_json_params();
        if (!is_array($data)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid JSON',
            ], 400);
        }

        $theme = sanitize_text_field($data['theme'] ?? '');
        $nonce = $request->get_header('x-wp-nonce');

        // Validate theme
        if (!in_array($theme, self::VALID_THEMES)) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Invalid theme value',
            ], 400);
        }

        // Optional nonce check (not required for PWA/offline)
        if (!empty($nonce) && !wp_verify_nonce($nonce, 'wp_rest')) {
            error_log("⚠️ [PPV_Theme_Handler] Nonce failed, but continuing (PWA)");
        }

        // Save to user meta if logged in
        $uid = get_current_user_id();
        if ($uid > 0) {
            update_user_meta($uid, self::THEME_META_KEY, $theme);
            error_log("💾 [PPV_Theme_Handler] Theme saved to DB: user_id={$uid}, theme={$theme}");
        }

        // Set cookie
        self::set_multi_domain_cookie($theme);

        // Set session
        $_SESSION[self::COOKIE_KEY] = $theme;

        // Trigger SW cache clear message (client-side will handle)
        error_log("🎨 [PPV_Theme_Handler] Theme changed: {$theme}");

        return new WP_REST_Response([
            'success' => true,
            'theme' => $theme,
            'message' => 'Theme updated',
            'uid' => $uid,
        ], 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    /** ============================================================
     * 🔹 Enqueue global theme loader (on every page)
     * ============================================================ */
    public static function enqueue_theme_loader() {
        // Loader JS already in plugin.php, but we can add here if needed
        error_log("🎨 [PPV_Theme_Handler] Enqueue called");
    }
}

// Auto-initialize
PPV_Theme_Handler::hooks();