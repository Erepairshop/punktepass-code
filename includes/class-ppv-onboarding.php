<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Handler Onboarding System
 * Version: 1.0
 * ✅ Csak handlereknek jelenik meg (akiknek van store-juk)
 * ✅ 2 kötelező lépés: Profile Lite + Első Prémium
 * ✅ Teljesen dismiss-elhető
 * ✅ Progress tracking + REST API
 */

class PPV_Onboarding {

    public static function hooks() {
        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // Assets - csak handlereknek
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_assets']);
    }

    /** ============================================================
     *  🔐 Handler ellenőrzés - Van-e store-ja a usernek?
     * ============================================================ */
    public static function is_handler($user_id = null) {
        global $wpdb;

        // Ha nincs megadott user_id, keressük a SESSION-ből (PPV custom user system)
        if (!$user_id) {
            // Session indítása ha kell
            if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
                @session_start();
            }

            // Restore session from token if needed
            if (empty($_SESSION['ppv_user_id']) && !empty($_COOKIE['ppv_user_token']) && class_exists('PPV_SessionBridge')) {
                PPV_SessionBridge::restore_from_token();
            }

            // PPV user ID a SESSION-ből
            if (!empty($_SESSION['ppv_user_id'])) {
                $user_id = intval($_SESSION['ppv_user_id']);
            } else {
                // Nincs bejelentkezve
                return false;
            }
        }

