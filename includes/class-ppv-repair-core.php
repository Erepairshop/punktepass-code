<?php
/**
 * PunktePass - Repair Form Module Core
 * Standalone routing (no WP theme), DB migrations, AJAX handlers, email, legal pages
 *
 * URL Structure:
 *   /formular              → Registration page
 *   /formular/admin        → Admin dashboard (requires login)
 *   /formular/admin/login  → Admin login
 *   /formular/{slug}       → Public repair form
 *   /formular/{slug}/datenschutz|agb|impressum → Legal pages
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Core {

    /** ============================================================
     * Hooks
     * ============================================================ */
    public static function hooks() {
        // Standalone route handler - runs early on init, before WP theme
        add_action('init', [__CLASS__, 'handle_routes'], 1);

        // DB migration
        add_action('admin_init', [__CLASS__, 'run_migrations'], 6);

        // AJAX: submit repair form (public, no login required)
        add_action('wp_ajax_ppv_repair_submit', [__CLASS__, 'ajax_submit_repair']);
        add_action('wp_ajax_nopriv_ppv_repair_submit', [__CLASS__, 'ajax_submit_repair']);

        // AJAX: customer lookup for returning customers (public, no login required)
        add_action('wp_ajax_ppv_repair_customer_lookup', [__CLASS__, 'ajax_customer_lookup']);
        add_action('wp_ajax_nopriv_ppv_repair_customer_lookup', [__CLASS__, 'ajax_customer_lookup']);

        // AJAX: repair comments
        add_action('wp_ajax_ppv_repair_comment_add', [__CLASS__, 'ajax_comment_add']);
        add_action('wp_ajax_nopriv_ppv_repair_comment_add', [__CLASS__, 'ajax_comment_add']);
        add_action('wp_ajax_ppv_repair_comment_delete', [__CLASS__, 'ajax_comment_delete']);
        add_action('wp_ajax_nopriv_ppv_repair_comment_delete', [__CLASS__, 'ajax_comment_delete']);
        add_action('wp_ajax_ppv_repair_comments_list', [__CLASS__, 'ajax_comments_list']);
        add_action('wp_ajax_nopriv_ppv_repair_comments_list', [__CLASS__, 'ajax_comments_list']);

        // AJAX: repair registration
        add_action('wp_ajax_nopriv_ppv_repair_register', [__CLASS__, 'ajax_register']);
        add_action('wp_ajax_ppv_repair_register', [__CLASS__, 'ajax_register']);

        // AJAX: repair admin login
        add_action('wp_ajax_nopriv_ppv_repair_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_ppv_repair_login', [__CLASS__, 'ajax_login']);

        // AJAX: repair admin actions (need nopriv because repair admin uses session auth, not WP login)
        $admin_actions = [
            'ppv_repair_update_status'  => [__CLASS__, 'ajax_update_status'],
            'ppv_repair_search'         => [__CLASS__, 'ajax_search_repairs'],
            'ppv_repair_save_settings'  => [__CLASS__, 'ajax_save_settings'],
            'ppv_repair_upload_logo'    => [__CLASS__, 'ajax_upload_logo'],
            'ppv_repair_logout'         => [__CLASS__, 'ajax_logout'],
            'ppv_repair_user_search'    => [__CLASS__, 'ajax_user_search'],
            'ppv_repair_delete'         => [__CLASS__, 'ajax_delete_repair'],
            'ppv_repair_reward_approve' => [__CLASS__, 'ajax_reward_approve'],
            'ppv_repair_reward_reject'  => [__CLASS__, 'ajax_reward_reject'],
            'ppv_repair_send_email'     => [__CLASS__, 'ajax_send_repair_email'],
        ];
        foreach ($admin_actions as $action => $callback) {
            add_action("wp_ajax_{$action}", $callback);
            add_action("wp_ajax_nopriv_{$action}", $callback);
        }

        // AJAX: invoice actions (also need nopriv for session-based auth)
        $invoice_actions = [
            'ppv_repair_invoice_pdf'     => ['PPV_Repair_Invoice', 'ajax_download_pdf'],
            'ppv_repair_invoice_csv'     => ['PPV_Repair_Invoice', 'ajax_export_csv'],
            'ppv_repair_invoices_list'   => ['PPV_Repair_Invoice', 'ajax_list_invoices'],
            'ppv_repair_invoice_update'  => ['PPV_Repair_Invoice', 'ajax_update_invoice'],
            'ppv_repair_invoice_delete'  => ['PPV_Repair_Invoice', 'ajax_delete_invoice'],
            'ppv_repair_invoice_create'  => ['PPV_Repair_Invoice', 'ajax_create_invoice'],
            'ppv_repair_invoice_email'   => ['PPV_Repair_Invoice', 'ajax_send_email'],
            'ppv_repair_invoice_reminder' => ['PPV_Repair_Invoice', 'ajax_send_reminder'],
            'ppv_repair_invoice_bulk'    => ['PPV_Repair_Invoice', 'ajax_bulk_operation'],
            'ppv_repair_angebot_create'  => ['PPV_Repair_Invoice', 'ajax_create_angebot'],
            'ppv_repair_customer_search' => ['PPV_Repair_Invoice', 'ajax_customer_search'],
            'ppv_repair_customer_save'   => ['PPV_Repair_Invoice', 'ajax_customer_save'],
            'ppv_repair_customers_list'  => ['PPV_Repair_Invoice', 'ajax_customers_list'],
            'ppv_repair_customer_delete' => ['PPV_Repair_Invoice', 'ajax_customer_delete'],
            'ppv_repair_billbee_import'  => ['PPV_Repair_Invoice', 'ajax_billbee_import'],
            'ppv_repair_erepairshop_import' => ['PPV_Repair_Invoice', 'ajax_erepairshop_import'],
        ];
        foreach ($invoice_actions as $action => $callback) {
            add_action("wp_ajax_{$action}", $callback);
            add_action("wp_ajax_nopriv_{$action}", $callback);
        }
    }

    /** ============================================================
     * Route Handler - Intercepts /formular/* URLs
     * Serves standalone HTML pages (no WP theme, no header/footer)
     * ============================================================ */
    public static function handle_routes() {
        $request_uri = $_SERVER['REQUEST_URI'] ?? '';
        $path = parse_url($request_uri, PHP_URL_PATH);
        $path = rtrim($path, '/');

        // Only handle /formular routes
        if ($path !== '/formular' && strpos($path, '/formular/') !== 0) {
            return;
        }

        // Start session for auth with longer lifetime (30 days)
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $lifetime = 30 * 24 * 60 * 60; // 30 days in seconds
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            ini_set('session.gc_maxlifetime', $lifetime);
            @session_start();
        }

        // /formular → Registration page
        if ($path === '/formular') {
            PPV_Repair_Registration::render_standalone();
            exit;
        }

        // /formular/admin/login → Login page
        if ($path === '/formular/admin/login') {
            PPV_Repair_Admin::render_login();
            exit;
        }

        // /formular/admin → Admin dashboard (requires auth)
        if ($path === '/formular/admin') {
            if (!self::is_repair_admin_logged_in()) {
                header('Location: /formular/admin/login');
                exit;
            }
            PPV_Repair_Admin::render_standalone();
            exit;
        }

        // /formular/{slug}/status/{token} → Customer tracking page
        if (preg_match('#^/formular/([^/]+)/status/([a-f0-9]{32})$#', $path, $m)) {
            $store = self::get_store_by_slug($m[1]);
            $token = $m[2];
            if (!$store) {
                self::send_error_page(404, 'Nicht gefunden', 'Diese Seite existiert nicht.');
                exit;
            }
            echo self::render_tracking_page($store, $token);
            exit;
        }

        // /formular/{slug}/datenschutz|agb|impressum → Legal pages
        if (preg_match('#^/formular/([^/]+)/(datenschutz|agb|impressum)$#', $path, $m)) {
            $store = self::get_store_by_slug($m[1]);
            if (!$store) {
                self::send_error_page(404, 'Formular nicht gefunden', 'Dieses Reparaturformular existiert nicht.');
                exit;
            }
            echo self::render_legal_page($store, $m[2]);
            exit;
        }

        // /formular/{slug} → Public repair form
        if (preg_match('#^/formular/([^/]+)$#', $path, $m)) {
            $slug = $m[1];
            // Skip reserved slugs
            if (in_array($slug, ['admin', 'login', 'register'])) return;

            $store = self::get_store_by_slug($slug);
            if (!$store) {
                self::send_error_page(404, 'Formular nicht gefunden', 'Dieses Reparaturformular existiert nicht oder ist deaktiviert.');
                exit;
            }

            $limit_reached = (!$store->repair_premium && $store->repair_form_count >= $store->repair_form_limit);
            echo PPV_Repair_Form::render_standalone_page($store, $limit_reached);
            exit;
        }
    }

    /** ============================================================
     * Check if repair admin is logged in
     * ============================================================ */
    public static function is_repair_admin_logged_in() {
        return !empty($_SESSION['ppv_repair_store_id']);
    }

    /** ============================================================
     * Get store by slug or store_key
     * ============================================================ */
    public static function get_store_by_slug($slug) {
        global $wpdb;
        $slug = sanitize_title($slug);

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_slug = %s AND repair_enabled = 1 AND active = 1",
            $slug
        ));

        if (!$store) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_key = %s AND repair_enabled = 1 AND active = 1",
                sanitize_text_field($slug)
            ));
        }

        return $store;
    }

    /** ============================================================
     * AJAX: Login for Repair Admin
     * ============================================================ */
    public static function ajax_login() {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $email = sanitize_email($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Bitte E-Mail und Passwort eingeben']);
        }

        $store_id = null;
        $store_name = null;
        $store_slug = null;
        $user_id = null;
        $user_type = 'repair_admin';
        $login_success = false;

        // Method 1: Try repair-specific user login (ppv_users table)
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT u.*, s.id AS store_id, s.name AS store_name, s.store_slug
             FROM {$prefix}ppv_users u
             JOIN {$prefix}ppv_stores s ON s.user_id = u.id AND s.repair_enabled = 1
             WHERE u.email = %s AND u.active = 1
             LIMIT 1",
            $email
        ));

        if ($user && password_verify($password, $user->password)) {
            $login_success = true;
            $user_id = $user->id;
            $user_type = $user->user_type ?: 'repair_admin';
            $store_id = $user->store_id;
            $store_name = $user->store_name;
            $store_slug = $user->store_slug;
        }

        // Method 2: Try PunktePass Händler login (ppv_stores table password)
        if (!$login_success) {
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_stores
                 WHERE email = %s AND active = 1 AND repair_enabled = 1
                 LIMIT 1",
                $email
            ));

            if ($store && !empty($store->password) && password_verify($password, $store->password)) {
                $login_success = true;
                $store_id = $store->id;
                $store_name = $store->name;
                $store_slug = $store->store_slug;
                $user_type = 'haendler';

                // Get or create vendor user for session
                $vendor_user = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$prefix}ppv_users WHERE email = %s AND user_type = 'vendor' LIMIT 1",
                    $email
                ));
                $user_id = $vendor_user ? $vendor_user->id : $store->user_id;
            }
        }

        if (!$login_success) {
            wp_send_json_error(['message' => 'E-Mail oder Passwort falsch']);
        }

        // Set session with longer lifetime (30 days)
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $lifetime = 30 * 24 * 60 * 60; // 30 days
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            ini_set('session.gc_maxlifetime', $lifetime);
            @session_start();
        }
        // Regenerate session ID on login for security
        session_regenerate_id(true);

        $_SESSION['ppv_user_id'] = $user_id;
        $_SESSION['ppv_user_type'] = $user_type;
        $_SESSION['ppv_repair_store_id'] = $store_id;
        $_SESSION['ppv_repair_store_name'] = $store_name;
        $_SESSION['ppv_repair_store_slug'] = $store_slug;

        // Also set PunktePass session vars so QR Center / Händler pages work
        $_SESSION['ppv_store_id'] = $store_id;
        $_SESSION['ppv_vendor_store_id'] = $store_id;
        $_SESSION['ppv_active_store'] = $store_id;
        $_SESSION['ppv_current_filiale_id'] = $store_id;

        wp_send_json_success(['redirect' => '/formular/admin']);
    }

    /** ============================================================
     * Database Migrations
     * ============================================================ */
    public static function run_migrations() {
        global $wpdb;

        $version = get_option('ppv_repair_migration_version', '0');

        if (version_compare($version, '1.0', '<')) {
            $charset = $wpdb->get_charset_collate();
            $table = $wpdb->prefix . 'ppv_repairs';

            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50) NULL,
                device_brand VARCHAR(100) NULL,
                device_model VARCHAR(255) NULL,
                device_imei VARCHAR(50) NULL,
                device_pattern VARCHAR(100) NULL,
                problem_description TEXT NOT NULL,
                accessories TEXT NULL,
                notes TEXT NULL,
                status ENUM('new','in_progress','waiting_parts','done','delivered','cancelled') DEFAULT 'new',
                points_awarded INT DEFAULT 0,
                estimated_cost DECIMAL(10,2) NULL,
                final_cost DECIMAL(10,2) NULL,
                signature_url VARCHAR(500) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                completed_at DATETIME NULL,
                delivered_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_store (store_id),
                KEY idx_store_status (store_id, status),
                KEY idx_email (customer_email),
                KEY idx_created (store_id, created_at DESC)
            ) {$charset};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            $stores_table = $wpdb->prefix . 'ppv_stores';
            $repair_columns = [
                'repair_enabled'        => "TINYINT(1) DEFAULT 0",
                'repair_points_per_form' => "INT DEFAULT 2",
                'repair_form_count'     => "INT UNSIGNED DEFAULT 0",
                'repair_form_limit'     => "INT UNSIGNED DEFAULT 50",
                'repair_premium'        => "TINYINT(1) DEFAULT 0",
                'repair_company_name'   => "VARCHAR(255) NULL",
                'repair_owner_name'     => "VARCHAR(255) NULL",
                'repair_tax_id'         => "VARCHAR(50) NULL",
                'repair_color'          => "VARCHAR(10) DEFAULT '#667eea'",
            ];

            foreach ($repair_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
                }
            }

            update_option('ppv_repair_migration_version', '1.0');
        }

        // v1.1: PunktePass toggle, reward settings, form customization
        if (version_compare($version, '1.1', '<')) {
            $stores_table = $wpdb->prefix . 'ppv_stores';
            $new_columns = [
                'repair_punktepass_enabled' => "TINYINT(1) DEFAULT 1",
                'repair_reward_name'        => "VARCHAR(255) DEFAULT '10 Euro Rabatt'",
                'repair_reward_description' => "VARCHAR(500) DEFAULT '10 Euro Rabatt auf Ihre nächste Reparatur'",
                'repair_required_points'    => "INT DEFAULT 4",
                'repair_form_title'         => "VARCHAR(255) DEFAULT 'Reparaturauftrag'",
                'repair_form_subtitle'      => "VARCHAR(500) NULL",
                'repair_service_type'       => "VARCHAR(100) DEFAULT 'Allgemein'",
                'repair_field_config'       => "TEXT NULL",
            ];

            foreach ($new_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
                }
            }

            update_option('ppv_repair_migration_version', '1.1');
        }

        // v1.2: Invoice system + reward types
        if (version_compare($version, '1.2', '<')) {
            $charset = $wpdb->get_charset_collate();
            $inv_table = $wpdb->prefix . 'ppv_repair_invoices';

            $sql = "CREATE TABLE IF NOT EXISTS {$inv_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                repair_id BIGINT(20) UNSIGNED NOT NULL,
                invoice_number VARCHAR(50) NOT NULL,
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50) NULL,
                device_info VARCHAR(500) NULL,
                description TEXT NULL,
                subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
                discount_type ENUM('none','discount_fixed','discount_percent','free_product') DEFAULT 'none',
                discount_value DECIMAL(10,2) DEFAULT 0,
                discount_description VARCHAR(255) NULL,
                net_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
                vat_rate DECIMAL(5,2) DEFAULT 0,
                vat_amount DECIMAL(10,2) DEFAULT 0,
                total DECIMAL(10,2) NOT NULL DEFAULT 0,
                is_kleinunternehmer TINYINT(1) DEFAULT 0,
                punktepass_reward_applied TINYINT(1) DEFAULT 0,
                points_used INT DEFAULT 0,
                notes TEXT NULL,
                status ENUM('draft','sent','paid','cancelled') DEFAULT 'draft',
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                paid_at DATETIME NULL,
                PRIMARY KEY (id),
                KEY idx_store (store_id),
                KEY idx_repair (repair_id),
                KEY idx_number (store_id, invoice_number),
                KEY idx_created (store_id, created_at DESC)
            ) {$charset};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            $stores_table = $wpdb->prefix . 'ppv_stores';
            $inv_columns = [
                'repair_invoice_prefix'      => "VARCHAR(20) DEFAULT 'RE-'",
                'repair_invoice_next_number'  => "INT UNSIGNED DEFAULT 1",
                'repair_reward_type'          => "VARCHAR(30) DEFAULT 'discount_fixed'",
                'repair_reward_value'         => "DECIMAL(10,2) DEFAULT 10.00",
                'repair_reward_product'       => "VARCHAR(255) NULL",
                'repair_vat_enabled'         => "TINYINT(1) DEFAULT 1",
                'repair_vat_rate'            => "DECIMAL(5,2) DEFAULT 19.00",
            ];

            foreach ($inv_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
                }
            }

            update_option('ppv_repair_migration_version', '1.2');
        }

        // v1.3: Line items for invoices
        if (version_compare($version, '1.3', '<')) {
            $inv_table = $wpdb->prefix . 'ppv_repair_invoices';
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$inv_table} LIKE 'line_items'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN line_items TEXT NULL AFTER description");
            }
            update_option('ppv_repair_migration_version', '1.3');
        }

        // v1.4: Fix existing formular stores missing subscription_status
        if (version_compare($version, '1.4', '<')) {
            $stores_table = $wpdb->prefix . 'ppv_stores';
            $wpdb->query(
                "UPDATE {$stores_table}
                 SET subscription_status = 'trial'
                 WHERE repair_enabled = 1
                   AND (subscription_status IS NULL OR subscription_status = '')"
            );
            update_option('ppv_repair_migration_version', '1.4');
        }

        // v1.5: Customer management + standalone invoices
        if (version_compare($version, '1.5', '<')) {
            $charset = $wpdb->get_charset_collate();

            // Create customers table
            $cust_table = $wpdb->prefix . 'ppv_repair_customers';
            $sql = "CREATE TABLE IF NOT EXISTS {$cust_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                company_name VARCHAR(255) NULL,
                tax_id VARCHAR(50) NULL,
                address VARCHAR(255) NULL,
                plz VARCHAR(20) NULL,
                city VARCHAR(100) NULL,
                notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                KEY idx_store (store_id),
                KEY idx_email (store_id, email),
                KEY idx_name (store_id, name)
            ) {$charset};";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);

            // Modify invoices table for standalone invoices
            $inv_table = $wpdb->prefix . 'ppv_repair_invoices';

            // Make repair_id nullable (for standalone invoices)
            $wpdb->query("ALTER TABLE {$inv_table} MODIFY COLUMN repair_id BIGINT(20) UNSIGNED NULL");

            // Add new columns for customer management
            $new_inv_columns = [
                'customer_id'      => "BIGINT(20) UNSIGNED NULL AFTER repair_id",
                'customer_company' => "VARCHAR(255) NULL AFTER customer_phone",
                'customer_tax_id'  => "VARCHAR(50) NULL AFTER customer_company",
                'customer_address' => "VARCHAR(255) NULL AFTER customer_tax_id",
                'customer_plz'     => "VARCHAR(20) NULL AFTER customer_address",
                'customer_city'    => "VARCHAR(100) NULL AFTER customer_plz",
            ];

            foreach ($new_inv_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$inv_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN {$col} {$definition}");
                }
            }

            // Add index for customer_id
            $idx_exists = $wpdb->get_var("SHOW INDEX FROM {$inv_table} WHERE Key_name = 'idx_customer'");
            if (!$idx_exists) {
                $wpdb->query("ALTER TABLE {$inv_table} ADD KEY idx_customer (customer_id)");
            }

            update_option('ppv_repair_migration_version', '1.5');
        }

        // v1.6: Payment method on invoices + email template settings
        if (version_compare($version, '1.6', '<')) {
            $inv_table = $wpdb->prefix . 'ppv_repair_invoices';
            $stores_table = $wpdb->prefix . 'ppv_stores';

            // Add payment_method column to invoices
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$inv_table} LIKE 'payment_method'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN payment_method VARCHAR(50) NULL AFTER paid_at");
            }

            // Add email template columns to stores
            $email_columns = [
                'repair_invoice_email_subject' => "VARCHAR(255) DEFAULT 'Ihre Rechnung {invoice_number}'",
                'repair_invoice_email_body'    => "TEXT NULL",
            ];
            foreach ($email_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
                }
            }

            // Set default email body
            $wpdb->query("UPDATE {$stores_table} SET repair_invoice_email_body = 'Sehr geehrte/r {customer_name},\n\nanbei erhalten Sie Ihre Rechnung {invoice_number} vom {invoice_date}.\n\nGesamtbetrag: {total} €\n\nBei Fragen stehen wir Ihnen gerne zur Verfügung.\n\nMit freundlichen Grüßen,\n{company_name}' WHERE repair_invoice_email_body IS NULL OR repair_invoice_email_body = ''");

            update_option('ppv_repair_migration_version', '1.6');
        }

        // v1.7: Angebot (quote) support
        if (version_compare($version, '1.7', '<')) {
            $inv_table = $wpdb->prefix . 'ppv_repair_invoices';

            // Add doc_type column (rechnung or angebot)
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$inv_table} LIKE 'doc_type'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN doc_type VARCHAR(20) DEFAULT 'rechnung' AFTER invoice_number");
            }

            // Add valid_until for Angebote
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$inv_table} LIKE 'valid_until'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$inv_table} ADD COLUMN valid_until DATE NULL AFTER paid_at");
            }

            // Add angebot_number_prefix and next_number to stores
            $stores_table = $wpdb->prefix . 'ppv_stores';
            $cols = [
                'repair_angebot_prefix' => "VARCHAR(20) DEFAULT 'AG-'",
                'repair_angebot_next_number' => "INT DEFAULT 1",
            ];
            foreach ($cols as $col => $def) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$def}");
                }
            }

            update_option('ppv_repair_migration_version', '1.7');
        }

        // v1.8: Tracking system for customers
        if (version_compare($version, '1.8', '<')) {
            $repairs_table = $wpdb->prefix . 'ppv_repairs';

            // Add tracking_token column
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'tracking_token'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN tracking_token VARCHAR(64) NULL AFTER id");
                $wpdb->query("ALTER TABLE {$repairs_table} ADD INDEX idx_tracking_token (tracking_token)");
            }

            update_option('ppv_repair_migration_version', '1.8');
        }

        // v1.9: Comments system for repairs
        if (version_compare($version, '1.9', '<')) {
            $charset = $wpdb->get_charset_collate();
            $comments_table = $wpdb->prefix . 'ppv_repair_comments';

            $wpdb->query("CREATE TABLE IF NOT EXISTS {$comments_table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                repair_id BIGINT(20) UNSIGNED NOT NULL,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                comment TEXT NOT NULL,
                created_by VARCHAR(100) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id),
                INDEX idx_repair_id (repair_id),
                INDEX idx_store_id (store_id)
            ) {$charset}");

            update_option('ppv_repair_migration_version', '1.9');
        }

        // v2.0: Add customer_address and muster_image columns
        if (version_compare($version, '2.0', '<')) {
            $repairs_table = $wpdb->prefix . 'ppv_repairs';

            // Add customer_address column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'customer_address'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN customer_address TEXT NULL AFTER customer_phone");
            }

            // Add muster_image column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'muster_image'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN muster_image VARCHAR(500) NULL AFTER accessories");
            }

            update_option('ppv_repair_migration_version', '2.0');
        }

        // v2.1: Add reward rejection tracking columns
        if (version_compare($version, '2.1', '<')) {
            $repairs_table = $wpdb->prefix . 'ppv_repairs';

            // Add reward_rejected column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'reward_rejected'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN reward_rejected TINYINT(1) DEFAULT 0 AFTER points_awarded");
            }

            // Add reward_rejection_reason column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'reward_rejection_reason'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN reward_rejection_reason VARCHAR(500) NULL AFTER reward_rejected");
            }

            // Add reward_rejection_date column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'reward_rejection_date'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN reward_rejection_date DATETIME NULL AFTER reward_rejection_reason");
            }

            // Add reward_approved column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'reward_approved'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN reward_approved TINYINT(1) DEFAULT 0 AFTER reward_rejection_date");
            }

            // Add reward_approved_date column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'reward_approved_date'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN reward_approved_date DATETIME NULL AFTER reward_approved");
            }

            update_option('ppv_repair_migration_version', '2.1');
        }

        // v2.2: Add signature_image column
        if (version_compare($version, '2.2', '<')) {
            $repairs_table = $wpdb->prefix . 'ppv_repairs';

            // Add signature_image column if not exists
            $col_exists = $wpdb->get_var("SHOW COLUMNS FROM {$repairs_table} LIKE 'signature_image'");
            if (!$col_exists) {
                $wpdb->query("ALTER TABLE {$repairs_table} ADD COLUMN signature_image LONGTEXT NULL AFTER muster_image");
            }

            update_option('ppv_repair_migration_version', '2.2');
        }
    }

    /** ============================================================
     * AJAX: Submit Repair Form (public)
     * ============================================================ */
    public static function ajax_submit_repair() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_form')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Store nicht gefunden']);
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d AND repair_enabled = 1 AND active = 1",
            $store_id
        ));

        if (!$store) {
            wp_send_json_error(['message' => 'Reparaturformular nicht verfügbar']);
        }

        if (!$store->repair_premium && $store->repair_form_count >= $store->repair_form_limit) {
            wp_send_json_error(['message' => 'Formularlimit erreicht.']);
        }

        $name  = sanitize_text_field($_POST['customer_name'] ?? '');
        $email = sanitize_email($_POST['customer_email'] ?? '');
        $phone = sanitize_text_field($_POST['customer_phone'] ?? '');

        if (empty($name) || empty($email)) {
            wp_send_json_error(['message' => 'Name und E-Mail sind Pflichtfelder']);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse']);
        }

        $device_brand = sanitize_text_field($_POST['device_brand'] ?? '');
        $device_model = sanitize_text_field($_POST['device_model'] ?? '');
        $device_imei  = sanitize_text_field($_POST['device_imei'] ?? '');
        $device_pattern = sanitize_text_field($_POST['device_pattern'] ?? '');
        $problem      = sanitize_textarea_field($_POST['problem_description'] ?? '');
        $accessories  = sanitize_text_field($_POST['accessories'] ?? '[]');
        $address      = sanitize_textarea_field($_POST['customer_address'] ?? '');

        if (empty($problem)) {
            wp_send_json_error(['message' => 'Bitte beschreiben Sie das Problem']);
        }

        // Handle muster pattern (base64 data URL from canvas)
        $muster_image = '';
        if (!empty($_POST['muster_image']) && strpos($_POST['muster_image'], 'data:image/') === 0) {
            // Limit size to prevent abuse (max ~500KB base64)
            if (strlen($_POST['muster_image']) <= 700000) {
                $muster_image = $_POST['muster_image'];
            }
        }

        // Handle signature image (base64 data URL from canvas)
        $signature_image = '';
        if (!empty($_POST['signature_image']) && strpos($_POST['signature_image'], 'data:image/') === 0) {
            // Limit size to prevent abuse (max ~500KB base64)
            if (strlen($_POST['signature_image']) <= 700000) {
                $signature_image = $_POST['signature_image'];
            }
        }

        // Generate unique tracking token
        $tracking_token = bin2hex(random_bytes(16));

        $wpdb->insert("{$prefix}ppv_repairs", [
            'store_id'            => $store_id,
            'tracking_token'      => $tracking_token,
            'customer_name'       => $name,
            'customer_email'      => $email,
            'customer_phone'      => $phone,
            'customer_address'    => $address,
            'device_brand'        => $device_brand,
            'device_model'        => $device_model,
            'device_imei'         => $device_imei,
            'device_pattern'      => $device_pattern,
            'problem_description' => $problem,
            'accessories'         => $accessories,
            'muster_image'        => $muster_image,
            'signature_image'     => $signature_image,
            'status'              => 'new',
            'created_at'          => current_time('mysql'),
            'updated_at'          => current_time('mysql'),
        ]);

        $repair_id = $wpdb->insert_id;
        if (!$repair_id) {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }

        $wpdb->query($wpdb->prepare(
            "UPDATE {$prefix}ppv_stores SET repair_form_count = repair_form_count + 1 WHERE id = %d",
            $store_id
        ));

        $bonus_result = ['user_id' => 0, 'points_added' => 0, 'total_points' => 0, 'is_new_user' => false];

        // Only award points if PunktePass is enabled for this store
        $pp_enabled = isset($store->repair_punktepass_enabled) ? intval($store->repair_punktepass_enabled) : 1;
        if ($pp_enabled) {
            $points = intval($store->repair_points_per_form ?: 2);
            $bonus_result = self::award_repair_bonus($store, $email, $name, $points);

            if ($bonus_result && !empty($bonus_result['user_id'])) {
                $wpdb->update("{$prefix}ppv_repairs", [
                    'user_id'        => $bonus_result['user_id'],
                    'points_awarded' => $bonus_result['points_added'],
                ], ['id' => $repair_id]);
            }
        }

        self::notify_store_owner($store, [
            'repair_id' => $repair_id,
            'name'      => $name,
            'email'     => $email,
            'phone'     => $phone,
            'device'    => trim("{$device_brand} {$device_model}"),
            'problem'   => $problem,
        ]);

        // Send tracking email to customer
        $store_slug = $store->store_slug;
        $tracking_url = home_url("/formular/{$store_slug}/status/{$tracking_token}");
        self::send_tracking_email($store, $email, $name, $repair_id, $tracking_url);

        wp_send_json_success([
            'repair_id'      => $repair_id,
            'tracking_token' => $tracking_token,
            'tracking_url'   => $tracking_url,
            'points_added'   => $bonus_result['points_added'] ?? 0,
            'total_points'   => $bonus_result['total_points'] ?? 0,
            'is_new_user'    => $bonus_result['is_new_user'] ?? false,
        ]);
    }

    /** ============================================================
     * Award Repair Bonus Points
     * ============================================================ */
    public static function award_repair_bonus($store, $email, $name, $points = 2) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $store_id = (int)$store->id;
        $reference = 'Reparatur-Formular Bonus';

        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name FROM {$prefix}ppv_users WHERE email = %s LIMIT 1", $email
        ));

        $is_new_user = false;
        $generated_password = null;

        if (!$user) {
            $is_new_user = true;
            $generated_password = wp_generate_password(8, false, false);
            $name_parts = explode(' ', trim($name), 2);

            $wpdb->insert("{$prefix}ppv_users", [
                'email'        => $email,
                'password'     => password_hash($generated_password, PASSWORD_DEFAULT),
                'first_name'   => $name_parts[0] ?? '',
                'last_name'    => $name_parts[1] ?? '',
                'display_name' => trim($name) ?: ($name_parts[0] ?? ''),
                'qr_token'     => wp_generate_password(10, false, false),
                'login_token'  => bin2hex(random_bytes(32)),
                'user_type'    => 'user',
                'active'       => 1,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ]);
            $user_id = $wpdb->insert_id;
            if (!$user_id) return ['user_id' => 0, 'points_added' => 0, 'total_points' => 0, 'is_new_user' => true];
        } else {
            $user_id = (int)$user->id;
        }

        $today = current_time('Y-m-d');
        $already = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d AND type='bonus' AND reference=%s AND DATE(created)=%s",
            $user_id, $store_id, $reference, $today
        ));

        $points_added = 0;
        if ($already === 0) {
            $wpdb->insert("{$prefix}ppv_points", [
                'user_id' => $user_id, 'store_id' => $store_id, 'points' => $points,
                'type' => 'bonus', 'reference' => $reference, 'created' => current_time('mysql'),
            ]);
            $points_added = $points;

            $wpdb->insert("{$prefix}ppv_pos_log", [
                'store_id' => $store_id, 'user_id' => $user_id, 'email' => $email,
                'message' => "+{$points} Punkte (Reparatur-Bonus)", 'type' => 'qr_scan',
                'points_change' => $points, 'status' => 'ok',
                'ip_address' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'metadata' => json_encode(['type' => 'repair_bonus', 'is_new_user' => $is_new_user]),
                'created_at' => current_time('mysql'),
            ]);

            if (class_exists('PPV_User_Level')) PPV_User_Level::add_lifetime_points($user_id, $points);
        }

        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d",
            $user_id, $store_id
        ));

        if ($points_added > 0) {
            self::send_points_email($store, $email, $name, $points_added, $total_points, $is_new_user, $generated_password);
        }

        return ['user_id' => $user_id, 'points_added' => $points_added, 'total_points' => $total_points, 'is_new_user' => $is_new_user];
    }

    /** Send Points Email */
    private static function send_points_email($store, $email, $name, $points, $total_points, $is_new_user, $password = null) {
        $first_name = explode(' ', trim($name))[0] ?: 'Kunde';
        $store_name = esc_html($store->name ?: $store->company_name ?: 'Reparaturservice');
        $pts_left = max(0, 4 - $total_points);

        $reward = '';
        if ($total_points >= 4) {
            $reward = '<div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #f59e0b;border-radius:12px;padding:20px;margin:0 0 24px;text-align:center;"><div style="font-size:28px;margin-bottom:8px;">&#127942;</div><div style="font-size:18px;font-weight:700;color:#92400e;margin-bottom:8px;">Sie haben 10&euro; Rabatt gewonnen!</div><p style="font-size:13px;color:#78350f;margin:0 0 16px;">Melden Sie sich bei PunktePass an!</p><a href="https://punktepass.de" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:10px;font-weight:700;">Einl&ouml;sen &rarr;</a></div>';
        }

        $progress = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;"><div style="font-size:36px;font-weight:800;color:#059669;">' . ($is_new_user ? '+' . $points : $total_points) . ' Punkte</div><div style="font-size:13px;color:#374151;margin-top:8px;">' . ($pts_left > 0 ? 'Noch ' . $pts_left . ' bis 10&euro; Rabatt!' : '10&euro; Rabatt einl&ouml;sbar!') . '</div></div>';

        if ($is_new_user && $password) {
            $subject = "Willkommen bei PunktePass - {$points} Bonuspunkte von {$store_name}!";
            $creds = '<div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;"><div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;">IHRE ZUGANGSDATEN</div><p style="margin:4px 0;font-size:14px;"><strong>E-Mail:</strong> ' . esc_html($email) . '</p><p style="margin:4px 0;font-size:14px;"><strong>Passwort:</strong> <code>' . esc_html($password) . '</code></p></div>';
        } else {
            $subject = ($total_points >= 4) ? "10 Euro Rabatt bei {$store_name} gewonnen!" : "+{$points} Bonuspunkte von {$store_name}!";
            $creds = '';
        }

        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,sans-serif;"><div style="max-width:560px;margin:0 auto;padding:20px;"><div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;"><h1 style="color:#fff;font-size:22px;margin:0 0 8px;">' . ($is_new_user ? 'Willkommen bei PunktePass!' : '+' . $points . ' Bonuspunkte') . '</h1><p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">' . $store_name . '</p></div><div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;"><p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>' . $progress . $reward . $creds . '<div style="text-align:center;"><a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Punkte ansehen</a></div></div></div></body></html>';

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** Notify Store Owner */
    private static function notify_store_owner($store, $data) {
        if (empty($store->email)) return;
        $subject = "Neue Reparatur #{$data['repair_id']} - {$data['name']}";
        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,sans-serif;"><div style="max-width:560px;margin:0 auto;padding:20px;"><div style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px 16px 0 0;padding:24px 28px;text-align:center;"><h1 style="color:#fff;font-size:20px;margin:0;">Neue Reparaturanfrage</h1></div><div style="background:#fff;padding:28px;border-radius:0 0 16px 16px;"><table style="width:100%;border-collapse:collapse;font-size:14px;color:#374151;"><tr><td style="padding:8px 0;font-weight:600;width:120px;">Auftrag:</td><td>#' . intval($data['repair_id']) . '</td></tr><tr><td style="padding:8px 0;font-weight:600;">Name:</td><td>' . esc_html($data['name']) . '</td></tr><tr><td style="padding:8px 0;font-weight:600;">E-Mail:</td><td>' . esc_html($data['email']) . '</td></tr><tr><td style="padding:8px 0;font-weight:600;">Telefon:</td><td>' . esc_html($data['phone'] ?: '-') . '</td></tr><tr><td style="padding:8px 0;font-weight:600;">Ger&auml;t:</td><td>' . esc_html($data['device'] ?: '-') . '</td></tr></table><div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;"><div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;">PROBLEM</div><div style="font-size:14px;color:#1f2937;line-height:1.5;">' . nl2br(esc_html($data['problem'])) . '</div></div><div style="margin-top:20px;text-align:center;"><a href="https://punktepass.de/formular/admin" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Reparatur verwalten</a></div></div></div></body></html>';
        wp_mail($store->email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** Send Tracking Email to Customer */
    private static function send_tracking_email($store, $email, $name, $repair_id, $tracking_url) {
        if (empty($email)) return;
        $store_name = esc_html($store->repair_company_name ?: $store->name);
        $color = esc_attr($store->repair_color ?: '#667eea');
        $subject = "Reparaturauftrag #{$repair_id} - Bestätigung";
        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,sans-serif;">
<div style="max-width:560px;margin:0 auto;padding:20px;">
<div style="background:linear-gradient(135deg,' . $color . ',color-mix(in srgb,' . $color . ',#000 20%));border-radius:16px 16px 0 0;padding:24px 28px;text-align:center;">
<h1 style="color:#fff;font-size:20px;margin:0;">Reparaturauftrag bestätigt</h1>
</div>
<div style="background:#fff;padding:28px;border-radius:0 0 16px 16px;">
<p style="font-size:15px;color:#374151;margin:0 0 16px 0;">Hallo ' . esc_html($name) . ',</p>
<p style="font-size:14px;color:#6b7280;line-height:1.6;margin:0 0 20px 0;">vielen Dank für Ihren Reparaturauftrag bei <strong>' . $store_name . '</strong>. Wir haben Ihre Anfrage erhalten und werden uns schnellstmöglich darum kümmern.</p>
<div style="background:#f8fafc;border-radius:12px;padding:20px;margin:20px 0;text-align:center;">
<div style="font-size:12px;color:#6b7280;margin-bottom:8px;">IHRE AUFTRAGSNUMMER</div>
<div style="font-size:28px;font-weight:700;color:#1f2937;">#' . intval($repair_id) . '</div>
</div>
<p style="font-size:14px;color:#6b7280;line-height:1.6;margin:0 0 20px 0;">Mit dem folgenden Link können Sie jederzeit den Status Ihrer Reparatur einsehen:</p>
<div style="text-align:center;margin:24px 0;">
<a href="' . esc_url($tracking_url) . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,' . $color . ',color-mix(in srgb,' . $color . ',#000 20%));color:#fff;text-decoration:none;border-radius:12px;font-weight:600;font-size:15px;">Status prüfen</a>
</div>
<p style="font-size:13px;color:#9ca3af;margin:20px 0 0 0;text-align:center;">Oder kopieren Sie diesen Link:<br><a href="' . esc_url($tracking_url) . '" style="color:' . $color . ';word-break:break-all;">' . esc_html($tracking_url) . '</a></p>
</div>
<div style="text-align:center;padding:16px;font-size:12px;color:#9ca3af;">' . $store_name . '</div>
</div></body></html>';
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $store_name . ' <' . ($store->email ?: get_option('admin_email')) . '>',
        ];
        wp_mail($email, $subject, $body, $headers);
    }

    /** AJAX: Lookup customer by email (for form autofill) */
    public static function ajax_customer_lookup() {
        $email = sanitize_email($_POST['email'] ?? '');
        $store_id = intval($_POST['store_id'] ?? 0);
        if (empty($email) || !$store_id) {
            wp_send_json_error(['message' => 'Missing data']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Search in repairs table for this store
        $customer = $wpdb->get_row($wpdb->prepare(
            "SELECT customer_name, customer_email, customer_phone, device_brand, device_model
             FROM {$prefix}ppv_repairs
             WHERE store_id = %d AND customer_email = %s
             ORDER BY created_at DESC LIMIT 1",
            $store_id, $email
        ));

        if ($customer) {
            wp_send_json_success([
                'found' => true,
                'name' => $customer->customer_name,
                'phone' => $customer->customer_phone,
                'brand' => $customer->device_brand,
                'model' => $customer->device_model,
            ]);
        }

        wp_send_json_success(['found' => false]);
    }

    /** ============================================================
     * AJAX: Add comment to repair
     * ============================================================ */
    public static function ajax_comment_add() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');

        if (!$repair_id || empty($comment)) {
            wp_send_json_error(['message' => 'Repair ID und Kommentar erforderlich']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Verify repair belongs to this store
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_repairs WHERE id = %d AND store_id = %d",
            $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);

        // Insert comment
        $wpdb->insert("{$prefix}ppv_repair_comments", [
            'repair_id'  => $repair_id,
            'store_id'   => $store_id,
            'comment'    => $comment,
            'created_by' => $_SESSION['ppv_repair_store_name'] ?? 'Admin',
            'created_at' => current_time('mysql'),
        ]);

        $comment_id = $wpdb->insert_id;
        if (!$comment_id) wp_send_json_error(['message' => 'Fehler beim Speichern']);

        wp_send_json_success([
            'comment_id' => $comment_id,
            'comment' => $comment,
            'created_at' => current_time('d.m.Y H:i'),
            'message' => 'Kommentar hinzugefügt',
        ]);
    }

    /** ============================================================
     * AJAX: Delete comment
     * ============================================================ */
    public static function ajax_comment_delete() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $comment_id = intval($_POST['comment_id'] ?? 0);
        if (!$comment_id) wp_send_json_error(['message' => 'Comment ID erforderlich']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Delete comment (only if belongs to this store)
        $deleted = $wpdb->delete("{$prefix}ppv_repair_comments", [
            'id' => $comment_id,
            'store_id' => $store_id,
        ]);

        if ($deleted) {
            wp_send_json_success(['message' => 'Kommentar gelöscht']);
        } else {
            wp_send_json_error(['message' => 'Kommentar nicht gefunden']);
        }
    }

    /** ============================================================
     * AJAX: List comments for a repair
     * ============================================================ */
    public static function ajax_comments_list() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        if (!$repair_id) wp_send_json_error(['message' => 'Repair ID erforderlich']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $comments = $wpdb->get_results($wpdb->prepare(
            "SELECT id, comment, created_by, created_at
             FROM {$prefix}ppv_repair_comments
             WHERE repair_id = %d AND store_id = %d
             ORDER BY created_at ASC",
            $repair_id, $store_id
        ));

        $result = [];
        foreach ($comments as $c) {
            $result[] = [
                'id' => $c->id,
                'comment' => $c->comment,
                'created_by' => $c->created_by,
                'created_at' => date('d.m.Y H:i', strtotime($c->created_at)),
            ];
        }

        wp_send_json_success(['comments' => $result]);
    }

    /** ============================================================
     * Render Customer Tracking Page
     * ============================================================ */
    private static function render_tracking_page($store, $token) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Look up repair by tracking token
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repairs WHERE store_id = %d AND tracking_token = %s",
            $store->id, $token
        ));

        $store_name = esc_html($store->repair_company_name ?: $store->name);
        $color = esc_attr($store->repair_color ?: '#667eea');
        $logo = esc_url($store->logo ?: PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png');

        ob_start();
        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
    <title>Reparaturstatus - <?php echo $store_name; ?></title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="<?php echo $color; ?>">
    <link rel="icon" href="<?php echo $logo; ?>" type="image/png">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
    <style>
        :root{--color-accent:<?php echo $color; ?>;}
        *{box-sizing:border-box;margin:0;padding:0}
        body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;background:#f4f4f5;color:#1f2937;min-height:100vh;padding:20px}
        .container{max-width:480px;margin:0 auto}
        .card{background:#fff;border-radius:16px;box-shadow:0 4px 20px rgba(0,0,0,0.08);overflow:hidden}
        .header{background:linear-gradient(135deg,var(--color-accent),color-mix(in srgb,var(--color-accent),#000 20%));padding:24px 20px;text-align:center}
        .header img{height:48px;border-radius:8px;margin-bottom:12px}
        .header h1{color:#fff;font-size:18px;font-weight:600;margin:0}
        .header p{color:rgba(255,255,255,0.85);font-size:13px;margin-top:4px}
        .content{padding:24px 20px}
        .status-badge{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:20px;font-size:13px;font-weight:600;margin-bottom:20px}
        .status-neu{background:#fef3c7;color:#92400e}
        .status-in-bearbeitung{background:#dbeafe;color:#1d4ed8}
        .status-warten{background:#fce7f3;color:#9d174d}
        .status-fertig{background:#d1fae5;color:#065f46}
        .status-abgeholt{background:#e5e7eb;color:#374151}
        .repair-id{font-size:32px;font-weight:700;color:#1f2937;margin-bottom:4px}
        .repair-date{font-size:13px;color:#6b7280;margin-bottom:20px}
        .detail-row{display:flex;justify-content:space-between;padding:12px 0;border-bottom:1px solid #f3f4f6;font-size:14px}
        .detail-row:last-child{border-bottom:none}
        .detail-label{color:#6b7280}
        .detail-value{font-weight:500;text-align:right}
        .problem-box{background:#f8fafc;border-radius:12px;padding:16px;margin-top:20px}
        .problem-label{font-size:12px;font-weight:600;color:#6b7280;text-transform:uppercase;margin-bottom:8px}
        .problem-text{font-size:14px;color:#374151;line-height:1.6}
        .timeline{margin-top:24px;padding-top:20px;border-top:1px solid #e5e7eb}
        .timeline-title{font-size:13px;font-weight:600;color:#6b7280;margin-bottom:12px;text-transform:uppercase}
        .timeline-item{display:flex;gap:12px;padding:8px 0}
        .timeline-dot{width:10px;height:10px;border-radius:50%;background:#e5e7eb;flex-shrink:0;margin-top:5px}
        .timeline-dot.active{background:var(--color-accent)}
        .timeline-text{font-size:13px;color:#6b7280}
        .timeline-text.active{color:#1f2937;font-weight:500}
        .not-found{text-align:center;padding:40px 20px}
        .not-found i{font-size:48px;color:#e5e7eb;margin-bottom:16px}
        .not-found h2{font-size:18px;color:#374151;margin-bottom:8px}
        .not-found p{font-size:14px;color:#6b7280}
        .footer{text-align:center;padding:16px;font-size:12px;color:#9ca3af}
        .footer a{color:var(--color-accent);text-decoration:none}
    </style>
</head>
<body>
<div class="container">
    <div class="card">
        <div class="header">
            <?php if ($store->logo): ?>
                <img src="<?php echo $logo; ?>" alt="<?php echo $store_name; ?>">
            <?php endif; ?>
            <h1><?php echo $store_name; ?></h1>
            <p>Reparaturstatus</p>
        </div>

        <?php if (!$repair): ?>
        <div class="not-found">
            <i class="ri-search-line"></i>
            <h2>Reparatur nicht gefunden</h2>
            <p>Der angegebene Tracking-Link ist ungültig oder abgelaufen.</p>
        </div>
        <?php else:
            $status_map = [
                'new' => ['Neu eingegangen', 'status-neu', 'ri-inbox-line'],
                'in_progress' => ['In Bearbeitung', 'status-in-bearbeitung', 'ri-tools-line'],
                'waiting_parts' => ['Warten auf Teile', 'status-warten', 'ri-time-line'],
                'done' => ['Fertig', 'status-fertig', 'ri-checkbox-circle-line'],
                'delivered' => ['Abgeholt', 'status-abgeholt', 'ri-check-double-line'],
                'cancelled' => ['Storniert', 'status-abgeholt', 'ri-close-circle-line'],
            ];
            $current_status = $repair->status ?: 'new';
            $status_info = $status_map[$current_status] ?? ['Unbekannt', 'status-neu', 'ri-question-line'];
        ?>
        <div class="content">
            <div class="status-badge <?php echo $status_info[1]; ?>">
                <i class="<?php echo $status_info[2]; ?>"></i>
                <?php echo $status_info[0]; ?>
            </div>

            <div class="repair-id">#<?php echo intval($repair->id); ?></div>
            <div class="repair-date">Eingereicht am <?php echo date('d.m.Y', strtotime($repair->created_at)); ?></div>

            <div class="detail-row">
                <span class="detail-label">Gerät</span>
                <span class="detail-value"><?php echo esc_html(trim(($repair->device_brand ?: '') . ' ' . ($repair->device_model ?: '')) ?: '-'); ?></span>
            </div>
            <?php if (!empty($repair->estimated_cost) && $repair->estimated_cost > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Geschätzte Kosten</span>
                <span class="detail-value"><?php echo number_format($repair->estimated_cost, 2, ',', '.'); ?> €</span>
            </div>
            <?php endif; ?>
            <?php if (!empty($repair->final_cost) && $repair->final_cost > 0): ?>
            <div class="detail-row">
                <span class="detail-label">Endpreis</span>
                <span class="detail-value" style="color:#059669;font-weight:600"><?php echo number_format($repair->final_cost, 2, ',', '.'); ?> €</span>
            </div>
            <?php endif; ?>

            <div class="problem-box">
                <div class="problem-label">Beschreibung</div>
                <div class="problem-text"><?php echo nl2br(esc_html($repair->problem_description ?: '-')); ?></div>
            </div>

            <div class="timeline">
                <div class="timeline-title">Fortschritt</div>
                <?php
                $stages = ['new', 'in_progress', 'done', 'delivered'];
                $stage_labels = ['Eingegangen', 'In Bearbeitung', 'Fertig', 'Abgeholt'];
                $current_idx = array_search($current_status, $stages);
                if ($current_idx === false) $current_idx = 0;
                if ($current_status === 'waiting_parts') $current_idx = 1; // Show as "in progress" level
                if ($current_status === 'cancelled') $current_idx = -1; // Don't highlight any
                foreach ($stages as $idx => $stage):
                    $is_active = ($current_status !== 'cancelled' && $idx <= $current_idx);
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot <?php echo $is_active ? 'active' : ''; ?>"></div>
                    <div class="timeline-text <?php echo $is_active ? 'active' : ''; ?>"><?php echo $stage_labels[$idx]; ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div class="footer">
        <a href="/formular/<?php echo esc_attr($store->store_slug); ?>">Neues Anliegen melden</a>
    </div>
</div>
</body>
</html>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     * AJAX: Register New Repair Shop
     * ============================================================ */
    public static function ajax_register() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_register')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $shop_name  = sanitize_text_field($_POST['shop_name'] ?? '');
        $owner_name = sanitize_text_field($_POST['owner_name'] ?? '');
        $email      = sanitize_email($_POST['email'] ?? '');
        $phone      = sanitize_text_field($_POST['phone'] ?? '');
        $address    = sanitize_text_field($_POST['address'] ?? '');
        $plz        = sanitize_text_field($_POST['plz'] ?? '');
        $city       = sanitize_text_field($_POST['city'] ?? '');
        $password   = $_POST['password'] ?? '';
        $tax_id     = sanitize_text_field($_POST['tax_id'] ?? '');

        if (empty($shop_name) || empty($owner_name) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Bitte alle Pflichtfelder ausfüllen']);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse']);
        }
        if (strlen($password) < 6) {
            wp_send_json_error(['message' => 'Passwort muss mindestens 6 Zeichen lang sein']);
        }

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_users WHERE email=%s", $email));
        if ($existing) {
            wp_send_json_error(['message' => 'Diese E-Mail ist bereits registriert']);
        }

        $slug = sanitize_title($shop_name);
        $base_slug = $slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_stores WHERE store_slug=%s", $slug))) {
            $slug = $base_slug . '-' . $counter++;
        }

        $name_parts = explode(' ', trim($owner_name), 2);

        $wpdb->insert("{$prefix}ppv_users", [
            'email' => $email, 'password' => password_hash($password, PASSWORD_DEFAULT),
            'first_name' => $name_parts[0] ?? '', 'last_name' => $name_parts[1] ?? '',
            'display_name' => trim($owner_name), 'qr_token' => wp_generate_password(10, false, false),
            'login_token' => bin2hex(random_bytes(32)), 'user_type' => 'handler',
            'active' => 1, 'created_at' => current_time('mysql'), 'updated_at' => current_time('mysql'),
        ]);
        $user_id = $wpdb->insert_id;
        if (!$user_id) wp_send_json_error(['message' => 'Registrierung fehlgeschlagen']);

        $api_key = bin2hex(random_bytes(16));
        $wpdb->insert("{$prefix}ppv_stores", [
            'user_id' => $user_id, 'store_key' => wp_generate_password(16, false, false),
            'name' => $shop_name, 'store_slug' => $slug, 'address' => $address, 'plz' => $plz,
            'city' => $city, 'phone' => $phone, 'email' => $email,
            'qr_secret' => wp_generate_password(32, false, false), 'pos_api_key' => $api_key,
            'active' => 1, 'visible' => 1,
            'repair_enabled' => 1, 'repair_points_per_form' => 2,
            'repair_form_count' => 0, 'repair_form_limit' => 50, 'repair_premium' => 0,
            'repair_company_name' => $shop_name, 'repair_owner_name' => $owner_name,
            'repair_tax_id' => $tax_id, 'repair_color' => '#667eea',
            'subscription_status' => 'trial',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at' => current_time('mysql'),
        ]);
        $store_id = $wpdb->insert_id;
        if (!$store_id) {
            $wpdb->delete("{$prefix}ppv_users", ['id' => $user_id]);
            wp_send_json_error(['message' => 'Shop-Erstellung fehlgeschlagen']);
        }

        $wpdb->insert("{$prefix}ppv_rewards", [
            'store_id' => $store_id, 'name' => '10 Euro Rabatt',
            'description' => '10 Euro Rabatt auf Ihre nächste Reparatur',
            'required_points' => 4, 'points_given' => 0, 'active' => 1,
            'created_at' => current_time('mysql'),
        ]);

        $form_url = home_url("/formular/{$slug}");
        $admin_url = home_url('/formular/admin');
        self::send_welcome_email($email, $owner_name, $shop_name, $form_url, $admin_url, $password);

        wp_send_json_success([
            'store_id' => $store_id, 'slug' => $slug,
            'form_url' => $form_url, 'message' => 'Registrierung erfolgreich!',
        ]);
    }

    /** Send Welcome Email */
    private static function send_welcome_email($email, $name, $shop_name, $form_url, $admin_url, $password) {
        $first = explode(' ', trim($name))[0] ?: 'Partner';
        $subject = "Willkommen bei PunktePass Reparatur - {$shop_name}";
        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head><body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,sans-serif;"><div style="max-width:560px;margin:0 auto;padding:20px;"><div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;"><h1 style="color:#fff;font-size:22px;margin:0 0 8px;">Willkommen bei PunktePass!</h1><p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Reparaturformular ist bereit</p></div><div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;"><p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first) . '</strong>,</p><div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;"><div style="font-size:12px;font-weight:600;color:#0369a1;margin-bottom:8px;">IHR FORMULAR-LINK</div><a href="' . esc_url($form_url) . '" style="font-size:16px;color:#1d4ed8;font-weight:600;">' . esc_html($form_url) . '</a></div><div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;"><div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;">ZUGANGSDATEN</div><p style="margin:4px 0;font-size:14px;"><strong>E-Mail:</strong> ' . esc_html($email) . '</p><p style="margin:4px 0;font-size:14px;"><strong>Passwort:</strong> <code>' . esc_html($password) . '</code></p><p style="margin:4px 0;font-size:14px;"><strong>Admin:</strong> <a href="' . esc_url($admin_url) . '">' . esc_html($admin_url) . '</a></p></div><div style="text-align:center;"><a href="' . esc_url($admin_url) . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Zum Admin-Bereich</a></div></div></div></body></html>';
        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** ============================================================
     * AJAX: Update Repair Status
     * ============================================================ */
    public static function ajax_update_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $repair_id  = intval($_POST['repair_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        $notes      = sanitize_textarea_field($_POST['notes'] ?? '');

        $valid = ['new', 'in_progress', 'waiting_parts', 'done', 'delivered', 'cancelled'];
        if (!in_array($new_status, $valid)) wp_send_json_error(['message' => 'Ungültiger Status']);

        global $wpdb;
        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_repairs WHERE id=%d AND store_id=%d", $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);

        $update = ['status' => $new_status, 'updated_at' => current_time('mysql')];
        if (!empty($notes)) $update['notes'] = $notes;
        if ($new_status === 'done') $update['completed_at'] = current_time('mysql');
        if ($new_status === 'delivered') $update['delivered_at'] = current_time('mysql');

        $old_status = $repair->status;
        $wpdb->update($wpdb->prefix . 'ppv_repairs', $update, ['id' => $repair_id]);

        // Send status notification email if enabled
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if ($store && !empty($store->repair_status_notify_enabled) && $old_status !== $new_status) {
            $notify_statuses = explode(',', $store->repair_status_notify_statuses ?: 'in_progress,done,delivered');
            if (in_array($new_status, $notify_statuses) && !empty($repair->customer_email)) {
                self::send_status_notification($store, $repair, $new_status);
            }
        }

        // Auto-generate invoice when status = done (unless skip_invoice is set)
        $invoice_id = null;
        $skip_invoice = !empty($_POST['skip_invoice']);
        if ($new_status === 'done' && class_exists('PPV_Repair_Invoice') && !$skip_invoice && $store) {
            try {
                // Ensure invoice table exists (run migration if needed)
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ppv_repair_invoices'");
                if (!$table_exists) {
                    self::run_migrations();
                }
                if ($store) {
                    $final_cost = floatval($_POST['final_cost'] ?? $repair->estimated_cost ?? 0);
                    $line_items = [];
                    if (!empty($_POST['line_items'])) {
                        $line_items = json_decode(stripslashes($_POST['line_items']), true);
                        if (!is_array($line_items)) $line_items = [];
                    }
                    $invoice_id = PPV_Repair_Invoice::generate_invoice($store, $repair, $final_cost, $line_items);

                    // If mark_paid is set, update invoice to paid status
                    if ($invoice_id && !empty($_POST['mark_paid'])) {
                        $invoice_update = [
                            'status' => 'paid',
                            'paid_at' => !empty($_POST['paid_at']) ? sanitize_text_field($_POST['paid_at']) : current_time('mysql'),
                        ];
                        if (!empty($_POST['payment_method'])) {
                            $invoice_update['payment_method'] = sanitize_text_field($_POST['payment_method']);
                        }
                        $wpdb->update($wpdb->prefix . 'ppv_repair_invoices', $invoice_update, ['id' => $invoice_id]);
                    }
                }
            } catch (\Exception $e) {
                // Invoice generation failed, but status update should still succeed
                error_log('PPV Repair Invoice Error: ' . $e->getMessage());
            }
        }

        wp_send_json_success([
            'message' => 'Status aktualisiert',
            'invoice_id' => $invoice_id,
        ]);
    }

    /** AJAX: Delete Repair */
    public static function ajax_delete_repair() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        if (!$repair_id) wp_send_json_error(['message' => 'Ungültige Reparatur-ID']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Verify repair belongs to this store
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_repairs WHERE id = %d AND store_id = %d",
            $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);

        // Delete related invoices first
        $wpdb->delete($prefix . 'ppv_repair_invoices', ['repair_id' => $repair_id]);

        // Delete the repair
        $wpdb->delete($prefix . 'ppv_repairs', ['id' => $repair_id]);

        wp_send_json_success(['message' => 'Reparatur gelöscht']);
    }

    /** AJAX: Approve Reward */
    public static function ajax_reward_approve() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        $points_to_deduct = intval($_POST['points'] ?? 4);
        if (!$repair_id) wp_send_json_error(['message' => 'Ungültige Reparatur-ID']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get repair and verify it belongs to this store
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.repair_reward_type, s.repair_reward_value, s.repair_reward_name
             FROM {$prefix}ppv_repairs r
             JOIN {$prefix}ppv_stores s ON r.store_id = s.id
             WHERE r.id = %d AND r.store_id = %d",
            $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);

        // Check if already approved
        if (!empty($repair->reward_approved)) {
            wp_send_json_error(['message' => 'Belohnung wurde bereits genehmigt']);
        }

        // Get user_id
        $user_id = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_users WHERE email = %s LIMIT 1",
            $repair->customer_email
        ));
        if (!$user_id) wp_send_json_error(['message' => 'Benutzer nicht gefunden']);

        // Deduct points
        $wpdb->insert("{$prefix}ppv_points", [
            'user_id' => $user_id,
            'store_id' => $store_id,
            'points' => -$points_to_deduct,
            'type' => 'redeem',
            'reference' => 'repair_reward_' . $repair_id,
            'created' => current_time('mysql'),
        ]);

        // Mark reward as approved on the repair
        $wpdb->update(
            "{$prefix}ppv_repairs",
            [
                'reward_approved' => 1,
                'reward_approved_date' => current_time('mysql'),
                'reward_rejected' => 0,
                'reward_rejection_reason' => null,
            ],
            ['id' => $repair_id]
        );

        // Log
        $wpdb->insert("{$prefix}ppv_scan_log", [
            'store_id' => $store_id,
            'user_id' => $user_id,
            'message' => "Belohnung eingelöst: {$repair->repair_reward_name} (Reparatur #{$repair_id})",
            'type' => 'reward_redeem',
            'points_change' => -$points_to_deduct,
            'status' => 'ok',
            'created' => current_time('mysql'),
        ]);

        wp_send_json_success([
            'message' => 'Belohnung genehmigt',
            'reward_type' => $repair->repair_reward_type,
            'reward_value' => $repair->repair_reward_value,
            'reward_name' => $repair->repair_reward_name,
        ]);
    }

    /** AJAX: Reject Reward */
    public static function ajax_reward_reject() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        $reason = sanitize_text_field($_POST['reason'] ?? '');
        if (!$repair_id) wp_send_json_error(['message' => 'Ungültige Reparatur-ID']);
        if (empty($reason)) wp_send_json_error(['message' => 'Bitte geben Sie einen Grund an']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Verify repair belongs to this store
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_repairs WHERE id = %d AND store_id = %d",
            $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);

        // Mark reward as rejected
        $wpdb->update(
            "{$prefix}ppv_repairs",
            [
                'reward_rejected' => 1,
                'reward_rejection_reason' => $reason,
                'reward_rejection_date' => current_time('mysql'),
            ],
            ['id' => $repair_id]
        );

        wp_send_json_success(['message' => 'Belohnung abgelehnt']);
    }

    /** AJAX: Send Repair Email to Customer */
    public static function ajax_send_repair_email() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $repair_id = intval($_POST['repair_id'] ?? 0);
        if (!$repair_id) wp_send_json_error(['message' => 'Ungültige Reparatur-ID']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get repair with store info
        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, s.name AS store_name, s.repair_company_name, s.repair_company_address, s.repair_company_phone, s.email AS store_email
             FROM {$prefix}ppv_repairs r
             JOIN {$prefix}ppv_stores s ON r.store_id = s.id
             WHERE r.id = %d AND r.store_id = %d",
            $repair_id, $store_id
        ));
        if (!$repair) wp_send_json_error(['message' => 'Reparatur nicht gefunden']);
        if (empty($repair->customer_email)) wp_send_json_error(['message' => 'Keine E-Mail-Adresse vorhanden']);

        // Build email
        $company_name = $repair->repair_company_name ?: $repair->store_name;
        $company_address = $repair->repair_company_address ?: '';
        $company_phone = $repair->repair_company_phone ?: '';
        $device = trim(($repair->device_brand ?: '') . ' ' . ($repair->device_model ?: ''));
        $date = date('d.m.Y H:i', strtotime($repair->created_at));

        $status_labels = [
            'new' => 'Neu',
            'in_progress' => 'In Bearbeitung',
            'waiting_parts' => 'Wartet auf Teile',
            'done' => 'Fertig',
            'delivered' => 'Abgeholt',
            'cancelled' => 'Storniert'
        ];
        $status_text = $status_labels[$repair->status] ?? 'Unbekannt';

        $subject = "Reparaturauftrag #{$repair_id} - {$company_name}";

        $email_body = "Sehr geehrte/r {$repair->customer_name},\n\n";
        $email_body .= "vielen Dank für Ihren Reparaturauftrag bei {$company_name}.\n\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "REPARATURAUFTRAG #{$repair_id}\n";
        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $email_body .= "📅 Datum: {$date}\n";
        $email_body .= "📊 Status: {$status_text}\n\n";

        $email_body .= "📱 GERÄT\n";
        $email_body .= "──────────────────────────────────\n";
        if ($device) $email_body .= "Gerät: {$device}\n";
        if (!empty($repair->device_pattern)) $email_body .= "PIN/Code: {$repair->device_pattern}\n";
        $email_body .= "\n";

        $email_body .= "🔧 PROBLEMBESCHREIBUNG\n";
        $email_body .= "──────────────────────────────────\n";
        $email_body .= "{$repair->problem_description}\n\n";

        $email_body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $email_body .= "Bei Fragen erreichen Sie uns unter:\n";
        if ($company_phone) $email_body .= "📞 {$company_phone}\n";
        if ($repair->store_email) $email_body .= "📧 {$repair->store_email}\n";
        if ($company_address) $email_body .= "📍 {$company_address}\n";
        $email_body .= "\n";
        $email_body .= "Mit freundlichen Grüßen,\n";
        $email_body .= "{$company_name}\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            "From: {$company_name} <noreply@punktepass.de>"
        ];
        if ($repair->store_email) {
            $headers[] = "Reply-To: {$repair->store_email}";
        }

        $sent = wp_mail($repair->customer_email, $subject, $email_body, $headers);

        if ($sent) {
            wp_send_json_success(['message' => 'E-Mail gesendet']);
        } else {
            wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden']);
        }
    }

    /**
     * Send status notification email to customer
     */
    private static function send_status_notification($store, $repair, $new_status) {
        $status_labels = [
            'new' => 'Neu - Auftrag eingegangen',
            'in_progress' => 'In Bearbeitung',
            'waiting_parts' => 'Wartet auf Ersatzteile',
            'done' => 'Fertig - Abholbereit',
            'delivered' => 'Abgeholt',
            'cancelled' => 'Storniert'
        ];
        $status_text = $status_labels[$new_status] ?? $new_status;

        $company_name = $store->repair_company_name ?: $store->name;
        $company_phone = $store->repair_company_phone ?: $store->phone;
        $company_email = $store->repair_company_email ?: '';
        $device = trim(($repair->device_brand ?: '') . ' ' . ($repair->device_model ?: ''));

        $subject = "Reparatur-Status Update: {$status_text}";

        $body = "Sehr geehrte/r {$repair->customer_name},\n\n";

        switch ($new_status) {
            case 'in_progress':
                $body .= "Ihre Reparatur wird jetzt bearbeitet.\n\n";
                break;
            case 'waiting_parts':
                $body .= "Für Ihre Reparatur werden Ersatzteile bestellt. Wir informieren Sie, sobald es weitergeht.\n\n";
                break;
            case 'done':
                $body .= "Gute Nachrichten! Ihre Reparatur ist abgeschlossen und Ihr Gerät ist zur Abholung bereit.\n\n";
                break;
            case 'delivered':
                $body .= "Vielen Dank für Ihren Besuch! Wir hoffen, Sie sind mit unserer Arbeit zufrieden.\n\n";
                break;
            case 'cancelled':
                $body .= "Ihre Reparatur wurde storniert. Bei Fragen kontaktieren Sie uns bitte.\n\n";
                break;
            default:
                $body .= "Der Status Ihrer Reparatur wurde aktualisiert.\n\n";
        }

        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "REPARATUR-DETAILS\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $body .= "📋 Auftragsnummer: #{$repair->id}\n";
        $body .= "📊 Neuer Status: {$status_text}\n";
        if ($device) $body .= "📱 Gerät: {$device}\n";
        $body .= "\n";

        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "KONTAKT\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        if ($company_phone) $body .= "📞 {$company_phone}\n";
        if ($company_email) $body .= "📧 {$company_email}\n";
        $body .= "\n";
        $body .= "Mit freundlichen Grüßen,\n";
        $body .= "{$company_name}\n";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            "From: {$company_name} <noreply@punktepass.de>"
        ];
        if ($company_email) {
            $headers[] = "Reply-To: {$company_email}";
        }

        wp_mail($repair->customer_email, $subject, $body, $headers);
    }

    /** AJAX: Search Repairs */
    public static function ajax_search_repairs() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) wp_send_json_error(['message' => 'Sicherheitsfehler']);

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['filter_status'] ?? '');
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $where = ["r.store_id = %d"];
        $params = [$store_id];

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(r.customer_name LIKE %s OR r.customer_email LIKE %s OR r.customer_phone LIKE %s OR r.device_model LIKE %s)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if (!empty($status)) { $where[] = "r.status = %s"; $params[] = $status; }

        $where_sql = implode(' AND ', $where);
        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}ppv_repairs r WHERE {$where_sql}", ...$params));
        $params[] = $per_page;
        $params[] = $offset;
        $repairs = $wpdb->get_results($wpdb->prepare("SELECT r.* FROM {$prefix}ppv_repairs r WHERE {$where_sql} ORDER BY CASE WHEN r.status IN ('done','delivered','cancelled') THEN 1 ELSE 0 END ASC, r.created_at DESC LIMIT %d OFFSET %d", ...$params));

        wp_send_json_success(['repairs' => $repairs, 'total' => $total, 'pages' => ceil($total / $per_page), 'page' => $page]);
    }

    /** AJAX: Save Settings */
    public static function ajax_save_settings() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) wp_send_json_error(['message' => 'Sicherheitsfehler']);
        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $stores_table = $wpdb->prefix . 'ppv_stores';

        // Auto-migrate: ensure all required columns exist
        $required_columns = [
            'repair_company_name' => "VARCHAR(255) NULL",
            'repair_owner_name' => "VARCHAR(255) NULL",
            'repair_tax_id' => "VARCHAR(100) NULL",
            'repair_company_address' => "VARCHAR(500) NULL",
            'repair_company_phone' => "VARCHAR(100) NULL",
            'repair_company_email' => "VARCHAR(255) NULL",
            'repair_punktepass_enabled' => "TINYINT(1) DEFAULT 1",
            'repair_reward_name' => "VARCHAR(255) DEFAULT '10 Euro Rabatt'",
            'repair_reward_description' => "VARCHAR(500) NULL",
            'repair_reward_type' => "VARCHAR(50) DEFAULT 'discount_fixed'",
            'repair_reward_value' => "DECIMAL(10,2) DEFAULT 10",
            'repair_reward_product' => "VARCHAR(255) NULL",
            'repair_required_points' => "INT DEFAULT 4",
            'repair_points_per_form' => "INT DEFAULT 2",
            'repair_form_title' => "VARCHAR(255) DEFAULT 'Reparaturauftrag'",
            'repair_form_subtitle' => "VARCHAR(500) NULL",
            'repair_service_type' => "VARCHAR(100) DEFAULT 'Allgemein'",
            'repair_field_config' => "TEXT NULL",
            'repair_color' => "VARCHAR(20) DEFAULT '#667eea'",
            'repair_invoice_prefix' => "VARCHAR(20) DEFAULT 'RE-'",
            'repair_invoice_next_number' => "INT DEFAULT 1",
            'repair_vat_enabled' => "TINYINT(1) DEFAULT 1",
            'repair_vat_rate' => "DECIMAL(5,2) DEFAULT 19.00",
            'repair_invoice_email_subject' => "VARCHAR(255) NULL",
            'repair_invoice_email_body' => "TEXT NULL",
            'repair_status_notify_enabled' => "TINYINT(1) DEFAULT 0",
            'repair_status_notify_statuses' => "VARCHAR(255) DEFAULT 'in_progress,done,delivered'",
            'repair_custom_brands' => "TEXT NULL",
            'repair_custom_problems' => "TEXT NULL",
            'repair_custom_accessories' => "TEXT NULL",
            'repair_success_message' => "TEXT NULL",
            'repair_opening_hours' => "VARCHAR(500) NULL",
            'repair_terms_url' => "VARCHAR(500) NULL",
        ];

        foreach ($required_columns as $col => $definition) {
            $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
            if (!$exists) {
                $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
            }
        }

        // Text fields
        $text_fields = ['repair_company_name', 'repair_owner_name', 'repair_tax_id', 'repair_company_address', 'repair_company_phone', 'repair_company_email',
                        'name', 'phone', 'address', 'plz', 'city',
                        'repair_reward_name', 'repair_reward_description', 'repair_form_title', 'repair_form_subtitle', 'repair_service_type',
                        'repair_invoice_prefix', 'repair_reward_type', 'repair_reward_product', 'repair_invoice_email_subject', 'repair_status_notify_statuses',
                        'repair_opening_hours', 'repair_terms_url'];
        // Textarea fields (allow newlines)
        $textarea_fields = ['repair_invoice_email_body', 'repair_custom_brands', 'repair_custom_problems', 'repair_custom_accessories', 'repair_success_message'];
        // Integer fields
        $int_fields = ['repair_points_per_form', 'repair_required_points', 'repair_invoice_next_number'];
        // Decimal fields
        $decimal_fields = ['repair_reward_value', 'repair_vat_rate'];
        // Toggle fields (0/1)
        $toggle_fields = ['repair_punktepass_enabled', 'repair_vat_enabled', 'repair_status_notify_enabled'];

        $update = [];
        foreach ($text_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = sanitize_text_field($_POST[$f]);
        }
        foreach ($textarea_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = sanitize_textarea_field($_POST[$f]);
        }
        foreach ($int_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = max(0, intval($_POST[$f]));
        }
        foreach ($decimal_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = max(0, round(floatval($_POST[$f]), 2));
        }
        foreach ($toggle_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = intval($_POST[$f]) ? 1 : 0;
        }

        // Build status notify statuses from individual checkboxes
        $notify_statuses = [];
        if (!empty($_POST['notify_in_progress'])) $notify_statuses[] = 'in_progress';
        if (!empty($_POST['notify_waiting_parts'])) $notify_statuses[] = 'waiting_parts';
        if (!empty($_POST['notify_done'])) $notify_statuses[] = 'done';
        if (!empty($_POST['notify_delivered'])) $notify_statuses[] = 'delivered';
        if (!empty($notify_statuses)) {
            $update['repair_status_notify_statuses'] = implode(',', $notify_statuses);
        }

        if (isset($_POST['repair_color'])) {
            $update['repair_color'] = sanitize_hex_color($_POST['repair_color']) ?: '#667eea';
        }
        // Field config (JSON)
        if (isset($_POST['repair_field_config'])) {
            $config = json_decode(stripslashes($_POST['repair_field_config']), true);
            if (is_array($config)) {
                $update['repair_field_config'] = wp_json_encode($config);
            }
        }

        if (!empty($update)) {
            $wpdb->update($wpdb->prefix . 'ppv_stores', $update, ['id' => $store_id]);
        }
        wp_send_json_success(['message' => 'Einstellungen gespeichert']);
    }

    /** AJAX: Upload Logo */
    public static function ajax_upload_logo() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) wp_send_json_error(['message' => 'Sicherheitsfehler']);
        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);
        if (empty($_FILES['logo'])) wp_send_json_error(['message' => 'Keine Datei']);

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file = $_FILES['logo'];
        if (!in_array($file['type'], ['image/jpeg','image/png','image/webp','image/svg+xml'])) wp_send_json_error(['message' => 'Nur JPG/PNG/WebP/SVG']);
        if ($file['size'] > 2*1024*1024) wp_send_json_error(['message' => 'Max 2MB']);

        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) wp_send_json_error(['message' => $upload['error']]);

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ppv_stores', ['logo' => $upload['url']], ['id' => $store_id]);
        wp_send_json_success(['url' => $upload['url']]);
    }

    /** Get Current Store ID from Session */
    public static function get_current_store_id() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            $lifetime = 30 * 24 * 60 * 60; // 30 days
            session_set_cookie_params([
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => is_ssl(),
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
            ini_set('session.gc_maxlifetime', $lifetime);
            @session_start();
        }
        // Try repair-specific session first
        if (!empty($_SESSION['ppv_repair_store_id'])) return (int)$_SESSION['ppv_repair_store_id'];
        // Fallback to general session
        $user_id = $_SESSION['ppv_user_id'] ?? 0;
        if (!$user_id) return 0;
        global $wpdb;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id=%d AND repair_enabled=1 LIMIT 1", $user_id
        ));
    }

    /** ============================================================
     * AJAX: User Search (PunktePass customer lookup)
     * ============================================================ */
    public static function ajax_user_search() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $query = sanitize_text_field($_POST['query'] ?? '');

        if (empty($query) || strlen($query) < 2) {
            wp_send_json_error(['message' => 'Bitte mindestens 2 Zeichen eingeben']);
        }

        $like = '%' . $wpdb->esc_like($query) . '%';

        // Search user by name or email
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, email, first_name, last_name, display_name
             FROM {$prefix}ppv_users
             WHERE (email LIKE %s OR display_name LIKE %s OR first_name LIKE %s OR last_name LIKE %s)
               AND active = 1
             ORDER BY
               CASE WHEN email = %s THEN 0
                    WHEN email LIKE %s THEN 1
                    ELSE 2 END,
               display_name ASC
             LIMIT 1",
            $like, $like, $like, $like,
            $query, $like
        ));

        if (!$user) {
            wp_send_json_error(['message' => 'Kein Kunde gefunden']);
        }

        // Get total points for this store
        $total_points = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points WHERE user_id = %d AND store_id = %d",
            $user->id, $store_id
        ));

        // Get store's required points for reward
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT repair_required_points FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        $required_points = intval($store->repair_required_points ?? 4);

        wp_send_json_success([
            'user' => [
                'display_name'    => $user->display_name ?: $user->first_name,
                'first_name'      => $user->first_name,
                'email'           => $user->email,
                'total_points'    => $total_points,
                'required_points' => $required_points,
            ],
        ]);
    }

    /** ============================================================
     * AJAX: Logout from Repair Admin
     * ============================================================ */
    public static function ajax_logout() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }
        unset($_SESSION['ppv_repair_store_id']);
        unset($_SESSION['ppv_repair_store_name']);
        unset($_SESSION['ppv_repair_store_slug']);
        wp_send_json_success(['message' => 'Abgemeldet']);
    }

    /** ============================================================
     * Send standalone error page
     * ============================================================ */
    public static function send_error_page($code, $title, $message) {
        status_header($code);
        echo '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . esc_html($title) . '</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2937;display:flex;align-items:center;justify-content:center;min-height:100vh}.box{text-align:center;padding:40px}h1{font-size:24px;margin-bottom:12px}p{color:#6b7280;font-size:16px;margin-bottom:24px}a{color:#667eea;text-decoration:none;font-weight:600}</style></head><body><div class="box"><h1>' . esc_html($title) . '</h1><p>' . esc_html($message) . '</p><a href="/formular">&larr; Zur Registrierung</a></div></body></html>';
    }

    /** ============================================================
     * Render Legal Page for a Store (standalone HTML)
     * ============================================================ */
    public static function render_legal_page($store, $page_type) {
        $sn = esc_html($store->repair_company_name ?: $store->name);
        $on = esc_html($store->repair_owner_name ?: '');
        $ad = esc_html($store->address ?: '');
        $pz = esc_html($store->plz ?: '');
        $ct = esc_html($store->city ?: '');
        $em = esc_html($store->email ?: '');
        $ph = esc_html($store->phone ?: '');
        $tx = esc_html($store->repair_tax_id ?: '');
        $co = esc_attr($store->repair_color ?: '#667eea');
        $lg = esc_url($store->logo ?: '');
        $sl = esc_attr($store->store_slug);

        $titles = ['datenschutz' => 'Datenschutzerkl&auml;rung', 'agb' => 'AGB', 'impressum' => 'Impressum'];
        $title = $titles[$page_type] ?? '';

        $content = '';
        if ($page_type === 'datenschutz') {
            $content = "<h2>1. Verantwortlicher</h2><p>{$sn}<br>{$on}<br>{$ad}<br>{$pz} {$ct}<br>E-Mail: {$em}</p><h2>2. Erhobene Daten</h2><p>Name, E-Mail, Telefon, Ger&auml;teinfo, Problembeschreibung, IP-Adresse.</p><h2>3. Zweck</h2><p>Bearbeitung Ihres Reparaturauftrags und PunktePass-Treuepunkte-Verwaltung.</p><h2>4. PunktePass</h2><p>Automatische Kontoerstellung bei Formularabsendung. Details: <a href='https://punktepass.de/datenschutz'>punktepass.de/datenschutz</a></p><h2>5. Ihre Rechte</h2><p>Auskunft, Berichtigung, L&ouml;schung, Widerspruch. Kontakt: {$em}</p>";
        } elseif ($page_type === 'agb') {
            $content = "<h2>1. Geltungsbereich</h2><p>F&uuml;r alle Reparaturauftr&auml;ge &uuml;ber das digitale Formular von {$sn}.</p><h2>2. Vertragsschluss</h2><p>Absenden = Angebot. Vertrag bei Annahme.</p><h2>3. Preise</h2><p>Endkosten nach Diagnose. Information bei Kosten&uuml;berschreitung.</p><h2>4. PunktePass</h2><p>Automatische Bonuspunkte. Details: <a href='https://punktepass.de/agb'>punktepass.de/agb</a></p><h2>5. Abholung</h2><p>Innerhalb von 30 Tagen nach Fertigstellung.</p><h2>6. Recht</h2><p>Es gilt deutsches Recht.</p>";
        } else {
            $tax_line = $tx ? "<br><strong>USt-IdNr.:</strong> {$tx}" : '';
            $phone_line = $ph ? "<br>Telefon: {$ph}" : '';
            $content = "<h2>Angaben gem. &sect;5 TMG</h2><p>{$sn}<br>{$on}<br>{$ad}<br>{$pz} {$ct}</p><h2>Kontakt</h2><p>E-Mail: {$em}{$phone_line}</p><h2>Verantwortlich</h2><p>{$on}<br>{$ad}<br>{$pz} {$ct}{$tax_line}</p><h2>EU-Streitschlichtung</h2><p><a href='https://ec.europa.eu/consumers/odr' target='_blank'>ec.europa.eu/consumers/odr</a></p><h2>PunktePass</h2><p>Betreiber: PunktePass, Erik Borota, Siedlungsring 51, 89415 Lauingen. <a href='https://punktepass.de/impressum'>punktepass.de/impressum</a></p>";
        }

        $logo_html = $lg ? "<img src='{$lg}' alt='' style='height:40px;border-radius:8px;margin-bottom:8px;'>" : '';

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . $title . ' - ' . $sn . '</title><style>*{margin:0;padding:0;box-sizing:border-box}body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2937;line-height:1.6}.hd{background:linear-gradient(135deg,' . $co . ',#764ba2);padding:24px 20px;text-align:center}.hd h1{color:#fff;font-size:20px}.hd .bk{color:rgba(255,255,255,0.8);text-decoration:none;font-size:14px;display:inline-block;margin-top:8px}.ct{max-width:700px;margin:0 auto;padding:24px 20px}.ct h2{font-size:18px;margin:24px 0 8px;color:#111827}.ct p{font-size:14px;margin-bottom:12px;color:#4b5563}.ct a{color:' . $co . '}.ft{text-align:center;padding:20px;font-size:12px;color:#9ca3af}.ft a{color:' . $co . ';text-decoration:none}</style></head><body><div class="hd">' . $logo_html . '<h1>' . $title . '</h1><a href="/formular/' . $sl . '" class="bk">&larr; Zur&uuml;ck</a></div><div class="ct">' . $content . '</div><div class="ft"><a href="/formular/' . $sl . '/datenschutz">Datenschutz</a> &middot; <a href="/formular/' . $sl . '/agb">AGB</a> &middot; <a href="/formular/' . $sl . '/impressum">Impressum</a><br><br>Powered by <a href="https://punktepass.de">PunktePass</a></div></body></html>';
    }
}
