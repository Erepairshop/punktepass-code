<?php
if (!defined('ABSPATH')) exit;

/**
 * PPV_Logout - FIXED REDIRECT VERSION
 * 
 * ✅ Redirect hiba javítva
 * ✅ Session/Cookie törlés
 * ✅ Kompakt design (header kompatibilis)
 */

class PPV_Logout {
    public static function hooks() {
        add_shortcode('ppv_logout_link', [__CLASS__, 'render_logout_link']);
        add_action('init', [__CLASS__, 'handle_logout_action'], 1); // Priority 1
    }

    /** ============================================================
     * 🔗 Kompakt logout link (header kompatibilis)
     * ============================================================ */
    public static function render_logout_link() {
        $url = site_url('/?ppv_logout=1');
        
        return sprintf(
            '<a href="%s" class="ppv-logout-btn ppv-nav-btn ppv-btn-logout" title="Abmelden">
                <i class="ri-logout-box-line"></i>
                <span class="ppv-nav-text">Abmelden</span>
            </a>',
            esc_url($url)
        );
    }

    /** ============================================================
     * 🚪 Logout handler - FIXED REDIRECT
     * ============================================================ */
    public static function handle_logout_action() {
        // Only run if logout param present
        if (!isset($_GET['ppv_logout']) || $_GET['ppv_logout'] != '1') {
            return;
        }

        // ✅ CRITICAL: No output before redirect!
        if (headers_sent($file, $line)) {
            ppv_log("❌ [PPV_Logout] Headers already sent in {$file}:{$line}");
            // Try JavaScript redirect as fallback
            echo '<script>window.location.href="' . home_url('/login') . '";</script>';
            exit;
        }

        // Get domain
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $domain = str_replace('www.', '', $host);
        
        ppv_log("🚪 [PPV_Logout] Starting logout - Domain: {$domain}");

        // 0️⃣ CRITICAL: Clear login_token in database FIRST!
        global $wpdb;
        $user_token = $_COOKIE['ppv_user_token'] ?? '';
        $user_id = $_SESSION['ppv_user_id'] ?? 0;

        if (!empty($user_token)) {
            $wpdb->update(
                $wpdb->prefix . 'ppv_users',
                ['login_token' => null],
                ['login_token' => $user_token],
                ['%s'],
                ['%s']
            );
            ppv_log("✅ [PPV_Logout] Cleared login_token from database (by token)");
        }

        if (!empty($user_id)) {
            $wpdb->update(
                $wpdb->prefix . 'ppv_users',
                ['login_token' => null],
                ['id' => intval($user_id)],
                ['%s'],
                ['%d']
            );
            ppv_log("✅ [PPV_Logout] Cleared login_token from database (by user_id={$user_id})");
        }

        // 1️⃣ WP logout
        if (is_user_logged_in()) {
            wp_logout();
        }

        // 2️⃣ Session destroy - COMPLETE destruction
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Clear all session data
        $_SESSION = [];

        // Get session cookie params BEFORE destroying
        $params = session_get_cookie_params();

        // Destroy the session
        session_destroy();

        // ✅ CRITICAL: Delete session cookie with SAME parameters it was created with
        setcookie(
            session_name(),           // PHPSESSID or custom name
            '',                       // Empty value
            time() - 42000,          // Expired
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );

        // Also try with SameSite (PHP 7.3+)
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax'
            ]);
        }

        ppv_log("✅ [PPV_Logout] Session completely destroyed, cookie deleted");

        // 3️⃣ Cookie cleanup
        $cookie_names = [
            'ppv_pos_token',
            'ppv_user_token',
            'ppv_user_id',
            'ppv_store_id',
            'ppv_lang',
            'ppv_theme',
            'ppv_handler_theme',
            'ppv_active_store',
            'PHPSESSID'
        ];

        $paths = ['/', '/mein-profil', '/dashboard', '/admin', '/user_dashboard', '/qr-center'];
        
        $domains = [
            $host,
            $domain,
            '.' . $domain,
            '.' . $host,
            '', // Empty domain for fallback
        ];

        foreach ($cookie_names as $name) {
            foreach ($paths as $path) {
                foreach ($domains as $d) {
                    setcookie($name, '', time() - 3600, $path, $d, !empty($_SERVER['HTTPS']), true);
                }
            }
        }

        ppv_log("✅ [PPV_Logout] Cookies cleared");

        // 4️⃣ SAFE Redirect (NO wp_safe_redirect - causes issues!)
        $redirect_url = home_url('/login');
        
        // Clean any existing output buffers
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        
        // Set headers manually
        header('Cache-Control: no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: Wed, 11 Jan 1984 05:00:00 GMT');
        header('Location: ' . $redirect_url, true, 302);
        
        ppv_log("✅ [PPV_Logout] Redirecting to: {$redirect_url}");
        
        exit;
    }
}

PPV_Logout::hooks();