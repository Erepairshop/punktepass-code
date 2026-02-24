<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Referral Link Handler
 * Handles /r/{code}/{store_key} URLs
 * Stores referral info in cookie and redirects to store page
 */

class PPV_Referral_Handler {

    const COOKIE_NAME = 'ppv_referral';
    const COOKIE_DAYS = 30;

    public static function hooks() {
        add_action('init', [__CLASS__, 'add_rewrite_rules'], 10);
        add_filter('query_vars', [__CLASS__, 'add_query_vars']);
        add_action('template_redirect', [__CLASS__, 'handle_referral_redirect']);
    }

    /**
     * Add rewrite rules for referral URLs
     */
    public static function add_rewrite_rules() {
        // /r/{code}/{store_key}
        add_rewrite_rule(
            '^r/([A-Za-z0-9]+)/([^/]+)/?$',
            'index.php?ppv_referral_code=$matches[1]&ppv_referral_store=$matches[2]',
            'top'
        );
    }

    /**
     * Register custom query vars
     */
    public static function add_query_vars($vars) {
        $vars[] = 'ppv_referral_code';
        $vars[] = 'ppv_referral_store';
        return $vars;
    }

    /**
     * Handle referral redirect
     */
    public static function handle_referral_redirect() {
        $code = get_query_var('ppv_referral_code');
        $store_key = get_query_var('ppv_referral_store');

        if (empty($code) || empty($store_key)) {
            return;
        }

        global $wpdb;

        // Validate referral code exists
        $referrer = $wpdb->get_row($wpdb->prepare(
            "SELECT user_id, referral_code FROM {$wpdb->prefix}ppv_user_referral_codes WHERE referral_code = %s",
            strtoupper($code)
        ));

        if (!$referrer) {
            ppv_log("⚠️ [PPV_Referral] Invalid referral code: {$code}");
            wp_redirect(home_url('/'));
            exit;
        }

        // Validate store exists and has referral enabled
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, store_key, referral_enabled, referral_activated_at, referral_grace_days
             FROM {$wpdb->prefix}ppv_stores
             WHERE store_key = %s",
            $store_key
        ));

        if (!$store) {
            ppv_log("⚠️ [PPV_Referral] Invalid store key: {$store_key}");
            wp_redirect(home_url('/'));
            exit;
        }

        // Check if referral is active for this store
        if (!$store->referral_enabled || !$store->referral_activated_at) {
            ppv_log("⚠️ [PPV_Referral] Referral not enabled for store: {$store_key}");
            wp_redirect(home_url("/store/{$store_key}"));
            exit;
        }

        // Check grace period
        $days_since_activation = (strtotime('now') - strtotime($store->referral_activated_at)) / 86400;
        if ($days_since_activation < $store->referral_grace_days) {
            ppv_log("⚠️ [PPV_Referral] Grace period not over for store: {$store_key}");
            wp_redirect(home_url("/store/{$store_key}"));
            exit;
        }

        // Store referral info in cookie
        $referral_data = json_encode([
            'code' => strtoupper($code),
            'store_id' => $store->id,
            'store_key' => $store_key,
            'store_name' => $store->name,
            'referrer_id' => $referrer->user_id,
            'timestamp' => time()
        ]);

        setcookie(
            self::COOKIE_NAME,
            $referral_data,
            time() + (self::COOKIE_DAYS * 86400),
            '/',
            '',
            is_ssl(),
            true
        );

        // Also store in session for immediate access
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['ppv_referral'] = [
            'code' => strtoupper($code),
            'store_id' => $store->id,
            'store_key' => $store_key,
            'store_name' => $store->name,
            'referrer_id' => $referrer->user_id,
        ];

        ppv_log("✅ [PPV_Referral] Referral cookie+session set: code={$code}, store={$store_key}, referrer={$referrer->user_id}");

        // Redirect to login page with referral flag
        wp_redirect(home_url("/login?ref=1&store=" . urlencode($store_key)));
        exit;
    }

    /**
     * Get referral data from session or cookie
     */
    public static function get_referral_data() {
        // First check session (immediate access after redirect)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        if (!empty($_SESSION['ppv_referral']) && isset($_SESSION['ppv_referral']['code'])) {
            return $_SESSION['ppv_referral'];
        }

        // Fallback to cookie
        if (empty($_COOKIE[self::COOKIE_NAME])) {
            return null;
        }

        $data = json_decode($_COOKIE[self::COOKIE_NAME], true);
        if (!$data || !isset($data['code']) || !isset($data['store_id'])) {
            return null;
        }

        // Check if cookie is still valid (not expired beyond 30 days)
        if (isset($data['timestamp']) && (time() - $data['timestamp']) > (self::COOKIE_DAYS * 86400)) {
            self::clear_referral_cookie();
            return null;
        }

        return $data;
    }

    /**
     * Check if current user came via referral for a specific store
     */
    public static function has_referral_for_store($store_id) {
        $data = self::get_referral_data();
        if (!$data) {
            return false;
        }
        return (int)$data['store_id'] === (int)$store_id;
    }

    /**
     * Clear referral cookie and session
     */
    public static function clear_referral_cookie() {
        setcookie(self::COOKIE_NAME, '', time() - 3600, '/', '', is_ssl(), true);
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        unset($_SESSION['ppv_referral']);
    }

    /**
     * Process referral when a new user registers or first scans at a store
     * Called when user gets their first points at a store
     */
    public static function process_referral($user_id, $store_id) {
        global $wpdb;

        $referral_data = self::get_referral_data();
        if (!$referral_data) {
            return false;
        }

        // Check if this referral is for the same store
        if ((int)$referral_data['store_id'] !== (int)$store_id) {
            return false;
        }

        $referrer_id = (int)$referral_data['referrer_id'];

        // Don't allow self-referral
        if ($referrer_id === $user_id) {
            ppv_log("⚠️ [PPV_Referral] Self-referral attempt blocked: user={$user_id}");
            self::clear_referral_cookie();
            return false;
        }

        // Get store reward settings
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT referral_reward_type, referral_reward_value, referral_reward_gift, referral_manual_approval
             FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return false;
        }

        // Create referral record
        $status = $store->referral_manual_approval ? 'pending' : 'completed';

        // 🔒 FIX: Use INSERT IGNORE to atomically prevent duplicate referrals
        // This prevents race condition where two concurrent requests both pass the "existing" check
        $result = $wpdb->query($wpdb->prepare("
            INSERT IGNORE INTO {$wpdb->prefix}ppv_referrals
            (referrer_user_id, referred_user_id, store_id, referral_code, status, reward_type, reward_value, reward_gift, created_at, completed_at)
            VALUES (%d, %d, %d, %s, %s, %s, %s, %s, %s, %s)
        ",
            $referrer_id,
            $user_id,
            $store_id,
            $referral_data['code'],
            $status,
            $store->referral_reward_type,
            $store->referral_reward_value,
            $store->referral_reward_gift,
            current_time('mysql'),
            $status === 'completed' ? current_time('mysql') : null
        ));

        // 🔒 Check if insert was successful (not duplicate)
        if ($result === 0 || $wpdb->insert_id === 0) {
            ppv_log("⚠️ [PPV_Referral] User already referred to this store (atomic check): user={$user_id}, store={$store_id}");
            self::clear_referral_cookie();
            return false;
        }

        $referral_id = $wpdb->insert_id;

        ppv_log("✅ [PPV_Referral] Referral created: id={$referral_id}, referrer={$referrer_id}, referred={$user_id}, store={$store_id}, status={$status}");

        // If auto-approved, give rewards immediately
        if ($status === 'completed') {
            self::give_rewards($referral_id);
        }

        // Clear the cookie
        self::clear_referral_cookie();

        return true;
    }

    /**
     * Give rewards to both referrer and referred user
     */
    public static function give_rewards($referral_id) {
        global $wpdb;

        $referral = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_referrals WHERE id = %d",
            $referral_id
        ));

        if (!$referral || $referral->referrer_rewarded) {
            return false;
        }

        // Give reward based on type
        if ($referral->reward_type === 'points') {
            // Add points to referrer
            $wpdb->insert($wpdb->prefix . 'ppv_points', [
                'user_id' => $referral->referrer_user_id,
                'store_id' => $referral->store_id,
                'points' => $referral->reward_value,
                'source' => 'referral',
                'created' => current_time('mysql')
            ]);
            do_action('ppv_points_changed', $referral->referrer_user_id);

            // Also give points to referred user (optional - same amount)
            $wpdb->insert($wpdb->prefix . 'ppv_points', [
                'user_id' => $referral->referred_user_id,
                'store_id' => $referral->store_id,
                'points' => $referral->reward_value,
                'source' => 'referral_bonus',
                'created' => current_time('mysql')
            ]);
            do_action('ppv_points_changed', $referral->referred_user_id);

            ppv_log("✅ [PPV_Referral] Points given: referrer={$referral->referrer_user_id} (+{$referral->reward_value}), referred={$referral->referred_user_id} (+{$referral->reward_value})");
        }

        // Mark as rewarded
        $wpdb->update(
            $wpdb->prefix . 'ppv_referrals',
            [
                'referrer_rewarded' => 1,
                'referred_rewarded' => 1,
                'approved_at' => current_time('mysql')
            ],
            ['id' => $referral_id]
        );

        return true;
    }
}

PPV_Referral_Handler::hooks();
