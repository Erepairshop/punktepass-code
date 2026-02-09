<?php
/**
 * PunktePass Repair - Email Marketing Sender
 * Route: /formular/email-sender
 * Professional email marketing tool for Repair Form promotion
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Email_Sender {

    /**
     * Render email sender page
     */
    public static function render() {
        global $wpdb;

        // Ensure table exists
        self::maybe_create_table();

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (isset($_POST['send_email'])) {
                self::handle_send_email();
            } elseif (isset($_POST['delete_log'])) {
                self::handle_delete_log();
            } elseif (isset($_POST['save_template'])) {
                self::handle_save_template();
            } elseif (isset($_POST['delete_template'])) {
                self::handle_delete_template();
            }
        }

        // Get filters
        $filter = isset($_GET['filter']) ? sanitize_text_field($_GET['filter']) : 'all';
        $search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';

        // Build WHERE
        $where = "1=1";
        $params = [];

        if ($filter === 'sent') {
            $where .= " AND status = 'sent'";
        } elseif ($filter === 'failed') {
            $where .= " AND status = 'failed'";
        } elseif ($filter === 'today') {
            $where .= " AND DATE(sent_at) = CURDATE()";
        }

        if (!empty($search)) {
            $where .= " AND (recipient_email LIKE %s OR recipient_name LIKE %s OR subject LIKE %s)";
            $search_like = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_like;
            $params[] = $search_like;
            $params[] = $search_like;
        }

        // Get logs
        $query = "SELECT * FROM {$wpdb->prefix}ppv_repair_email_logs WHERE $where ORDER BY sent_at DESC LIMIT 100";
        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $logs = $wpdb->get_results($query);
        }

        // Stats
        $total_sent = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repair_email_logs WHERE status = 'sent'");
        $today_sent = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repair_email_logs WHERE status = 'sent' AND DATE(sent_at) = CURDATE()");
        $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_repair_email_logs WHERE status = 'failed'");

        self::render_html($logs, $filter, $search, $total_sent, $today_sent, $failed_count);
    }

    /**
     * Create tables if not exists
     */
    private static function maybe_create_table() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        // Email logs table
        $table = $wpdb->prefix . 'ppv_repair_email_logs';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                recipient_email varchar(255) NOT NULL,
                recipient_name varchar(255) DEFAULT '',
                subject varchar(500) NOT NULL,
                message_preview text,
                attachment_name varchar(255) DEFAULT '',
                status varchar(50) NOT NULL DEFAULT 'sent',
                sent_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                notes text DEFAULT '',
                PRIMARY KEY (id),
                KEY recipient_email (recipient_email),
                KEY status (status),
                KEY sent_at (sent_at)
            ) $charset_collate;";
            dbDelta($sql);
        }

        // Email templates table
        $templates_table = $wpdb->prefix . 'ppv_repair_email_templates';
        if ($wpdb->get_var("SHOW TABLES LIKE '$templates_table'") !== $templates_table) {
            $sql = "CREATE TABLE $templates_table (
                id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                name varchar(255) NOT NULL,
                subject varchar(500) NOT NULL,
                message longtext NOT NULL,
                created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (id)
            ) $charset_collate;";
            dbDelta($sql);
        }
    }

    /**
     * Handle save template
     */
    private static function handle_save_template() {
        global $wpdb;

        $name = sanitize_text_field($_POST['template_name'] ?? '');
        $subject = sanitize_text_field($_POST['template_subject'] ?? '');
        $message = wp_kses_post($_POST['template_message'] ?? '');

        if (empty($name) || empty($message)) {
            wp_redirect("/formular/email-sender?error=template_empty");
            exit;
        }

        $wpdb->insert(
            $wpdb->prefix . 'ppv_repair_email_templates',
            [
                'name' => $name,
                'subject' => $subject,
                'message' => $message
            ],
            ['%s', '%s', '%s']
        );

        wp_redirect("/formular/email-sender?success=template_saved");
        exit;
    }

    /**
     * Handle delete template
     */
    private static function handle_delete_template() {
        global $wpdb;

        $id = intval($_POST['template_id'] ?? 0);

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'ppv_repair_email_templates', ['id' => $id], ['%d']);
        }

        wp_redirect("/formular/email-sender?success=template_deleted");
        exit;
    }

    /**
     * Handle send email
     */
    private static function handle_send_email() {
        global $wpdb;

        $to_emails_raw = $_POST['to_email'] ?? '';
        $to_name = sanitize_text_field($_POST['to_name'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = wp_kses_post($_POST['message'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $force_send = isset($_POST['force_send']);

        if (empty($subject) || empty($message)) {
            wp_redirect("/formular/email-sender?error=empty_fields");
            exit;
        }

        // Parse multiple emails (comma, semicolon, newline, or space separated)
        $email_list = preg_split('/[\s,;]+/', $to_emails_raw, -1, PREG_SPLIT_NO_EMPTY);
        $valid_emails = [];

        foreach ($email_list as $email) {
            $email = trim(sanitize_email($email));
            if (is_email($email)) {
                $valid_emails[] = $email;
            }
        }

        if (empty($valid_emails)) {
            wp_redirect("/formular/email-sender?error=invalid_email");
            exit;
        }

        // Build HTML email
        $html_message = self::build_html_email($message);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Erik Borota - Reparaturverwaltung <info@punktepass.de>',
            'Reply-To: Erik Borota <info@punktepass.de>',
        ];

        // Handle attachment (once for all emails)
        $attachments = [];
        $attachment_name = '';

        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $attachments_dir = $upload_dir['basedir'] . '/ppv-repair-email-attachments';

            if (!file_exists($attachments_dir)) {
                wp_mkdir_p($attachments_dir);
            }

            $filename = sanitize_file_name($_FILES['attachment']['name']);
            $filepath = $attachments_dir . '/' . time() . '_' . $filename;

            if (move_uploaded_file($_FILES['attachment']['tmp_name'], $filepath)) {
                $attachments[] = $filepath;
                $attachment_name = $filename;
            }
        }

        // Send to each email
        $sent_count = 0;
        $failed_count = 0;
        $skipped_count = 0;
        $skipped_emails = [];

        foreach ($valid_emails as $to_email) {
            // Check duplicate (skip if already sent and not force_send)
            $already_sent = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}ppv_repair_email_logs WHERE recipient_email = %s AND status = 'sent' LIMIT 1",
                $to_email
            ));

            if ($already_sent && !$force_send) {
                $skipped_count++;
                $skipped_emails[] = $to_email;
                continue;
            }

            // Send email
            $sent = wp_mail($to_email, $subject, $html_message, $headers, $attachments);

            // Log
            $wpdb->insert(
                $wpdb->prefix . 'ppv_repair_email_logs',
                [
                    'recipient_email' => $to_email,
                    'recipient_name' => $to_name,
                    'subject' => $subject,
                    'message_preview' => wp_trim_words(strip_tags($message), 30),
                    'attachment_name' => $attachment_name,
                    'status' => $sent ? 'sent' : 'failed',
                    'notes' => $notes
                ],
                ['%s', '%s', '%s', '%s', '%s', '%s', '%s']
            );

            if ($sent) {
                $sent_count++;
            } else {
                $failed_count++;
            }

            // Small delay between emails to avoid rate limiting
            if (count($valid_emails) > 1) {
                usleep(200000); // 200ms delay
            }
        }

        // Build redirect URL with results
        $redirect_params = [];
        if ($sent_count > 0) {
            $redirect_params[] = "sent=$sent_count";
        }
        if ($failed_count > 0) {
            $redirect_params[] = "failed=$failed_count";
        }
        if ($skipped_count > 0) {
            $redirect_params[] = "skipped=$skipped_count";
        }

        $redirect_url = "/formular/email-sender?" . ($sent_count > 0 ? "success=bulk&" : "error=bulk_partial&") . implode("&", $redirect_params);
        wp_redirect($redirect_url);
        exit;
    }

    /**
     * Handle delete log
     */
    private static function handle_delete_log() {
        global $wpdb;

        $id = intval($_POST['log_id'] ?? 0);

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'ppv_repair_email_logs', ['id' => $id], ['%d']);
        }

        wp_redirect("/formular/email-sender?success=deleted");
        exit;
    }

    /**
     * Build HTML email with Repair Form branding
     */
    private static function build_html_email($message) {
        $message = nl2br($message);
        $year = date('Y');

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <style>
        @import url("https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap");

        * { box-sizing: border-box; }
        body {
            font-family: "Inter", -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
            color: #1f2937;
            margin: 0;
            padding: 0;
            background: #f3f4f6;
        }

        .email-wrapper {
            padding: 30px 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }

        .email-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 24px 35px;
            text-align: center;
        }

        .email-header-title {
            color: #fff;
            font-size: 18px;
            font-weight: 700;
            margin: 0;
        }

        .email-header-subtitle {
            color: rgba(255,255,255,0.8);
            font-size: 13px;
            margin: 4px 0 0 0;
        }

        .email-body {
            padding: 35px;
            font-size: 15px;
            color: #374151;
            line-height: 1.8;
        }

        .email-body p {
            margin-bottom: 16px;
        }

        .email-body strong {
            color: #1f2937;
        }

        .cta-button {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff !important;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            margin: 10px 0;
        }

        .email-footer {
            background: #1e293b;
            padding: 24px 35px;
        }

        .footer-main {
            display: table;
            width: 100%;
        }

        .footer-left {
            display: table-cell;
            vertical-align: middle;
        }

        .footer-right {
            display: table-cell;
            vertical-align: middle;
            text-align: right;
        }

        .footer-name {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            margin: 0 0 2px 0;
        }

        .footer-title {
            font-size: 12px;
            color: #94a3b8;
            margin: 0;
        }

        .footer-links a {
            display: inline-block;
            width: 32px;
            height: 32px;
            line-height: 32px;
            text-align: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: #94a3b8;
            text-decoration: none;
            font-size: 14px;
            margin-left: 8px;
        }

        .footer-bottom {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 12px;
            color: #64748b;
        }

        .footer-bottom a {
            color: #94a3b8;
            text-decoration: none;
            margin-right: 16px;
        }

        @media only screen and (max-width: 600px) {
            .email-wrapper { padding: 15px; }
            .email-body { padding: 25px; font-size: 14px; }
            .email-footer { padding: 20px 25px; }
            .footer-left, .footer-right { display: block; text-align: center; }
            .footer-right { margin-top: 15px; }
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-container">
            <!-- Header -->
            <div class="email-header">
                <p class="email-header-title">Reparaturverwaltung</p>
                <p class="email-header-subtitle">Digitale L&ouml;sung f&uuml;r Ihren Reparatur-Service</p>
            </div>

            <!-- Body -->
            <div class="email-body">' . $message . '</div>

            <!-- Footer -->
            <div class="email-footer">
                <div class="footer-main">
                    <div class="footer-left">
                        <p class="footer-name">Erik Borota</p>
                        <p class="footer-title">Reparaturverwaltung &middot; PunktePass</p>
                    </div>
                    <div class="footer-right">
                        <div class="footer-links">
                            <a href="https://wa.me/4917698479520" title="WhatsApp">&#9993;</a>
                            <a href="https://punktepass.de/formular" title="Website">&#9679;</a>
                        </div>
                    </div>
                </div>
                <div class="footer-bottom">
                    <a href="tel:+4917698479520">+49 176 98479520</a>
                    <a href="mailto:info@punktepass.de">info@punktepass.de</a>
                    <span>Siedlungsring 51, 89415 Lauingen</span>
                </div>
            </div>
        </div>
    </div>
</body>
</html>';
    }

    /**
     * Render HTML
     */
    private static function render_html($logs, $filter, $search, $total_sent, $today_sent, $failed_count) {
        global $wpdb;

        $success = isset($_GET['success']) ? $_GET['success'] : '';
        $error = isset($_GET['error']) ? $_GET['error'] : '';
        $duplicate_email = isset($_GET['email']) ? $_GET['email'] : '';

        // Load saved templates
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppv_repair_email_templates ORDER BY name ASC");

        // Get saved form data from transient (for duplicate re-send)
        $saved_form_data = get_transient('ppv_repair_email_form_data');
        if ($saved_form_data && $error === 'duplicate') {
            delete_transient('ppv_repair_email_form_data');
        } else {
            $saved_form_data = null;
        }

        // Pre-filled from URL
        $prefill_to = isset($_GET['to']) ? sanitize_textarea_field($_GET['to']) : '';
        $prefill_name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';

        // Default template for Repair Form promotion
        $default_template = 'Guten Tag!

Mein Name ist Erik Borota, ich bin der Betreiber der digitalen Reparaturverwaltung von PunktePass.

Mit unserer <strong>Reparaturverwaltung</strong> k&ouml;nnen Sie Ihren Reparatur-Service komplett digitalisieren &ndash; von der Auftragsannahme bis zur Rechnung.

<strong>Ihre Vorteile als Gesch&auml;ft:</strong>

&#128241; <strong>Online &amp; Vor-Ort nutzbar</strong> &ndash; Kunden f&uuml;llen das Formular online aus oder Sie nutzen ein Tablet im Gesch&auml;ft

&#128206; <strong>Rechnungen &amp; Angebote</strong> &ndash; PDF erstellen und direkt per E-Mail versenden

&#128176; <strong>Digitaler Ankauf</strong> &ndash; Kaufvertr&auml;ge f&uuml;r Handy, KFZ und mehr mit digitaler Unterschrift

&#128202; <strong>DATEV &amp; Export</strong> &ndash; CSV, Excel und DATEV-Export f&uuml;r Ihren Steuerberater

&#128101; <strong>Kundenverwaltung</strong> &ndash; Alle Kunden und deren Reparatur-Historie auf einen Blick

&#11088; <strong>Bonuspunkte (optional)</strong> &ndash; Kunden sammeln Punkte und werden zu Stammkunden

&#9989; <strong>Jede Branche</strong> &ndash; Handy, Computer, KFZ, Fahrrad, Schmuck und mehr

Die Einrichtung dauert nur wenige Minuten und ist <strong>kostenlos</strong>.

<strong>Probieren Sie es jetzt unverbindlich aus:</strong>
<a href="https://punktepass.de/formular" class="cta-button">&#128073; Kostenlos starten</a>

Gerne stelle ich Ihnen das System kurz und unverbindlich, pers&ouml;nlich oder telefonisch, vor.

Mit freundlichen Gr&uuml;&szlig;en
Erik Borota';

        ?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Sender - Reparaturverwaltung</title>

    <!-- Google Analytics (loads only with consent) -->
    <script>
        function loadGoogleAnalytics() {
            if (localStorage.getItem('cookie_consent') === 'accepted') {
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id=G-NDVQK1WSG3';
                document.head.appendChild(s);
                window.dataLayer = window.dataLayer || [];
                function gtag(){dataLayer.push(arguments);}
                window.gtag = gtag;
                gtag('js', new Date());
                gtag('config', 'G-NDVQK1WSG3');
            }
        }
        loadGoogleAnalytics();
    </script>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@4.0.0/fonts/remixicon.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f1f5f9;
            color: #1e293b;
            line-height: 1.6;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 24px;
            color: #fff;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .header p {
            font-size: 13px;
            opacity: 0.9;
            margin-top: 4px;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 24px;
        }
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }
        @media (max-width: 900px) {
            .grid { grid-template-columns: 1fr; }
        }
        .card {
            background: #fff;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.08);
            overflow: hidden;
        }
        .card-header {
            padding: 16px 20px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .card-body {
            padding: 20px;
        }
        .form-group {
            margin-bottom: 16px;
        }
        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 500;
            color: #475569;
            margin-bottom: 6px;
        }
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        textarea.form-control {
            min-height: 300px;
            resize: vertical;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 16px;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
        }
        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102,126,234,0.4);
        }
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
        }
        .btn-secondary:hover {
            background: #e2e8f0;
        }
        .btn-danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .btn-danger:hover {
            background: #fecaca;
        }
        .btn-sm {
            padding: 6px 10px;
            font-size: 12px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: #fff;
            border-radius: 10px;
            padding: 16px;
            text-align: center;
        }
        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        .stat-label {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .alert-success {
            background: #dcfce7;
            color: #166534;
        }
        .alert-error {
            background: #fee2e2;
            color: #dc2626;
        }
        .alert-warning {
            background: #fef3c7;
            color: #92400e;
        }
        .tabs {
            display: flex;
            gap: 4px;
            margin-bottom: 16px;
        }
        .tab {
            padding: 8px 14px;
            font-size: 13px;
            color: #64748b;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .tab:hover {
            background: #f1f5f9;
            color: #475569;
        }
        .tab.active {
            background: #667eea;
            color: #fff;
        }
        .log-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }
        .log-table th {
            text-align: left;
            padding: 10px 12px;
            background: #f8fafc;
            font-weight: 500;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
        }
        .log-table td {
            padding: 10px 12px;
            border-bottom: 1px solid #f1f5f9;
        }
        .log-table tr:hover {
            background: #f8fafc;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }
        .badge-success {
            background: #dcfce7;
            color: #166534;
        }
        .badge-error {
            background: #fee2e2;
            color: #dc2626;
        }
        .template-select {
            margin-bottom: 12px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            font-size: 13px;
            margin-bottom: 8px;
        }
        .back-link:hover {
            color: #fff;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-top: 12px;
        }
        .checkbox-group input {
            width: 16px;
            height: 16px;
        }
        .checkbox-group label {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }
    </style>
</head>
<body>

<div class="header">
    <a href="/formular" class="back-link"><i class="ri-arrow-left-line"></i> Zur&uuml;ck</a>
    <h1><i class="ri-mail-send-line"></i> Email Sender</h1>
    <p>Marketing-Emails f&uuml;r Reparaturverwaltung versenden</p>
</div>

<div class="container">

    <!-- Stats -->
    <div class="stats">
        <div class="stat-card">
            <div class="stat-value"><?php echo intval($total_sent); ?></div>
            <div class="stat-label">Gesamt gesendet</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo intval($today_sent); ?></div>
            <div class="stat-label">Heute gesendet</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?php echo intval($failed_count); ?></div>
            <div class="stat-label">Fehlgeschlagen</div>
        </div>
    </div>

    <!-- Alerts -->
    <?php if ($success === 'sent'): ?>
        <div class="alert alert-success">
            <i class="ri-check-line"></i> Email erfolgreich gesendet an <?php echo esc_html($_GET['to'] ?? ''); ?>
        </div>
    <?php elseif ($success === 'template_saved'): ?>
        <div class="alert alert-success">
            <i class="ri-check-line"></i> Template gespeichert
        </div>
    <?php elseif ($success === 'deleted'): ?>
        <div class="alert alert-success">
            <i class="ri-check-line"></i> Eintrag gel&ouml;scht
        </div>
    <?php elseif ($success === 'bulk'): ?>
        <div class="alert alert-success">
            <i class="ri-mail-send-line"></i> <strong>Massen-Versand abgeschlossen!</strong><br>
            <?php
            $sent = intval($_GET['sent'] ?? 0);
            $failed = intval($_GET['failed'] ?? 0);
            $skipped = intval($_GET['skipped'] ?? 0);
            echo "&#10004; $sent erfolgreich gesendet";
            if ($failed > 0) echo " &bull; &#10060; $failed fehlgeschlagen";
            if ($skipped > 0) echo " &bull; &#9888; $skipped &uuml;bersprungen (bereits gesendet)";
            ?>
        </div>
    <?php endif; ?>

    <?php if ($error === 'invalid_email'): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i> Ung&uuml;ltige E-Mail-Adresse
        </div>
    <?php elseif ($error === 'empty_fields'): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i> Bitte alle Pflichtfelder ausf&uuml;llen
        </div>
    <?php elseif ($error === 'send_failed'): ?>
        <div class="alert alert-error">
            <i class="ri-error-warning-line"></i> Email konnte nicht gesendet werden
        </div>
    <?php elseif ($error === 'duplicate'): ?>
        <div class="alert alert-warning">
            <i class="ri-alert-line"></i> An diese Adresse wurde bereits eine Email gesendet: <strong><?php echo esc_html($duplicate_email); ?></strong>
            <form method="post" enctype="multipart/form-data" style="display:inline;margin-left:12px;">
                <input type="hidden" name="to_email" value="<?php echo esc_attr($saved_form_data['to_email'] ?? ''); ?>">
                <input type="hidden" name="to_name" value="<?php echo esc_attr($saved_form_data['to_name'] ?? ''); ?>">
                <input type="hidden" name="subject" value="<?php echo esc_attr($saved_form_data['subject'] ?? ''); ?>">
                <input type="hidden" name="message" value="<?php echo esc_attr($saved_form_data['message'] ?? ''); ?>">
                <input type="hidden" name="notes" value="<?php echo esc_attr($saved_form_data['notes'] ?? ''); ?>">
                <input type="hidden" name="force_send" value="1">
                <button type="submit" name="send_email" class="btn btn-sm btn-primary">Trotzdem senden</button>
            </form>
        </div>
    <?php endif; ?>

    <!-- Lead Finder Link -->
    <div style="margin-bottom:20px;padding:16px;background:linear-gradient(135deg,#667eea,#764ba2);border-radius:12px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
        <div style="color:#fff">
            <strong style="font-size:16px"><i class="ri-search-eye-line"></i> Lead Finder</strong><br>
            <span style="opacity:0.9;font-size:13px">Automatisch Gesch&auml;fte finden &amp; Emails extrahieren (Google, Bing, DuckDuckGo)</span>
        </div>
        <a href="/formular/lead-finder" class="btn" style="background:#fff;color:#667eea;font-weight:600">
            <i class="ri-arrow-right-line"></i> Lead Finder &ouml;ffnen
        </a>
    </div>

    <!-- Email Extractor Tool -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-header" style="cursor:pointer" onclick="document.getElementById('extractor-body').style.display=document.getElementById('extractor-body').style.display==='none'?'block':'none';this.querySelector('.toggle-icon').classList.toggle('ri-arrow-down-s-line');this.querySelector('.toggle-icon').classList.toggle('ri-arrow-up-s-line')">
            <i class="ri-search-eye-line"></i> Email Extractor
            <i class="toggle-icon ri-arrow-down-s-line" style="float:right"></i>
        </div>
        <div class="card-body" id="extractor-body" style="display:none">
            <p style="color:#64748b;font-size:13px;margin-bottom:12px">
                <i class="ri-information-line"></i> Paste text, HTML or webpage content here and click "Extract" to find all email addresses.
            </p>
            <div class="form-group">
                <textarea id="extractor-input" class="form-control" rows="5" placeholder="Paste website content, HTML, or any text containing email addresses here..."></textarea>
            </div>
            <div style="display:flex;gap:10px;margin-bottom:12px">
                <button type="button" class="btn btn-primary" onclick="extractEmails()">
                    <i class="ri-search-line"></i> Emails extrahieren
                </button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('extractor-input').value=''">
                    <i class="ri-delete-bin-line"></i> Leeren
                </button>
            </div>
            <div id="extractor-result" style="display:none">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                    <strong><span id="extractor-count">0</span> Emails gefunden:</strong>
                    <button type="button" class="btn btn-sm btn-success" onclick="useExtractedEmails()">
                        <i class="ri-arrow-down-line"></i> In Formular &uuml;bernehmen
                    </button>
                </div>
                <textarea id="extractor-output" class="form-control" rows="3" readonly style="font-family:monospace;font-size:12px;background:#f1f5f9"></textarea>
            </div>
        </div>
    </div>

    <script>
    function extractEmails() {
        var input = document.getElementById('extractor-input').value;
        // Regex to find email addresses
        var emailRegex = /[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}/g;
        var matches = input.match(emailRegex) || [];
        // Remove duplicates
        var unique = [...new Set(matches.map(e => e.toLowerCase()))];

        var resultDiv = document.getElementById('extractor-result');
        var output = document.getElementById('extractor-output');
        var count = document.getElementById('extractor-count');

        if (unique.length > 0) {
            output.value = unique.join(', ');
            count.textContent = unique.length;
            resultDiv.style.display = 'block';
        } else {
            output.value = '';
            count.textContent = '0';
            resultDiv.style.display = 'block';
            alert('Keine Email-Adressen gefunden!');
        }
    }

    function useExtractedEmails() {
        var emails = document.getElementById('extractor-output').value;
        var toField = document.querySelector('textarea[name="to_email"]');
        if (toField && emails) {
            if (toField.value.trim()) {
                toField.value = toField.value.trim() + ', ' + emails;
            } else {
                toField.value = emails;
            }
            // Scroll to form
            toField.scrollIntoView({behavior: 'smooth', block: 'center'});
            toField.focus();
        }
    }
    </script>

    <div class="grid">
        <!-- Email Form -->
        <div class="card">
            <div class="card-header">
                <i class="ri-edit-line"></i> Neue Email
            </div>
            <div class="card-body">
                <form method="post" enctype="multipart/form-data">
                    <!-- Template Select -->
                    <?php if (!empty($templates)): ?>
                    <div class="form-group template-select">
                        <label>Template laden</label>
                        <select class="form-control" id="template-select" onchange="loadTemplate(this.value)">
                            <option value="">-- Template w&auml;hlen --</option>
                            <?php foreach ($templates as $tpl): ?>
                                <option value="<?php echo esc_attr($tpl->id); ?>"
                                        data-subject="<?php echo esc_attr($tpl->subject); ?>"
                                        data-message="<?php echo esc_attr($tpl->message); ?>">
                                    <?php echo esc_html($tpl->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <div class="form-group">
                        <label>Empf&auml;nger Email(s) * <small style="font-weight:normal;color:#64748b">&mdash; Mehrere Adressen mit Komma, Semikolon oder Zeilenumbruch trennen</small></label>
                        <textarea name="to_email" class="form-control" required rows="3"
                                  placeholder="email1@beispiel.de, email2@beispiel.de, email3@beispiel.de"
                                  style="font-family:monospace;font-size:13px"><?php echo esc_textarea($prefill_to ?: ($saved_form_data['to_email'] ?? '')); ?></textarea>
                    </div>
                    <div class="form-group">
                        <label>Empf&auml;nger Name <small style="font-weight:normal;color:#64748b">(optional, wird bei allen verwendet)</small></label>
                        <input type="text" name="to_name" class="form-control"
                               value="<?php echo esc_attr($prefill_name ?: ($saved_form_data['to_name'] ?? '')); ?>"
                               placeholder="Max Mustermann">
                    </div>

                    <div class="form-group">
                        <label>Betreff *</label>
                        <input type="text" name="subject" id="email-subject" class="form-control" required
                               value="<?php echo esc_attr($saved_form_data['subject'] ?? 'Digitale Reparaturverwaltung für Ihren Shop'); ?>"
                               placeholder="Betreff eingeben...">
                    </div>

                    <div class="form-group">
                        <label>Nachricht * (HTML erlaubt)</label>
                        <textarea name="message" id="email-message" class="form-control" required placeholder="Nachricht eingeben..."><?php echo esc_textarea($saved_form_data['message'] ?? $default_template); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label>Anhang (optional)</label>
                        <input type="file" name="attachment" class="form-control">
                    </div>

                    <div class="form-group">
                        <label>Notizen (intern)</label>
                        <input type="text" name="notes" class="form-control"
                               value="<?php echo esc_attr($saved_form_data['notes'] ?? ''); ?>"
                               placeholder="z.B. Quelle, Branche...">
                    </div>

                    <button type="submit" name="send_email" class="btn btn-primary">
                        <i class="ri-send-plane-fill"></i> Email senden
                    </button>
                </form>
            </div>
        </div>

        <!-- History -->
        <div class="card">
            <div class="card-header">
                <i class="ri-history-line"></i> Verlauf
            </div>
            <div class="card-body">
                <div class="tabs">
                    <a href="/formular/email-sender?filter=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">Alle</a>
                    <a href="/formular/email-sender?filter=today" class="tab <?php echo $filter === 'today' ? 'active' : ''; ?>">Heute</a>
                    <a href="/formular/email-sender?filter=sent" class="tab <?php echo $filter === 'sent' ? 'active' : ''; ?>">Erfolgreich</a>
                    <a href="/formular/email-sender?filter=failed" class="tab <?php echo $filter === 'failed' ? 'active' : ''; ?>">Fehlgeschlagen</a>
                </div>

                <div style="overflow-x:auto;">
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>Empf&auml;nger</th>
                                <th>Betreff</th>
                                <th>Status</th>
                                <th>Datum</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr>
                                    <td colspan="5" style="text-align:center;color:#94a3b8;padding:24px;">
                                        Keine Eintr&auml;ge
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td>
                                            <div style="font-weight:500;"><?php echo esc_html($log->recipient_email); ?></div>
                                            <?php if ($log->recipient_name): ?>
                                                <div style="font-size:11px;color:#94a3b8;"><?php echo esc_html($log->recipient_name); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                            <?php echo esc_html($log->subject); ?>
                                        </td>
                                        <td>
                                            <?php if ($log->status === 'sent'): ?>
                                                <span class="badge badge-success">Gesendet</span>
                                            <?php else: ?>
                                                <span class="badge badge-error">Fehler</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="font-size:12px;color:#64748b;">
                                            <?php echo date('d.m.Y H:i', strtotime($log->sent_at)); ?>
                                        </td>
                                        <td>
                                            <form method="post" style="display:inline;" onsubmit="return confirm('Wirklich löschen?');">
                                                <input type="hidden" name="log_id" value="<?php echo $log->id; ?>">
                                                <button type="submit" name="delete_log" class="btn btn-sm btn-danger">
                                                    <i class="ri-delete-bin-line"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadTemplate(id) {
    if (!id) return;
    var select = document.getElementById('template-select');
    var option = select.options[select.selectedIndex];
    document.getElementById('email-subject').value = option.getAttribute('data-subject') || '';
    document.getElementById('email-message').value = option.getAttribute('data-message') || '';
}
</script>

<!-- Cookie Consent Banner -->
<div id="cookieConsent" style="display:none; position:fixed; bottom:0; left:0; right:0; background:rgba(30,41,59,0.97); color:#fff; padding:16px 24px; z-index:9999; box-shadow:0 -4px 20px rgba(0,0,0,0.15);">
    <div style="max-width:1200px; margin:0 auto; display:flex; flex-wrap:wrap; align-items:center; justify-content:space-between; gap:16px;">
        <div style="flex:1; min-width:280px;">
            <p style="margin:0; font-size:14px; line-height:1.5;">
                <strong>Cookie-Hinweis:</strong> Wir verwenden Cookies und Google Analytics, um unsere Website zu verbessern.
                <a href="https://punktepass.de/datenschutz" target="_blank" style="color:#93c5fd; text-decoration:underline;">Datenschutzerklärung</a>
            </p>
        </div>
        <div style="display:flex; gap:12px; flex-shrink:0;">
            <button onclick="rejectCookies()" style="padding:10px 20px; background:transparent; border:1px solid rgba(255,255,255,0.3); color:#fff; border-radius:8px; cursor:pointer; font-size:14px; transition:all 0.2s;">Ablehnen</button>
            <button onclick="acceptCookies()" style="padding:10px 24px; background:linear-gradient(135deg,#667eea,#764ba2); border:none; color:#fff; border-radius:8px; cursor:pointer; font-size:14px; font-weight:600; transition:all 0.2s;">Akzeptieren</button>
        </div>
    </div>
</div>
<script>
(function() {
    var consent = localStorage.getItem('cookie_consent');
    if (!consent) {
        document.getElementById('cookieConsent').style.display = 'block';
    }
})();
function acceptCookies() {
    localStorage.setItem('cookie_consent', 'accepted');
    document.getElementById('cookieConsent').style.display = 'none';
    loadGoogleAnalytics();
}
function rejectCookies() {
    localStorage.setItem('cookie_consent', 'rejected');
    document.getElementById('cookieConsent').style.display = 'none';
}
</script>

</body>
</html>
        <?php
    }
}

// Auto-render if called directly
if (basename($_SERVER['PHP_SELF']) === 'class-ppv-repair-email-sender.php') {
    PPV_Repair_Email_Sender::render();
}
