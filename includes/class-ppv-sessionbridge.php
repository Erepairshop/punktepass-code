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
     * ✅ MULTI-DEVICE SESSION TRACKING
     * ============================================================ */
    public static function restore_from_token() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        error_log("🔍 [SessionBridge] restore_from_token() called");
        error_log("🔍 [SessionBridge] Current session_id: " . (session_status() === PHP_SESSION_ACTIVE ? session_id() : 'NO SESSION'));
        error_log("🔍 [SessionBridge] ppv_vendor_store_id in session: " . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));
        error_log("🔍 [SessionBridge] ppv_user_id in session: " . ($_SESSION['ppv_user_id'] ?? 'EMPTY'));

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

        // 🆕 USER TOKEN RESTORE (Normal users!) - MULTI-DEVICE
        $user_token = $_COOKIE['ppv_user_token'] ?? '';
        error_log("🔍 [SessionBridge] ppv_user_token cookie: " . ($user_token ? 'EXISTS (len=' . strlen($user_token) . ', value=' . substr($user_token, 0, 20) . '...)' : 'MISSING'));

        if (!empty($user_token) && empty($_SESSION['ppv_user_id'])) {
            error_log("🔍 [SessionBridge] Querying database for token: " . substr($user_token, 0, 20) . "...");

            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_users WHERE login_token=%s AND active=1 LIMIT 1",
                $user_token
            ));

            error_log("🔍 [SessionBridge] Database query result: " . ($user ? "FOUND user ID={$user->id}" : "NO USER FOUND"));
            error_log("🔍 [SessionBridge] Last SQL error: " . ($wpdb->last_error ?: 'none'));

            if ($user) {
                // ✅ MULTI-DEVICE: Restore device-specific session
                $session_data = json_decode($user->session_data, true) ?: [];
                $device_fingerprint = self::get_device_fingerprint();

                error_log("🔍 [SessionBridge] Device fingerprint: " . $device_fingerprint);
                error_log("🔍 [SessionBridge] Stored sessions count: " . count($session_data));

                // Check if this device has a stored session
                if (isset($session_data[$device_fingerprint])) {
                    $stored_session = $session_data[$device_fingerprint];
                    $session_age = time() - ($stored_session['last_activity'] ?? 0);

                    error_log("🔍 [SessionBridge] Found session for this device, age: {$session_age}s");

                    // If session is not too old (30 days), use stored session ID
                    if ($session_age < 2592000) { // 30 days
                        error_log("✅ [SessionBridge] Restoring session ID: " . substr($stored_session['session_id'], 0, 20) . "...");
                        // Session ID is already set by PHP, we just restore the data
                    } else {
                        error_log("⚠️ [SessionBridge] Session expired, creating new");
                        unset($session_data[$device_fingerprint]);
                    }
                } else {
                    error_log("🆕 [SessionBridge] New device detected, creating new session");
                }

                // Restore user data to session
                $_SESSION['ppv_user_id'] = $user->id;
                $_SESSION['ppv_user_type'] = $user->user_type ?? 'user';
                $_SESSION['ppv_user_email'] = $user->email;

                // Vendor user esetén restore store is
                if ($user->user_type === 'vendor' && !empty($user->vendor_store_id)) {
                    $_SESSION['ppv_vendor_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_store_id'] = $user->vendor_store_id;
                    $_SESSION['ppv_active_store'] = $user->vendor_store_id;
                }

                // Save current session info to database
                self::save_session_data($user->id, $device_fingerprint);

                error_log("✅ [PPV_SessionBridge] User restored from token: ID={$user->id}, type=" . ($user->user_type ?? 'user'));
                return;
            } else {
                // ✅ FIX: Invalid token - töröljük a cookie-t hogy ne próbálkozzon újra
                error_log("⚠️ [PPV_SessionBridge] Invalid user token (deleted or expired) - removing cookie");
                self::clear_cookie('ppv_user_token');
                unset($_COOKIE['ppv_user_token']);
            }
        } else {
            error_log("🔍 [SessionBridge] Skipping token restore: user_token=" . ($user_token ? 'exists' : 'missing') . ", ppv_user_id=" . ($_SESSION['ppv_user_id'] ?? 'empty'));
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

    /** ============================================================
     * 🆕 MULTI-DEVICE HELPER FUNCTIONS
     * ============================================================ */

    /**
     * Get device fingerprint (browser + OS + IP combination)
     * This creates a semi-unique identifier for each device/browser
     */
    public static function get_device_fingerprint() {
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        // Create fingerprint from UA + IP (first 3 octets for privacy)
        $ip_parts = explode('.', $ip);
        $ip_prefix = implode('.', array_slice($ip_parts, 0, 3));

        return md5($user_agent . '|' . $ip_prefix);
    }

    /**
     * Save current session data to database
     */
    public static function save_session_data($user_id, $device_fingerprint) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get existing session data
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT session_data FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
            $user_id
        ));

        $session_data = json_decode($user->session_data ?? '{}', true) ?: [];

        // Add/update current device session
        $session_data[$device_fingerprint] = [
            'session_id' => session_id(),
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? 'unknown', 0, 255),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
            'last_activity' => time(),
            'created_at' => $session_data[$device_fingerprint]['created_at'] ?? time()
        ];

        // Cleanup expired sessions (older than 30 days)
        foreach ($session_data as $fingerprint => $data) {
            if ((time() - ($data['last_activity'] ?? 0)) > 2592000) {
                unset($session_data[$fingerprint]);
                error_log("🗑️ [SessionBridge] Cleaned up expired session: " . $fingerprint);
            }
        }

        // Save to database
        $updated = $wpdb->update(
            $prefix . 'ppv_users',
            ['session_data' => wp_json_encode($session_data)],
            ['id' => $user_id],
            ['%s'],
            ['%d']
        );

        if ($updated !== false) {
            error_log("✅ [SessionBridge] Session data saved for user {$user_id}, device {$device_fingerprint}");
        } else {
            error_log("❌ [SessionBridge] Failed to save session data: " . $wpdb->last_error);
        }

        return $updated !== false;
    }

    /**
     * Clear cookie with proper domain settings
     */
    public static function clear_cookie($name) {
        $domains = ['', '.punktepass.de', 'punktepass.de'];

        foreach ($domains as $domain) {
            setcookie($name, '', time() - 3600, '/', $domain, true, true);
        }

        error_log("🗑️ [SessionBridge] Cleared cookie: {$name}");
    }

    /**
     * Cleanup all expired sessions for a user
     */
    public static function cleanup_expired_sessions($user_id) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT session_data FROM {$prefix}ppv_users WHERE id=%d LIMIT 1",
            $user_id
        ));

        $session_data = json_decode($user->session_data ?? '{}', true) ?: [];
        $cleaned = 0;

        foreach ($session_data as $fingerprint => $data) {
            if ((time() - ($data['last_activity'] ?? 0)) > 2592000) {
                unset($session_data[$fingerprint]);
                $cleaned++;
            }
        }

        if ($cleaned > 0) {
            $wpdb->update(
                $prefix . 'ppv_users',
                ['session_data' => wp_json_encode($session_data)],
                ['id' => $user_id],
                ['%s'],
                ['%d']
            );
            error_log("🗑️ [SessionBridge] Cleaned up {$cleaned} expired sessions for user {$user_id}");
        }

        return $cleaned;
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
