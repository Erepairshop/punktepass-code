<?php
/**
 * DEPRECATED: PPV User Signup (Old Version)
 *
 * This file is no longer in use. The signup functionality has been moved to:
 * - includes/ppv-signup.php (main signup page with [ppv_signup] shortcode)
 * - assets/js/ppv-signup.js (signup JavaScript)
 *
 * This file is kept for backwards compatibility only.
 * DO NOT USE THIS FILE FOR NEW DEVELOPMENT.
 *
 * @deprecated 2.4.0 Use PPV_Signup class instead (includes/ppv-signup.php)
 */

if (!defined('ABSPATH')) exit;

// Empty class to prevent errors if still referenced somewhere
if (!class_exists('PPV_User_Signup', false)) {
    class PPV_User_Signup {
        public static function hooks() {
            // Deprecated - no longer in use
            // Use PPV_Signup::hooks() instead (includes/ppv-signup.php)
            ppv_log("⚠️ [DEPRECATED] PPV_User_Signup is deprecated. Use PPV_Signup instead.");
        }

        public static function render_form() {
            return '<p>This signup form is deprecated. Please contact support.</p>';
        }

        public static function enqueue_assets() {
            // No assets to enqueue
        }

        public static function ajax_register_user() {
            wp_send_json_error(['msg' => 'This signup method is deprecated.']);
        }

        public static function handle_google_oauth() {
            // Deprecated
        }
    }
}
