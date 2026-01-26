<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Multi-Language Handler
 * Version: 4.0 - Browser Detection + Redirect Support
 *
 * Priority:
 * 1. REST header (X-PPV-Lang) - for API calls
 * 2. GET param (?lang=ro) - from redirect or manual switch
 * 3. Cookie (ppv_lang)
 * 4. Session
 * 5. Browser Accept-Language
 * 6. Default: Romanian
 */

class PPV_Lang {

    public static $strings = [];
    public static $active  = 'ro';

    /** ============================================================
     *  🔹 Init
     * ============================================================ */
    public static function hooks() {
        add_action('init', [__CLASS__, 'detect'], 1);
    }

    /** ============================================================
     *  🔹 Detect active language
     * ============================================================ */
    public static function detect() {

        // --- Start safe session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // 🧠 LITESPEED / CLOUDFLARE REST HEADER FIX
        foreach (getallheaders() as $hkey => $hval) {
            if (strtolower($hkey) === 'x-ppv-lang') {
                $_SERVER['HTTP_X_PPV_LANG'] = $hval;
                break;
            }
        }

        $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? 'punktepass.de');
        $secure = !empty($_SERVER['HTTPS']);

        $lang = null;

        // 1️⃣ REST header (API calls)
        if (!empty($_SERVER['HTTP_X_PPV_LANG'])) {
            $rest_lang = strtolower(sanitize_text_field($_SERVER['HTTP_X_PPV_LANG']));
            if (in_array($rest_lang, ['de','hu','ro'], true)) {
                self::$active = $rest_lang;
                $_COOKIE['ppv_lang'] = $rest_lang;
                $_SESSION['ppv_lang'] = $rest_lang;
                ppv_log("🌍 [PPV_Lang] REST header → {$rest_lang}");
                self::load($rest_lang);
                return;
            }
        }

        // 2️⃣ GET param - ?lang=ro (from redirect or language switcher)
        // Also handle ?ppv_lang= and ?ppv_js_lang=
        $get_lang = $_GET['lang'] ?? $_GET['ppv_lang'] ?? $_GET['ppv_js_lang'] ?? null;
        if ($get_lang) {
            $get_lang = strtolower(sanitize_text_field($get_lang));
            if (in_array($get_lang, ['de', 'hu', 'ro'], true)) {
                $lang = $get_lang;
                $_SESSION['ppv_lang'] = $lang;
                self::set_cookie_all($lang, $domain, $secure);
                ppv_log("🌍 [PPV_Lang] GET param → {$lang}");
            }
        }

        // 3️⃣ Cookie
        if (!$lang && !empty($_COOKIE['ppv_lang'])) {
            $cookie_lang = strtolower($_COOKIE['ppv_lang']);
            if (in_array($cookie_lang, ['de', 'hu', 'ro'], true)) {
                $lang = $cookie_lang;
                ppv_log("🌍 [PPV_Lang] Cookie → {$lang}");
            }
        }

        // 4️⃣ Session
        if (!$lang && !empty($_SESSION['ppv_lang'])) {
            $session_lang = strtolower($_SESSION['ppv_lang']);
            if (in_array($session_lang, ['de', 'hu', 'ro'], true)) {
                $lang = $session_lang;
                ppv_log("🌍 [PPV_Lang] Session → {$lang}");
            }
        }

        // 5️⃣ Browser Accept-Language detection
        if (!$lang) {
            $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            if ($accept) {
                // Check for German (de, de-DE, de-AT, de-CH)
                if (preg_match('/\bde\b/i', $accept)) {
                    $lang = 'de';
                    ppv_log("🌍 [PPV_Lang] Browser detection → de");
                }
                // Check for Hungarian (hu, hu-HU)
                elseif (preg_match('/\bhu\b/i', $accept)) {
                    $lang = 'hu';
                    ppv_log("🌍 [PPV_Lang] Browser detection → hu");
                }
                // Check for Romanian (ro, ro-RO)
                elseif (preg_match('/\bro\b/i', $accept)) {
                    $lang = 'ro';
                    ppv_log("🌍 [PPV_Lang] Browser detection → ro");
                }
            }
        }

        // 6️⃣ Default: Romanian
        if (!$lang) {
            $lang = 'ro';
            ppv_log("🌍 [PPV_Lang] Default → ro");
        }

        // Set cookie if not already set (for subsequent requests)
        if (empty($_COOKIE['ppv_lang'])) {
            self::set_cookie_all($lang, $domain, $secure);
        }

        self::$active = $lang;
        ppv_log("🧠 [PPV_Lang::FINAL] Active={$lang}");

        self::load($lang);
    }

    /** ============================================================
     *  🔹 Set cookie
     * ============================================================ */
    private static function set_cookie_all($lang, $domain, $secure) {
        @setcookie('ppv_lang', $lang, time() + 31536000, '/', '', $secure, false);
        $_COOKIE['ppv_lang'] = $lang;
    }

    /** ============================================================
     *  🔹 Load language file
     * ============================================================ */
    public static function load($lang) {
        $path = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        $fallback = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-ro.php"; // Romanian fallback

        if (!file_exists($path)) {
            $path = $fallback;
            $lang = 'ro';
        }

        $data = include $path;

        if (is_array($data)) {
            self::$strings = $data;
        } else {
            $strings = [];
            include $path;
            if (isset($strings) && is_array($strings)) {
                self::$strings = $strings;
            } else {
                self::$strings = [];
                ppv_log("⚠️ [PPV_Lang] No valid strings in {$path}");
            }
        }

        self::$active = $lang;
    }

    /** ============================================================
     *  🔹 Translate helper
     * ============================================================ */
    public static function t($key) {
        return self::$strings[$key] ?? $key;
    }

    /** ============================================================
     *  🔹 Get active language
     * ============================================================ */
    public static function current() {
        return self::$active ?? 'ro';
    }
}

PPV_Lang::hooks();
