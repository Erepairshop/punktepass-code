<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Multi-Language Handler
 * Version: 3.9 Stable (Cookie + JS + Session Sync)
 * - Works on PWA, Dashboard, and MyPoints pages
 * - No more header conflicts
 * - Self-healing cookie overwrite
 */

class PPV_Lang {

    public static $strings = [];
    public static $active  = 'de';

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
        error_log("🧩 [PPV_Lang] Header recovered via getallheaders(): {$hval}");
        break;
    }
}

        // 🔹 REST fix – ha van HTTP_X_PPV_LANG header, az mindent felülír
if (!empty($_SERVER['HTTP_X_PPV_LANG'])) {
    $rest_lang = strtolower(sanitize_text_field($_SERVER['HTTP_X_PPV_LANG']));
    if (in_array($rest_lang, ['de','hu','ro'], true)) {
        self::$active = $rest_lang;
        $_COOKIE['ppv_lang'] = $rest_lang;
        $_SESSION['ppv_lang'] = $rest_lang;
        error_log("🌍 [PPV_Lang] REST header forced language → {$rest_lang}");
        self::load($rest_lang);
        return; // ⛔ nincs további detektálás, REST fix prioritás
    }
}



        $domain = str_replace('www.', '', $_SERVER['HTTP_HOST'] ?? 'punktepass.de');
        $secure = !empty($_SERVER['HTTPS']);

        $lang = null;

        // 1️⃣ JS-Sync param (always wins)
        if (!empty($_GET['ppv_js_lang'])) {
            $jslang = strtolower(sanitize_text_field($_GET['ppv_js_lang']));
            if (in_array($jslang, ['de', 'hu', 'ro'], true)) {
                $lang = $jslang;
                $_SESSION['ppv_lang'] = $lang;
                $_COOKIE['ppv_lang']  = $lang;
                self::set_cookie_all($lang, $domain, $secure);
                error_log("🌍 [PPV_Lang] Synced language via JS param → {$lang}");
            }
        }
        // 🔹 Universal GET alias (handle ?lang= too)
if (!$lang && !empty($_GET['lang'])) {
    $_GET['ppv_lang'] = $_GET['lang']; // unify
}


        // 2️⃣ GET param (manual switch)
        if (!$lang && !empty($_GET['ppv_lang'])) {
            $getlang = strtolower(sanitize_text_field($_GET['ppv_lang']));
            if (in_array($getlang, ['de','hu','ro'], true)) {
                $lang = $getlang;
                $_SESSION['ppv_lang'] = $lang;
                $_COOKIE['ppv_lang']  = $lang;
                self::set_cookie_all($lang, $domain, $secure);
                error_log("🌍 [PPV_Lang] Selected via GET → {$lang}");
            }
        }

        // 3️⃣ Cookie
        if (!$lang && !empty($_COOKIE['ppv_lang'])) {
            $lang = strtolower($_COOKIE['ppv_lang']);
            error_log("🌍 [PPV_Lang] Using cookie → {$lang}");
        }

        // 4️⃣ Session fallback
        if (!$lang && !empty($_SESSION['ppv_lang'])) {
            $lang = $_SESSION['ppv_lang'];
            error_log("🌍 [PPV_Lang] Using session → {$lang}");
        }

        // 5️⃣ Geo fallback
        if (!$lang) {
            $ip  = $_SERVER['REMOTE_ADDR'] ?? '';
            $geo = @json_decode(@file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode"), true);
            $cc  = strtolower($geo['countryCode'] ?? '');
            if ($cc === 'hu') $lang = 'hu';
            elseif ($cc === 'ro') $lang = 'ro';
            else $lang = 'de';
            error_log("🌍 [PPV_Lang] Geo fallback → {$lang}");
        }

        self::$active = $lang;
        error_log("🧠 [PPV_Lang::FINAL] Active={$lang} | GET=" . json_encode($_GET) . " | COOKIE=" . ($_COOKIE['ppv_lang'] ?? '-') . " | SESSION=" . ($_SESSION['ppv_lang'] ?? '-'));

        self::load($lang);
        
        error_log('🧠 [PPV_Lang REST/GET Sync] lang=' . (self::$active ?? '-') . 
          ' | GET=' . json_encode($_GET) . 
          ' | HEADER=' . ($_SERVER['HTTP_X_PPV_LANG'] ?? '-') . 
          ' | COOKIE=' . ($_COOKIE['ppv_lang'] ?? '-') . 
          ' | SESSION=' . ($_SESSION['ppv_lang'] ?? '-'));

    }

    /** ============================================================
     *  🔹 Set cookie on all variants
     * ============================================================ */
    private static function set_cookie_all($lang, $domain, $secure) {
        foreach (['', $domain, '.' . $domain] as $dom) {
            @setcookie('ppv_lang', $lang, time() + 31536000, '/', $dom, $secure, true);
        }
        $_COOKIE['ppv_lang'] = $lang;
    }

    /** ============================================================
 *  🔹 Load language file (universal)
 * ============================================================ */
public static function load($lang) {
    $path = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-{$lang}.php";
    $fallback = PPV_PLUGIN_DIR . "includes/lang/ppv-lang-de.php";

    // ha nem létezik, német fallback
    if (!file_exists($path)) {
        $path = $fallback;
        $lang = 'de';
    }

    // próbálja include-olni
    $data = include $path;

    if (is_array($data)) {
        self::$strings = $data;
    } else {
        // ha a fájl nem return-t használ, próbáljuk $strings változóból olvasni
        $strings = [];
        include $path;
        if (isset($strings) && is_array($strings)) {
            self::$strings = $strings;
        } else {
            self::$strings = [];
            error_log("⚠️ [PPV_Lang] No valid strings in {$path}");
        }
    }

    self::$active = $lang;
    error_log("🧠 [PPV_Lang] Loaded " . count(self::$strings) . " keys for '{$lang}' from {$path}");
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
        return self::$active ?? 'de';
        
        
    }
    
    
}

PPV_Lang::hooks();
