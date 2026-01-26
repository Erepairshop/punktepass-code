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
        ppv_log("🧩 [PPV_Lang] Header recovered via getallheaders(): {$hval}");
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
        ppv_log("🌍 [PPV_Lang] REST header forced language → {$rest_lang}");
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
                ppv_log("🌍 [PPV_Lang] Synced language via JS param → {$lang}");
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
                // 🔧 FIX: Set manual flag so browser detection won't override after logout
                @setcookie('ppv_lang_manual', '1', time() + 31536000, '/', '', $secure, false);
                $_COOKIE['ppv_lang_manual'] = '1';
                ppv_log("🌍 [PPV_Lang] Selected via GET → {$lang} (manual flag set)");
            }
        }

        // 3️⃣ Cookie
        if (!$lang && !empty($_COOKIE['ppv_lang'])) {
            $lang = strtolower($_COOKIE['ppv_lang']);
            ppv_log("🌍 [PPV_Lang] Using cookie → {$lang}");
        }

        // 4️⃣ Session fallback
        if (!$lang && !empty($_SESSION['ppv_lang'])) {
            $lang = $_SESSION['ppv_lang'];
            ppv_log("🌍 [PPV_Lang] Using session → {$lang}");
        }

        // 5️⃣ Domain-based language detection (for punktepass.ro, punktepass.hu)
        if (!$lang) {
            // Check if user ever manually selected a language
            $manual_selection = !empty($_COOKIE['ppv_lang_manual']);

            if ($manual_selection) {
                // User previously chose a language manually, don't use domain/browser detection
                $lang = 'ro';
                ppv_log("🌍 [PPV_Lang] Manual flag exists but no lang cookie - using default → {$lang}");
            } else {
                // Check multiple sources for original domain (handles redirects)
                $check_domains = [
                    $domain,
                    $_SERVER['HTTP_X_FORWARDED_HOST'] ?? '',
                    $_SERVER['HTTP_REFERER'] ?? '',
                    $_SERVER['HTTP_ORIGIN'] ?? ''
                ];
                $domain_lang = null;

                foreach ($check_domains as $check) {
                    if (strpos($check, 'punktepass.ro') !== false) {
                        $domain_lang = 'ro';
                        ppv_log("🌍 [PPV_Lang] Domain detection → punktepass.ro found in: {$check}");
                        break;
                    } elseif (strpos($check, 'punktepass.hu') !== false) {
                        $domain_lang = 'hu';
                        ppv_log("🌍 [PPV_Lang] Domain detection → punktepass.hu found in: {$check}");
                        break;
                    }
                }

                if ($domain_lang) {
                    $lang = $domain_lang;
                } elseif (!empty($_SESSION['ppv_browser_lang'])) {
                    // Check session cache for browser lang
                    $lang = $_SESSION['ppv_browser_lang'];
                    ppv_log("🌍 [PPV_Lang] Browser lang from session → {$lang}");
                } else {
                    // Parse Accept-Language header (e.g. "hu-HU,hu;q=0.9,de;q=0.8")
                    $accept = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
                    $lang = 'ro'; // Default Romanian

                    if ($accept) {
                        // Check for German
                        if (preg_match('/\bde\b/i', $accept)) {
                            $lang = 'de';
                        }
                        // Check for Hungarian
                        elseif (preg_match('/\bhu\b/i', $accept)) {
                            $lang = 'hu';
                        }
                    }

                    // Cache in session
                    $_SESSION['ppv_browser_lang'] = $lang;
                    ppv_log("🌍 [PPV_Lang] Browser Accept-Language → {$lang}");
                }
            }
        }

        self::$active = $lang;
        ppv_log("🧠 [PPV_Lang::FINAL] Active={$lang} | GET=" . json_encode($_GET) . " | COOKIE=" . ($_COOKIE['ppv_lang'] ?? '-') . " | SESSION=" . ($_SESSION['ppv_lang'] ?? '-'));

        self::load($lang);
        
        ppv_log('🧠 [PPV_Lang REST/GET Sync] lang=' . (self::$active ?? '-') . 
          ' | GET=' . json_encode($_GET) . 
          ' | HEADER=' . ($_SERVER['HTTP_X_PPV_LANG'] ?? '-') . 
          ' | COOKIE=' . ($_COOKIE['ppv_lang'] ?? '-') . 
          ' | SESSION=' . ($_SESSION['ppv_lang'] ?? '-'));

    }

    /** ============================================================
     *  🔹 Set cookie (single, no domain - consistent with JS)
     * ============================================================ */
    private static function set_cookie_all($lang, $domain, $secure) {
        // Only set ONE cookie without domain (consistent with JS language switcher)
        @setcookie('ppv_lang', $lang, time() + 31536000, '/', '', $secure, false);
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
            ppv_log("⚠️ [PPV_Lang] No valid strings in {$path}");
        }
    }

    self::$active = $lang;
    ppv_log("🧠 [PPV_Lang] Loaded " . count(self::$strings) . " keys for '{$lang}' from {$path}");
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
