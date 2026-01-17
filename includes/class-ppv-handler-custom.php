<?php
/**
 * PunktePass – Handler Custom Settings
 * Személyre szabott beállítások és funkciók kezelése händlereknek
 *
 * Version: 1.0
 * Author: PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Handler_Custom {

    /**
     * Hooks inicializálása
     */
    public static function hooks() {
        // Database migration
        add_action('init', [__CLASS__, 'maybe_run_migration']);

        // REST API endpoints
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);

        // Admin hook - egyedi funkciók engedélyezése
        add_action('rest_api_init', [__CLASS__, 'register_admin_routes']);
    }

    /** ============================================================
     *  🗄️ DATABASE MIGRATION
     * ============================================================ */
    public static function maybe_run_migration() {
        $version = get_option('ppv_handler_custom_version', '0');

        if (version_compare($version, '1.0', '<')) {
            self::run_migration_v1();
            update_option('ppv_handler_custom_version', '1.0');
        }
    }

    private static function run_migration_v1() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_stores';

        // Add custom_settings JSON column if not exists
        $column_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'custom_settings'");

        if (!$column_exists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN custom_settings LONGTEXT DEFAULT NULL");
            ppv_log("✅ [PPV_Handler_Custom] Added custom_settings column to ppv_stores");
        }

        // Add custom_features JSON column for admin-controlled features
        $features_exists = $wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE 'custom_features'");

        if (!$features_exists) {
            $wpdb->query("ALTER TABLE {$table} ADD COLUMN custom_features LONGTEXT DEFAULT NULL");
            ppv_log("✅ [PPV_Handler_Custom] Added custom_features column to ppv_stores");
        }
    }

    /** ============================================================
     *  📡 REST API ROUTES - Handler
     * ============================================================ */
    public static function register_rest_routes() {
        // Get custom settings
        register_rest_route('ppv/v1', '/handler/custom-settings', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_settings'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        // Save custom settings
        register_rest_route('ppv/v1', '/handler/custom-settings', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_save_settings'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        // Get available features (what the handler can use)
        register_rest_route('ppv/v1', '/handler/available-features', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_get_available_features'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);
    }

    /** ============================================================
     *  📡 REST API ROUTES - Admin (standalone admin)
     * ============================================================ */
    public static function register_admin_routes() {
        // Admin: Get handler's custom features
        register_rest_route('ppv/v1', '/admin/handler/(?P<store_id>\d+)/features', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'admin_get_features'],
            'permission_callback' => [__CLASS__, 'check_admin_permission']
        ]);

        // Admin: Set handler's custom features
        register_rest_route('ppv/v1', '/admin/handler/(?P<store_id>\d+)/features', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'admin_set_features'],
            'permission_callback' => [__CLASS__, 'check_admin_permission']
        ]);
    }

    /** ============================================================
     *  🔐 PERMISSIONS
     * ============================================================ */
    public static function check_admin_permission() {
        // Check standalone admin session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        return !empty($_SESSION['ppv_admin_logged_in']);
    }

    /** ============================================================
     *  🏪 GET STORE ID
     * ============================================================ */
    private static function get_store_id() {
        global $wpdb;

        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        // Session store
        if (!empty($_SESSION['ppv_store_id'])) {
            return intval($_SESSION['ppv_store_id']);
        }

        // Filiale
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return intval($_SESSION['ppv_current_filiale_id']);
        }

        // WP User → Store lookup
        $user_id = get_current_user_id();
        if ($user_id > 0) {
            $store_id = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d LIMIT 1",
                $user_id
            ));
            if ($store_id) return intval($store_id);
        }

        return 0;
    }

    /** ============================================================
     *  📡 REST: Get Custom Settings (Handler)
     * ============================================================ */
    public static function rest_get_settings(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $settings = self::get_settings($store_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => $settings
        ], 200);
    }

    /** ============================================================
     *  📡 REST: Save Custom Settings (Handler)
     * ============================================================ */
    public static function rest_save_settings(WP_REST_Request $request) {
        global $wpdb;

        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $settings = $request->get_json_params();

        // Sanitize settings
        $sanitized = self::sanitize_settings($settings);

        // Save to database
        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['custom_settings' => wp_json_encode($sanitized)],
            ['id' => $store_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            ppv_log("❌ [PPV_Handler_Custom] Failed to save settings for store {$store_id}");
            return new WP_REST_Response(['success' => false, 'msg' => 'Database error'], 500);
        }

        ppv_log("✅ [PPV_Handler_Custom] Settings saved for store {$store_id}");

        return new WP_REST_Response(['success' => true, 'msg' => 'Settings saved'], 200);
    }

    /** ============================================================
     *  📡 REST: Get Available Features (Handler)
     * ============================================================ */
    public static function rest_get_available_features(WP_REST_Request $request) {
        $store_id = self::get_store_id();
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 403);
        }

        $features = self::get_features($store_id);

        return new WP_REST_Response([
            'success' => true,
            'data' => $features
        ], 200);
    }

    /** ============================================================
     *  📡 REST: Admin - Get Handler Features
     * ============================================================ */
    public static function admin_get_features(WP_REST_Request $request) {
        global $wpdb;

        $store_id = intval($request->get_param('store_id'));

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, company_name, email, custom_features FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Store not found'], 404);
        }

        $features = self::get_features($store_id);

        return new WP_REST_Response([
            'success' => true,
            'store' => [
                'id' => $store->id,
                'name' => $store->name ?: $store->company_name,
                'email' => $store->email
            ],
            'features' => $features,
            'available_features' => self::get_all_available_features()
        ], 200);
    }

    /** ============================================================
     *  📡 REST: Admin - Set Handler Features
     * ============================================================ */
    public static function admin_set_features(WP_REST_Request $request) {
        global $wpdb;

        $store_id = intval($request->get_param('store_id'));
        $features = $request->get_json_params();

        // Validate features against available list
        $available = self::get_all_available_features();
        $valid_features = [];

        foreach ($features as $key => $value) {
            if (isset($available[$key])) {
                $valid_features[$key] = $value;
            }
        }

        $result = $wpdb->update(
            $wpdb->prefix . 'ppv_stores',
            ['custom_features' => wp_json_encode($valid_features)],
            ['id' => $store_id],
            ['%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_REST_Response(['success' => false, 'msg' => 'Database error'], 500);
        }

        ppv_log("✅ [PPV_Handler_Custom] Admin set features for store {$store_id}: " . wp_json_encode($valid_features));

        return new WP_REST_Response(['success' => true, 'msg' => 'Features updated'], 200);
    }

    /** ============================================================
     *  🔧 HELPER FUNCTIONS
     * ============================================================ */

    /**
     * Get custom settings for a store
     */
    public static function get_settings($store_id) {
        global $wpdb;

        $json = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_settings FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $settings = json_decode($json, true);

        // Default settings
        $defaults = [
            'email_notifications' => true,
            'weekly_report' => true,
            'dashboard_theme' => 'default',
            'language_preference' => 'de',
        ];

        return array_merge($defaults, $settings ?: []);
    }

    /**
     * Get custom features for a store (admin-controlled)
     */
    public static function get_features($store_id) {
        global $wpdb;

        $json = $wpdb->get_var($wpdb->prepare(
            "SELECT custom_features FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        $features = json_decode($json, true);

        // Default all features to false
        $defaults = [];
        foreach (self::get_all_available_features() as $key => $info) {
            $defaults[$key] = false;
        }

        return array_merge($defaults, $features ?: []);
    }

    /**
     * Check if a store has a specific feature enabled
     */
    public static function has_feature($store_id, $feature_key) {
        $features = self::get_features($store_id);
        return !empty($features[$feature_key]);
    }

    /**
     * Get all available features that can be enabled for handlers
     * Ez a lista bővíthető új funkciókkal
     */
    public static function get_all_available_features() {
        return [
            // Példa funkciók - később bővíthető
            'advanced_analytics' => [
                'name' => 'Advanced Analytics',
                'description' => 'Részletes statisztikák és grafikonok',
                'icon' => 'ri-bar-chart-2-line'
            ],
            'custom_branding' => [
                'name' => 'Custom Branding',
                'description' => 'Egyedi színek és logó a QR kódon',
                'icon' => 'ri-palette-line'
            ],
            'api_access' => [
                'name' => 'API Access',
                'description' => 'REST API hozzáférés külső integrációkhoz',
                'icon' => 'ri-code-s-slash-line'
            ],
            'multi_location' => [
                'name' => 'Multi-Location',
                'description' => 'Több filiale kezelése',
                'icon' => 'ri-building-2-line'
            ],
            'priority_support' => [
                'name' => 'Priority Support',
                'description' => 'Elsőbbségi ügyfélszolgálat',
                'icon' => 'ri-customer-service-2-line'
            ],
            'white_label' => [
                'name' => 'White Label',
                'description' => 'PunktePass branding eltávolítása',
                'icon' => 'ri-eye-off-line'
            ],
            'bulk_import' => [
                'name' => 'Bulk Import',
                'description' => 'Tömeges adatimport (customers, rewards)',
                'icon' => 'ri-upload-cloud-2-line'
            ],
            'webhook_notifications' => [
                'name' => 'Webhook Notifications',
                'description' => 'Valós idejű webhook értesítések',
                'icon' => 'ri-notification-3-line'
            ],
            'custom_rewards' => [
                'name' => 'Custom Rewards',
                'description' => 'Egyedi jutalom típusok létrehozása',
                'icon' => 'ri-gift-2-line'
            ],
            'beta_features' => [
                'name' => 'Beta Features',
                'description' => 'Hozzáférés béta funkciókhoz',
                'icon' => 'ri-flask-line'
            ],
        ];
    }

    /**
     * Sanitize settings input
     */
    private static function sanitize_settings($settings) {
        $sanitized = [];

        // Boolean fields
        $bool_fields = ['email_notifications', 'weekly_report'];
        foreach ($bool_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = (bool) $settings[$field];
            }
        }

        // String fields
        $string_fields = ['dashboard_theme', 'language_preference'];
        foreach ($string_fields as $field) {
            if (isset($settings[$field])) {
                $sanitized[$field] = sanitize_text_field($settings[$field]);
            }
        }

        // Allow custom fields (sanitized)
        foreach ($settings as $key => $value) {
            if (!isset($sanitized[$key])) {
                if (is_bool($value)) {
                    $sanitized[$key] = $value;
                } elseif (is_numeric($value)) {
                    $sanitized[$key] = intval($value);
                } elseif (is_string($value)) {
                    $sanitized[$key] = sanitize_text_field($value);
                } elseif (is_array($value)) {
                    $sanitized[$key] = array_map('sanitize_text_field', $value);
                }
            }
        }

        return $sanitized;
    }
}

// Initialize
PPV_Handler_Custom::hooks();
