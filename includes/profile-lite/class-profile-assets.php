<?php
/**
 * PunktePass Profile Lite - Assets Module
 * Handles CSS/JS enqueue and cache control headers
 */

if (!defined('ABSPATH')) exit;

class PPV_Profile_Assets {

    const NONCE_ACTION = 'ppv_save_profile';

    /**
     * Add Turbo no-cache meta tag to head
     * Must be in <head> for Turbo to pick it up BEFORE caching
     */
    public static function add_turbo_no_cache_meta() {
        global $post;

        // Only add on profile pages
        if ($post && has_shortcode($post->post_content, 'pp_store_profile')) {
            // This meta tag tells Turbo NOT to cache this page
            echo '<meta name="turbo-cache-control" content="no-cache">' . "\n";
            // Also add a unique cache-bust parameter marker
            echo '<meta name="ppv-page-load" content="' . time() . '">' . "\n";
        }
    }

    /**
     * Send no-cache HTTP headers
     * Prevents server-level caching (LiteSpeed, Cloudflare, nginx, etc.)
     */
    public static function send_no_cache_headers() {
        if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
            header('Cache-Control: post-check=0, pre-check=0', false);
            header('Pragma: no-cache');
            header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
            // Vary header to prevent proxy caching
            header('Vary: *');
        }
    }

    /**
     * Enqueue all CSS and JS assets
     */
    public static function enqueue_assets() {
        if (!class_exists('PPV_Lang')) {
            return;
        }

        // CSS - Legacy theme REMOVED – ppv-handler.css replaces ppv-theme-light.css
        // wp_enqueue_style('ppv-theme-light');

        // Google Maps API (if configured)
        if (defined('PPV_GOOGLE_MAPS_KEY') && PPV_GOOGLE_MAPS_KEY) {
            wp_enqueue_script(
                'google-maps-api',
                'https://maps.googleapis.com/maps/api/js?key=' . PPV_GOOGLE_MAPS_KEY,
                [],
                null,
                true
            );
        }

        // Profile Lite Modular JS (v3.0)
        self::enqueue_profile_js();

        // Localize script data
        wp_localize_script('pp-profile-core', 'ppv_profile', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(self::NONCE_ACTION),
            'strings' => PPV_Lang::$strings,
            'lang' => PPV_Lang::current(),
            'googleMapsKey' => defined('PPV_GOOGLE_MAPS_KEY') ? PPV_GOOGLE_MAPS_KEY : '',
            'pageLoadTime' => time(), // For cache detection
        ]);
    }

    /**
     * Enqueue modular Profile JS files
     */
    private static function enqueue_profile_js() {
        $js_base = PPV_PLUGIN_URL . 'assets/js/';
        $js_dir = PPV_PLUGIN_DIR . 'assets/js/';

        // 1. Core module (state, helpers, Turbo cache fix)
        wp_enqueue_script(
            'pp-profile-core',
            $js_base . 'pp-profile-core.js',
            [],
            filemtime($js_dir . 'pp-profile-core.js'),
            true
        );

        // 2. Tabs module
        wp_enqueue_script(
            'pp-profile-tabs',
            $js_base . 'pp-profile-tabs.js',
            ['pp-profile-core'],
            filemtime($js_dir . 'pp-profile-tabs.js'),
            true
        );

        // 3. Form module
        wp_enqueue_script(
            'pp-profile-form',
            $js_base . 'pp-profile-form.js',
            ['pp-profile-core', 'pp-profile-tabs'],
            filemtime($js_dir . 'pp-profile-form.js'),
            true
        );

        // 4. Media module
        wp_enqueue_script(
            'pp-profile-media',
            $js_base . 'pp-profile-media.js',
            ['pp-profile-core'],
            filemtime($js_dir . 'pp-profile-media.js'),
            true
        );

        // 5. Geocoding module
        wp_enqueue_script(
            'pp-profile-geocoding',
            $js_base . 'pp-profile-geocoding.js',
            ['pp-profile-core'],
            filemtime($js_dir . 'pp-profile-geocoding.js'),
            true
        );

        // 6. Init module (depends on all others)
        wp_enqueue_script(
            'pp-profile-init',
            $js_base . 'pp-profile-init.js',
            ['pp-profile-core', 'pp-profile-tabs', 'pp-profile-form', 'pp-profile-media', 'pp-profile-geocoding'],
            filemtime($js_dir . 'pp-profile-init.js'),
            true
        );
    }
}
