<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Session Handler v3.8
 * ✅ POS Priority (ppv_pos_token elsőbbség)
 * ✅ Multi-Filiale / GET fallback / Admin Preview
 * ✅ Stabil Store Sync + Session Restore
 * ✅ Auto-mode support (init + REST API)
 * ✅ FIXED: Undefined array key "ppv_user_id" at line 267
 * Author: PunktePass (Erik Borota)
 */

class PPV_Session {

    /** =============================
     *  Aktív Store lekérése
     * ============================= */
    public static function current_store($fallback_user_id = 0) {
        global $wpdb;

        // 🔹 REST kompatibilitás – ne módosítson session-t REST alatt
        $is_rest = false;
        if (function_exists('rest_get_url_prefix')) {
            $prefix = rest_get_url_prefix();
            if (strpos($_SERVER['REQUEST_URI'] ?? '', "/$prefix/") !== false) {
                $is_rest = true;
            }
        }
        $GLOBALS['ppv_is_rest'] = $is_rest;

        // 🔹 0️⃣ Token Priority – minden más elé (cookie / header)
        $token = $_SERVER['HTTP_PPV_POS_TOKEN'] ?? ($_COOKIE['ppv_pos_token'] ?? '');
        if (!empty($token)) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores 
                WHERE pos_token=%s OR store_key=%s LIMIT 1",
                $token, $token
            ));
            if ($store) {
                $_SESSION['ppv_active_store'] = $store->id;
                $_SESSION['ppv_is_pos'] = true;
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_active_store_id'] = $store->id;
                $GLOBALS['ppv_active_token'] = $token;
                $GLOBALS['ppv_is_pos'] = true;
                error_log("✅ [PPV_Session] Token priority active → Store={$store->id}");
                return $store;
            }
        }

        // 🔹 Device Key felismerés (POS eszköz)
        $device_key = $_SERVER['HTTP_PPV_DEVICE_KEY'] ?? ($_COOKIE['ppv_device_key'] ?? '');
        if (!empty($device_key)) {
            $device = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_pos_devices WHERE device_key=%s LIMIT 1",
                $device_key
            ));
            if ($device) {
                $GLOBALS['ppv_active_device'] = $device;
                $GLOBALS['ppv_active_device_key'] = $device->device_key;
                error_log("📱 [PPV_Session] Active device detected → {$device->device_name}");
            }
        }

        // 1️⃣ GET paraméter – admin preview (?store_id= / ?store_key=)
        // 🔹 Admin Preview mód (nem módosít session-t)
        if (current_user_can('manage_options') && !empty($_GET['store_preview'])) {
            $preview = sanitize_text_field($_GET['store_preview']);
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores 
                WHERE id=%d OR store_key=%s LIMIT 1",
                intval($preview), $preview
            ));
            if ($store) {
                $GLOBALS['ppv_preview_store'] = $store;
                error_log("👁️ [PPV_Session] Admin preview store={$store->id}");
                return $store;
            }
        }

        if (!empty($_GET['store_id'])) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1",
                intval($_GET['store_id'])
            ));
            if ($store) {
                $_SESSION['ppv_active_store'] = $store->id;
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_is_pos'] = false;
                return $store;
            }
        }

        if (!empty($_GET['store_key'])) {
            $key = sanitize_text_field($_GET['store_key']);
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_key=%s LIMIT 1",
                $key
            ));
            if ($store) {
                $_SESSION['ppv_active_store'] = $store->id;
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_is_pos'] = false;
                return $store;
            }
        }

        // 2️⃣ POS session (ha már aktív)
        // 🔹 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST (overrides ppv_active_store)
        if (!empty($_SESSION['ppv_is_pos'])) {
            $store_id = null;
            if (!empty($_SESSION['ppv_current_filiale_id'])) {
                $store_id = intval($_SESSION['ppv_current_filiale_id']);
            } elseif (!empty($_SESSION['ppv_active_store'])) {
                $store_id = intval($_SESSION['ppv_active_store']);
            }

            if ($store_id) {
                $store = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1", $store_id));
                if ($store) {
                    $GLOBALS['ppv_active_store'] = $store;
                    $GLOBALS['ppv_is_pos'] = true;
                    return $store;
                }
            }
        }

        // 3️⃣ Handler session (ppv_store_id vagy ppv_active_store - trial + active handlers)
        // 🔹 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST (overrides ppv_store_id)
        $store_id = null;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id = intval($_SESSION['ppv_store_id']);
        }

        if ($store_id) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1", $store_id));
            if ($store) {
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_active_store_id'] = $store->id;
                $GLOBALS['ppv_is_pos'] = false;
                return $store;
            }
        }

        // 🔹 FILIALE SUPPORT: ppv_active_store fallback (also checks ppv_current_filiale_id first)
        $store_id = null;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_active_store'])) {
            $store_id = intval($_SESSION['ppv_active_store']);
        }

        if ($store_id) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1", $store_id));
            if ($store) {
                $GLOBALS['ppv_active_store'] = $store;
                $GLOBALS['ppv_active_store_id'] = $store->id;
                $GLOBALS['ppv_is_pos'] = false;
                return $store;
            }
        }

        // 4️⃣ REST-Auth token → user → store
        if (class_exists('PPV_Auth')) {
            $req = new WP_REST_Request('GET', '/punktepass/v1/auth/check');
            $user_id = PPV_Auth::get_user_from_token($req);
            if ($user_id > 0) {
                $store = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                    $user_id
                ));
                if ($store) {
                    $GLOBALS['ppv_active_store'] = $store;
                    return $store;
                }
            }
        }

        // 🔹 Eszköz globális mentése, ha elérhető
        if (!empty($GLOBALS['ppv_active_device']) && empty($_COOKIE['ppv_device_key'])) {
            setcookie('ppv_device_key', $GLOBALS['ppv_active_device']->device_key, time() + 86400 * 30, '/');
        }

        // 5️⃣ WP login fallback
        $user_id = get_current_user_id() ?: intval($fallback_user_id);
        if ($user_id > 0) {
            $store = $wpdb->get_row($wpdb->prepare("
                SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $user_id
            ));
            if ($store) {
                $GLOBALS['ppv_active_store'] = $store;
                return $store;
            }
        }

        // 6️⃣ Admin fallback
        if (current_user_can('manage_options')) {
            return $wpdb->get_row("SELECT * FROM {$wpdb->prefix}ppv_stores ORDER BY id ASC LIMIT 1");
        }

        // 🔹 Globális változók frissítése, ha eddig nem voltak beállítva
        if (!empty($GLOBALS['ppv_active_store']) && empty($GLOBALS['ppv_active_store_id'])) {
            $GLOBALS['ppv_active_store_id'] = intval($GLOBALS['ppv_active_store']->id ?? 0);
        }
        if (empty($GLOBALS['ppv_active_token']) && !empty($_COOKIE['ppv_pos_token'])) {
            $GLOBALS['ppv_active_token'] = sanitize_text_field($_COOKIE['ppv_pos_token']);
        }

        return null;
    }

    /** =============================
     *  Helper: POS státusz
     * ============================= */
    public static function is_pos() {
        if (!empty($GLOBALS['ppv_is_pos'])) return true;
        if (!empty($_SESSION['ppv_is_pos'])) return true;
        if (!empty($_COOKIE['ppv_pos_token'])) return true;
        return false;
    }

    /** =============================
     *  Helper: store ID
     * ============================= */
    public static function store_id() {
        $store = self::current_store();
        return $store ? intval($store->id) : 0;
    }

    /** =============================
     *  Helper: store key
     * ============================= */
    public static function store_key() {
        $store = self::current_store();
        return $store ? sanitize_text_field($store->store_key) : '';
    }

    /** =============================
     *  Helper: POS token vagy store_key lookup
     * ============================= */
    public static function get_store_by_key($key) {
        global $wpdb;
        if (empty($key)) return null;
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores 
            WHERE pos_token=%s OR store_key=%s LIMIT 1",
            $key, $key
        ));
        if ($store) {
            error_log("🧩 [PPV_Session::get_store_by_key] Store found → ID={$store->id}");
        } else {
            error_log("⚠️ [PPV_Session::get_store_by_key] Not found for {$key}");
        }
        return $store;
    }

    /** =============================
     *  Helper: store váltás (Multi-Filiale)
     * ============================= */
    public static function switch_store($store_id) {
        if (empty($store_id)) return false;
        $_SESSION['ppv_active_store'] = intval($store_id);
        error_log("🔁 [PPV_Session] switched store → {$store_id}");
        return true;
    }

    /** =============================
     *  POS logout
     * ============================= */
    public static function logout_pos() {
        if (isset($_COOKIE['ppv_pos_token'])) {
            setcookie('ppv_pos_token', '', time() - 3600, '/');
            unset($_COOKIE['ppv_pos_token']);
        }
        unset($_SESSION['ppv_active_store'], $_SESSION['ppv_is_pos']);
        $GLOBALS['ppv_active_store'] = null;
        $GLOBALS['ppv_is_pos'] = false;

        if (isset($_COOKIE['ppv_device_key'])) {
            setcookie('ppv_device_key', '', time() - 3600, '/');
            unset($_COOKIE['ppv_device_key']);
        }
        unset($GLOBALS['ppv_active_device'], $GLOBALS['ppv_active_device_key']);
    }

    /** =============================
     *  🔄 AUTO-MODE: Auto-detect & switch (ANY USER)
     * ============================= */
    public static function auto_detect_and_switch() {
        if (is_admin()) {
            return;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';

        // ✅ IF REST API OR AJAX + vendor_store_id exists → ALWAYS HANDLER MODE!
        if ((strpos($uri, '/wp-json/ppv/v1/') !== false ||
             strpos($uri, '/wp-json/punktepass/v1/') !== false ||
             strpos($uri, '/wp-admin/admin-ajax.php') !== false) &&
            !empty($_SESSION['ppv_vendor_store_id'])) {

            $_SESSION['ppv_user_type'] = 'store';

            // 🏪 FILIALE SUPPORT: Don't overwrite if active filiale exists!
            if (empty($_SESSION['ppv_current_filiale_id'])) {
                $_SESSION['ppv_store_id'] = intval($_SESSION['ppv_vendor_store_id']);
                $_SESSION['ppv_active_store'] = intval($_SESSION['ppv_vendor_store_id']);
                error_log("🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (store={$_SESSION['ppv_vendor_store_id']})");
            } else {
                error_log("🔄 [AutoMode] REST/AJAX + VENDOR → type=store, POS ON (FILIALE ACTIVE: {$_SESSION['ppv_current_filiale_id']})");
            }

            $_SESSION['ppv_is_pos'] = true;
            return;
        }

        $is_handler_page = self::is_handler_page($uri);
        $is_user_page = self::is_user_page($uri);

        // 🎯 USER PAGE → MINDIG kikapcsol POS (ANY USER!)
        if ($is_user_page) {
            $_SESSION['ppv_user_type'] = 'user';
            $_SESSION['ppv_store_id'] = 0;
            $_SESSION['ppv_active_store'] = 0;
            $_SESSION['ppv_is_pos'] = false;

            // ✅ FIXED: Safe user_id access
            $uid = $_SESSION['ppv_user_id'] ?? 0;
            error_log("🔄 [AutoMode] USER PAGE → type=user, NO POS (user_id={$uid})");
            return;
        }

        // 🎯 HANDLER PAGE – csak vendor user-nek!
        if ($is_handler_page && !empty($_SESSION['ppv_vendor_store_id'])) {
            $_SESSION['ppv_user_type'] = 'store';

            // 🏪 FILIALE SUPPORT: Don't overwrite if active filiale exists!
            if (empty($_SESSION['ppv_current_filiale_id'])) {
                $_SESSION['ppv_store_id'] = intval($_SESSION['ppv_vendor_store_id']);
                $_SESSION['ppv_active_store'] = intval($_SESSION['ppv_vendor_store_id']);
                error_log("🔄 [AutoMode] HANDLER PAGE → type=store, POS ON (store={$_SESSION['ppv_vendor_store_id']})");
            } else {
                error_log("🔄 [AutoMode] HANDLER PAGE → type=store, POS ON (FILIALE ACTIVE: {$_SESSION['ppv_current_filiale_id']})");
            }

            $_SESSION['ppv_is_pos'] = true;
            return;
        }
    }
    
    private static function is_handler_page($uri) {
        // Frontend handler pages
        $pages = ['/qr-center', '/pos-admin', '/kasse', '/statistics', '/statistik', '/rewards-center', '/handler', '/mein-profil', '/qr-scanner'];

        foreach ($pages as $page) {
            if (strpos($uri, $page) !== false) {
                return true;
            }
        }

        // REST API handler endpoints
        if (strpos($uri, '/wp-json/punktepass/v1/pos/') !== false) {
            return true;
        }
        if (strpos($uri, '/wp-json/ppv/v1/rewards/') !== false) {
            return true;
        }
        if (strpos($uri, '/wp-json/ppv/v1/redeem/') !== false) {
            return true;
        }

        return false;
    }
    
    private static function is_user_page($uri) {
        // 🔹 REST API user endpoints (PRIORITY!)
        if (strpos($uri, '/ppv/v1/user/') !== false) {
            return true;
        }
        if (strpos($uri, '/punktepass/v1/user/') !== false) {
            return true;
        }
        
        // 🔹 Frontend user pages
        $pages = ['/user_dashboard', '/user-dashboard', '/dashboard', '/meine-punkte', '/punkte', '/belohnungen', '/einstellungen', '/settings', '/profile'];
        foreach ($pages as $page) {
            if (stripos($uri, $page) !== false) {
                return true;
            }
        }
        return false;
    }
}

