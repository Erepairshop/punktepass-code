<?php
/**
 * PPV Permissions Class
 *
 * Handles authentication and authorization for REST API endpoints
 * PWA-friendly: supports long-lived sessions and token-based auth
 *
 * @package PunktePass
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

// 🔧 DEBUG FLAG - set to true only for debugging, false for production
define('PPV_PERMISSIONS_DEBUG', false);

function ppv_perm_log($msg) {
    if (PPV_PERMISSIONS_DEBUG) {
        error_log($msg);
    }
}

class PPV_Permissions {

    /**
     * Check if user is authenticated via any method
     * - Session authentication ($_SESSION['ppv_user_id'])
     * - Token authentication (Bearer, PPV-POS-Token, query param)
     * - WordPress user authentication
     *
     * PWA-friendly: Supports long-lived tokens for app usage
     *
     * @return bool|WP_Error True if authenticated, WP_Error otherwise
     */
    public static function check_authenticated() {
        ppv_perm_log("🔍 [PPV_Permissions] check_authenticated() called");

        // 0. Ensure session is started
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
            ppv_perm_log("🔍 [PPV_Permissions] Session started");
        }

        // 1. Check session authentication
        if (!empty($_SESSION['ppv_user_id'])) {
            ppv_perm_log("✅ [PPV_Permissions] Auth via SESSION: user_id=" . $_SESSION['ppv_user_id']);
            return true;
        }

        // 1b. 🏪 TRIAL HANDLER SUPPORT: Check ppv_vendor_store_id (händler trial has this set)
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            ppv_perm_log("✅ [PPV_Permissions] Auth via SESSION: vendor_store_id=" . $_SESSION['ppv_vendor_store_id']);
            return true;
        }

        ppv_perm_log("🔍 [PPV_Permissions] No session user_id, checking token restore...");

        // 1a. Try to restore session from token (Google/Facebook/TikTok login)
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            ppv_perm_log("🔄 [PPV_Permissions] Calling PPV_SessionBridge::restore_from_token()");
            PPV_SessionBridge::restore_from_token();

            // Check again after restore
            if (!empty($_SESSION['ppv_user_id'])) {
                ppv_perm_log("✅ [PPV_Permissions] Auth via SESSION RESTORE: user_id=" . $_SESSION['ppv_user_id']);
                return true;
            }
            ppv_perm_log("⚠️ [PPV_Permissions] Session restore did not populate user_id");
        }

        // 2. Check token authentication (for PWA - ppv_tokens table)
        ppv_perm_log("🔍 [PPV_Permissions] Checking token authentication...");
        $token_user = self::get_user_from_token();
        if ($token_user) {
            ppv_perm_log("✅ [PPV_Permissions] Auth via TOKEN: user_id=" . $token_user->id);
            return true;
        }

        // 3. Check WordPress authentication
        if (is_user_logged_in()) {
            $wp_user_id = get_current_user_id();
            ppv_perm_log("✅ [PPV_Permissions] Auth via WORDPRESS: user_id=" . $wp_user_id);
            return true;
        }

        ppv_perm_log("❌ [PPV_Permissions] UNAUTHORIZED - no valid authentication found");
        return new WP_Error(
            'unauthorized',
            'Bejelentkezés szükséges',
            ['status' => 401]
        );
    }

    /**
     * Check if user is a handler (store, vendor, admin)
     * Handlers can manage stores, scan QR codes, approve redemptions
     *
     * @return bool|WP_Error True if handler, WP_Error otherwise
     */
    public static function check_handler() {
        global $wpdb;
        ppv_perm_log("🔍 [PPV_Permissions] check_handler() called");

        $auth_check = self::check_authenticated();
        if (is_wp_error($auth_check)) {
            ppv_perm_log("❌ [PPV_Permissions] check_handler() FAILED: auth check failed");
            return $auth_check;
        }

        // Check if WordPress admin
        if (current_user_can('manage_options')) {
            ppv_perm_log("✅ [PPV_Permissions] check_handler() SUCCESS: WordPress admin");
            return true;
        }

        // ✅ NEW: Check if scanner user (limited access to QR Center only)
        if (self::is_scanner_user()) {
            $store_id = self::get_scanner_store_id();
            if ($store_id) {
                ppv_perm_log("✅ [PPV_Permissions] check_handler() SUCCESS: Scanner user with store_id={$store_id}");

                // Set session variables for scanner user
                $_SESSION['ppv_store_id'] = $store_id;
                $_SESSION['ppv_user_type'] = 'scanner';

                return true;
            } else {
                ppv_perm_log("❌ [PPV_Permissions] check_handler() FAILED: Scanner user has no store_id");
                return new WP_Error(
                    'scanner_no_store',
                    'Scanner Konfigurationsfehler. Bitte kontaktieren Sie Ihren Administrator.',
                    ['status' => 403]
                );
            }
        }

        // 🔧 AUTO-FIX: If we have user_id but no store_id, lookup from database
        // ⚠️ ONLY for handler types (vendor, store, handler, admin) - NOT regular users!
        if (!empty($_SESSION['ppv_user_id']) && empty($_SESSION['ppv_vendor_store_id']) && empty($_SESSION['ppv_store_id'])) {
            $ppv_user_id = intval($_SESSION['ppv_user_id']);
            ppv_perm_log("🔧 [PPV_Permissions] AUTO-FIX: Looking up store for user_id={$ppv_user_id}");

            $user_data = $wpdb->get_row($wpdb->prepare(
                "SELECT user_type, vendor_store_id FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                $ppv_user_id
            ));

            if ($user_data) {
                $_SESSION['ppv_user_type'] = $user_data->user_type;
                ppv_perm_log("🔧 [PPV_Permissions] AUTO-FIX: Found user_type={$user_data->user_type}");

                // ⚠️ Only set store for HANDLER types - not regular users!
                $handler_types_for_fix = ['vendor', 'store', 'handler', 'admin'];
                if (in_array($user_data->user_type, $handler_types_for_fix) && !empty($user_data->vendor_store_id)) {
                    $_SESSION['ppv_vendor_store_id'] = $user_data->vendor_store_id;
                    $_SESSION['ppv_store_id'] = $user_data->vendor_store_id;
                    $_SESSION['ppv_active_store'] = $user_data->vendor_store_id;
                    ppv_perm_log("🔧 [PPV_Permissions] AUTO-FIX: Set store_id={$user_data->vendor_store_id}");
                } else {
                    ppv_perm_log("🔧 [PPV_Permissions] AUTO-FIX: Skipped - user_type={$user_data->user_type} is not a handler");
                }
            }
        }

        // Check user type from session
        $user_type = $_SESSION['ppv_user_type'] ?? '';
        ppv_perm_log("🔍 [PPV_Permissions] check_handler() user_type from SESSION: " . ($user_type ?: 'EMPTY'));

        $handler_types = ['store', 'handler', 'vendor', 'admin'];

        $is_handler = false;
        $user_id_to_check = null;

        if (in_array($user_type, $handler_types)) {
            ppv_perm_log("✅ [PPV_Permissions] check_handler() user_type={$user_type} is in handler_types");
            $is_handler = true;
            $user_id_to_check = self::get_current_user_id();
        }

        // 🏪 TRIAL HANDLER SUPPORT: If ppv_vendor_store_id is set, treat as handler
        if (!$is_handler && !empty($_SESSION['ppv_vendor_store_id'])) {
            ppv_perm_log("✅ [PPV_Permissions] check_handler() TRIAL HANDLER: ppv_vendor_store_id=" . $_SESSION['ppv_vendor_store_id']);
            $is_handler = true;
            $_SESSION['ppv_user_type'] = 'vendor'; // Set default user_type for trial handlers
        }

        // Check user type from database (via token auth)
        if (!$is_handler) {
            ppv_perm_log("🔍 [PPV_Permissions] check_handler() checking database for user_type...");
            $user_data = self::get_authenticated_user_data();

            if ($user_data) {
                ppv_perm_log("🔍 [PPV_Permissions] check_handler() user_data found: user_type=" . ($user_data['user_type'] ?? 'NONE'));
                if (in_array($user_data['user_type'], $handler_types)) {
                    ppv_perm_log("✅ [PPV_Permissions] check_handler() DB user_type={$user_data['user_type']} is in handler_types");
                    $is_handler = true;
                    $user_id_to_check = $user_data['id'] ?? null;
                }
            } else {
                ppv_perm_log("⚠️ [PPV_Permissions] check_handler() no user_data from database");
            }
        }

        if (!$is_handler) {
            ppv_perm_log("❌ [PPV_Permissions] check_handler() FAILED: Nincs jogosultság");
            return new WP_Error(
                'forbidden',
                'Nincs jogosultság. Handler szerepkör szükséges.',
                ['status' => 403]
            );
        }

        // ✅ FIXED: Check subscription expiry using store_id from session (not user_id!)
        // This prevents issues with multiple stores (filialen) having the same user_id
        $store_id_to_check = 0;
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id_to_check = intval($_SESSION['ppv_current_filiale_id']);
        } elseif (!empty($_SESSION['ppv_store_id'])) {
            $store_id_to_check = intval($_SESSION['ppv_store_id']);
        } elseif (!empty($_SESSION['ppv_vendor_store_id'])) {
            $store_id_to_check = intval($_SESSION['ppv_vendor_store_id']);
        }

        if ($store_id_to_check) {
            ppv_perm_log("🔍 [PPV_Permissions] Checking subscription expiry for store_id={$store_id_to_check}");

            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT subscription_status, trial_ends_at, subscription_expires_at
                FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d
                LIMIT 1",
                $store_id_to_check
            ));

            if ($store) {
                $now = current_time('timestamp');
                $is_expired = false;

                // Check trial expiry
                if ($store->subscription_status === 'trial' && !empty($store->trial_ends_at)) {
                    $trial_end = strtotime($store->trial_ends_at);
                    if ($trial_end < $now) {
                        $is_expired = true;
                        ppv_perm_log("❌ [PPV_Permissions] TRIAL EXPIRED: trial_ends_at={$store->trial_ends_at}");
                    }
                }

                // Check active subscription expiry
                if ($store->subscription_status === 'active' && !empty($store->subscription_expires_at)) {
                    $sub_end = strtotime($store->subscription_expires_at);
                    if ($sub_end < $now) {
                        $is_expired = true;
                        ppv_perm_log("❌ [PPV_Permissions] SUBSCRIPTION EXPIRED: subscription_expires_at={$store->subscription_expires_at}");
                    }
                }

                if ($is_expired) {
                    return new WP_Error(
                        'subscription_expired',
                        'Ihr Abonnement ist abgelaufen. Bitte verlängern Sie Ihr Abo.',
                        ['status' => 403]
                    );
                }

                ppv_perm_log("✅ [PPV_Permissions] Subscription is VALID");
            }
        }

        ppv_perm_log("✅ [PPV_Permissions] check_handler() SUCCESS");
        return true;
    }

    /**
     * Check if user is admin
     *
     * @return bool|WP_Error True if admin, WP_Error otherwise
     */
    public static function check_admin() {
        $auth_check = self::check_authenticated();
        if (is_wp_error($auth_check)) {
            return $auth_check;
        }

        if (current_user_can('manage_options')) {
            return true;
        }

        return new WP_Error(
            'forbidden',
            'Admin jogosultság szükséges',
            ['status' => 403]
        );
    }

    /**
     * Check if user can access their own data
     * Used for endpoints like /mypoints, /profile
     *
     * @param int $user_id User ID to check access for
     * @return bool|WP_Error True if allowed, WP_Error otherwise
     */
    public static function check_own_data($user_id = null) {
        $auth_check = self::check_authenticated();
        if (is_wp_error($auth_check)) {
            return $auth_check;
        }

        // Admins can access any data
        if (current_user_can('manage_options')) {
            return true;
        }

        // Get current user ID
        $current_user_id = self::get_current_user_id();

        // If no user_id specified, just check if authenticated
        if ($user_id === null) {
            return $current_user_id > 0;
        }

        // Check if accessing own data
        if ($current_user_id === (int)$user_id) {
            return true;
        }

        return new WP_Error(
            'forbidden',
            'Csak a saját adataidat érheted el',
            ['status' => 403]
        );
    }

    /**
     * Get current authenticated user ID from any auth method
     *
     * @return int User ID or 0 if not authenticated
     */
    public static function get_current_user_id() {
        // 1. Check session
        if (!empty($_SESSION['ppv_user_id'])) {
            return (int)$_SESSION['ppv_user_id'];
        }

        // 2. Check token auth
        $user_data = self::get_authenticated_user_data();
        if ($user_data && !empty($user_data['id'])) {
            return (int)$user_data['id'];
        }

        // 3. Check WordPress user
        $wp_user_id = get_current_user_id();
        if ($wp_user_id > 0) {
            return $wp_user_id;
        }

        return 0;
    }

    /**
     * Get user data from token authentication
     * Checks multiple token sources (PWA-friendly)
     *
     * @return array|null User data or null if not found
     */
    private static function get_authenticated_user_data() {
        global $wpdb;

        $token = self::get_token_from_request();
        if (!$token) {
            return null;
        }

        $prefix = $wpdb->prefix;

        // 1. Try ppv_tokens table (new system - with expiry)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT u.* FROM {$prefix}ppv_users u
             INNER JOIN {$prefix}ppv_tokens t ON t.user_id = u.id
             WHERE t.token = %s
             AND t.expires_at > NOW()
             AND u.active = 1
             LIMIT 1",
            $token
        ), ARRAY_A);

        if ($user) {
            return $user;
        }

        // 2. Fallback to ppv_users.login_token (legacy - Google/Facebook/TikTok login)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_users
             WHERE login_token = %s
             AND active = 1
             LIMIT 1",
            $token
        ), ARRAY_A);

        return $user ?: null;
    }

    /**
     * Get user from token (for backward compatibility with PPV_Auth)
     *
     * @return object|null User object or null
     */
    private static function get_user_from_token() {
        $user_data = self::get_authenticated_user_data();
        return $user_data ? (object)$user_data : null;
    }

    /**
     * Extract token from request
     * Checks multiple sources for PWA compatibility
     *
     * @return string|null Token or null if not found
     */
    private static function get_token_from_request() {
        // 1. Check Authorization header (Bearer token)
        if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $auth = $_SERVER['HTTP_AUTHORIZATION'];
            if (preg_match('/Bearer\s+(.+)/i', $auth, $matches)) {
                return sanitize_text_field($matches[1]);
            }
        }

        // 2. Check PPV-POS-Token header
        if (!empty($_SERVER['HTTP_PPV_POS_TOKEN'])) {
            return sanitize_text_field($_SERVER['HTTP_PPV_POS_TOKEN']);
        }

        // 3. Check query parameter
        if (!empty($_GET['token'])) {
            return sanitize_text_field($_GET['token']);
        }

        // 4. Check POST parameter
        if (!empty($_POST['token'])) {
            return sanitize_text_field($_POST['token']);
        }

        // 5. Check cookie token
        if (!empty($_COOKIE['ppv_user_token'])) {
            return sanitize_text_field($_COOKIE['ppv_user_token']);
        }

        return null;
    }

    /**
     * Rate limiting check
     * Prevents brute force attacks
     *
     * @param string $action Action identifier (e.g., 'pos_login', 'user_login')
     * @param int $max_attempts Maximum attempts allowed
     * @param int $time_window Time window in seconds
     * @return bool|WP_Error True if allowed, WP_Error if rate limited
     */
    public static function check_rate_limit($action, $max_attempts = 5, $time_window = 900) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'ppv_rate_limit_' . md5($action . '_' . $ip);

        $attempts = get_transient($key) ?: 0;

        if ($attempts >= $max_attempts) {
            $retry_after = $time_window; // seconds
            return new WP_Error(
                'rate_limit_exceeded',
                sprintf(
                    'Túl sok próbálkozás. Próbáld újra %d perc múlva.',
                    ceil($retry_after / 60)
                ),
                [
                    'status' => 429,
                    'retry_after' => $retry_after
                ]
            );
        }

        return true;
    }

    /**
     * Increment rate limit counter
     *
     * @param string $action Action identifier
     * @param int $time_window Time window in seconds
     */
    public static function increment_rate_limit($action, $time_window = 900) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'ppv_rate_limit_' . md5($action . '_' . $ip);

        $attempts = get_transient($key) ?: 0;
        set_transient($key, $attempts + 1, $time_window);
    }

    /**
     * Reset rate limit counter (on successful auth)
     *
     * @param string $action Action identifier
     */
    public static function reset_rate_limit($action) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = 'ppv_rate_limit_' . md5($action . '_' . $ip);
        delete_transient($key);
    }

    /**
     * Check if endpoint allows anonymous access
     * Some endpoints like login, signup should be accessible without auth
     *
     * @return bool True (always allows)
     */
    public static function allow_anonymous() {
        return true;
    }

    /**
     * Check if user is logged in (PPV user or WP user)
     * Used for endpoints that require any authenticated user (not just handlers)
     *
     * @return bool|WP_Error True if logged in, WP_Error otherwise
     */
    public static function check_logged_in_user() {
        ppv_perm_log("🔍 [PPV_Permissions] check_logged_in_user() called");

        // 0. Ensure session is started
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // 1. Check session authentication (PPV user)
        if (!empty($_SESSION['ppv_user_id'])) {
            ppv_perm_log("✅ [PPV_Permissions] check_logged_in_user() SUCCESS via SESSION");
            return true;
        }

        // 2. Try to restore session from token
        if (class_exists('PPV_SessionBridge')) {
            PPV_SessionBridge::restore_from_token();
            if (!empty($_SESSION['ppv_user_id'])) {
                ppv_perm_log("✅ [PPV_Permissions] check_logged_in_user() SUCCESS via SESSION RESTORE");
                return true;
            }
        }

        // 3. Check token authentication
        $token_user = self::get_user_from_token();
        if ($token_user) {
            ppv_perm_log("✅ [PPV_Permissions] check_logged_in_user() SUCCESS via TOKEN");
            return true;
        }

        // 4. Check WordPress authentication
        if (is_user_logged_in()) {
            ppv_perm_log("✅ [PPV_Permissions] check_logged_in_user() SUCCESS via WORDPRESS");
            return true;
        }

        ppv_perm_log("❌ [PPV_Permissions] check_logged_in_user() FAILED");
        return new WP_Error(
            'unauthorized',
            'Bejelentkezés szükséges',
            ['status' => 401]
        );
    }

    /**
     * Check if current user is a scanner employee
     * Scanner users have limited access - only QR Center
     *
     * @return bool True if scanner user, false otherwise
     */
    public static function is_scanner_user() {
        global $wpdb;

        // Check session first
        if (!empty($_SESSION['ppv_user_type']) && $_SESSION['ppv_user_type'] === 'scanner') {
            return true;
        }

        // Check PPV users database via session user_id
        if (!empty($_SESSION['ppv_user_id'])) {
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT user_type FROM {$wpdb->prefix}ppv_users WHERE id = %d LIMIT 1",
                $_SESSION['ppv_user_id']
            ));

            if ($user && $user->user_type === 'scanner') {
                return true;
            }
        }

        // ✅ NEW: Check via token-based auth (REST API calls)
        $user_data = self::get_authenticated_user_data();
        if ($user_data && isset($user_data['user_type']) && $user_data['user_type'] === 'scanner') {
            // Populate session for subsequent calls
            $_SESSION['ppv_user_id'] = $user_data['id'];
            $_SESSION['ppv_user_type'] = 'scanner';
            if (!empty($user_data['vendor_store_id'])) {
                $_SESSION['ppv_store_id'] = intval($user_data['vendor_store_id']);
            }
            ppv_perm_log("✅ [PPV_Permissions] is_scanner_user() detected via TOKEN: user_id={$user_data['id']}");
            return true;
        }

        return false;
    }

    /**
     * Get store ID for scanner user
     * Scanner users are linked to a parent handler's store
     *
     * @return int|false Store ID or false if not found
     */
    public static function get_scanner_store_id() {
        global $wpdb;

        if (!self::is_scanner_user()) {
            return false;
        }

        // Check session first (should be populated by is_scanner_user())
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Get from database via session user_id
        if (!empty($_SESSION['ppv_user_id'])) {
            $scanner = $wpdb->get_row($wpdb->prepare(
                "SELECT vendor_store_id FROM {$wpdb->prefix}ppv_users WHERE id = %d AND user_type = 'scanner' LIMIT 1",
                $_SESSION['ppv_user_id']
            ));

            if ($scanner && $scanner->vendor_store_id) {
                $_SESSION['ppv_store_id'] = intval($scanner->vendor_store_id);
                return intval($scanner->vendor_store_id);
            }
        }

        // ✅ NEW: Fallback to token-based auth
        $user_data = self::get_authenticated_user_data();
        if ($user_data && $user_data['user_type'] === 'scanner' && !empty($user_data['vendor_store_id'])) {
            $_SESSION['ppv_store_id'] = intval($user_data['vendor_store_id']);
            ppv_perm_log("✅ [PPV_Permissions] get_scanner_store_id() via TOKEN: store_id={$user_data['vendor_store_id']}");
            return intval($user_data['vendor_store_id']);
        }

        return false;
    }
}
