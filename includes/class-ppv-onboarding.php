<?php
/**
 * PunktePass – Handler Onboarding System Backend
 * Version: 1.0
 * Kezeli az onboarding folyamatot backend oldalon
 */

if (!defined('ABSPATH')) exit;

// DEBUG: Immediate file load check - this runs when file is included
add_action('wp_head', function() {
    echo "<!-- PPV_ONBOARDING FILE LOADED -->";
}, 1);
add_action('wp_footer', function() {
    echo "<script>console.log('🟢 [PPV_ONBOARDING] FILE LOADED (top-level)');</script>";
}, 1);

if (!class_exists('PPV_Onboarding')) {

    class PPV_Onboarding {

        public static function hooks() {
            // DEBUG: Add footer log to confirm hooks() runs
            add_action('wp_footer', function() {
                echo "<script>console.log('🔍 [PPV_ONBOARDING] hooks() was called - file loaded OK');</script>";
            }, 1);

            // DB migration - ensure columns exist
            add_action('init', [__CLASS__, 'ensure_db_columns'], 5);

            // Enqueue scripts & styles
            add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

            // REST API endpoints
            add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        }

        /**
         * Ensure onboarding columns exist in ppv_stores table
         */
        public static function ensure_db_columns() {
            global $wpdb;
            $table = $wpdb->prefix . 'ppv_stores';

            // Check if migration already done (v2 includes postponed_until column)
            if (get_option('ppv_onboarding_db_migrated_v2')) {
                return;
            }

            // Add columns if they don't exist
            $columns = [
                'onboarding_dismissed' => 'TINYINT(1) DEFAULT 0',
                'onboarding_welcome_shown' => 'TINYINT(1) DEFAULT 0',
                'onboarding_completed' => 'TINYINT(1) DEFAULT 0',
                'onboarding_postponed_until' => 'DATETIME DEFAULT NULL'
            ];

            foreach ($columns as $col_name => $col_definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM `{$table}` LIKE '{$col_name}'");

                if (!$exists) {
                    $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `{$col_name}` {$col_definition}");
                    ppv_log("✅ [PPV_ONBOARDING] Added column: {$col_name}");
                }
            }

            // Mark as migrated (v2)
            update_option('ppv_onboarding_db_migrated_v2', true);
            ppv_log("✅ [PPV_ONBOARDING] DB migration v2 completed (with postponed_until)");
        }

        /**
         * Enqueue onboarding JS & localize config
         */
        public static function enqueue_assets() {
            // DEBUG: Add inline console log to see if this method runs at all
            add_action('wp_footer', function() {
                $session_vars = json_encode([
                    'ppv_store_id' => $_SESSION['ppv_store_id'] ?? null,
                    'ppv_vendor_store_id' => $_SESSION['ppv_vendor_store_id'] ?? null,
                    'ppv_current_filiale_id' => $_SESSION['ppv_current_filiale_id'] ?? null,
                    'ppv_user_type' => $_SESSION['ppv_user_type'] ?? null,
                ]);
                echo "<script>console.log('🔍 [PPV_ONBOARDING_PHP] enqueue_assets() CALLED, session:', {$session_vars});</script>";
            }, 1);

            // ✅ SCANNER USERS: Don't load onboarding
            if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
                ppv_log("🔍 [PPV_ONBOARDING] Skip: Scanner user");
                return;
            }

            // Csak bejelentkezett handler-eknek
            $auth = self::check_auth();
            ppv_log("🔍 [PPV_ONBOARDING] Auth check: " . json_encode($auth));

            if (!$auth['valid'] || $auth['type'] !== 'ppv_stores') {
                ppv_log("🔍 [PPV_ONBOARDING] Skip: Auth not valid or not ppv_stores type");
                return;
            }

            $store_id = $auth['store_id'];
            $store = self::get_store($store_id);

            if (!$store) {
                ppv_log("🔍 [PPV_ONBOARDING] Skip: Store not found for ID={$store_id}");
                return;
            }

            // ✅ ONLY SHOW FOR TRIAL STORES - Active stores don't need onboarding
            $subscription_status = $store->subscription_status ?? 'trial';
            ppv_log("🔍 [PPV_ONBOARDING] Store #{$store_id} subscription_status='{$subscription_status}'");

            if ($subscription_status !== 'trial') {
                // Active or other status - skip onboarding
                ppv_log("🔍 [PPV_ONBOARDING] Skip: subscription_status is NOT 'trial'");
                return;
            }

            // ⏰ Check if onboarding is postponed (8 hours delay)
            $postponed_until = $store->onboarding_postponed_until ?? null;
            ppv_log("🔍 [PPV_ONBOARDING] postponed_until='{$postponed_until}', dismissed=" . ($store->onboarding_dismissed ?? 0) . ", completed=" . ($store->onboarding_completed ?? 0));

            if (!empty($postponed_until) && strtotime($postponed_until) > time()) {
                // Still postponed - don't show onboarding
                ppv_log("🔍 [PPV_ONBOARDING] Skip: Postponed until {$postponed_until}");
                return;
            }

            ppv_log("✅ [PPV_ONBOARDING] LOADING onboarding for store #{$store_id}");

            // DEBUG: Inline log to browser console
            add_action('wp_footer', function() use ($store_id, $store, $subscription_status) {
                echo "<script>console.log('🔍 [PPV_ONBOARDING_PHP] Store #{$store_id}, subscription_status={$subscription_status}, dismissed=" . ($store->onboarding_dismissed ?? 0) . ", completed=" . ($store->onboarding_completed ?? 0) . ", postponed=" . ($store->onboarding_postponed_until ?? 'null') . "');</script>";
            }, 1);

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

            // Reset onboarding
            register_rest_route('ppv/v1', '/onboarding/reset', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_reset_onboarding'],
                'permission_callback' => [__CLASS__, 'rest_permission_check']
            ]);

            // Postpone onboarding (8 hours)
            register_rest_route('ppv/v1', '/onboarding/postpone', [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'rest_postpone_onboarding'],
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
            // ✅ SCANNER USERS: Block REST API access
            if (class_exists('PPV_Permissions') && PPV_Permissions::is_scanner_user()) {
                return false;
            }

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

            ppv_log("🚫 [PPV_ONBOARDING] Dismissed store #{$auth['store_id']}: result=" . ($result !== false ? 'OK' : 'FAILED'));

            return rest_ensure_response(['success' => $result !== false]);
        }

        /**
         * REST: Postpone onboarding (8 hours)
         */
        public static function rest_postpone_onboarding($request) {
            $auth = self::check_auth();
            global $wpdb;

            // 8 óra múlva jelenjen meg újra
            $postpone_until = date('Y-m-d H:i:s', strtotime('+8 hours'));

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['onboarding_postponed_until' => $postpone_until],
                ['id' => $auth['store_id']],
                ['%s'],
                ['%d']
            );

            ppv_log("⏰ [PPV_ONBOARDING] Postponed store #{$auth['store_id']} until {$postpone_until}");

            return rest_ensure_response([
                'success' => $result !== false,
                'postponed_until' => $postpone_until
            ]);
        }

        /**
         * REST: Reset onboarding (start fresh)
         */
        public static function rest_reset_onboarding($request) {
            $auth = self::check_auth();
            global $wpdb;

            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                [
                    'onboarding_dismissed' => 0,
                    'onboarding_welcome_shown' => 0,
                    'onboarding_completed' => 0
                ],
                ['id' => $auth['store_id']],
                ['%d', '%d', '%d'],
                ['%d']
            );

            ppv_log("🔄 [PPV_ONBOARDING] Reset store #{$auth['store_id']}: result=" . ($result !== false ? 'OK' : 'FAILED'));

            // Get fresh progress
            $store = self::get_store($auth['store_id']);
            $progress = self::calculate_progress($store);

            return rest_ensure_response([
                'success' => $result !== false,
                'progress' => $progress
            ]);
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

            // Debug: Session state
            ppv_log("🔍 [PPV_ONBOARDING] check_auth() - session vars: ppv_current_filiale_id=" . ($_SESSION['ppv_current_filiale_id'] ?? 'EMPTY') . ", ppv_store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY') . ", ppv_vendor_store_id=" . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY') . ", ppv_user_type=" . ($_SESSION['ppv_user_type'] ?? 'EMPTY'));

            // 🏪 FILIALE SUPPORT: Check ppv_current_filiale_id FIRST
            if (!empty($_SESSION['ppv_current_filiale_id'])) {
                return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($_SESSION['ppv_current_filiale_id'])];
            }

            if (!empty($_SESSION['ppv_store_id'])) {
                return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($_SESSION['ppv_store_id'])];
            }

            // Fallback: vendor store
            if (!empty($_SESSION['ppv_vendor_store_id'])) {
                return ['valid' => true, 'type' => 'ppv_stores', 'store_id' => intval($_SESSION['ppv_vendor_store_id'])];
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