/* ============================================================
 *  Globális helper függvények
 * ============================================================ */
if (!function_exists('ppv_current_store')) {
    function ppv_current_store() { return PPV_Session::current_store(); }
}
if (!function_exists('ppv_is_pos')) {
    function ppv_is_pos() { return PPV_Session::is_pos(); }
}
if (!function_exists('ppv_store_id')) {
    function ppv_store_id() { return PPV_Session::store_id(); }
}
if (!function_exists('ppv_store_key')) {
    function ppv_store_key() { return PPV_Session::store_key(); }
}

/* ============================================================
 *  INIT – Session start + POS restore + GET fallback
 * ============================================================ */
add_action('init', function() {
    global $wpdb;

    if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
        @session_start();
    }

    // Cookie alapú helyreállítás
    if (empty($_SESSION['ppv_is_pos']) && isset($_COOKIE['ppv_pos_token'])) {
        $token = sanitize_text_field($_COOKIE['ppv_pos_token']);
        $store = PPV_Session::get_store_by_key($token);
        if ($store) {
            $_SESSION['ppv_active_store'] = intval($store->id);
            $_SESSION['ppv_is_pos'] = true;
            $GLOBALS['ppv_active_store'] = $store;
            $GLOBALS['ppv_is_pos'] = true;
            error_log("✅ [PPV_Session] POS restored via cookie | Store={$store->id}");
        }
    }

    // GET token login
    if (isset($_GET['token'])) {
        $token = sanitize_text_field($_GET['token']);
        $store = PPV_Session::get_store_by_key($token);
        if ($store) {
            setcookie('ppv_pos_token', $token, time() + 86400 * 30, '/');
            $_SESSION['ppv_active_store'] = intval($store->id);
            $_SESSION['ppv_is_pos'] = true;
            $GLOBALS['ppv_active_store'] = $store;
            $GLOBALS['ppv_is_pos'] = true;
            error_log("✅ [PPV_Session] POS login via GET token | Store={$store->id}");
        }
    }

    // Multi-Filiale: GET ?store_id preferálása
    if (isset($_GET['store_id']) && intval($_GET['store_id']) > 0) {
        PPV_Session::switch_store(intval($_GET['store_id']));
    }

    // Session szinkron
    if (!empty($_SESSION['ppv_active_store'])) {
        $store_id = intval($_SESSION['ppv_active_store']);
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id=%d LIMIT 1", $store_id));
        if ($store) {
            $GLOBALS['ppv_active_store'] = $store;
            $GLOBALS['ppv_is_pos'] = !empty($_SESSION['ppv_is_pos']);
            $_SESSION['ppv_store_id'] = $store->id;
            error_log("✅ [PPV_Session] store sync ok | ID={$store_id}");
        } else {
            error_log("⚠️ [PPV_Session] store sync fail (id={$store_id})");
        }
    }
}, 1);

/* ============================================================
 *  AUTO-MODE INIT ACTION (Priority 10 – after SessionBridge)
 * ============================================================ */
add_action('init', [PPV_Session::class, 'auto_detect_and_switch'], 10);

/* ============================================================
 *  AUTO-MODE REST ACTION (Priority 100 – very late, after routes)
 * ============================================================ */
add_action('rest_api_init', [PPV_Session::class, 'auto_detect_and_switch'], 100);