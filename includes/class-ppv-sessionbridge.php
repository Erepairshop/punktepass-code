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

            // ✅ Biztonságos cookie beállítások
            session_set_cookie_params([
                'lifetime' => 2592000,  // 30 days
                'path' => '/',
                'domain' => '',
                'secure' => true,       // HTTPS only
                'httponly' => true,     // No JavaScript access
                'samesite' => 'Lax'     // CSRF protection
            ]);

            @session_start();
            error_log("✅ [PPV_SessionBridge] Session started with 30-day lifetime");
        }
    }
    
    /** ============================================================
     * 🔑 Token visszaállítás POS / Händler / User loginhoz
     * ============================================================ */
    public static function restore_from_token() {
        global $wpdb;
        $prefix = $wpdb->prefix;
        
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
                        "SELECT id FROM {$prefix}ppv_users WHERE email=%s AND user_type='vendor' LIMIT 1",
                        $store->email
                    ));
                    
                    if ($user) {
                        $_SESSION['ppv_user_id'] = $user->id;
                        error_log("✅ [PPV_SessionBridge] Vendor user ID restored: {$user->id}");
                    } else {
                        error_log("⚠️ [PPV_SessionBridge] Vendor user not found for email: {$store->email}");
                    }
                }
            }
            
            error_log("🔒 [PPV_SessionBridge] VENDOR MODE active, user_id=" . ($_SESSION['ppv_user_id'] ?? 0));
            return;
        }
        
        // 🆕 USER TOKEN RESTORE (Normal users!)
        $user_token = $_COOKIE['ppv_user_token'] ?? '';
        if (!empty($user_token) && empty($_SESSION['ppv_user_id'])) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE login_token=%s AND active=1 LIMIT 1",
                $user_token
            ));

            if ($user) {
                $_SESSION['ppv_user_id'] = $user->id;
                $_SESSION['ppv_user_type'] = $user->user_type ?? 'user';
                $_SESSION['ppv_user_email'] = $user->email;

                // Vendor user esetén restore store is
                if ($user->user_type === 'vendor' && !empty($user->vendor_store_id)) {
                    $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_active_store'] = $user->vendor_store_id;
                }

                error_log("✅ [PPV_SessionBridge] User restored from token: ID={$user->id}, type=" . ($user->user_type ?? 'user'));
                return;
            } else {
                // ✅ FIX: Invalid token - töröljük a cookie-t hogy ne próbálkozzon újra
                error_log("⚠️ [PPV_SessionBridge] Invalid user token (deleted or expired) - removing cookie");
                setcookie('ppv_user_token', '', time() - 3600, '/', '', true, true);
                unset($_COOKIE['ppv_user_token']);
            }
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
                error_log("⚠️ [PPV_SessionBridge] Removed invalid user_id (was same as store_id, no user_type)");
            }
            error_log("✅ [PPV_SessionBridge] Fallback POS active | store={$_SESSION['ppv_store_id']}");
            return;
        }
        
        if (empty($row)) {
            error_log("⚠️ [PPV_SessionBridge] Token not found");
            return;
        }
        
        // 3️⃣ Lejárt token?
        if (strtotime($row->expires_at) < time()) {
            error_log("⚠️ [PPV_SessionBridge] Token expired");
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
        error_log("✅ [PPV_SessionBridge] POS restored | user={$row->user_id}, store={$store_id}");
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
