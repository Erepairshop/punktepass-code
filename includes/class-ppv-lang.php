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
 * 6. Default: English
 */

class PPV_Lang {

    public static $strings = [];
    public static $active  = 'en';

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
            ppv_maybe_start_session();
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
            if (in_array($rest_lang, ['de','en','hu','ro','it'], true)) {
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
            if (in_array($get_lang, ['de', 'en', 'hu', 'ro', 'it'], true)) {
                $lang = $get_lang;
                $_SESSION['ppv_lang'] = $lang;
                self::set_cookie_all($lang, $domain, $secure);
                ppv_log("🌍 [PPV_Lang] GET param → {$lang}");
            }
        }

        // 3️⃣ Cookie
        if (!$lang && !empty($_COOKIE['ppv_lang'])) {
            $cookie_lang = strtolower($_COOKIE['ppv_lang']);
            if (in_array($cookie_lang, ['de', 'en', 'hu', 'ro', 'it'], true)) {
                $lang = $cookie_lang;
                ppv_log("🌍 [PPV_Lang] Cookie → {$lang}");
            }
        }

        // 4️⃣ Session
        if (!$lang && !empty($_SESSION['ppv_lang'])) {
            $session_lang = strtolower($_SESSION['ppv_lang']);
            if (in_array($session_lang, ['de', 'en', 'hu', 'ro', 'it'], true)) {
                $lang = $session_lang;
                ppv_log("🌍 [PPV_Lang] Session → {$lang}");
            }
        }

        // 5️⃣ Browser Accept-Language detection (respects priority/q-values)
        if (!$lang) {
            $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
            if ($accept) {
                $supported = ['de', 'hu', 'ro', 'en', 'it'];
                $detected = self::parse_accept_language($accept, $supported);
                if ($detected) {
                    $lang = $detected;
                    ppv_log("🌍 [PPV_Lang] Browser detection → {$lang}");
                }
            }
        }

        // 6️⃣ Default: English
        if (!$lang) {
            $lang = 'en';
            ppv_log("🌍 [PPV_Lang] Default → en");
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
     *  🔹 Parse Accept-Language header with priority (q-values)
     *  Example: "hu-HU,hu;q=0.9,de;q=0.8,en;q=0.7"
     *  Returns the highest priority supported language or null
     * ============================================================ */
    private static function parse_accept_language($header, $supported) {
        $langs = [];

        // Parse header into array with priorities
        foreach (explode(',', $header) as $part) {
            $part = trim($part);
            if (empty($part)) continue;

            // Extract language and q-value
            if (preg_match('/^([a-z]{2})(?:-[A-Za-z]{2})?(?:;q=([0-9.]+))?$/i', $part, $m)) {
                $code = strtolower($m[1]);
                $q = isset($m[2]) ? floatval($m[2]) : 1.0;

                // Only track if supported and higher priority than existing
                if (in_array($code, $supported, true)) {
                    if (!isset($langs[$code]) || $langs[$code] < $q) {
                        $langs[$code] = $q;
                    }
                }
            }
        }

        if (empty($langs)) {
            return null;
        }

        // Sort by priority (descending) and return highest
        arsort($langs);
        return array_key_first($langs);
    }

    /** ============================================================
     *  🔹 Load language file
     * ============================================================ */
    public static function load($lang) {
        $path = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
        $fallback = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-en.php"; // English fallback

        $used_fallback = false;
        if (!file_exists($path)) {
            $path = $fallback;
            $used_fallback = true;
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

        // Keep original $lang as active even when base file fell back to DE
        // This allows load_extra() to find module-specific translations (e.g. ppv-repair-lang-en.php)
        self::$active = $lang;
    }

    /** ============================================================
     *  🔹 Translate helper
     * ============================================================ */
    public static function t($key) {
        return self::$strings[$key] ?? $key;
    }

    /** ============================================================
     *  🔹 Load extra translation file and merge into $strings
     *  Usage: PPV_Lang::load_extra('ppv-repair-lang');
     * ============================================================ */
    public static function load_extra($prefix) {
        $lang = self::$active ?: 'en';
        $path = PPV_PLUGIN_DIR . "includes/lang/{$prefix}-{$lang}.php";

        if (!file_exists($path)) {
            // Fallback to English
            $path = PPV_PLUGIN_DIR . "includes/lang/{$prefix}-en.php";
        }

        if (file_exists($path)) {
            $data = include $path;
            if (is_array($data)) {
                self::$strings = array_merge(self::$strings, $data);
            }
        }
    }

    /** ============================================================
     *  🔹 Get active language
     * ============================================================ */
    public static function current() {
        return self::$active ?? 'en';
    }
}

PPV_Lang::hooks();

