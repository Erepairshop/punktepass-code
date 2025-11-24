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
        add_action('wp_ajax_ppv_check_filiale_limit', [__CLASS__, 'ajax_check_filiale_limit']);
        add_action('wp_ajax_nopriv_ppv_check_filiale_limit', [__CLASS__, 'ajax_check_filiale_limit']);
        add_action('wp_ajax_ppv_request_more_filialen', [__CLASS__, 'ajax_request_more_filialen']);
        add_action('wp_ajax_nopriv_ppv_request_more_filialen', [__CLASS__, 'ajax_request_more_filialen']);
    }

    /**
     * Ensure parent_store_id and max_filialen columns exist in ppv_stores table
     * Runs on every init but only adds columns once
     */
    public static function ensure_db_column() {
        global $wpdb;

        $table_name = $wpdb->prefix . 'ppv_stores';

        // Check if parent_store_id column already exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'parent_store_id'",
            DB_NAME,
            $table_name
        ));

        // Add parent_store_id column if it doesn't exist
        if (empty($column_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN parent_store_id BIGINT(20) UNSIGNED NULL DEFAULT NULL AFTER id,
                ADD INDEX idx_parent_store (parent_store_id)
            ");
        }

        // Check if max_filialen column already exists
        $max_filialen_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = %s
             AND TABLE_NAME = %s
             AND COLUMN_NAME = 'max_filialen'",
            DB_NAME,
            $table_name
        ));

        // Add max_filialen column if it doesn't exist (default: 1)
        if (empty($max_filialen_exists)) {
            $wpdb->query("
                ALTER TABLE {$table_name}
                ADD COLUMN max_filialen INT(11) UNSIGNED NOT NULL DEFAULT 1 AFTER parent_store_id
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
     * Get filiale count for a parent store (including parent itself)
     */
    public static function get_filiale_count($parent_store_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_stores
             WHERE parent_store_id = %d OR id = %d",
            $parent_store_id,
            $parent_store_id
        ));
    }

    /**
     * Get max filialen allowed for a parent store
     */
    public static function get_max_filialen($parent_store_id) {
        global $wpdb;

        $max = $wpdb->get_var($wpdb->prepare(
            "SELECT max_filialen FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $parent_store_id
        ));

        // Default to 1 if not set
        return $max ? (int) $max : 1;
    }

    /**
     * Check if a new filiale can be added
     * Returns: ['can_add' => bool, 'current' => int, 'max' => int]
     */
    public static function can_add_filiale($parent_store_id) {
        $current = self::get_filiale_count($parent_store_id);
        $max = self::get_max_filialen($parent_store_id);

        return [
            'can_add' => $current < $max,
            'current' => $current,
            'max' => $max
        ];
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

        ppv_log("🏪 [FILIALE] set_current_filiale({$store_id}) START");
        ppv_log("🏪 [FILIALE] BEFORE: ppv_store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        ppv_log("🏪 [FILIALE] BEFORE: ppv_vendor_store_id=" . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));
        ppv_log("🏪 [FILIALE] BEFORE: ppv_current_filiale_id=" . ($_SESSION['ppv_current_filiale_id'] ?? 'EMPTY'));

        // ✅ CORRECT: Only set ppv_current_filiale_id, DON'T overwrite base store!
        // ppv_store_id and ppv_vendor_store_id should ALWAYS stay as original handler store
        // ppv_current_store() will prioritize ppv_current_filiale_id anyway!
        $_SESSION['ppv_current_filiale_id'] = $store_id;

        ppv_log("🏪 [FILIALE] AFTER SET: ppv_current_filiale_id=" . $_SESSION['ppv_current_filiale_id']);
        ppv_log("🏪 [FILIALE] ppv_store_id UNCHANGED: " . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        ppv_log("🏪 [FILIALE] ppv_vendor_store_id UNCHANGED: " . ($_SESSION['ppv_vendor_store_id'] ?? 'EMPTY'));

        // Force session write
        ppv_log("🏪 [FILIALE] Calling session_write_close()...");
        @session_write_close();

        // Flush WordPress cache
        wp_cache_flush();

        // Flush database cache
        global $wpdb;
        $wpdb->flush();

        // Restart session for continued use
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
            ppv_log("🏪 [FILIALE] Session restarted. VERIFY: ppv_store_id=" . ($_SESSION['ppv_store_id'] ?? 'EMPTY'));
        }

        ppv_log("🏪 [FILIALE] set_current_filiale({$store_id}) COMPLETE");
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
        $filiale_city = sanitize_text_field($_POST['city'] ?? '');
        $filiale_plz = sanitize_text_field($_POST['plz'] ?? '');

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

        // Check filiale limit
        $limit_info = self::can_add_filiale($parent_store_id);
        if (!$limit_info['can_add']) {
            wp_send_json_error([
                'msg' => 'Filiale-Limit erreicht',
                'limit_reached' => true,
                'current' => $limit_info['current'],
                'max' => $limit_info['max']
            ]);
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
            // Location fields from form input
            'address' => '',
            'plz' => $filiale_plz,
            'city' => $filiale_city,
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

    /**
     * AJAX: Check filiale limit before showing add modal
     */
    public static function ajax_check_filiale_limit() {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Auth check
        if (class_exists('PPV_Permissions')) {
            $auth_check = PPV_Permissions::check_handler();
            if (is_wp_error($auth_check)) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        }

        $parent_store_id = intval($_POST['parent_store_id'] ?? 0);

        if (!$parent_store_id) {
            wp_send_json_error(['msg' => 'Missing parent store ID']);
            return;
        }

        $limit_info = self::can_add_filiale($parent_store_id);

        wp_send_json_success([
            'can_add' => $limit_info['can_add'],
            'current' => $limit_info['current'],
            'max' => $limit_info['max']
        ]);
    }

    /**
     * AJAX: Request more filialen (send email to info@punktepass.de)
     */
    public static function ajax_request_more_filialen() {
        // Start session if needed
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        // Auth check
        if (class_exists('PPV_Permissions')) {
            $auth_check = PPV_Permissions::check_handler();
            if (is_wp_error($auth_check)) {
                wp_send_json_error(['msg' => 'Not authenticated']);
                return;
            }
        }

        global $wpdb;

        $parent_store_id = intval($_POST['parent_store_id'] ?? 0);
        $contact_email = sanitize_email($_POST['contact_email'] ?? '');
        $contact_phone = sanitize_text_field($_POST['contact_phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (!$parent_store_id) {
            wp_send_json_error(['msg' => 'Missing parent store ID']);
            return;
        }

        if (empty($contact_email) && empty($contact_phone)) {
            wp_send_json_error(['msg' => 'Bitte geben Sie eine E-Mail oder Telefonnummer an']);
            return;
        }

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, name, company_name, email, phone, city FROM {$wpdb->prefix}ppv_stores WHERE id = %d LIMIT 1",
            $parent_store_id
        ));

        if (!$store) {
            wp_send_json_error(['msg' => 'Store not found']);
            return;
        }

        // Get current filiale count
        $limit_info = self::can_add_filiale($parent_store_id);

        // Build email
        $to = 'info@punktepass.de';
        $subject = 'Anfrage: Mehr Filialen - ' . ($store->company_name ?: $store->name);

        $body = "Neue Anfrage für zusätzliche Filialen\n";
        $body .= "=====================================\n\n";
        $body .= "Handler Information:\n";
        $body .= "- Store ID: {$store->id}\n";
        $body .= "- Firma: " . ($store->company_name ?: $store->name) . "\n";
        $body .= "- Stadt: {$store->city}\n";
        $body .= "- Registrierte E-Mail: {$store->email}\n";
        $body .= "- Registriertes Telefon: {$store->phone}\n\n";

        $body .= "Aktuelle Filialen:\n";
        $body .= "- Aktuell: {$limit_info['current']} Filiale(n)\n";
        $body .= "- Maximum: {$limit_info['max']} Filiale(n)\n\n";

        $body .= "Kontaktdaten für Rückruf:\n";
        $body .= "- E-Mail: {$contact_email}\n";
        $body .= "- Telefon: {$contact_phone}\n\n";

        if (!empty($message)) {
            $body .= "Nachricht:\n";
            $body .= "----------\n";
            $body .= $message . "\n\n";
        }

        $body .= "=====================================\n";
        $body .= "Gesendet am: " . current_time('Y-m-d H:i:s') . "\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: PunktePass System <noreply@punktepass.de>'
        ];

        // Add reply-to if contact email provided
        if (!empty($contact_email)) {
            $headers[] = 'Reply-To: ' . $contact_email;
        }

        // Send email
        $sent = wp_mail($to, $subject, $body, $headers);

        if ($sent) {
            error_log("[PPV_Filiale] More filialen request sent for store #{$parent_store_id}");
            wp_send_json_success([
                'msg' => 'Ihre Anfrage wurde erfolgreich gesendet. Wir melden uns bald bei Ihnen!'
            ]);
        } else {
            error_log("[PPV_Filiale] Failed to send more filialen request for store #{$parent_store_id}");
            wp_send_json_error([
                'msg' => 'Fehler beim Senden der Anfrage. Bitte versuchen Sie es später erneut.'
            ]);
        }
    }
}

PPV_Filiale::hooks();

