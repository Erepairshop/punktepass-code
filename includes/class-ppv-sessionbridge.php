<?php
if (!defined('ABSPATH')) exit;
/**
 * PunktePass – Global Session Bridge (POS + Händler + User)
 * Version: 1.4 – FIXED: User token restore + vendor protection
 * Author: Erik Borota / PunktePass
 */
class PPV_SessionBridge {
    public static function hooks() {
        add_action('init', [__CLASS__, 'start_session'], 1);
        add_action('init', [__CLASS__, 'restore_from_token'], 3); // Priority 3 (was 5)
    }
    
    /** ============================================================
     * 🧠 Session indítás biztonságosan - HOSSZÚ ÉLETTARTAM
     * ============================================================ */
    public static function start_session() {
        if (session_status() !== PHP_SESSION_ACTIVE && !headers_sent()) {
            // ✅ Session 30 nap élettartam (2592000 sec)
            ini_set('session.gc_maxlifetime', 2592000);  // 30 days
            ini_set('session.cookie_lifetime', 2592000); // 30 days

            // ✅ FIX: Unified domain handling (remove www prefix for consistency)
            $domain = '';
            if (!empty($_SERVER['HTTP_HOST'])) {
                $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);
            }

            // ✅ Biztonságos cookie beállítások
            session_set_cookie_params([
                'lifetime' => 2592000,  // 30 days
                'path' => '/',
                'domain' => $domain,    // ✅ FIX: Use consistent domain
                'secure' => true,       // HTTPS only
                'httponly' => true,     // No JavaScript access
                'samesite' => 'Lax'     // CSRF protection
            ]);

            ppv_maybe_start_session();
            ppv_log("✅ [PPV_SessionBridge] Session started with 30-day lifetime, domain={$domain}");
        }
    }
    
    /** ============================================================
     * 🔑 Token visszaállítás POS / Händler / User loginhoz
     * ============================================================ */
    public static function restore_from_token() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        ppv_log("🔍 [SessionBridge] restore_from_token() called");
        ppv_log("🔍 [SessionBridge] Current session_id: " . (session_status() === PHP_SESSION_ACTIVE ? session_id() : 'NO SESSION'));
        ppv_log("🔍 [SessionBridge] ppv_vendor_store_id in session: " . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));
        ppv_log("🔍 [SessionBridge] ppv_user_id in session: " . ($_SESSION['ppv_user_id'] ?? 'EMPTY'));

        // 🔒 VENDOR MODE – vendor user ID-t vissza KELL állítani!
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            // Ha nincs ppv_user_id, lookup kell az email alapján
            if (empty($_SESSION['ppv_user_id'])) {
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT id, email FROM {$prefix}ppv_stores WHERE id=%d LIMIT 1",
                    $_SESSION['ppv_vendor_store_id']
                ));

                if ($store && $store->email) {
                    $user = $wpdb->get_row($wpdb->prepare(
                        "SELECT id FROM {$prefix}ppv_users WHERE email=%s AND user_type IN ('vendor', 'store', 'handler') LIMIT 1",
                        $store->email
                    ));

                    if ($user) {
                        $_SESSION['ppv_user_id'] = $user->id;
                        ppv_log("✅ [PPV_SessionBridge] Store owner user ID restored: {$user->id}");
                    } else {
                        ppv_log("⚠️ [PPV_SessionBridge] Store owner user not found for email: {$store->email}");
                    }
                }
            }

            ppv_log("🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=" . ($_SESSION['ppv_user_id'] ?? 0));
            return;
        }

        // 🆕 USER TOKEN RESTORE (Normal users!)
        $user_token = $_COOKIE['ppv_user_token'] ?? '';
        ppv_log("🔍 [SessionBridge] ppv_user_token cookie: " . ($user_token ? 'EXISTS (len=' . strlen($user_token) . ', value=' . substr($user_token, 0, 20) . '...)' : 'MISSING'));

        if (!empty($user_token) && empty($_SESSION['ppv_user_id'])) {
            ppv_log("🔍 [SessionBridge] Querying database for token: " . substr($user_token, 0, 20) . "...");

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE login_token=%s AND active=1 LIMIT 1",
                $user_token
            ));

            ppv_log("🔍 [SessionBridge] Database query result: " . ($user ? "FOUND user ID={$user->id}" : "NO USER FOUND"));
            ppv_log("🔍 [SessionBridge] Last SQL error: " . ($wpdb->last_error ?: 'none'));

            if ($user && !empty($user->email)) {
                $_SESSION['ppv_user_id'] = $user->id;
                $_SESSION['ppv_user_email'] = $user->email;

                // ✅ FIX: Determine user_type with fallback logic
                // If user_type is NULL/empty in DB, try to detect from vendor_store_id
                $user_type = $user->user_type;
                if (empty($user_type) && !empty($user->vendor_store_id)) {
                    // User has vendor_store_id but no user_type - assume 'store'
                    $user_type = 'store';
                    ppv_log("⚠️ [PPV_SessionBridge] user_type was empty, detected as 'store' from vendor_store_id");
                }
                $_SESSION['ppv_user_type'] = $user_type ?: 'user';

                // Vendor/Store/Handler/Scanner user esetén restore store is
                if (in_array($user_type, ['vendor', 'store', 'handler', 'scanner']) && !empty($user->vendor_store_id)) {
                    $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;

                    // 🔹 FILIALE SUPPORT: Only restore if ppv_current_filiale_id is NOT set
                    // (don't overwrite active filiale selection!)
                    if (empty($_SESSION['ppv_current_filiale_id'])) {
                        $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                        $_SESSION['ppv_active_store'] = $user->vendor_store_id;
                        ppv_log("✅ [PPV_SessionBridge] Store restored for {$user_type}: store_id={$user->vendor_store_id}");
                    } else {
                        ppv_log("🔹 [PPV_SessionBridge] Filiale active (ID=" . $_SESSION['ppv_current_filiale_id'] . "), skipping store_id restore");
                    }
                }

                ppv_log("✅ [PPV_SessionBridge] User restored from token: ID={$user->id}, type={$user_type}, vendor_store_id=" . ($user->vendor_store_id ?? 'NONE'));
                return;
            } else {
                // ✅ FIX: Invalid token - töröljük a cookie-t hogy ne próbálkozzon újra
                ppv_log("⚠️ [PPV_SessionBridge] Invalid user token (deleted or expired) - removing cookie");
                // ✅ FIX: Use consistent domain for cookie deletion
                $cookie_domain = !empty($_SERVER['HTTP_HOST']) ? str_replace('www.', '', $_SERVER['HTTP_HOST']) : '';
                setcookie('ppv_user_token', '', time() - 3600, '/', $cookie_domain, true, true);
                unset($_COOKIE['ppv_user_token']);
            }
        } else {
            ppv_log("🔍 [SessionBridge] Skipping token restore: user_token=" . ($user_token ? 'exists' : 'missing') . ", ppv_user_id=" . ($_SESSION['ppv_user_id'] ?? 'empty'));
        }
        
        // 1️⃣ POS token sessionből vagy cookie-ból
        $token = $_SESSION['ppv_pos_token'] ?? ($_COOKIE['ppv_pos_token'] ?? '');
        if (empty($token)) return;
        
        // 2️⃣ Token ellenőrzés
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, created_at, expires_at FROM {$prefix}ppv_tokens WHERE token = %s LIMIT 1",
            $token
        ));
        
        // 🧩 Fallback – ha POS session már él, token nélkül is aktív
        if (!empty($_SESSION['ppv_store_id'])) {
            $GLOBALS['ppv_active_store'] = $_SESSION['ppv_store_id'];
            $GLOBALS['ppv_is_pos'] = true;
            // 🔒 Only remove user_id if it's EXACTLY the store_id AND user_type is NOT set
            // This prevents kicking out legitimate vendor users
            if (!empty($_SESSION['ppv_user_id']) &&
                $_SESSION['ppv_user_id'] == $_SESSION['ppv_store_id'] &&
                empty($_SESSION['ppv_user_type'])) {
                unset($_SESSION['ppv_user_id']);
                ppv_log("⚠️ [PPV_SessionBridge] Removed invalid user_id (was same as store_id, no user_type)");
            }
            ppv_log("✅ [PPV_SessionBridge] Fallback POS active | store={$_SESSION['ppv_store_id']}");
            return;
        }
        
        if (empty($row)) {
            ppv_log("⚠️ [PPV_SessionBridge] Token not found");
            return;
        }
        
        // 3️⃣ Lejárt token?
        if (strtotime($row->expires_at) < time()) {
            ppv_log("⚠️ [PPV_SessionBridge] Token expired");
            return;
        }
        
        // 4️⃣ Session feltöltés + globális változók
        $_SESSION['ppv_is_pos'] = true;
        $store_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_stores WHERE user_id = %d LIMIT 1",
            $row->user_id
        ));
        $_SESSION['ppv_store_id'] = $store_id;
        $GLOBALS['ppv_active_store'] = $store_id;
        $GLOBALS['ppv_is_pos'] = true;
        $GLOBALS['ppv_active_user'] = $row->user_id;
        ppv_log("✅ [PPV_SessionBridge] POS restored | user={$row->user_id}, store={$store_id}");
    }
}

/** ============================================================
 * 🔹 Helper function – unified login check
 * ============================================================ */
if (!function_exists('ppv_is_logged_in')) {
    function ppv_is_logged_in() {
        return is_user_logged_in() || !empty($_SESSION['ppv_pos_token']) || !empty($_SESSION['ppv_user_id']);
    }
}

// Aktiválás
PPV_SessionBridge::hooks();

