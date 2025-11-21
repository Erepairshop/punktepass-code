<?php
/**
 * PunktePass – Handler Onboarding System Backend
 * Version: 1.0
 * Kezeli az onboarding folyamatot backend oldalon
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('PPV_Onboarding')) {

    class PPV_Onboarding {

        public static function hooks() {
            // Enqueue scripts & styles
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

            // REST API endpoints
            add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        }

        /**
         * Enqueue onboarding JS & localize config
         */
        public static function enqueue_assets() {
            // Csak bejelentkezett handler-eknek
            $auth = self::check_auth();
            if (!$auth['valid'] || $auth['type'] !== 'ppv_stores') {
                return;
            }

            $store_id = $auth['store_id'];
            $store = self::get_store($store_id);

            if (!$store) {
                return;
            }

            // Enqueue JS
            wp_enqueue_script(
                'ppv-onboarding',
                PPV_PLUGIN_URL . 'assets/js/ppv-onboarding.js',
                ['jquery'],
                filemtime(PPV_PLUGIN_DIR . 'assets/js/ppv-onboarding.js'),
                true
            );

            // Progress számítás
            $progress = self::calculate_progress($store);

            // Config localize
            wp_localize_script('ppv-onboarding', 'ppv_onboarding', [
                'rest_url' => rest_url('ppv/v1/onboarding/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'store_id' => $store_id,
                'progress' => $progress,
                'dismissed' => (bool) ($store->onboarding_dismissed ?? 0),
                'welcome_shown' => (bool) ($store->onboarding_welcome_shown ?? 0),
                'is_qr_center' => (strpos($_SERVER['REQUEST_URI'] ?? '', 'qr-center') !== false),
                'sticky_hidden' => false
            ]);
        }

        /**
         * REST API routes regisztráció
         */
        public static function register_rest_routes() {
            // Get progress
            register_rest_route('ppv/v1', '/onboarding/progress', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'rest_get_progress'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);

            // Mark welcome shown
            register_rest_route('ppv/v1', '/onboarding/mark-welcome-shown', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_mark_welcome_shown'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);

            // Complete step
            register_rest_route('ppv/v1', '/onboarding/complete-step', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_complete_step'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);

            // Dismiss onboarding
            register_rest_route('ppv/v1', '/onboarding/dismiss', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_dismiss_onboarding'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);

            // Geocode address
            register_rest_route('ppv/v1', '/onboarding/geocode', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_geocode_address'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);
        }

        /**
         * REST permission check
         */
        public static function rest_permission_check() {
            $auth = self::check_auth();
            return $auth['valid'] && $auth['type'] === 'ppv_stores';
        }

        /**
         * REST: Get progress
         */
        public static function rest_get_progress($request) {
            $auth = self::check_auth();
            $store = self::get_store($auth['store_id']);

            if (!$store) {
                return new WP_Error('store_not_found', 'Store not found', ['status' => 404]);
            }

            $progress = self::calculate_progress($store);

            return rest_ensure_response([
                'success' => true,
                'progress' => $progress
            ]);
        }

        /**
         * REST: Mark welcome shown
         */
        public static function rest_mark_welcome_shown($request) {
            $auth = self::check_auth();
            global $wpdb;

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['onboarding_welcome_shown' => 1],
                ['id' => $auth['store_id']],
                ['%d'],
                ['%d']
            );

            return rest_ensure_response(['success' => $result !== false]);
        }

        /**
         * REST: Complete step (profile_lite vagy reward)
         */
        public static function rest_complete_step($request) {
            $auth = self::check_auth();
            $body = json_decode($request->get_body(), true);
            $step = sanitize_text_field($body['step'] ?? '');
            $value = $body['value'] ?? [];

            global $wpdb;

            if ($step === 'profile_lite') {
                // Update profile fields
                $wpdb->update(
                    $wpdb->prefix . 'ppv_stores',
                    [
                        'name' => sanitize_text_field($value['company_name'] ?? ''),
                        'country' => sanitize_text_field($value['country'] ?? 'HU'),
                        'address' => sanitize_text_field($value['address'] ?? ''),
                        'city' => sanitize_text_field($value['city'] ?? ''),
                        'plz' => sanitize_text_field($value['zip'] ?? ''),
                        'phone' => sanitize_text_field($value['phone'] ?? ''),
                        'latitude' => floatval($value['latitude'] ?? 0),
                        'longitude' => floatval($value['longitude'] ?? 0),
                    ],
                    ['id' => $auth['store_id']],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f'],
                    ['%d']
                );
            } elseif ($step === 'reward') {
                // Create reward (prémium)
                $wpdb->insert(
                    $wpdb->prefix . 'ppv_rewards',
                    [
                        'store_id' => $auth['store_id'],
                        'title' => sanitize_text_field($value['title'] ?? ''),
                        'description' => wp_kses_post($value['description'] ?? ''),
                        'required_points' => intval($value['required_points'] ?? 100),
                        'action_type' => sanitize_text_field($value['action_type'] ?? 'free_product'),
                        'action_value' => floatval($value['action_value'] ?? 0),
                        'points_given' => intval($value['points_given'] ?? 0),
                        'free_product' => sanitize_text_field($value['free_product'] ?? ''),
                        'free_product_value' => floatval($value['free_product_value'] ?? 0),
                        'active' => 1,
                        'created_at' => current_time('mysql')
                    ],
                    ['%d', '%s', '%s', '%d', '%s', '%f', '%d', '%s', '%f', '%d', '%s']
                );

                // Mark onboarding complete
                $wpdb->update(
                    $wpdb->prefix . 'ppv_stores',
                    ['onboarding_completed' => 1],
                    ['id' => $auth['store_id']],
                    ['%d'],
                    ['%d']
                );
            }

            // Get updated progress
            $store = self::get_store($auth['store_id']);
            $progress = self::calculate_progress($store);

            return rest_ensure_response([
                'success' => true,
                'progress' => $progress
            ]);
        }

        /**
         * REST: Dismiss onboarding
         */
        public static function rest_dismiss_onboarding($request) {
            $auth = self::check_auth();
            $body = json_decode($request->get_body(), true);
            $type = sanitize_text_field($body['type'] ?? 'permanent');

            global $wpdb;

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['onboarding_dismissed' => 1],
                ['id' => $auth['store_id']],
                ['%d'],
                ['%d']
            );

            error_log("🚫 [PPV_ONBOARDING] Dismissed store #{$auth['store_id']}: result=" . ($result !== false ? 'OK' : 'FAILED'));

            return rest_ensure_response(['success' => $result !== false]);
        }

        /**
         * REST: Geocode address
         */
        public static function rest_geocode_address($request) {
            $body = json_decode($request->get_body(), true);
            $address = sanitize_text_field($body['address'] ?? '');
            $city = sanitize_text_field($body['city'] ?? '');
            $zip = sanitize_text_field($body['zip'] ?? '');
            $country = sanitize_text_field($body['country'] ?? 'HU');

            if (empty($address) || empty($city)) {
                return new WP_Error('missing_data', 'Address and city required', ['status' => 400]);
            }

            // Simple Nominatim geocoding
            $query = urlencode("{$address}, {$zip} {$city}, {$country}");
            $url = "https://nominatim.openstreetmap.org/search?format=json&q={$query}&limit=1";

            $response = wp_remote_get($url, [
                'timeout' => 10,
                'headers' => [
                    'User-Agent' => 'PunktePass (https://punktepass.de)'
                ]
            ]);

            if (is_wp_error($response)) {
                return new WP_Error('geocode_failed', 'Geocoding failed', ['status' => 500]);
            }

            $data = json_decode(wp_remote_retrieve_body($response), true);

            if (!empty($data[0])) {
                return rest_ensure_response([
                    'success' => true,
                    'lat' => floatval($data[0]['lat']),
                    'lng' => floatval($data[0]['lon'])
                ]);
            }

            return new WP_Error('not_found', 'Address not found', ['status' => 404]);
        }

        /**
         * Progress számítás
         */
        private static function calculate_progress($store) {
            $steps = [
                'profile_lite' => !empty($store->name) && !empty($store->address),
                'reward' => self::has_reward($store->id)
            ];

            $completed = count(array_filter($steps));
            $total = count($steps);
            $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'steps' => $steps,
                'completed' => $completed,
                'total' => $total,
                'percentage' => $percentage,
                'is_complete' => $percentage >= 100
            ];
        }

        /**
         * Check if store has at least one reward
         */
        private static function has_reward($store_id) {
            global $wpdb;
            $count = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_rewards WHERE store_id = %d",
                $store_id
            ));
            return $count > 0;
        }

        /**
         * Get store by ID
         */
        private static function get_store($store_id) {
            global $wpdb;
            return $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));
        }

        /**
         * Auth check (PPV session)
         */
        private static function check_auth() {
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }

            if (!empty($_SESSION['ppv_store_id'])) {
                return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($_SESSION['ppv_store_id'])];
            }

            if (!empty($_COOKIE['ppv_pos_token'])) {
                global $wpdb;
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE pos_token = %s LIMIT 1",
                    sanitize_text_field($_COOKIE['ppv_pos_token'])
                ));
                if ($store) {
                    return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($store->id)];
                }
            }

            return ['valid' => false];
        }
    }

    PPV_Onboarding::hooks();
}
