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

        // AJAX: repair registration
        add_action('wp_ajax_nopriv_ppv_repair_register', [__CLASS__, 'ajax_register']);
        add_action('wp_ajax_ppv_repair_register', [__CLASS__, 'ajax_register']);

        // AJAX: repair admin login
        add_action('wp_ajax_nopriv_ppv_repair_login', [__CLASS__, 'ajax_login']);
        add_action('wp_ajax_ppv_repair_login', [__CLASS__, 'ajax_login']);

        // AJAX: repair admin actions
        add_action('wp_ajax_ppv_repair_update_status', [__CLASS__, 'ajax_update_status']);
        add_action('wp_ajax_ppv_repair_search', [__CLASS__, 'ajax_search_repairs']);
        add_action('wp_ajax_ppv_repair_save_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_ppv_repair_upload_logo', [__CLASS__, 'ajax_upload_logo']);
        add_action('wp_ajax_ppv_repair_logout', [__CLASS__, 'ajax_logout']);

        // AJAX: invoice actions
        add_action('wp_ajax_ppv_repair_invoice_pdf', ['PPV_Repair_Invoice', 'ajax_download_pdf']);
        add_action('wp_ajax_ppv_repair_invoice_csv', ['PPV_Repair_Invoice', 'ajax_export_csv']);
        add_action('wp_ajax_ppv_repair_invoices_list', ['PPV_Repair_Invoice', 'ajax_list_invoices']);
        add_action('wp_ajax_ppv_repair_invoice_update', ['PPV_Repair_Invoice', 'ajax_update_invoice']);
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

        // Start session for auth
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
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

        // Find user
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT u.*, s.id AS store_id, s.name AS store_name, s.store_slug
             FROM {$prefix}ppv_users u
             JOIN {$prefix}ppv_stores s ON s.user_id = u.id AND s.repair_enabled = 1
             WHERE u.email = %s AND u.active = 1
             LIMIT 1",
            $email
        ));

        if (!$user || !password_verify($password, $user->password)) {
            wp_send_json_error(['message' => 'E-Mail oder Passwort falsch']);
        }

        // Set session
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $_SESSION['ppv_user_id'] = $user->id;
        $_SESSION['ppv_user_type'] = $user->user_type;
        $_SESSION['ppv_repair_store_id'] = $user->store_id;
        $_SESSION['ppv_repair_store_name'] = $user->store_name;
        $_SESSION['ppv_repair_store_slug'] = $user->store_slug;

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

        if (empty($problem)) {
            wp_send_json_error(['message' => 'Bitte beschreiben Sie das Problem']);
        }

        $wpdb->insert("{$prefix}ppv_repairs", [
            'store_id'            => $store_id,
            'customer_name'       => $name,
            'customer_email'      => $email,
            'customer_phone'      => $phone,
            'device_brand'        => $device_brand,
            'device_model'        => $device_model,
            'device_imei'         => $device_imei,
            'device_pattern'      => $device_pattern,
            'problem_description' => $problem,
            'accessories'         => $accessories,
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

        wp_send_json_success([
            'repair_id'    => $repair_id,
            'points_added' => $bonus_result['points_added'] ?? 0,
            'total_points' => $bonus_result['total_points'] ?? 0,
            'is_new_user'  => $bonus_result['is_new_user'] ?? false,
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

        $wpdb->update($wpdb->prefix . 'ppv_repairs', $update, ['id' => $repair_id]);

        // Auto-generate invoice when status = done
        $invoice_id = null;
        if ($new_status === 'done' && class_exists('PPV_Repair_Invoice')) {
            try {
                // Ensure invoice table exists (run migration if needed)
                $table_exists = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}ppv_repair_invoices'");
                if (!$table_exists) {
                    self::run_migrations();
                }
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id
                ));
                if ($store) {
                    $invoice_id = PPV_Repair_Invoice::generate_invoice($store, $repair, floatval($_POST['final_cost'] ?? $repair->estimated_cost ?? 0));
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
        $repairs = $wpdb->get_results($wpdb->prepare("SELECT r.* FROM {$prefix}ppv_repairs r WHERE {$where_sql} ORDER BY r.created_at DESC LIMIT %d OFFSET %d", ...$params));

        wp_send_json_success(['repairs' => $repairs, 'total' => $total, 'pages' => ceil($total / $per_page), 'page' => $page]);
    }

    /** AJAX: Save Settings */
    public static function ajax_save_settings() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) wp_send_json_error(['message' => 'Sicherheitsfehler']);
        $store_id = self::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;

        // Text fields
        $text_fields = ['repair_company_name', 'repair_owner_name', 'repair_tax_id', 'name', 'phone', 'address', 'plz', 'city',
                        'repair_reward_name', 'repair_reward_description', 'repair_form_title', 'repair_form_subtitle', 'repair_service_type',
                        'repair_invoice_prefix', 'repair_reward_type', 'repair_reward_product'];
        // Integer fields
        $int_fields = ['repair_points_per_form', 'repair_required_points', 'repair_invoice_next_number'];
        // Decimal fields
        $decimal_fields = ['repair_reward_value', 'repair_vat_rate'];
        // Toggle fields (0/1)
        $toggle_fields = ['repair_punktepass_enabled', 'repair_vat_enabled'];

        $update = [];
        foreach ($text_fields as $f) {
            if (isset($_POST[$f])) $update[$f] = sanitize_text_field($_POST[$f]);
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

        if (!empty($update)) $wpdb->update($wpdb->prefix . 'ppv_stores', $update, ['id' => $store_id]);
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
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) @session_start();
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
