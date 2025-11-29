<?php
/**
 * PunktePass Profile Lite - Auth Module
 * Handles authentication, session management, and store ID resolution
 */

if (!defined('ABSPATH')) exit;

class PPV_Profile_Auth {

    /**
     * Ensure PHP session is started
     */
    public static function ensure_session() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
    }

    /**
     * Check authentication status
     * Returns array with 'valid', 'type', and relevant IDs
     */
    public static function check_auth() {
        // WordPress user
        if (is_user_logged_in()) {
            return [
                'valid' => true,
                'type' => 'wp_user',
                'user_id' => get_current_user_id()
            ];
        }

        // PPV Store session (with Filiale support)
        if (!empty($_SESSION['ppv_store_id']) || !empty($_SESSION['ppv_current_filiale_id'])) {
            $store_id = self::get_store_id();
            return [
                'valid' => true,
                'type' => 'ppv_stores',
                'store_id' => $store_id,
                'is_pos' => !empty($_SESSION['ppv_is_pos'])
            ];
        }

        // PPV User session
        if (!empty($_SESSION['ppv_user_id'])) {
            return [
                'valid' => true,
                'type' => 'ppv_user',
                'user_id' => intval($_SESSION['ppv_user_id'])
            ];
        }

        // POS Token cookie
        if (!empty($_COOKIE['ppv_pos_token'])) {
            global $wpdb;
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s LIMIT 1",
                sanitize_text_field($_COOKIE['ppv_pos_token'])
            ));
            if ($store) {
                return [
                    'valid' => true,
                    'type' => 'ppv_stores',
                    'store_id' => intval($store->id),
                    'store' => $store
                ];
            }
        }

        // User Token cookie
        if (!empty($_COOKIE['ppv_user_token'])) {
            global $wpdb;
            $user = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_users WHERE login_token = %s LIMIT 1",
                sanitize_text_field($_COOKIE['ppv_user_token'])
            ));
            if ($user) {
                return [
                    'valid' => true,
                    'type' => 'ppv_user',
                    'user_id' => intval($user->id),
                    'user' => $user
                ];
            }
        }

        return ['valid' => false];
    }

    /**
     * Get current store ID (with Filiale support)
     * Priority: filiale_id > store_id > vendor_store_id > WP user store
     */
    public static function get_store_id() {
        global $wpdb;

        self::ensure_session();

        // 1. Filiale ID (highest priority)
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // 2. Base store ID
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // 3. Vendor store ID
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return intval($_SESSION['ppv_vendor_store_id']);
        }

        // 4. WordPress user's store
        if (is_user_logged_in()) {
            $uid = get_current_user_id();
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d LIMIT 1",
                $uid
            ));
            if ($store_id) {
                return intval($store_id);
            }
        }

        return 0;
    }

    /**
     * Get current store object with fresh data (no cache)
     */
    public static function get_current_store() {
        global $wpdb;

        // Flush caches to ensure fresh data
        wp_cache_flush();
        $wpdb->flush();

        $store_id = self::get_store_id();

        if (function_exists('ppv_log')) {
            ppv_log("[Profile-Auth] get_current_store() - store_id: {$store_id}");
        }

        if ($store_id) {
            // SQL_NO_CACHE prevents MySQL query cache
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT SQL_NO_CACHE * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));
            return $store;
        }

        // Fallback: GET parameter (admin use)
        if (!empty($_GET['store_id'])) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                intval($_GET['store_id'])
            ));
        }

        // Fallback: POS token cookie
        if (!empty($_COOKIE['ppv_pos_token'])) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s LIMIT 1",
                sanitize_text_field($_COOKIE['ppv_pos_token'])
            ));
        }

        // Fallback: WordPress user
        if (is_user_logged_in()) {
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                get_current_user_id()
            ));
        }

        return null;
    }
}