        // Ellenőrizzük van-e store a PPV usernek
        $store_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d AND active = 1 LIMIT 1",
            $user_id
        ));

        return $store_id ? intval($store_id) : false;
    }

    /** ============================================================
     *  📊 Progress számítás - Profile Lite + Reward
     * ============================================================ */
    public static function get_progress($store_id) {
        global $wpdb;

        if (!$store_id) {
            return [
                'percentage' => 0,
                'completed' => 0,
                'total' => 2,
                'steps' => [
                    'profile_lite' => false,
                    'reward' => false
                ],
                'is_complete' => false
            ];
        }

        // 1. Profile Lite ellenőrzés
        $profile_complete = self::check_profile_lite($store_id);

        // 2. Reward ellenőrzés
        $reward_exists = self::check_has_reward($store_id);

        $completed = 0;
        if ($profile_complete) $completed++;
        if ($reward_exists) $completed++;

        return [
            'percentage' => ($completed / 2) * 100,
            'completed' => $completed,
            'total' => 2,
            'steps' => [
                'profile_lite' => $profile_complete,
                'reward' => $reward_exists
            ],
            'is_complete' => $completed === 2
        ];
    }

    /** ============================================================
     *  ✅ Profile Lite Check
     * ============================================================ */
    private static function check_profile_lite($store_id) {
        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT company_name, country, address, city, plz, phone
             FROM {$wpdb->prefix}ppv_stores
             WHERE id = %d",
            $store_id
        ), ARRAY_A);

        if (!$store) {
            error_log("❌ [PPV_Onboarding] check_profile_lite: Store not found");
            return false;
        }

        // Minden kötelező mező ki van töltve?
        $is_complete = !empty($store['company_name']) &&
               !empty($store['country']) &&
               !empty($store['address']) &&
               !empty($store['city']) &&
               !empty($store['plz']) &&
               !empty($store['phone']);

        error_log("🔍 [PPV_Onboarding] check_profile_lite: " . ($is_complete ? 'COMPLETE ✅' : 'INCOMPLETE ❌') . " | Data: " . json_encode($store));

        return $is_complete;
    }

    /** ============================================================
     *  ✅ Reward Check
     * ============================================================ */
    private static function check_has_reward($store_id) {
        global $wpdb;

        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}ppv_rewards
             WHERE store_id = %d",
            $store_id
        ));

        error_log("🔍 [PPV_Onboarding] check_has_reward: Count = $count");

        return $count > 0;
    }

    /** ============================================================
     *  🔐 User Meta Getters/Setters - PPV custom user meta
     * ============================================================ */
    private static function get_ppv_user_id() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        if (empty($_SESSION['ppv_user_id']) && !empty($_COOKIE['ppv_user_token']) && class_exists('PPV_SessionBridge')) {
            PPV_SessionBridge::restore_from_token();
        }

        return !empty($_SESSION['ppv_user_id']) ? intval($_SESSION['ppv_user_id']) : 0;
    }

    public static function is_completed($store_id = null) {
        if (!$store_id) {
            $user_id = self::get_ppv_user_id();
            $store_id = self::is_handler($user_id);
        }
        if (!$store_id) return false;

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT onboarding_completed FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));
        return (bool) $value;
    }

    public static function is_dismissed($store_id = null) {
        if (!$store_id) {
            $user_id = self::get_ppv_user_id();
            $store_id = self::is_handler($user_id);
        }
        if (!$store_id) return false;

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT onboarding_dismissed FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));
        return (bool) $value;
    }

    public static function is_sticky_hidden($store_id = null) {
        if (!$store_id) {
            $user_id = self::get_ppv_user_id();
            $store_id = self::is_handler($user_id);
        }
        if (!$store_id) return false;

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT onboarding_sticky_hidden FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));
        return (bool) $value;
    }

    public static function is_welcome_shown($store_id = null) {
        if (!$store_id) {
            $user_id = self::get_ppv_user_id();
            $store_id = self::is_handler($user_id);
        }
        if (!$store_id) return false;

        global $wpdb;
        $value = $wpdb->get_var($wpdb->prepare(
            "SELECT onboarding_welcome_shown FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $store_id
        ));
        return (bool) $value;
    }

    public static function set_welcome_shown($store_id = null) {
        if (!$store_id) {
            $user_id = self::get_ppv_user_id();
            $store_id = self::is_handler($user_id);
        }
        if (!$store_id) return false;

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['onboarding_welcome_shown' => 1],
            ['id' => $store_id],
            ['%d'],
            ['%d']
        );
    }

    /** ============================================================
     *  🎨 ASSETS - Csak handlereknek
     * ============================================================ */
    public static function enqueue_assets() {
        // PPV user session ellenőrzés (nem WordPress login!)
        $user_id = self::get_ppv_user_id();

        if (!$user_id) return; // Nincs bejelentkezve

        $store_id = self::is_handler($user_id);

        if (!$store_id) return; // Nem handler = nem töltjük be

        // ✅ CSAK TRIAL USEREKNEK - Aktív előfizetőknek már nem kell az onboarding!
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_status FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if ($store && $store->subscription_status !== 'trial') {
            return; // Csak trial usereknek kell az onboarding
        }

        // Ha már teljesen dismissed vagy completed, nem kell
        if (self::is_dismissed($user_id) || self::is_completed($user_id)) {
            return;
        }

        // JS
        wp_enqueue_script(
            'ppv-onboarding',
            PPV_PLUGIN_URL . 'assets/js/ppv-onboarding.js',
            ['jquery'],
            time(),
            true
        );

        // CSS - manuálisan hozzáadva a global CSS-hez, nem enqueue-oljuk itt

        // Config
        $progress = self::get_progress($store_id);
        $welcome_shown = self::is_welcome_shown($user_id);

        wp_add_inline_script(
            'ppv-onboarding',
            "window.ppv_onboarding = " . wp_json_encode([
                'rest_url' => rest_url('ppv/v1/onboarding/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'store_id' => $store_id,
                'progress' => $progress,
                'welcome_shown' => $welcome_shown,
                'is_qr_center' => is_page() && has_shortcode(get_post()->post_content, 'ppv_qr_center')
            ]) . ";",
            'before'
        );

        // Fordítások
        if (class_exists('PPV_Lang')) {
            wp_add_inline_script(
                'ppv-onboarding',
                "window.ppv_lang = " . wp_json_encode(PPV_Lang::$strings) . ";",
                'before'
            );
        }
    }

    public static function enqueue_admin_assets() {
        // PPV user session ellenőrzés (nem WordPress login!)
        $user_id = self::get_ppv_user_id();

        if (!$user_id) return; // Nincs bejelentkezve

        $store_id = self::is_handler($user_id);

        if (!$store_id) return; // Nem handler

        // ✅ CSAK TRIAL USEREKNEK - Aktív előfizetőknek már nem kell az onboarding!
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_status FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if ($store && $store->subscription_status !== 'trial') {
            return; // Csak trial usereknek kell az onboarding
        }

        if (self::is_dismissed($user_id) || self::is_completed($user_id)) {
            return;
        }

        // JS asset (CSS manuálisan hozzáadva a global CSS-hez)
        wp_enqueue_script(
            'ppv-onboarding',
            PPV_PLUGIN_URL . 'assets/js/ppv-onboarding.js',
            ['jquery'],
            time(),
            true
        );

        $progress = self::get_progress($store_id);
        $welcome_shown = self::is_welcome_shown($user_id);

        wp_add_inline_script(
            'ppv-onboarding',
            "window.ppv_onboarding = " . wp_json_encode([
                'rest_url' => rest_url('ppv/v1/onboarding/'),
                'nonce' => wp_create_nonce('wp_rest'),
                'store_id' => $store_id,
                'progress' => $progress,
                'welcome_shown' => $welcome_shown,
                'is_qr_center' => false // Admin area
            ]) . ";",
            'before'
        );

        if (class_exists('PPV_Lang')) {
            wp_add_inline_script(
                'ppv-onboarding',
                "window.ppv_lang = " . wp_json_encode(PPV_Lang::$strings) . ";",
                'before'
            );
        }
    }

    /** ============================================================
     *  🔡 REST API ENDPOINTS
     * ============================================================ */
    public static function register_rest_routes() {
        // GET /ppv/v1/onboarding/progress
        register_rest_route('ppv/v1', '/onboarding/progress', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_progress'],
            'permission_callback' => '__return_true'
        ]);

        // POST /ppv/v1/onboarding/dismiss
        register_rest_route('ppv/v1', '/onboarding/dismiss', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_dismiss'],
            'permission_callback' => '__return_true'
        ]);

        // POST /ppv/v1/onboarding/complete-step
        register_rest_route('ppv/v1', '/onboarding/complete-step', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_complete_step'],
            'permission_callback' => '__return_true'
        ]);

        // POST /ppv/v1/onboarding/mark-welcome-shown
        register_rest_route('ppv/v1', '/onboarding/mark-welcome-shown', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_mark_welcome_shown'],
            'permission_callback' => '__return_true'
        ]);

        // POST /ppv/v1/onboarding/reset
        register_rest_route('ppv/v1', '/onboarding/reset', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_reset'],
            'permission_callback' => '__return_true'
        ]);

        // POST /ppv/v1/onboarding/geocode
        register_rest_route('ppv/v1', '/onboarding/geocode', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_geocode'],
            'permission_callback' => '__return_true'
        ]);
    }

    /** ============================================================
     *  📊 REST – Get Progress
     * ============================================================ */
    public static function rest_get_progress($request) {
        $user_id = self::get_ppv_user_id();

        if (!$user_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Not logged in'
            ], 401);
        }

        $store_id = self::is_handler($user_id);

        if (!$store_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Not a handler'
            ], 403);
        }

        $progress = self::get_progress($store_id);

        return new WP_REST_Response([
            'success' => true,
            'progress' => $progress,
            'dismissed' => self::is_dismissed($user_id),
            'completed' => self::is_completed($user_id),
            'sticky_hidden' => self::is_sticky_hidden($user_id)
        ], 200);
    }

    /** ============================================================
     *  ❌ REST – Dismiss
     * ============================================================ */
    public static function rest_dismiss($request) {
        $user_id = self::get_ppv_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $store_id = self::is_handler($user_id);

        if (!$store_id) {
            return new WP_REST_Response(['success' => false], 403);
        }

        $data = $request->get_json_params();
        $type = sanitize_text_field($data['type'] ?? 'permanent');

        global $wpdb;
        $column = $type === 'permanent' ? 'onboarding_dismissed' : 'onboarding_sticky_hidden';

        $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            [$column => 1],
            ['id' => $store_id],
            ['%d'],
            ['%d']
        );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Onboarding dismissed'
        ], 200);
    }

    /** ============================================================
     *  ✅ REST – Complete Step (Wizard-ból)
     * ============================================================ */
    public static function rest_complete_step($request) {
        $user_id = self::get_ppv_user_id();

        error_log("🧙 [PPV_Onboarding] rest_complete_step START | user_id: $user_id");

        if (!$user_id) {
            error_log("❌ [PPV_Onboarding] No user_id");
            return new WP_REST_Response(['success' => false, 'message' => 'Not logged in'], 401);
        }

        $data = $request->get_json_params();
        $step = sanitize_text_field($data['step'] ?? '');
        $value = $data['value'] ?? null;

        error_log("📝 [PPV_Onboarding] Step: $step | Data: " . json_encode($value));

        $store_id = self::is_handler($user_id);

        if (!$store_id) {
            error_log("❌ [PPV_Onboarding] User is not a handler");
            return new WP_REST_Response(['success' => false, 'message' => 'Not a handler'], 403);
        }

        error_log("🏪 [PPV_Onboarding] Store ID: $store_id");

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_stores';

        // Profile Lite lépés
        if ($step === 'profile_lite') {
            $update_data = [
                'company_name' => sanitize_text_field($value['company_name'] ?? ''),
                'country' => sanitize_text_field($value['country'] ?? ''),
                'address' => sanitize_text_field($value['address'] ?? ''),
                'city' => sanitize_text_field($value['city'] ?? ''),
                'plz' => sanitize_text_field($value['zip'] ?? ''),  // ZIP from frontend → PLZ in DB
                'phone' => sanitize_text_field($value['phone'] ?? '')
            ];

            // Latitude & Longitude (opcionális)
            if (!empty($value['latitude'])) {
                $update_data['latitude'] = floatval($value['latitude']);
            }
            if (!empty($value['longitude'])) {
                $update_data['longitude'] = floatval($value['longitude']);
            }

            $result = $wpdb->update(
                $table,
                $update_data,
                ['id' => $store_id]
            );

            error_log("✅ [PPV_Onboarding] Profile updated | Rows affected: " . ($result !== false ? $result : 'ERROR'));
            if ($result === false) {
                error_log("❌ [PPV_Onboarding] DB Error: " . $wpdb->last_error);
            }
        }

        // Reward lépés
        if ($step === 'reward') {
            $rewards_table = $wpdb->prefix . 'ppv_rewards';

            // Get store country for currency
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id=%d",
                $store_id
            ));
            $country = $store->country ?? 'DE';
            $currency_map = ['DE' => 'EUR', 'HU' => 'HUF', 'RO' => 'RON'];
            $currency = $currency_map[$country] ?? 'EUR';

            $result = $wpdb->insert(
                $rewards_table,
                [
                    'store_id' => $store_id,
                    'title' => sanitize_text_field($value['title'] ?? 'Első Prémium'),
                    'description' => sanitize_textarea_field($value['description'] ?? ''),
                    'required_points' => intval($value['required_points'] ?? 100),
                    'action_type' => sanitize_text_field($value['action_type'] ?? 'free_product'),
                    'action_value' => sanitize_text_field($value['action_value'] ?? '0'),
                    'points_given' => intval($value['points_given'] ?? 0),
                    'free_product' => sanitize_text_field($value['free_product'] ?? ''),
                    'free_product_value' => floatval($value['free_product_value'] ?? 0),
                    'currency' => $currency,
                    'created_at' => current_time('mysql')
                ],
                ['%d', '%s', '%s', '%d', '%s', '%s', '%d', '%s', '%f', '%s', '%s']
            );

            error_log("✅ [PPV_Onboarding] Reward created | Result: " . ($result !== false ? 'SUCCESS' : 'ERROR'));
            if ($result === false) {
                error_log("❌ [PPV_Onboarding] DB Error: " . $wpdb->last_error);
            }
        }

        // Progress újraszámolás
        $progress = self::get_progress($store_id);

        error_log("📊 [PPV_Onboarding] Progress: " . json_encode($progress));

        // Ha 100% → completed flag
        if ($progress['is_complete']) {
            error_log("🎉 [PPV_Onboarding] Onboarding COMPLETE! Setting flag...");
            $result = $wpdb->update(
                $wpdb->prefix . 'ppv_stores',
                ['onboarding_completed' => 1],
                ['id' => $store_id],
                ['%d'],
                ['%d']
            );
            error_log("✅ [PPV_Onboarding] Completed flag set | Rows affected: " . ($result !== false ? $result : 'ERROR'));
        }

        error_log("✅ [PPV_Onboarding] rest_complete_step SUCCESS");

        return new WP_REST_Response([
            'success' => true,
            'progress' => $progress
        ], 200);
    }

    /** ============================================================
     *  👋 REST – Mark Welcome Shown
     * ============================================================ */
    public static function rest_mark_welcome_shown($request) {
        $user_id = self::get_ppv_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $store_id = self::is_handler($user_id);

        if (!$store_id) {
            return new WP_REST_Response(['success' => false], 403);
        }

        self::set_welcome_shown($store_id);

        return new WP_REST_Response([
            'success' => true
        ], 200);
    }

    /** ============================================================
     *  🔄 REST – Reset Onboarding
     * ============================================================ */
    public static function rest_reset($request) {
        $user_id = self::get_ppv_user_id();

        if (!$user_id) {
            return new WP_REST_Response(['success' => false], 401);
        }

        $store_id = self::is_handler($user_id);

        if (!$store_id) {
            return new WP_REST_Response(['success' => false], 403);
        }

        global $wpdb;

        // Reset all onboarding flags
        $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            [
                'onboarding_completed' => 0,
                'onboarding_dismissed' => 0,
                'onboarding_sticky_hidden' => 0,
                'onboarding_welcome_shown' => 0
            ],
            ['id' => $store_id],
            ['%d', '%d', '%d', '%d'],
            ['%d']
        );

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Onboarding reset'
        ], 200);
    }

    /** ============================================================
     *  🗺️ REST – Geocode Address
     * ============================================================ */
    public static function rest_geocode($request) {
        try {
            $data = $request->get_json_params();

            if (!$data) {
                $data = $request->get_params(); // Fallback to GET params
            }

            $address = sanitize_text_field($data['address'] ?? '');
            $city = sanitize_text_field($data['city'] ?? '');
            $zip = sanitize_text_field($data['zip'] ?? '');
            $country = sanitize_text_field($data['country'] ?? 'DE');

            if (empty($address) || empty($city)) {
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Address and city required'
                ], 200); // 200 instead of 400 to avoid CORS issues
            }

            // Google Maps API key (használjuk a meglévő konstanst)
            $google_api_key = defined('PPV_GOOGLE_MAPS_KEY')
                ? PPV_GOOGLE_MAPS_KEY
                : get_option('ppv_google_maps_api_key', '');

            if (empty($google_api_key)) {
                error_log('❌ [PPV_Onboarding] Google Maps API key missing');
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Geocoding not available (API key missing)'
                ], 200); // 200 instead of 500
            }

            // Geocode keresés
            $search_query = trim("$address, $zip $city, $country");
            $url = 'https://maps.googleapis.com/maps/api/geocode/json';

            $response = wp_remote_get(
                add_query_arg([
                    'address' => $search_query,
                    'components' => 'country:' . strtolower($country),
                    'key' => $google_api_key,
                    'language' => 'en'
                ], $url),
                ['timeout' => 10]
            );

            if (is_wp_error($response)) {
                error_log('❌ [PPV_Onboarding] Geocoding API error: ' . $response->get_error_message());
                return new WP_REST_Response([
                    'success' => false,
                    'message' => 'Geocoding API error'
                ], 200); // 200 instead of 500
            }

            $body = wp_remote_retrieve_body($response);
            $geo_data = json_decode($body, true);

            if (isset($geo_data['results'][0]['geometry']['location'])) {
                $location = $geo_data['results'][0]['geometry']['location'];

                return new WP_REST_Response([
                    'success' => true,
                    'lat' => $location['lat'],
                    'lng' => $location['lng'],
                    'formatted_address' => $geo_data['results'][0]['formatted_address'] ?? ''
                ], 200);
            }

            return new WP_REST_Response([
                'success' => false,
                'message' => 'Location not found'
            ], 200); // 200 instead of 404

        } catch (Exception $e) {
            error_log('❌ [PPV_Onboarding] Geocode exception: ' . $e->getMessage());
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Geocoding failed'
            ], 200);
        }
    }
}

PPV_Onboarding::hooks();
