<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Filiale (Branch) Management
 * Handles branch stores for multi-location businesses
 */
class PPV_Filiale {

    /**
     * Initialize hooks
     */
    public static function hooks() {
        add_action('init', [__CLASS__, 'ensure_db_column'], 1);
        add_action('wp_ajax_ppv_create_filiale', [__CLASS__, 'ajax_create_filiale']);
        add_action('wp_ajax_nopriv_ppv_create_filiale', [__CLASS__, 'ajax_create_filiale']);
        add_action('wp_ajax_ppv_switch_filiale', [__CLASS__, 'ajax_switch_filiale']);
        add_action('wp_ajax_nopriv_ppv_switch_filiale', [__CLASS__, 'ajax_switch_filiale']);
        add_action('wp_ajax_ppv_get_filialen', [__CLASS__, 'ajax_get_filialen']);
        add_action('wp_ajax_nopriv_ppv_get_filialen', [__CLASS__, 'ajax_get_filialen']);
    }

    /**
     * Ensure parent_store_id column exists in ppv_stores table
     * Runs on every init but only adds column once
     */
    public static function ensure_db_column() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_stores';

        // Check if column already exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'parent_store_id'",
            DB_NAME,
            $table_name
        ));

        // Add column if it doesn't exist
        if (empty($column_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN parent_store_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER id,
                ADD INDEX idx_parent_store (parent_store_id)
            ");
        }
    }

    /**
     * Get parent store ID (main location)
     * Returns the parent_store_id or the store's own ID if it's a parent
     */
    public static function get_parent_id($store_id) {
        global $wpdb;

        $parent_id = $wpdb->get_var($wpdb->prepare(
            "SELECT parent_store_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        return $parent_id ? intval($parent_id) : intval($store_id);
    }

    /**
     * Get all filialen (branches) for a parent store
     */
    public static function get_filialen($parent_store_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, address, city, plz, active, parent_store_id
             FROM {$wpdb->prefix}ppv_stores
             WHERE parent_store_id = %d OR id = %d
             ORDER BY id ASC",
            $parent_store_id,
            $parent_store_id
        ));
    }

    /**
     * Check if user has access to a store (parent or filiale)
     */
    public static function has_access($user_id, $store_id) {
        global $wpdb;

        $parent_id = self::get_parent_id($store_id);

        // Check if user owns the parent store
        $owner_id = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $parent_id
        ));

        return intval($owner_id) === intval($user_id);
    }

    /**
     * Get current active filiale from session
     */
    public static function get_current_filiale() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        return isset($_SESSION['ppv_current_filiale_id'])
            ? intval($_SESSION['ppv_current_filiale_id'])
            : null;
    }

    /**
     * Set current active filiale in session
     */
    public static function set_current_filiale($store_id) {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $store_id = intval($store_id);

        error_log("🏪 [FILIALE] set_current_filiale({$store_id}) START");
        error_log("🏪 [FILIALE] BEFORE: ppv_store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        error_log("🏪 [FILIALE] BEFORE: ppv_vendor_store_id=" . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));
        error_log("🏪 [FILIALE] BEFORE: ppv_current_filiale_id=" . ($_SESSION['ppv_current_filiale_id'] ?? 'EMPTY'));

        // ✅ CORRECT: Only set ppv_current_filiale_id, DON'T overwrite base store!
        // ppv_store_id and ppv_vendor_store_id should ALWAYS stay as original handler store
        // ppv_current_store() will prioritize ppv_current_filiale_id anyway!
        $_SESSION['ppv_current_filiale_id'] = $store_id;

        error_log("🏪 [FILIALE] AFTER SET: ppv_current_filiale_id=" . $_SESSION['ppv_current_filiale_id']);
        error_log("🏪 [FILIALE] ppv_store_id UNCHANGED: " . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        error_log("🏪 [FILIALE] ppv_vendor_store_id UNCHANGED: " . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));

        // Force session write
        error_log("🏪 [FILIALE] Calling session_write_close()...");
        @session_write_close();

        // Flush WordPress cache
        wp_cache_flush();

        // Flush database cache
        global $wpdb;
        $wpdb->flush();

        // Restart session for continued use
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            error_log("🏪 [FILIALE] Session restarted. VERIFY: ppv_store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        }

        error_log("🏪 [FILIALE] set_current_filiale({$store_id}) COMPLETE");
    }

    /**
     * AJAX: Create new filiale (branch)
     * Copies data from parent store
     */
    public static function ajax_create_filiale() {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Auth check using PPV_Permissions (same as QR-Center)
        if (class_exists('PPV_Permissions')) {
            $auth_check = PPV_Permissions::check_handler();
            if (is_wp_error($auth_check)) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        } else {
            // Fallback if PPV_Permissions not available
            if (!is_user_logged_in() && empty($_SESSION['ppv_store_id'])) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        }

        $parent_store_id = intval($_POST['parent_store_id'] ?? 0);
        $filiale_name = sanitize_text_field($_POST['filiale_name'] ?? '');

        if (!$parent_store_id || !$filiale_name) {
            wp_send_json_error(['msg' => 'Missing required fields']);
            return;
        }

        global $wpdb;

        // Get parent store data
        $parent_store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $parent_store_id
        ), ARRAY_A);

        if (!$parent_store) {
            wp_send_json_error(['msg' => 'Parent store not found']);
            return;
        }

        // Prepare filiale data (copy from parent, but clear location-specific fields)
        $filiale_data = [
            'parent_store_id' => $parent_store_id,
            'user_id' => $parent_store['user_id'],
            'name' => $filiale_name,
            'company_name' => $parent_store['company_name'],
            'contact_person' => $parent_store['contact_person'],
            'tax_id' => $parent_store['tax_id'],
            'is_taxable' => $parent_store['is_taxable'],
            'country' => $parent_store['country'],
            'phone' => $parent_store['phone'],
            'email' => $parent_store['email'],
            'password' => $parent_store['password'],
            'website' => $parent_store['website'],
            'slogan' => $parent_store['slogan'],
            'category' => $parent_store['category'],
            'description' => $parent_store['description'],
            'logo' => $parent_store['logo'],
            'cover' => $parent_store['cover'],
            'gallery' => $parent_store['gallery'],
            'opening_hours' => $parent_store['opening_hours'],
            'facebook' => $parent_store['facebook'],
            'instagram' => $parent_store['instagram'],
            'tiktok' => $parent_store['tiktok'],
            'whatsapp' => $parent_store['whatsapp'],
            'timezone' => $parent_store['timezone'],
            'qr_secret' => bin2hex(random_bytes(16)),
            'store_key' => bin2hex(random_bytes(32)),
            'pos_token' => md5(uniqid(rand(), true)),
            'pos_api_key' => bin2hex(random_bytes(32)),
            'pos_enabled' => 1,
            'pos_pin' => '1234',
            'active' => 1,
            'visible' => 1,
            'subscription_status' => $parent_store['subscription_status'],
            'trial_ends_at' => $parent_store['trial_ends_at'],
            // Location fields are empty - user must fill them
            'address' => '',
            'plz' => '',
            'city' => '',
            'latitude' => null,
            'longitude' => null,
            'store_slug' => sanitize_title($filiale_name),
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql'),
        ];

        // Insert filiale
        $result = $wpdb->insert(
            $wpdb->prefix . 'ppv_stores',
            $filiale_data
        );

        if ($result === false) {
            wp_send_json_error(['msg' => 'Failed to create filiale']);
            return;
        }

        $new_filiale_id = $wpdb->insert_id;

        // Set as current filiale
        self::set_current_filiale($new_filiale_id);

        wp_send_json_success([
            'msg' => 'Filiale created successfully!',
            'filiale_id' => $new_filiale_id,
            'filiale_name' => $filiale_name
        ]);
    }

    /**
     * AJAX: Switch active filiale
     */
    public static function ajax_switch_filiale() {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Auth check using PPV_Permissions (same as QR-Center)
        if (class_exists('PPV_Permissions')) {
            $auth_check = PPV_Permissions::check_handler();
            if (is_wp_error($auth_check)) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        } else {
            // Fallback if PPV_Permissions not available
            if (!is_user_logged_in() && empty($_SESSION['ppv_store_id'])) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        }

        $filiale_id = intval($_POST['filiale_id'] ?? 0);

        if (!$filiale_id) {
            wp_send_json_error(['msg' => 'Invalid filiale ID']);
            return;
        }

        // Verify filiale exists
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $filiale_id
        ));

        if (!$exists) {
            wp_send_json_error(['msg' => 'Filiale not found']);
            return;
        }

        // Set as current (this handles all session vars + cache flush)
        self::set_current_filiale($filiale_id);

        wp_send_json_success([
            'msg' => 'Filiale switched!',
            'filiale_id' => $filiale_id
        ]);
    }

    /**
     * AJAX: Get list of filialen for dropdown
     */
    public static function ajax_get_filialen() {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Auth check using PPV_Permissions (same as QR-Center)
        if (class_exists('PPV_Permissions')) {
            $auth_check = PPV_Permissions::check_handler();
            if (is_wp_error($auth_check)) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        } else {
            // Fallback if PPV_Permissions not available
            if (!is_user_logged_in() && empty($_SESSION['ppv_store_id'])) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        }

        $parent_store_id = intval($_POST['parent_store_id'] ?? 0);

        if (!$parent_store_id) {
            wp_send_json_error(['msg' => 'Missing parent store ID']);
            return;
        }

        $filialen = self::get_filialen($parent_store_id);

        wp_send_json_success([
            'filialen' => $filialen,
            'current' => self::get_current_filiale()
        ]);
    }
}

PPV_Filiale::hooks();

