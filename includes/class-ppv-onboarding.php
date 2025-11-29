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

            // Enqueue Leaflet CSS & JS for interactive map
            wp_enqueue_style(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
                [],
                '1.9.4'
            );
            wp_enqueue_script(
                'leaflet',
                'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
                [],
                '1.9.4',
                true
            );

            // Enqueue Ably shared manager if available
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                PPV_Ably::enqueue_scripts();
            }

            // Enqueue JS (depends on ably-manager if available)
            $onboarding_deps = ['jquery', 'leaflet'];
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                $onboarding_deps[] = 'ppv-ably-manager';
            }
            wp_enqueue_script(
                'ppv-onboarding',
                PPV_PLUGIN_URL . 'assets/js/ppv-onboarding.js',
                $onboarding_deps,
                filemtime(PPV_PLUGIN_DIR . 'assets/js/ppv-onboarding.js'),
                true
            );

            // Progress számítás
            $progress = self::calculate_progress($store);

            // Config localize
            $onboarding_config = [
                'rest_url' => rest_url('ppv/v1/onboarding/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'store_id' => $store_id,
                'progress' => $progress,
                'dismissed' => (bool) ($store->onboarding_dismissed ?? 0),
                'welcome_shown' => (bool) ($store->onboarding_welcome_shown ?? 0),
                'is_qr_center' => (strpos($_SERVER['REQUEST_URI'] ?? '', 'qr-center') !== false),
                'sticky_hidden' => false
            ];

            // Add Ably config if available (for real-time updates)
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                $onboarding_config['ably'] = [
                    'key' => PPV_Ably::get_key(),
                    'channel' => 'store-' . $store_id
                ];
            }

            wp_localize_script('ppv-onboarding', 'ppv_onboarding', $onboarding_config);
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
                ppv_log("❌ [PPV_ONBOARDING] Permission denied: scanner user");
                return new WP_Error('rest_forbidden', 'Scanner users cannot access onboarding', ['status' => 403]);
            }

            $auth = self::check_auth();

            // Debug permission check
            ppv_log("🔐 [PPV_ONBOARDING] Permission check: valid=" . ($auth['valid'] ? 'true' : 'false') . ", type=" . ($auth['type'] ?? 'NONE') . ", store_id=" . ($auth['store_id'] ?? 'NONE'));

            if ($auth['valid'] && $auth['type'] === 'ppv_stores') {
                return true;
            }

            // ✅ Fallback: Check if vendor user is logged in via ppv_users
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }

            if (!empty($_SESSION['ppv_user_type']) && $_SESSION['ppv_user_type'] === 'vendor' && !empty($_SESSION['ppv_user_id'])) {
                global $wpdb;
                $vendor = $wpdb->get_row($wpdb->prepare(
                    "SELECT vendor_store_id FROM {$wpdb->prefix}ppv_users WHERE id = %d AND user_type = 'vendor' LIMIT 1",
                    intval($_SESSION['ppv_user_id'])
                ));

                if ($vendor && !empty($vendor->vendor_store_id)) {
                    // Set session vars for subsequent calls
                    $_SESSION['ppv_vendor_store_id'] = $vendor->vendor_store_id;
                    $_SESSION['ppv_store_id'] = $vendor->vendor_store_id;
                    ppv_log("✅ [PPV_ONBOARDING] Permission granted via vendor user fallback: store_id={$vendor->vendor_store_id}");
                    return true;
                }
            }

            ppv_log("❌ [PPV_ONBOARDING] Permission denied: no valid auth");
            return new WP_Error('rest_forbidden', 'Authentication required for onboarding', ['status' => 403]);
        }

        /**
         * REST: Get progress
         */
        public static function rest_get_progress($request) {
            $auth = self::check_auth();

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_get_progress: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

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

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_mark_welcome_shown: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

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

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_complete_step: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

            $body = json_decode($request->get_body(), true);
            $step = sanitize_text_field($body['step'] ?? '');
            $value = $body['value'] ?? [];

            global $wpdb;

            if ($step === 'profile_lite') {
                // Prepare opening hours JSON
                $opening_hours = [];
                if (!empty($value['opening_hours']) && is_array($value['opening_hours'])) {
                    foreach ($value['opening_hours'] as $day => $hours) {
                        $opening_hours[sanitize_key($day)] = [
                            'von' => sanitize_text_field($hours['von'] ?? ''),
                            'bis' => sanitize_text_field($hours['bis'] ?? ''),
                            'closed' => intval($hours['closed'] ?? 0)
                        ];
                    }
                }

                // Update profile fields
                $wpdb->update(
                    $wpdb->prefix . 'ppv_stores',
                    [
                        'name' => sanitize_text_field($value['shop_name'] ?? ''),
                        'company_name' => sanitize_text_field($value['company_name'] ?? ''),
                        'country' => sanitize_text_field($value['country'] ?? 'HU'),
                        'address' => sanitize_text_field($value['address'] ?? ''),
                        'city' => sanitize_text_field($value['city'] ?? ''),
                        'plz' => sanitize_text_field($value['zip'] ?? ''),
                        'latitude' => floatval($value['latitude'] ?? 0),
                        'longitude' => floatval($value['longitude'] ?? 0),
                        'timezone' => sanitize_text_field($value['timezone'] ?? 'Europe/Budapest'),
                        'opening_hours' => json_encode($opening_hours),
                    ],
                    ['id' => $auth['store_id']],
                    ['%s', '%s', '%s', '%s', '%s', '%s', '%f', '%f', '%s', '%s'],
                    ['%d']
                );

                ppv_log("✅ [PPV_ONBOARDING] Profile saved for store #{$auth['store_id']}");
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

            // 📡 Trigger Ably real-time update for onboarding progress
            if (class_exists('PPV_Ably') && PPV_Ably::is_enabled()) {
                PPV_Ably::trigger_onboarding_progress($auth['store_id'], $progress);
            }

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

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_dismiss_onboarding: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

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

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_postpone_onboarding: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

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

            if (!$auth['valid'] || empty($auth['store_id'])) {
                ppv_log("❌ [PPV_ONBOARDING] rest_reset_onboarding: auth invalid");
                return new WP_Error('auth_failed', 'Authentication failed', ['status' => 403]);
            }

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
            // Check if profile is complete:
            // - Shop name (name)
            // - Address (address + city)
            // - Coordinates (latitude + longitude)
            // - Opening hours
            // - Timezone
            $has_name = !empty($store->name);
            $has_address = !empty($store->address) && !empty($store->city);
            $has_coords = !empty($store->latitude) && !empty($store->longitude) &&
                          floatval($store->latitude) != 0 && floatval($store->longitude) != 0;
            $has_hours = !empty($store->opening_hours) && $store->opening_hours !== '[]' && $store->opening_hours !== '{}';
            $has_timezone = !empty($store->timezone);

            // Profile is complete only if ALL required fields are filled
            $profile_complete = $has_name && $has_address && $has_coords && $has_hours && $has_timezone;

            $steps = [
                'profile_lite' => $profile_complete,
                'reward' => self::has_reward($store->id),
                'device' => self::has_device($store->id)
            ];

            $completed = count(array_filter($steps));
            $total = count($steps);
            $percentage = $total > 0 ? round(($completed / $total) * 100) : 0;

            return [
                'steps' => $steps,
                'completed' => $completed,
                'total' => $total,
                'percentage' => $percentage,
                'is_complete' => $percentage >= 100,
                // Debug info
                'profile_details' => [
                    'has_name' => $has_name,
                    'has_address' => $has_address,
                    'has_coords' => $has_coords,
                    'has_hours' => $has_hours,
                    'has_timezone' => $has_timezone
                ]
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
         * Ellenőrzi hogy a store-nak van-e beállított POS eszköze (pos_token)
         */
        private static function has_device($store_id) {
            global $wpdb;

            $pos_token = $wpdb->get_var($wpdb->prepare(
                "SELECT pos_token FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
                $store_id
            ));

            return !empty($pos_token);
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
