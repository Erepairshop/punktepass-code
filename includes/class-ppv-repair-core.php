<?php
/**
 * PunktePass - Repair Form Module Core
 * Database migrations, URL routing, shared utilities, AJAX handlers
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Core {

    /** ============================================================
     * Hooks
     * ============================================================ */
    public static function hooks() {
        // URL rewrite rules
        add_action('init', [__CLASS__, 'register_rewrite_rules']);

        // Template redirects for standalone pages
        add_action('template_redirect', [__CLASS__, 'handle_repair_page'], 5);

        // DB migration
        add_action('admin_init', [__CLASS__, 'run_migrations'], 6);

        // AJAX: submit repair form (public, no login required)
        add_action('wp_ajax_ppv_repair_submit', [__CLASS__, 'ajax_submit_repair']);
        add_action('wp_ajax_nopriv_ppv_repair_submit', [__CLASS__, 'ajax_submit_repair']);

        // AJAX: repair registration
        add_action('wp_ajax_nopriv_ppv_repair_register', [__CLASS__, 'ajax_register']);
        add_action('wp_ajax_ppv_repair_register', [__CLASS__, 'ajax_register']);

        // AJAX: repair admin actions
        add_action('wp_ajax_ppv_repair_update_status', [__CLASS__, 'ajax_update_status']);
        add_action('wp_ajax_ppv_repair_search', [__CLASS__, 'ajax_search_repairs']);
        add_action('wp_ajax_ppv_repair_save_settings', [__CLASS__, 'ajax_save_settings']);
        add_action('wp_ajax_ppv_repair_upload_logo', [__CLASS__, 'ajax_upload_logo']);
    }

    /** ============================================================
     * URL Rewrite Rules
     * /repair/shopslug          → public form
     * /repair/shopslug/legal    → datenschutz/agb/impressum
     * /repair-register          → registration page
     * /repair-admin             → admin dashboard
     * ============================================================ */
    public static function register_rewrite_rules() {
        // Public repair form: /repair/shopslug
        add_rewrite_rule(
            '^repair/([^/]+)/(datenschutz|agb|impressum)/?$',
            'index.php?ppv_repair_shop=$matches[1]&ppv_repair_legal=$matches[2]',
            'top'
        );
        add_rewrite_rule(
            '^repair/([^/]+)/?$',
            'index.php?ppv_repair_shop=$matches[1]',
            'top'
        );
        add_rewrite_tag('%ppv_repair_shop%', '([^/]+)');
        add_rewrite_tag('%ppv_repair_legal%', '([^/]+)');
    }

    /** ============================================================
     * Handle standalone repair form page (template_redirect)
     * Serves a complete standalone HTML page (fast, no WP theme)
     * ============================================================ */
    public static function handle_repair_page() {
        $shop_slug = get_query_var('ppv_repair_shop');
        if (empty($shop_slug)) return;

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE slug = %s AND repair_enabled = 1 AND active = 1",
            sanitize_title($shop_slug)
        ));

        if (!$store) {
            // Try by store_key
            $store = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE store_key = %s AND repair_enabled = 1 AND active = 1",
                sanitize_text_field($shop_slug)
            ));
        }

        if (!$store) {
            status_header(404);
            echo self::render_error_page('Formular nicht gefunden', 'Dieses Reparaturformular existiert nicht oder ist deaktiviert.');
            exit;
        }

        // Check form limit (free tier: 50 forms)
        $limit_reached = false;
        if (!$store->repair_premium && $store->repair_form_count >= $store->repair_form_limit) {
            $limit_reached = true;
        }

        $legal_page = get_query_var('ppv_repair_legal');
        if ($legal_page) {
            echo self::render_legal_page($store, $legal_page);
            exit;
        }

        echo PPV_Repair_Form::render_standalone_page($store, $limit_reached);
        exit;
    }

    /** ============================================================
     * Database Migrations
     * ============================================================ */
    public static function run_migrations() {
        global $wpdb;

        $version = get_option('ppv_repair_migration_version', '0');

        // Migration 1.0: Create repairs table + add store columns
        if (version_compare($version, '1.0', '<')) {
            $charset = $wpdb->get_charset_collate();
            $table = $wpdb->prefix . 'ppv_repairs';

            $sql = "CREATE TABLE IF NOT EXISTS {$table} (
                id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                store_id BIGINT(20) UNSIGNED NOT NULL,
                user_id BIGINT(20) UNSIGNED NULL COMMENT 'PunktePass user (created on form submit)',
                customer_name VARCHAR(255) NOT NULL,
                customer_email VARCHAR(255) NOT NULL,
                customer_phone VARCHAR(50) NULL,
                device_brand VARCHAR(100) NULL,
                device_model VARCHAR(255) NULL,
                device_imei VARCHAR(50) NULL,
                device_pattern VARCHAR(100) NULL COMMENT 'Lock pattern or PIN info',
                problem_description TEXT NOT NULL,
                accessories TEXT NULL COMMENT 'JSON: charger, case, sim, sd, etc.',
                notes TEXT NULL COMMENT 'Internal notes from handler',
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

            // Add repair columns to ppv_stores
            $stores_table = $wpdb->prefix . 'ppv_stores';

            $repair_columns = [
                'repair_enabled'        => "TINYINT(1) DEFAULT 0 COMMENT 'Repair form module active'",
                'repair_points_per_form' => "INT DEFAULT 2 COMMENT 'Bonus points per repair form'",
                'repair_form_count'     => "INT UNSIGNED DEFAULT 0 COMMENT 'Total forms submitted'",
                'repair_form_limit'     => "INT UNSIGNED DEFAULT 50 COMMENT 'Max forms for free tier'",
                'repair_premium'        => "TINYINT(1) DEFAULT 0 COMMENT 'Premium repair tier (admin approved)'",
                'repair_company_name'   => "VARCHAR(255) NULL COMMENT 'Company name for legal pages'",
                'repair_owner_name'     => "VARCHAR(255) NULL COMMENT 'Owner name for Impressum'",
                'repair_tax_id'         => "VARCHAR(50) NULL COMMENT 'USt-ID for Impressum'",
                'repair_color'          => "VARCHAR(10) DEFAULT '#667eea' COMMENT 'Repair form accent color'",
            ];

            foreach ($repair_columns as $col => $definition) {
                $exists = $wpdb->get_var("SHOW COLUMNS FROM {$stores_table} LIKE '{$col}'");
                if (!$exists) {
                    $wpdb->query("ALTER TABLE {$stores_table} ADD COLUMN {$col} {$definition}");
                    ppv_log("[PPV_Repair] Added '{$col}' column to ppv_stores");
                }
            }

            ppv_log("[PPV_Repair] Migration 1.0 completed - repairs table + store columns created");
            update_option('ppv_repair_migration_version', '1.0');
        }
    }

    /** ============================================================
     * AJAX: Submit Repair Form (public)
     * ============================================================ */
    public static function ajax_submit_repair() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_form')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Store nicht gefunden']);
        }

        // Verify store exists and repair is enabled
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d AND repair_enabled = 1 AND active = 1",
            $store_id
        ));

        if (!$store) {
            wp_send_json_error(['message' => 'Reparaturformular nicht verfügbar']);
        }

        // Check form limit
        if (!$store->repair_premium && $store->repair_form_count >= $store->repair_form_limit) {
            wp_send_json_error(['message' => 'Formularlimit erreicht. Bitte kontaktieren Sie den Anbieter.']);
        }

        // Sanitize inputs
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

        // Insert repair entry
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

        // Increment form count
        $wpdb->query($wpdb->prepare(
            "UPDATE {$prefix}ppv_stores SET repair_form_count = repair_form_count + 1 WHERE id = %d",
            $store_id
        ));

        // Award PunktePass bonus points via the repair bonus API logic
        $points = intval($store->repair_points_per_form ?: 2);
        $bonus_result = self::award_repair_bonus($store, $email, $name, $points);

        // Update repair entry with user_id and points
        if ($bonus_result && !empty($bonus_result['user_id'])) {
            $wpdb->update("{$prefix}ppv_repairs", [
                'user_id'        => $bonus_result['user_id'],
                'points_awarded' => $bonus_result['points_added'],
            ], ['id' => $repair_id]);
        }

        // Send notification email to store owner
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
     * Award Repair Bonus Points (reusable logic from api-repair-bonus.php)
     * ============================================================ */
    public static function award_repair_bonus($store, $email, $name, $points = 2) {
        global $wpdb;
        $prefix = $wpdb->prefix;
        $store_id = (int)$store->id;
        $reference = 'Reparatur-Formular Bonus';

        // Check if user exists
        $user = $wpdb->get_row($wpdb->prepare(
            "SELECT id, first_name FROM {$prefix}ppv_users WHERE email = %s LIMIT 1",
            $email
        ));

        $is_new_user = false;
        $generated_password = null;

        if (!$user) {
            // Create new PunktePass user
            $is_new_user = true;
            $generated_password = wp_generate_password(8, false, false);
            $password_hash = password_hash($generated_password, PASSWORD_DEFAULT);
            $qr_token = wp_generate_password(10, false, false);
            $login_token = bin2hex(random_bytes(32));

            $name_parts = explode(' ', trim($name), 2);
            $first_name = $name_parts[0] ?? '';
            $last_name  = $name_parts[1] ?? '';

            $wpdb->insert("{$prefix}ppv_users", [
                'email'        => $email,
                'password'     => $password_hash,
                'first_name'   => $first_name,
                'last_name'    => $last_name,
                'display_name' => trim($name) ?: $first_name,
                'qr_token'     => $qr_token,
                'login_token'  => $login_token,
                'user_type'    => 'user',
                'active'       => 1,
                'created_at'   => current_time('mysql'),
                'updated_at'   => current_time('mysql'),
            ]);

            $user_id = $wpdb->insert_id;
            if (!$user_id) {
                return ['user_id' => 0, 'points_added' => 0, 'total_points' => 0, 'is_new_user' => true];
            }
        } else {
            $user_id = (int) $user->id;
        }

        // Duplicate protection: max 1 repair bonus per email per store per day
        $today = current_time('Y-m-d');
        $already = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_points
             WHERE user_id = %d AND store_id = %d AND type = 'bonus' AND reference = %s AND DATE(created) = %s",
            $user_id, $store_id, $reference, $today
        ));

        $points_added = 0;
        if ($already === 0) {
            // Add bonus points
            $wpdb->insert("{$prefix}ppv_points", [
                'user_id'   => $user_id,
                'store_id'  => $store_id,
                'points'    => $points,
                'type'      => 'bonus',
                'reference' => $reference,
                'created'   => current_time('mysql'),
            ]);
            $points_added = $points;

            // Log to pos_log
            $wpdb->insert("{$prefix}ppv_pos_log", [
                'store_id'      => $store_id,
                'user_id'       => $user_id,
                'email'         => $email,
                'message'       => "+{$points} Punkte (Reparatur-Bonus)",
                'type'          => 'qr_scan',
                'points_change' => $points,
                'status'        => 'ok',
                'ip_address'    => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'metadata'      => json_encode([
                    'timestamp'   => current_time('mysql'),
                    'type'        => 'repair_bonus',
                    'reference'   => $reference,
                    'is_new_user' => $is_new_user,
                ]),
                'created_at'    => current_time('mysql'),
            ]);

            // Update lifetime points
            if (class_exists('PPV_User_Level')) {
                PPV_User_Level::add_lifetime_points($user_id, $points);
            }
        }

        // Total points for this store
        $total_points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$prefix}ppv_points WHERE user_id = %d AND store_id = %d",
            $user_id, $store_id
        ));

        // Send points email to customer
        if ($points_added > 0) {
            self::send_points_email($store, $email, $name, $points_added, $total_points, $is_new_user, $generated_password);
        }

        return [
            'user_id'      => $user_id,
            'points_added' => $points_added,
            'total_points' => $total_points,
            'is_new_user'  => $is_new_user,
        ];
    }

    /** ============================================================
     * Send Points Email to Customer
     * ============================================================ */
    private static function send_points_email($store, $email, $name, $points, $total_points, $is_new_user, $password = null) {
        $first_name = explode(' ', trim($name))[0] ?: 'Kunde';
        $store_name = esc_html($store->name ?: $store->company_name ?: 'Reparaturservice');
        $points_to_reward = max(0, 4 - $total_points);

        $reward_section = '';
        if ($total_points >= 4) {
            $reward_section = '
            <div style="background:linear-gradient(135deg,#fef3c7,#fde68a);border:1px solid #f59e0b;border-radius:12px;padding:20px;margin:0 0 24px;text-align:center;">
                <div style="font-size:28px;margin-bottom:8px;">&#127942;</div>
                <div style="font-size:18px;font-weight:700;color:#92400e;margin-bottom:8px;">Sie haben 10&euro; Rabatt gewonnen!</div>
                <p style="font-size:13px;color:#78350f;margin:0 0 16px;line-height:1.5;">
                    Melden Sie sich bei <strong>PunktePass</strong> an und l&ouml;sen Sie Ihre Pr&auml;mie ein!
                </p>
                <a href="https://punktepass.de" target="_blank" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;text-decoration:none;border-radius:10px;font-size:14px;font-weight:700;">
                    Pr&auml;mie einl&ouml;sen &rarr;
                </a>
            </div>';
        }

        if ($is_new_user && $password) {
            $subject = "Willkommen bei PunktePass - {$points} Bonuspunkte von {$store_name}!";
            $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
            <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="max-width:560px;margin:0 auto;padding:20px;">
            <div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
                <h1 style="color:#fff;font-size:24px;margin:0 0 8px;">Willkommen bei PunktePass!</h1>
                <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Treuekonto bei ' . $store_name . '</p>
            </div>
            <div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
                <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
                <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">vielen Dank! Wir haben ein <strong>PunktePass-Konto</strong> f&uuml;r Sie erstellt.</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
                    <div style="font-size:36px;font-weight:800;color:#059669;">+' . $points . ' Punkte</div>
                    <div style="font-size:13px;color:#6b7280;margin-top:4px;">Gesamt: ' . $total_points . ' / 4 Punkte' . ($points_to_reward > 0 ? ' &mdash; noch ' . $points_to_reward . ' bis 10&euro; Rabatt!' : ' &mdash; 10&euro; Rabatt einl&ouml;sbar!') . '</div>
                </div>
                ' . $reward_section . '
                <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;">
                    <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;">IHRE ZUGANGSDATEN</div>
                    <p style="margin:4px 0;font-size:14px;"><strong>E-Mail:</strong> ' . esc_html($email) . '</p>
                    <p style="margin:4px 0;font-size:14px;"><strong>Passwort:</strong> <code>' . esc_html($password) . '</code></p>
                </div>
                <div style="text-align:center;"><a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Jetzt anmelden</a></div>
            </div>
            </div></body></html>';
        } else {
            $subject = ($total_points >= 4)
                ? "Sie haben 10 Euro Rabatt bei {$store_name} gewonnen!"
                : "+{$points} Bonuspunkte auf Ihr PunktePass-Konto!";

            $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
            <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
            <div style="max-width:560px;margin:0 auto;padding:20px;">
            <div style="background:linear-gradient(135deg,#10b981,#059669);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
                <div style="font-size:48px;font-weight:800;color:#fff;">+' . $points . '</div>
                <p style="color:rgba(255,255,255,0.9);font-size:15px;margin:8px 0 0;">Bonuspunkte gutgeschrieben</p>
            </div>
            <div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
                <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
                <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">+' . $points . ' Bonuspunkte von ' . $store_name . ' gutgeschrieben.</p>
                <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
                    <div style="font-size:36px;font-weight:800;color:#059669;">' . $total_points . ' Punkte</div>
                    <div style="font-size:13px;color:#374151;margin-top:8px;">' . ($points_to_reward > 0 ? 'Noch ' . $points_to_reward . ' bis 10&euro; Rabatt!' : '10&euro; Rabatt einl&ouml;sbar!') . '</div>
                </div>
                ' . $reward_section . '
                <div style="text-align:center;"><a href="https://punktepass.de" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#10b981,#059669);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Punkte ansehen</a></div>
            </div>
            </div></body></html>';
        }

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** ============================================================
     * Notify Store Owner about new repair
     * ============================================================ */
    private static function notify_store_owner($store, $data) {
        $to = $store->email;
        if (empty($to)) return;

        $subject = "Neue Reparatur #{$data['repair_id']} - {$data['name']}";
        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        <div style="max-width:560px;margin:0 auto;padding:20px;">
        <div style="background:linear-gradient(135deg,#3b82f6,#1d4ed8);border-radius:16px 16px 0 0;padding:24px 28px;text-align:center;">
            <h1 style="color:#fff;font-size:20px;margin:0;">Neue Reparaturanfrage</h1>
        </div>
        <div style="background:#fff;padding:28px;border-radius:0 0 16px 16px;">
            <table style="width:100%;border-collapse:collapse;font-size:14px;color:#374151;">
                <tr><td style="padding:8px 0;font-weight:600;width:120px;">Auftrag:</td><td>#' . intval($data['repair_id']) . '</td></tr>
                <tr><td style="padding:8px 0;font-weight:600;">Name:</td><td>' . esc_html($data['name']) . '</td></tr>
                <tr><td style="padding:8px 0;font-weight:600;">E-Mail:</td><td>' . esc_html($data['email']) . '</td></tr>
                <tr><td style="padding:8px 0;font-weight:600;">Telefon:</td><td>' . esc_html($data['phone'] ?: '-') . '</td></tr>
                <tr><td style="padding:8px 0;font-weight:600;">Ger&auml;t:</td><td>' . esc_html($data['device'] ?: '-') . '</td></tr>
            </table>
            <div style="margin-top:16px;padding:12px;background:#f8fafc;border-radius:8px;">
                <div style="font-size:12px;font-weight:600;color:#6b7280;margin-bottom:4px;">PROBLEMBESCHREIBUNG</div>
                <div style="font-size:14px;color:#1f2937;line-height:1.5;">' . nl2br(esc_html($data['problem'])) . '</div>
            </div>
            <div style="margin-top:20px;text-align:center;">
                <a href="https://punktepass.de/repair-admin" style="display:inline-block;padding:12px 28px;background:linear-gradient(135deg,#3b82f6,#1d4ed8);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;font-size:14px;">Reparatur verwalten</a>
            </div>
        </div>
        </div></body></html>';

        wp_mail($to, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
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

        // Sanitize inputs
        $shop_name    = sanitize_text_field($_POST['shop_name'] ?? '');
        $owner_name   = sanitize_text_field($_POST['owner_name'] ?? '');
        $email        = sanitize_email($_POST['email'] ?? '');
        $phone        = sanitize_text_field($_POST['phone'] ?? '');
        $address      = sanitize_text_field($_POST['address'] ?? '');
        $plz          = sanitize_text_field($_POST['plz'] ?? '');
        $city         = sanitize_text_field($_POST['city'] ?? '');
        $password     = $_POST['password'] ?? '';
        $tax_id       = sanitize_text_field($_POST['tax_id'] ?? '');

        // Validate
        if (empty($shop_name) || empty($owner_name) || empty($email) || empty($password)) {
            wp_send_json_error(['message' => 'Bitte alle Pflichtfelder ausfüllen']);
        }
        if (!is_email($email)) {
            wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse']);
        }
        if (strlen($password) < 6) {
            wp_send_json_error(['message' => 'Passwort muss mindestens 6 Zeichen lang sein']);
        }

        // Check email not taken
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_users WHERE email = %s LIMIT 1",
            $email
        ));
        if ($existing) {
            wp_send_json_error(['message' => 'Diese E-Mail ist bereits registriert']);
        }

        // Generate slug from shop name
        $slug = sanitize_title($shop_name);
        $base_slug = $slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM {$prefix}ppv_stores WHERE slug = %s", $slug))) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        // Generate store key and secrets
        $store_key = wp_generate_password(16, false, false);
        $qr_secret = wp_generate_password(32, false, false);
        $api_key   = bin2hex(random_bytes(16));

        // Create PunktePass user as handler
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $qr_token = wp_generate_password(10, false, false);
        $login_token = bin2hex(random_bytes(32));

        $name_parts = explode(' ', trim($owner_name), 2);
        $first_name = $name_parts[0] ?? '';
        $last_name  = $name_parts[1] ?? '';

        $wpdb->insert("{$prefix}ppv_users", [
            'email'        => $email,
            'password'     => $password_hash,
            'first_name'   => $first_name,
            'last_name'    => $last_name,
            'display_name' => trim($owner_name),
            'qr_token'     => $qr_token,
            'login_token'  => $login_token,
            'user_type'    => 'handler',
            'active'       => 1,
            'created_at'   => current_time('mysql'),
            'updated_at'   => current_time('mysql'),
        ]);

        $user_id = $wpdb->insert_id;
        if (!$user_id) {
            wp_send_json_error(['message' => 'Fehler bei der Registrierung']);
        }

        // Create store with repair enabled
        $wpdb->insert("{$prefix}ppv_stores", [
            'user_id'              => $user_id,
            'store_key'            => $store_key,
            'name'                 => $shop_name,
            'slug'                 => $slug,
            'address'              => $address,
            'plz'                  => $plz,
            'city'                 => $city,
            'phone'                => $phone,
            'email'                => $email,
            'qr_secret'            => $qr_secret,
            'pos_api_key'          => $api_key,
            'active'               => 1,
            'visible'              => 1,
            'repair_enabled'       => 1,
            'repair_points_per_form' => 2,
            'repair_form_count'    => 0,
            'repair_form_limit'    => 50,
            'repair_premium'       => 0,
            'repair_company_name'  => $shop_name,
            'repair_owner_name'    => $owner_name,
            'repair_tax_id'        => $tax_id,
            'repair_color'         => '#667eea',
            'trial_ends_at'        => date('Y-m-d H:i:s', strtotime('+30 days')),
            'created_at'           => current_time('mysql'),
        ]);

        $store_id = $wpdb->insert_id;
        if (!$store_id) {
            // Cleanup user
            $wpdb->delete("{$prefix}ppv_users", ['id' => $user_id]);
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Shops']);
        }

        // Create a default reward
        $wpdb->insert("{$prefix}ppv_rewards", [
            'store_id'        => $store_id,
            'name'            => '10 Euro Rabatt',
            'description'     => '10 Euro Rabatt auf Ihre nächste Reparatur',
            'required_points' => 4,
            'points_given'    => 0,
            'active'          => 1,
            'created_at'      => current_time('mysql'),
        ]);

        // Send welcome email
        $form_url = home_url("/repair/{$slug}");
        $admin_url = home_url('/repair-admin');
        self::send_welcome_email($email, $owner_name, $shop_name, $form_url, $admin_url, $password);

        wp_send_json_success([
            'store_id' => $store_id,
            'slug'     => $slug,
            'form_url' => $form_url,
            'message'  => 'Registrierung erfolgreich!',
        ]);
    }

    /** ============================================================
     * Send Welcome Email to new repair shop owner
     * ============================================================ */
    private static function send_welcome_email($email, $name, $shop_name, $form_url, $admin_url, $password) {
        $first_name = explode(' ', trim($name))[0] ?: 'Partner';

        $subject = "Willkommen bei PunktePass Reparatur - {$shop_name}";
        $body = '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
        <div style="max-width:560px;margin:0 auto;padding:20px;">
        <div style="background:linear-gradient(135deg,#667eea,#764ba2);border-radius:16px 16px 0 0;padding:32px 28px;text-align:center;">
            <h1 style="color:#fff;font-size:22px;margin:0 0 8px;">Willkommen bei PunktePass!</h1>
            <p style="color:rgba(255,255,255,0.85);font-size:14px;margin:0;">Ihr Reparaturformular ist bereit</p>
        </div>
        <div style="background:#fff;padding:32px 28px;border-radius:0 0 16px 16px;">
            <p style="font-size:16px;color:#1f2937;margin:0 0 20px;">Hallo <strong>' . esc_html($first_name) . '</strong>,</p>
            <p style="font-size:14px;color:#4b5563;line-height:1.6;margin:0 0 24px;">Ihr digitales Reparaturformular f&uuml;r <strong>' . esc_html($shop_name) . '</strong> ist einsatzbereit! Kunden k&ouml;nnen es &uuml;ber den folgenden Link ausf&uuml;llen:</p>

            <div style="background:#f0f9ff;border:1px solid #bae6fd;border-radius:12px;padding:20px;text-align:center;margin:0 0 24px;">
                <div style="font-size:12px;font-weight:600;color:#0369a1;margin-bottom:8px;">IHR FORMULAR-LINK</div>
                <a href="' . esc_url($form_url) . '" style="font-size:16px;color:#1d4ed8;font-weight:600;word-break:break-all;">' . esc_html($form_url) . '</a>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:20px;margin:0 0 24px;">
                <div style="font-size:13px;font-weight:600;color:#374151;margin-bottom:12px;">IHRE ZUGANGSDATEN</div>
                <p style="margin:4px 0;font-size:14px;"><strong>E-Mail:</strong> ' . esc_html($email) . '</p>
                <p style="margin:4px 0;font-size:14px;"><strong>Passwort:</strong> <code>' . esc_html($password) . '</code></p>
                <p style="margin:4px 0;font-size:14px;"><strong>Admin:</strong> <a href="' . esc_url($admin_url) . '">' . esc_html($admin_url) . '</a></p>
            </div>

            <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:12px;padding:16px;margin:0 0 24px;">
                <div style="font-size:13px;color:#166534;line-height:1.6;">
                    <strong>Inklusiv (kostenlos):</strong><br>
                    &#10003; Digitales Reparaturformular<br>
                    &#10003; Bis zu 50 Formulare<br>
                    &#10003; Automatische Bonuspunkte f&uuml;r Kunden<br>
                    &#10003; PunktePass Kundenbindungssystem<br>
                    &#10003; Datenschutz, AGB &amp; Impressum
                </div>
            </div>

            <div style="text-align:center;">
                <a href="' . esc_url($admin_url) . '" style="display:inline-block;padding:14px 32px;background:linear-gradient(135deg,#667eea,#764ba2);color:#fff;text-decoration:none;border-radius:10px;font-weight:600;">Zum Admin-Bereich</a>
            </div>
        </div>
        </div></body></html>';

        wp_mail($email, $subject, $body, ['Content-Type: text/html; charset=UTF-8']);
    }

    /** ============================================================
     * AJAX: Update Repair Status
     * ============================================================ */
    public static function ajax_update_status() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $repair_id = intval($_POST['repair_id'] ?? 0);
        $new_status = sanitize_text_field($_POST['status'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        $valid_statuses = ['new', 'in_progress', 'waiting_parts', 'done', 'delivered', 'cancelled'];
        if (!in_array($new_status, $valid_statuses)) {
            wp_send_json_error(['message' => 'Ungültiger Status']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        // Verify this repair belongs to the handler's store
        $store_id = self::get_current_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        $repair = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repairs WHERE id = %d AND store_id = %d",
            $repair_id, $store_id
        ));

        if (!$repair) {
            wp_send_json_error(['message' => 'Reparatur nicht gefunden']);
        }

        $update_data = [
            'status'     => $new_status,
            'updated_at' => current_time('mysql'),
        ];

        if (!empty($notes)) {
            $update_data['notes'] = $notes;
        }

        if ($new_status === 'done') {
            $update_data['completed_at'] = current_time('mysql');
        }
        if ($new_status === 'delivered') {
            $update_data['delivered_at'] = current_time('mysql');
        }

        $wpdb->update("{$prefix}ppv_repairs", $update_data, ['id' => $repair_id]);

        wp_send_json_success(['message' => 'Status aktualisiert']);
    }

    /** ============================================================
     * AJAX: Search Repairs
     * ============================================================ */
    public static function ajax_search_repairs() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $search = sanitize_text_field($_POST['search'] ?? '');
        $status = sanitize_text_field($_POST['filter_status'] ?? '');
        $page   = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        $where = ["r.store_id = %d"];
        $params = [$store_id];

        if (!empty($search)) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $where[] = "(r.customer_name LIKE %s OR r.customer_email LIKE %s OR r.customer_phone LIKE %s OR r.device_model LIKE %s)";
            $params = array_merge($params, [$like, $like, $like, $like]);
        }

        if (!empty($status)) {
            $where[] = "r.status = %s";
            $params[] = $status;
        }

        $where_sql = implode(' AND ', $where);

        $total = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$prefix}ppv_repairs r WHERE {$where_sql}",
            ...$params
        ));

        $params[] = $per_page;
        $params[] = $offset;

        $repairs = $wpdb->get_results($wpdb->prepare(
            "SELECT r.* FROM {$prefix}ppv_repairs r WHERE {$where_sql} ORDER BY r.created_at DESC LIMIT %d OFFSET %d",
            ...$params
        ));

        wp_send_json_success([
            'repairs'  => $repairs,
            'total'    => $total,
            'pages'    => ceil($total / $per_page),
            'page'     => $page,
        ]);
    }

    /** ============================================================
     * AJAX: Save Repair Settings
     * ============================================================ */
    public static function ajax_save_settings() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        global $wpdb;

        $update = [];
        if (isset($_POST['repair_points_per_form'])) {
            $update['repair_points_per_form'] = max(0, intval($_POST['repair_points_per_form']));
        }
        if (isset($_POST['repair_company_name'])) {
            $update['repair_company_name'] = sanitize_text_field($_POST['repair_company_name']);
        }
        if (isset($_POST['repair_owner_name'])) {
            $update['repair_owner_name'] = sanitize_text_field($_POST['repair_owner_name']);
        }
        if (isset($_POST['repair_tax_id'])) {
            $update['repair_tax_id'] = sanitize_text_field($_POST['repair_tax_id']);
        }
        if (isset($_POST['repair_color'])) {
            $update['repair_color'] = sanitize_hex_color($_POST['repair_color']) ?: '#667eea';
        }
        if (isset($_POST['name'])) {
            $update['name'] = sanitize_text_field($_POST['name']);
        }
        if (isset($_POST['phone'])) {
            $update['phone'] = sanitize_text_field($_POST['phone']);
        }
        if (isset($_POST['address'])) {
            $update['address'] = sanitize_text_field($_POST['address']);
        }
        if (isset($_POST['plz'])) {
            $update['plz'] = sanitize_text_field($_POST['plz']);
        }
        if (isset($_POST['city'])) {
            $update['city'] = sanitize_text_field($_POST['city']);
        }

        if (!empty($update)) {
            $wpdb->update($wpdb->prefix . 'ppv_stores', $update, ['id' => $store_id]);
        }

        wp_send_json_success(['message' => 'Einstellungen gespeichert']);
    }

    /** ============================================================
     * AJAX: Upload Logo
     * ============================================================ */
    public static function ajax_upload_logo() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = self::get_current_store_id();
        if (!$store_id) {
            wp_send_json_error(['message' => 'Nicht autorisiert']);
        }

        if (empty($_FILES['logo'])) {
            wp_send_json_error(['message' => 'Keine Datei ausgewählt']);
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $file = $_FILES['logo'];
        $allowed = ['image/jpeg', 'image/png', 'image/webp', 'image/svg+xml'];
        if (!in_array($file['type'], $allowed)) {
            wp_send_json_error(['message' => 'Nur JPG, PNG, WebP oder SVG erlaubt']);
        }

        if ($file['size'] > 2 * 1024 * 1024) {
            wp_send_json_error(['message' => 'Datei zu groß (max 2MB)']);
        }

        $upload = wp_handle_upload($file, ['test_form' => false]);
        if (isset($upload['error'])) {
            wp_send_json_error(['message' => $upload['error']]);
        }

        global $wpdb;
        $wpdb->update($wpdb->prefix . 'ppv_stores', [
            'logo' => $upload['url'],
        ], ['id' => $store_id]);

        wp_send_json_success(['url' => $upload['url']]);
    }

    /** ============================================================
     * Get Current Store ID from Session
     * ============================================================ */
    public static function get_current_store_id() {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            @session_start();
        }

        $user_id = $_SESSION['ppv_user_id'] ?? 0;
        if (!$user_id) return 0;

        global $wpdb;
        $store_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE user_id = %d AND repair_enabled = 1 LIMIT 1",
            $user_id
        ));

        return $store_id;
    }

    /** ============================================================
     * Render Error Page (standalone)
     * ============================================================ */
    public static function render_error_page($title, $message) {
        return '<!DOCTYPE html><html lang="de"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc_html($title) . ' - PunktePass</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0b0f17;color:#fff;display:flex;align-items:center;justify-content:center;min-height:100vh}
            .error-box{text-align:center;padding:40px}
            h1{font-size:24px;margin-bottom:12px}
            p{color:rgba(255,255,255,0.6);font-size:16px;margin-bottom:24px}
            a{color:#667eea;text-decoration:none;font-weight:600}
        </style></head><body>
        <div class="error-box">
            <h1>' . esc_html($title) . '</h1>
            <p>' . esc_html($message) . '</p>
            <a href="/">Zur Startseite</a>
        </div></body></html>';
    }

    /** ============================================================
     * Render Legal Page for a Store
     * ============================================================ */
    public static function render_legal_page($store, $page_type) {
        $store_name = esc_html($store->repair_company_name ?: $store->name);
        $owner_name = esc_html($store->repair_owner_name ?: '');
        $address    = esc_html($store->address ?: '');
        $plz        = esc_html($store->plz ?: '');
        $city       = esc_html($store->city ?: '');
        $email      = esc_html($store->email ?: '');
        $phone      = esc_html($store->phone ?: '');
        $tax_id     = esc_html($store->repair_tax_id ?: '');
        $color      = esc_attr($store->repair_color ?: '#667eea');
        $logo       = esc_url($store->logo ?: PPV_PLUGIN_URL . 'assets/img/punktepass-logo.png');
        $slug       = esc_attr($store->slug);

        switch ($page_type) {
            case 'datenschutz':
                $title = 'Datenschutzerklärung';
                $content = self::get_datenschutz_content($store_name, $owner_name, $address, $plz, $city, $email);
                break;
            case 'agb':
                $title = 'Allgemeine Geschäftsbedingungen';
                $content = self::get_agb_content($store_name, $owner_name, $address, $plz, $city, $email);
                break;
            case 'impressum':
                $title = 'Impressum';
                $content = self::get_impressum_content($store_name, $owner_name, $address, $plz, $city, $email, $phone, $tax_id);
                break;
            default:
                return self::render_error_page('Seite nicht gefunden', 'Diese Seite existiert nicht.');
        }

        return '<!DOCTYPE html><html lang="de"><head>
        <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>' . esc_html($title) . ' - ' . $store_name . '</title>
        <style>
            *{margin:0;padding:0;box-sizing:border-box}
            body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#f4f5f7;color:#1f2937;line-height:1.6}
            .legal-header{background:linear-gradient(135deg,' . $color . ',#764ba2);padding:24px 20px;text-align:center}
            .legal-header img{height:40px;margin-bottom:8px;border-radius:8px}
            .legal-header h1{color:#fff;font-size:20px}
            .legal-header .back{color:rgba(255,255,255,0.8);text-decoration:none;font-size:14px;display:inline-block;margin-top:8px}
            .legal-content{max-width:700px;margin:0 auto;padding:24px 20px}
            .legal-content h2{font-size:18px;margin:24px 0 8px;color:#111827}
            .legal-content p{font-size:14px;margin-bottom:12px;color:#4b5563}
            .legal-content ul{padding-left:20px;margin-bottom:12px}
            .legal-content li{font-size:14px;color:#4b5563;margin-bottom:4px}
            .legal-footer{text-align:center;padding:20px;font-size:12px;color:#9ca3af}
            .legal-footer a{color:' . $color . ';text-decoration:none}
        </style></head><body>
        <div class="legal-header">
            <img src="' . $logo . '" alt="' . $store_name . '">
            <h1>' . esc_html($title) . '</h1>
            <a href="/repair/' . $slug . '" class="back">&larr; Zur&uuml;ck zum Formular</a>
        </div>
        <div class="legal-content">' . $content . '</div>
        <div class="legal-footer">
            <a href="/repair/' . $slug . '/datenschutz">Datenschutz</a> &middot;
            <a href="/repair/' . $slug . '/agb">AGB</a> &middot;
            <a href="/repair/' . $slug . '/impressum">Impressum</a>
            <br><br>Powered by <a href="https://punktepass.de">PunktePass</a>
        </div></body></html>';
    }

    /** Auto-generated Datenschutz content */
    private static function get_datenschutz_content($name, $owner, $address, $plz, $city, $email) {
        return '
        <h2>1. Verantwortlicher</h2>
        <p>' . $name . '<br>' . $owner . '<br>' . $address . '<br>' . $plz . ' ' . $city . '<br>E-Mail: ' . $email . '</p>

        <h2>2. Erhobene Daten</h2>
        <p>Bei Nutzung unseres Reparaturformulars erheben wir folgende Daten:</p>
        <ul>
            <li>Name, E-Mail-Adresse, Telefonnummer</li>
            <li>Ger&auml;teinformationen (Marke, Modell, IMEI)</li>
            <li>Problembeschreibung und Zubeh&ouml;r</li>
            <li>Technische Daten (IP-Adresse, Browser)</li>
        </ul>

        <h2>3. Zweck der Verarbeitung</h2>
        <p>Die Daten werden ausschlie&szlig;lich zur Bearbeitung Ihres Reparaturauftrags und zur Verwaltung Ihres PunktePass-Treuepunkte-Kontos verwendet.</p>

        <h2>4. PunktePass Integration</h2>
        <p>Bei Absenden des Formulars wird automatisch ein PunktePass-Konto erstellt (sofern noch nicht vorhanden). Sie erhalten Bonuspunkte, die bei teilnehmenden Gesch&auml;ften einl&ouml;sbar sind. Weitere Informationen: <a href="https://punktepass.de/datenschutz">punktepass.de/datenschutz</a></p>

        <h2>5. Speicherdauer</h2>
        <p>Ihre Reparaturdaten werden f&uuml;r die Dauer der Gesch&auml;ftsbeziehung und gem&auml;&szlig; gesetzlicher Aufbewahrungsfristen gespeichert.</p>

        <h2>6. Ihre Rechte</h2>
        <p>Sie haben das Recht auf Auskunft, Berichtigung, L&ouml;schung, Einschr&auml;nkung der Verarbeitung, Daten&uuml;bertragbarkeit und Widerspruch. Kontaktieren Sie uns unter: ' . $email . '</p>

        <h2>7. Cookies</h2>
        <p>Unser Formular verwendet nur technisch notwendige Cookies. Es werden keine Tracking-Cookies eingesetzt.</p>';
    }

    /** Auto-generated AGB content */
    private static function get_agb_content($name, $owner, $address, $plz, $city, $email) {
        return '
        <h2>1. Geltungsbereich</h2>
        <p>Diese AGB gelten f&uuml;r alle Reparaturauftr&auml;ge, die &uuml;ber das digitale Reparaturformular von ' . $name . ' erteilt werden.</p>

        <h2>2. Vertragsschluss</h2>
        <p>Mit dem Absenden des Reparaturformulars geben Sie ein Angebot zur Erteilung eines Reparaturauftrags ab. Der Vertrag kommt zustande, wenn wir den Auftrag annehmen.</p>

        <h2>3. Reparaturleistungen</h2>
        <p>Wir f&uuml;hren Reparaturen nach bestem Wissen und Gewissen durch. Eine Garantie auf Erfolg der Reparatur kann nicht gegeben werden. Der Kostenvoranschlag ist unverbindlich.</p>

        <h2>4. Preise und Zahlung</h2>
        <p>Die endg&uuml;ltigen Kosten werden nach Diagnose mitgeteilt. Bei wesentlicher Kosten&uuml;berschreitung informieren wir Sie vor Durchf&uuml;hrung.</p>

        <h2>5. PunktePass Bonuspunkte</h2>
        <p>Bei Absenden eines Reparaturformulars erhalten Sie automatisch Bonuspunkte auf Ihr PunktePass-Konto. Diese Punkte k&ouml;nnen f&uuml;r Pr&auml;mien eingetauscht werden. Details unter <a href="https://punktepass.de/agb">punktepass.de/agb</a></p>

        <h2>6. Haftung</h2>
        <p>' . $name . ' haftet nur f&uuml;r Sch&auml;den, die auf Vorsatz oder grobe Fahrl&auml;ssigkeit zur&uuml;ckzuf&uuml;hren sind.</p>

        <h2>7. Abholung</h2>
        <p>Reparierte Ger&auml;te m&uuml;ssen innerhalb von 30 Tagen nach Fertigstellungsmeldung abgeholt werden.</p>

        <h2>8. Schlussbestimmungen</h2>
        <p>Es gilt das Recht der Bundesrepublik Deutschland. Gerichtsstand ist der Sitz des Unternehmens.</p>';
    }

    /** Auto-generated Impressum content */
    private static function get_impressum_content($name, $owner, $address, $plz, $city, $email, $phone, $tax_id) {
        $tax_line = $tax_id ? '<br><strong>USt-IdNr.:</strong> ' . $tax_id : '';
        $phone_line = $phone ? '<br>Telefon: ' . $phone : '';

        return '
        <h2>Angaben gem&auml;&szlig; &sect; 5 TMG</h2>
        <p>' . $name . '<br>' . $owner . '<br>' . $address . '<br>' . $plz . ' ' . $city . '</p>

        <h2>Kontakt</h2>
        <p>E-Mail: <a href="mailto:' . $email . '">' . $email . '</a>' . $phone_line . '</p>

        <h2>Verantwortlich f&uuml;r den Inhalt</h2>
        <p>' . $owner . '<br>' . $address . '<br>' . $plz . ' ' . $city . $tax_line . '</p>

        <h2>EU-Streitschlichtung</h2>
        <p>Die Europ&auml;ische Kommission stellt eine Plattform zur Online-Streitbeilegung (OS) bereit: <a href="https://ec.europa.eu/consumers/odr" target="_blank">https://ec.europa.eu/consumers/odr</a></p>

        <h2>PunktePass Integration</h2>
        <p>Dieses Reparaturformular nutzt die PunktePass-Plattform f&uuml;r das Kundenbindungsprogramm. Betreiber: PunktePass, Erik Borota, Siedlungsring 51, 89415 Lauingen. <a href="https://punktepass.de/impressum">punktepass.de/impressum</a></p>';
    }
}
