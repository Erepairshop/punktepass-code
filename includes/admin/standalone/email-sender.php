<?php
/**
 * PunktePass Standalone Admin - Email Marketing Sender
 * Route: /admin/email-sender
 * Professional email marketing tool with templates, attachments, and logging
 */

if (!defined('ABSPATH')) exit;

class PPV_Standalone_Email_Sender {

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
        $query = "SELECT * FROM {$wpdb->prefix}ppv_email_logs WHERE $where ORDER BY sent_at DESC LIMIT 100";
        if (!empty($params)) {
            $logs = $wpdb->get_results($wpdb->prepare($query, $params));
        } else {
            $logs = $wpdb->get_results($query);
        }

        // Stats
        $total_sent = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_email_logs WHERE status = 'sent'");
        $today_sent = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_email_logs WHERE status = 'sent' AND DATE(sent_at) = CURDATE()");
        $failed_count = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}ppv_email_logs WHERE status = 'failed'");

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
        $table = $wpdb->prefix . 'ppv_email_logs';
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
        $templates_table = $wpdb->prefix . 'ppv_email_templates';
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
            wp_redirect("/admin/email-sender?error=template_empty");
            exit;
        }

        $wpdb->insert(
            $wpdb->prefix . 'ppv_email_templates',
            [
                'name' => $name,
                'subject' => $subject,
                'message' => $message
            ],
            ['%s', '%s', '%s']
        );

        ppv_log("📝 [Email Sender] Template mentve: {$name}");
        wp_redirect("/admin/email-sender?success=template_saved");
        exit;
    }

    /**
     * Handle delete template
     */
    private static function handle_delete_template() {
        global $wpdb;

        $id = intval($_POST['template_id'] ?? 0);

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'ppv_email_templates', ['id' => $id], ['%d']);
            ppv_log("🗑️ [Email Sender] Template törölve: #{$id}");
        }

        wp_redirect("/admin/email-sender?success=template_deleted");
        exit;
    }

    /**
     * Handle send email
     */
    private static function handle_send_email() {
        global $wpdb;

        $to_email = sanitize_email($_POST['to_email'] ?? '');
        $to_name = sanitize_text_field($_POST['to_name'] ?? '');
        $subject = sanitize_text_field($_POST['subject'] ?? '');
        $message = wp_kses_post($_POST['message'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $force_send = isset($_POST['force_send']);

        if (empty($to_email) || !is_email($to_email)) {
            wp_redirect("/admin/email-sender?error=invalid_email");
            exit;
        }

        if (empty($subject) || empty($message)) {
            wp_redirect("/admin/email-sender?error=empty_fields");
            exit;
        }

        // Check duplicate
        $already_sent = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}ppv_email_logs WHERE recipient_email = %s AND status = 'sent' LIMIT 1",
            $to_email
        ));

        if ($already_sent && !$force_send) {
            // Store form data in transient for re-display
            set_transient('ppv_email_form_data', [
                'to_email' => $to_email,
                'to_name' => $to_name,
                'subject' => $subject,
                'message' => $message,
                'notes' => $notes
            ], 300); // 5 minutes

            wp_redirect("/admin/email-sender?error=duplicate&email=" . urlencode($to_email));
            exit;
        }

        // Build HTML email
        $html_message = self::build_html_email($message);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Erik Borota - PunktePass <info@punktepass.de>',
            'Reply-To: Erik Borota <info@punktepass.de>',
        ];

        // Handle attachment
        $attachments = [];
        $attachment_name = '';
        if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = wp_upload_dir();
            $attachments_dir = $upload_dir['basedir'] . '/ppv-email-attachments';

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

        // Send email
        $sent = wp_mail($to_email, $subject, $html_message, $headers, $attachments);

        // Log
        $wpdb->insert(
            $wpdb->prefix . 'ppv_email_logs',
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
            ppv_log("📧 [Email Sender] Email küldve: {$to_email}");
            wp_redirect("/admin/email-sender?success=sent&to=" . urlencode($to_email));
        } else {
            ppv_log("❌ [Email Sender] Email küldés sikertelen: {$to_email}");
            wp_redirect("/admin/email-sender?error=send_failed");
        }
        exit;
    }

    /**
     * Handle delete log
     */
    private static function handle_delete_log() {
        global $wpdb;

        $id = intval($_POST['log_id'] ?? 0);

        if ($id > 0) {
            $wpdb->delete($wpdb->prefix . 'ppv_email_logs', ['id' => $id], ['%d']);
            ppv_log("🗑️ [Email Sender] Log törölve: #{$id}");
        }

        wp_redirect("/admin/email-sender?success=deleted");
        exit;
    }

    /**
     * Build HTML email
     */
    private static function build_html_email($message) {
        $message = nl2br($message);
        $logo_url = site_url('/wp-content/plugins/punktepass/assets/img/logo.webp');

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body { font-family: "Segoe UI", Tahoma, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f4f4f4; }
        .email-container { max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        .email-header { background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%); padding: 30px; text-align: center; }
        .email-header img.logo { width: 80px; height: auto; margin-bottom: 15px; }
        .email-header h1 { color: #fff; margin: 0; font-size: 1.5rem; }
        .email-body { padding: 35px; }
        .email-body p { margin-bottom: 15px; }
        .email-footer { background: #1a1a2e; color: #fff; padding: 25px 35px; text-align: center; }
        .email-footer p { margin: 5px 0; font-size: 0.9rem; opacity: 0.9; }
        .email-footer a { color: #00d4ff; text-decoration: none; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="email-header">
            <img src="' . $logo_url . '" alt="PunktePass" class="logo">
            <h1>PunktePass</h1>
        </div>
        <div class="email-body">' . $message . '</div>
        <div class="email-footer">
            <p><strong>Erik Borota</strong></p>
            <p>Erepairshop / PunktePass</p>
            <p>Tel/WhatsApp: <a href="tel:+4917698479520">0176 98479520</a></p>
            <p>E-Mail: <a href="mailto:info@punktepass.de">info@punktepass.de</a></p>
            <p style="margin-top: 15px; font-size: 0.8rem; opacity: 0.7;">© ' . date('Y') . ' PunktePass - Digitales Kundenbindungsprogramm</p>
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
        $templates = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}ppv_email_templates ORDER BY name ASC");

        // Get saved form data from transient (for duplicate re-send)
        $saved_form_data = get_transient('ppv_email_form_data');
        if ($saved_form_data && $error === 'duplicate') {
            delete_transient('ppv_email_form_data');
        } else {
            $saved_form_data = null;
        }

        // Default template
        $default_template = 'Guten Tag,

mein Name ist Erik Borota und ich betreibe das Unternehmen Erepairshop sowie das digitale Kundenbindungsprogramm PunktePass.

PunktePass ermöglicht lokalen Geschäften ein modernes Bonus- und Treueprogramm – ähnlich wie bei großen Ketten (z. B. Netto, Lidl, Rewe), jedoch deutlich einfacher, günstiger und ohne technischen Aufwand.

Kunden sammeln Punkte über einen QR-Code, erhalten Prämien und besuchen das Geschäft nachweislich häufiger.

<strong>Ihre Vorteile auf einen Blick:</strong>
• mehr Stammkunden & höhere Besuchsfrequenz
• automatische Kundenrückgewinnung
• kostenlose Sichtbarkeit in der PunktePass-App
• extrem einfache Bedienung: QR scannen – fertig
• funktioniert auf allen Geräten (iPhone, Android, Xiaomi)
• für die ersten 10 Partner: ein kostenloses Handy zum Scannen inklusive Tischständer – komplett gratis

Die Nutzung kostet lediglich <strong>30 € pro Monat</strong>, und Sie können das System <strong>30 Tage unverbindlich testen</strong>.

Im Anhang finden Sie eine übersichtliche Präsentation mit allen Details.

Gerne stelle ich Ihnen PunktePass persönlich in 1 Minute vor.

Ich freue mich über eine kurze Rückmeldung, falls Interesse besteht oder ein mögliches Kennenlernen passt.';

        ?>
        <!DOCTYPE html>
        <html lang="hu">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Sender - PunktePass Admin</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css">
            <style>
                * { box-sizing: border-box; margin: 0; padding: 0; }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                    background: #1a1a2e;
                    color: #e0e0e0;
                    min-height: 100vh;
                }
                .admin-header {
                    background: linear-gradient(135deg, #16213e 0%, #0f3460 100%);
                    padding: 20px 25px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: 15px;
                }
                .admin-header h1 {
                    font-size: 22px;
                    color: #00d9ff;
                    display: flex;
                    align-items: center;
                    gap: 12px;
                }
                .admin-header h1 i {
                    font-size: 28px;
                    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
                    -webkit-background-clip: text;
                    -webkit-text-fill-color: transparent;
                    background-clip: text;
                }
                .header-right { display: flex; align-items: center; gap: 20px; }
                .back-link {
                    color: #888;
                    text-decoration: none;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                    gap: 5px;
                    transition: color 0.2s;
                }
                .back-link:hover { color: #00d9ff; }

                .stats-bar {
                    display: flex;
                    gap: 15px;
                    flex-wrap: wrap;
                }
                .stat-card {
                    background: rgba(0,217,255,0.1);
                    border: 1px solid rgba(0,217,255,0.2);
                    padding: 12px 20px;
                    border-radius: 10px;
                    text-align: center;
                    min-width: 100px;
                }
                .stat-card .number {
                    font-size: 24px;
                    font-weight: bold;
                    color: #00d9ff;
                }
                .stat-card .label {
                    font-size: 11px;
                    color: #888;
                    text-transform: uppercase;
                }
                .stat-card.success { background: rgba(34,197,94,0.1); border-color: rgba(34,197,94,0.3); }
                .stat-card.success .number { color: #22c55e; }
                .stat-card.warning { background: rgba(245,158,11,0.1); border-color: rgba(245,158,11,0.3); }
                .stat-card.warning .number { color: #f59e0b; }

                .container { max-width: 1600px; margin: 0 auto; padding: 25px; }

                .main-grid {
                    display: grid;
                    grid-template-columns: 1fr 450px;
                    gap: 25px;
                }
                @media (max-width: 1200px) {
                    .main-grid { grid-template-columns: 1fr; }
                }

                .card {
                    background: #16213e;
                    border-radius: 16px;
                    overflow: hidden;
                    border: 1px solid #0f3460;
                }
                .card-header {
                    background: #0f3460;
                    padding: 18px 25px;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                }
                .card-header h2 {
                    font-size: 16px;
                    color: #00d9ff;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .card-body { padding: 25px; }

                .success-msg {
                    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
                    color: #d1fae5;
                    padding: 15px 20px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                    display: flex;
                    align-items: center;
                    gap: 10px;
                }
                .error-msg {
                    background: linear-gradient(135deg, #7f1d1d 0%, #991b1b 100%);
                    color: #fecaca;
                    padding: 15px 20px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .warning-msg {
                    background: linear-gradient(135deg, #78350f 0%, #92400e 100%);
                    color: #fef3c7;
                    padding: 15px 20px;
                    border-radius: 10px;
                    margin-bottom: 20px;
                }
                .warning-msg .btns {
                    margin-top: 12px;
                    display: flex;
                    gap: 10px;
                }

                .form-row {
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 20px;
                    margin-bottom: 20px;
                }
                .form-group { margin-bottom: 20px; }
                .form-group.full-width { grid-column: 1 / -1; }
                .form-group label {
                    display: block;
                    margin-bottom: 8px;
                    font-weight: 600;
                    color: #e0e0e0;
                    font-size: 13px;
                }
                .form-group input,
                .form-group textarea,
                .form-group select {
                    width: 100%;
                    padding: 14px 16px;
                    background: #0a1628;
                    border: 2px solid #1f2b4d;
                    border-radius: 10px;
                    color: #fff;
                    font-size: 14px;
                    font-family: inherit;
                    transition: all 0.2s;
                }
                .form-group input:focus,
                .form-group textarea:focus {
                    outline: none;
                    border-color: #00d9ff;
                    box-shadow: 0 0 0 3px rgba(0,217,255,0.1);
                }
                .form-group textarea {
                    min-height: 400px;
                    resize: vertical;
                    line-height: 1.7;
                }
                .form-group input[type="file"] {
                    padding: 12px;
                    cursor: pointer;
                }
                .form-group input[type="file"]::file-selector-button {
                    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
                    color: #000;
                    border: none;
                    padding: 8px 16px;
                    border-radius: 6px;
                    font-weight: 600;
                    cursor: pointer;
                    margin-right: 12px;
                }

                .btn {
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 14px 24px;
                    border-radius: 10px;
                    font-size: 14px;
                    font-weight: 600;
                    text-decoration: none;
                    cursor: pointer;
                    border: none;
                    transition: all 0.2s;
                }
                .btn-primary {
                    background: linear-gradient(135deg, #00d9ff 0%, #0099cc 100%);
                    color: #000;
                    box-shadow: 0 4px 15px rgba(0,217,255,0.3);
                }
                .btn-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 6px 20px rgba(0,217,255,0.4);
                }
                .btn-secondary {
                    background: #374151;
                    color: #fff;
                }
                .btn-secondary:hover { background: #4b5563; }
                .btn-danger {
                    background: #dc2626;
                    color: #fff;
                }
                .btn-danger:hover { background: #b91c1c; }
                .btn-sm {
                    padding: 8px 14px;
                    font-size: 12px;
                }

                .action-buttons {
                    display: flex;
                    gap: 15px;
                    margin-top: 25px;
                    flex-wrap: wrap;
                }

                /* Log list */
                .log-header {
                    padding: 15px 20px;
                    background: #0a1628;
                    border-bottom: 1px solid #1f2b4d;
                }
                .log-header input {
                    width: 100%;
                    padding: 12px 15px;
                    background: #16213e;
                    border: 1px solid #1f2b4d;
                    border-radius: 8px;
                    color: #fff;
                    font-size: 14px;
                }
                .log-header input:focus {
                    outline: none;
                    border-color: #00d9ff;
                }

                .tabs {
                    display: flex;
                    gap: 8px;
                    padding: 15px 20px;
                    background: #0f3460;
                    flex-wrap: wrap;
                }
                .tab {
                    padding: 8px 16px;
                    background: #16213e;
                    border-radius: 8px;
                    text-decoration: none;
                    color: #888;
                    font-size: 12px;
                    font-weight: 600;
                    transition: all 0.2s;
                }
                .tab:hover { background: #1f2b4d; color: #fff; }
                .tab.active { background: #00d9ff; color: #000; }

                .log-list {
                    max-height: 600px;
                    overflow-y: auto;
                }
                .log-item {
                    padding: 18px 20px;
                    border-bottom: 1px solid #0f3460;
                    transition: background 0.2s;
                }
                .log-item:hover { background: #1f2b4d; }
                .log-item .recipient {
                    font-weight: 600;
                    color: #fff;
                    margin-bottom: 3px;
                }
                .log-item .email {
                    color: #00d9ff;
                    font-size: 13px;
                }
                .log-item .subject {
                    color: #888;
                    font-size: 12px;
                    margin-top: 6px;
                }
                .log-item .meta {
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    margin-top: 10px;
                    font-size: 11px;
                }
                .log-item .date { color: #666; }
                .log-item .notes {
                    color: #888;
                    font-size: 11px;
                    font-style: italic;
                    margin-top: 6px;
                    padding: 6px 10px;
                    background: #0a1628;
                    border-radius: 6px;
                }

                .badge {
                    display: inline-block;
                    padding: 4px 10px;
                    border-radius: 20px;
                    font-size: 10px;
                    font-weight: 600;
                }
                .badge-success { background: #065f46; color: #d1fae5; }
                .badge-error { background: #7f1d1d; color: #fecaca; }

                .empty-state {
                    text-align: center;
                    padding: 50px 20px;
                    color: #666;
                }
                .empty-state i {
                    font-size: 48px;
                    margin-bottom: 15px;
                    display: block;
                    color: #00d9ff;
                }

                .delete-form {
                    display: inline;
                }
                .delete-btn {
                    background: none;
                    border: none;
                    color: #666;
                    cursor: pointer;
                    padding: 5px;
                    font-size: 14px;
                    transition: color 0.2s;
                }
                .delete-btn:hover { color: #ef4444; }

                .preview-btn {
                    background: none;
                    border: 1px solid #374151;
                    color: #888;
                    padding: 8px 14px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-size: 12px;
                    transition: all 0.2s;
                }
                .preview-btn:hover {
                    border-color: #00d9ff;
                    color: #00d9ff;
                }

                /* Modal */
                .modal-overlay {
                    display: none;
                    position: fixed;
                    top: 0;
                    left: 0;
                    right: 0;
                    bottom: 0;
                    background: rgba(0,0,0,0.85);
                    z-index: 1000;
                    justify-content: center;
                    align-items: center;
                    padding: 20px;
                }
                .modal-overlay.active { display: flex; }
                .modal {
                    background: #16213e;
                    border-radius: 16px;
                    width: 100%;
                    max-width: 700px;
                    max-height: 90vh;
                    overflow: auto;
                    box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                }
                .modal-header {
                    padding: 20px;
                    border-bottom: 1px solid #0f3460;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                    position: sticky;
                    top: 0;
                    background: #16213e;
                }
                .modal-header h2 { font-size: 18px; color: #00d9ff; }
                .modal-close {
                    background: none;
                    border: none;
                    color: #888;
                    font-size: 28px;
                    cursor: pointer;
                    line-height: 1;
                }
                .modal-close:hover { color: #fff; }
                .modal-body { padding: 0; }
                .modal-body iframe {
                    width: 100%;
                    height: 70vh;
                    border: none;
                }

                /* Attachment indicator */
                .attachment-indicator {
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                    color: #00d9ff;
                    font-size: 11px;
                    margin-left: 8px;
                }
            </style>
        </head>
        <body>
            <div class="admin-header">
                <h1><i class="ri-mail-send-line"></i> Email Marketing Sender</h1>
                <div class="header-right">
                    <div class="stats-bar">
                        <div class="stat-card success">
                            <div class="number"><?php echo intval($total_sent); ?></div>
                            <div class="label">Összes</div>
                        </div>
                        <div class="stat-card">
                            <div class="number"><?php echo intval($today_sent); ?></div>
                            <div class="label">Ma</div>
                        </div>
                        <?php if ($failed_count > 0): ?>
                        <div class="stat-card warning">
                            <div class="number"><?php echo intval($failed_count); ?></div>
                            <div class="label">Sikertelen</div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <a href="/admin" class="back-link"><i class="ri-arrow-left-line"></i> Vissza</a>
                </div>
            </div>

            <div class="container">
                <?php if ($success === 'sent'): ?>
                    <div class="success-msg">
                        <i class="ri-checkbox-circle-fill"></i>
                        Email sikeresen elküldve: <?php echo esc_html($_GET['to'] ?? ''); ?>
                    </div>
                <?php elseif ($success === 'template_saved'): ?>
                    <div class="success-msg">
                        <i class="ri-checkbox-circle-fill"></i>
                        Sablon sikeresen mentve!
                    </div>
                <?php elseif ($success === 'template_deleted'): ?>
                    <div class="success-msg">
                        <i class="ri-checkbox-circle-fill"></i>
                        Sablon törölve!
                    </div>
                <?php elseif ($success === 'deleted'): ?>
                    <div class="success-msg">
                        <i class="ri-checkbox-circle-fill"></i>
                        Log sikeresen törölve!
                    </div>
                <?php endif; ?>

                <?php if ($error === 'invalid_email'): ?>
                    <div class="error-msg">
                        <i class="ri-error-warning-fill"></i> Hibás email cím!
                    </div>
                <?php elseif ($error === 'empty_fields'): ?>
                    <div class="error-msg">
                        <i class="ri-error-warning-fill"></i> Minden kötelező mezőt ki kell tölteni!
                    </div>
                <?php elseif ($error === 'send_failed'): ?>
                    <div class="error-msg">
                        <i class="ri-error-warning-fill"></i> Email küldés sikertelen!
                    </div>
                <?php elseif ($error === 'duplicate' && $saved_form_data): ?>
                    <div class="warning-msg">
                        <i class="ri-alert-fill"></i>
                        <strong>Figyelem:</strong> Erre az email címre már küldtél korábban: <?php echo esc_html($duplicate_email); ?>
                        <div class="btns">
                            <form method="post" enctype="multipart/form-data" style="display: inline;">
                                <input type="hidden" name="send_email" value="1">
                                <input type="hidden" name="force_send" value="1">
                                <input type="hidden" name="to_email" value="<?php echo esc_attr($saved_form_data['to_email']); ?>">
                                <input type="hidden" name="to_name" value="<?php echo esc_attr($saved_form_data['to_name']); ?>">
                                <input type="hidden" name="subject" value="<?php echo esc_attr($saved_form_data['subject']); ?>">
                                <input type="hidden" name="message" value="<?php echo esc_attr($saved_form_data['message']); ?>">
                                <input type="hidden" name="notes" value="<?php echo esc_attr($saved_form_data['notes']); ?>">
                                <button type="submit" class="btn btn-primary btn-sm">Mégis küldöm</button>
                            </form>
                            <a href="/admin/email-sender" class="btn btn-secondary btn-sm">Mégsem</a>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="main-grid">
                    <!-- Email Form -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="ri-edit-line"></i> Új Email küldése</h2>
                            <button type="button" class="preview-btn" onclick="previewEmail()">
                                <i class="ri-eye-line"></i> Előnézet
                            </button>
                        </div>
                        <div class="card-body">
                            <form method="post" enctype="multipart/form-data" id="email-form">
                                <input type="hidden" name="send_email" value="1">

                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="ri-user-line"></i> Címzett neve</label>
                                        <input type="text" name="to_name" id="to_name" placeholder="pl. Max Mustermann">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="ri-mail-line"></i> Címzett email *</label>
                                        <input type="email" name="to_email" id="to_email" required placeholder="email@example.de">
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><i class="ri-text"></i> Tárgy *</label>
                                    <input type="text" name="subject" id="subject" required value="PunktePass – Digitales Kundenbindungsprogramm für Ihr Geschäft">
                                </div>

                                <!-- Template selector -->
                                <div class="form-group">
                                    <label><i class="ri-bookmark-line"></i> Sablon</label>
                                    <div style="display: flex; gap: 10px; align-items: center;">
                                        <select id="template-select" onchange="loadTemplate()" style="flex: 1; padding: 10px 12px; background: #0a1628; border: 1px solid #1f2b4d; border-radius: 6px; color: #fff; font-size: 13px;">
                                            <option value="">-- Válassz sablont --</option>
                                            <option value="default">📄 Alapértelmezett sablon</option>
                                            <?php foreach ($templates as $tpl): ?>
                                                <option value="<?php echo $tpl->id; ?>" data-subject="<?php echo esc_attr($tpl->subject); ?>" data-message="<?php echo esc_attr($tpl->message); ?>">
                                                    📝 <?php echo esc_html($tpl->name); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" class="btn btn-secondary btn-sm" onclick="showSaveTemplateModal()" title="Sablon mentése">
                                            <i class="ri-save-line"></i>
                                        </button>
                                        <?php if (!empty($templates)): ?>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="deleteSelectedTemplate()" title="Sablon törlése" id="delete-template-btn" style="display: none;">
                                            <i class="ri-delete-bin-line"></i>
                                        </button>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label><i class="ri-file-text-line"></i> Üzenet * <small style="color: #666; font-weight: normal;">(HTML támogatott: &lt;strong&gt;, &lt;em&gt;, stb.)</small></label>
                                    <textarea name="message" id="message" required><?php echo esc_textarea($default_template); ?></textarea>
                                </div>

                                <div class="form-row">
                                    <div class="form-group">
                                        <label><i class="ri-attachment-line"></i> Csatolmány (PDF, kép)</label>
                                        <input type="file" name="attachment" accept=".pdf,.doc,.docx,.jpg,.jpeg,.png,.gif">
                                    </div>
                                    <div class="form-group">
                                        <label><i class="ri-sticky-note-line"></i> Megjegyzés (belső)</label>
                                        <input type="text" name="notes" id="notes" placeholder="pl. Bäckerei München, Follow-up 1 hét">
                                    </div>
                                </div>

                                <div class="action-buttons">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="ri-send-plane-fill"></i> Email küldése
                                    </button>
                                    <button type="button" class="btn btn-secondary" onclick="resetForm()">
                                        <i class="ri-refresh-line"></i> Alaphelyzet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Log list -->
                    <div class="card">
                        <div class="card-header">
                            <h2><i class="ri-history-line"></i> Küldési napló</h2>
                        </div>

                        <div class="tabs">
                            <a href="/admin/email-sender?filter=all" class="tab <?php echo $filter === 'all' ? 'active' : ''; ?>">Mind</a>
                            <a href="/admin/email-sender?filter=today" class="tab <?php echo $filter === 'today' ? 'active' : ''; ?>">Ma</a>
                            <a href="/admin/email-sender?filter=sent" class="tab <?php echo $filter === 'sent' ? 'active' : ''; ?>">Sikeres</a>
                            <a href="/admin/email-sender?filter=failed" class="tab <?php echo $filter === 'failed' ? 'active' : ''; ?>">Sikertelen</a>
                        </div>

                        <div class="log-header">
                            <form method="get">
                                <input type="hidden" name="filter" value="<?php echo esc_attr($filter); ?>">
                                <input type="text" name="search" placeholder="🔍 Keresés email, név, tárgy..." value="<?php echo esc_attr($search); ?>">
                            </form>
                        </div>

                        <div class="log-list">
                            <?php if (empty($logs)): ?>
                                <div class="empty-state">
                                    <i class="ri-inbox-line"></i>
                                    <p>Még nincs elküldött email</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                    <div class="log-item">
                                        <div class="recipient">
                                            <?php echo esc_html($log->recipient_name ?: 'Ismeretlen'); ?>
                                            <?php if (!empty($log->attachment_name)): ?>
                                                <span class="attachment-indicator"><i class="ri-attachment-line"></i> <?php echo esc_html($log->attachment_name); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="email"><?php echo esc_html($log->recipient_email); ?></div>
                                        <div class="subject"><?php echo esc_html($log->subject); ?></div>
                                        <?php if (!empty($log->notes)): ?>
                                            <div class="notes">📝 <?php echo esc_html($log->notes); ?></div>
                                        <?php endif; ?>
                                        <div class="meta">
                                            <span class="date"><?php echo date('Y-m-d H:i', strtotime($log->sent_at)); ?></span>
                                            <div style="display: flex; align-items: center; gap: 10px;">
                                                <span class="badge badge-<?php echo $log->status === 'sent' ? 'success' : 'error'; ?>">
                                                    <?php echo $log->status === 'sent' ? '✓ Elküldve' : '✗ Sikertelen'; ?>
                                                </span>
                                                <form method="post" class="delete-form" onsubmit="return confirm('Biztosan törlöd?');">
                                                    <input type="hidden" name="delete_log" value="1">
                                                    <input type="hidden" name="log_id" value="<?php echo intval($log->id); ?>">
                                                    <button type="submit" class="delete-btn" title="Törlés"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Preview Modal -->
            <div class="modal-overlay" id="previewModal">
                <div class="modal">
                    <div class="modal-header">
                        <h2><i class="ri-eye-line"></i> Email előnézet</h2>
                        <button class="modal-close" onclick="closePreview()">&times;</button>
                    </div>
                    <div class="modal-body">
                        <iframe id="preview-frame"></iframe>
                    </div>
                </div>
            </div>

            <!-- Save Template Modal -->
            <div class="modal-overlay" id="saveTemplateModal">
                <div class="modal" style="max-width: 500px;">
                    <div class="modal-header">
                        <h2><i class="ri-save-line"></i> Sablon mentése</h2>
                        <button class="modal-close" onclick="closeSaveTemplateModal()">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 25px;">
                        <form method="post" id="save-template-form">
                            <input type="hidden" name="save_template" value="1">
                            <input type="hidden" name="template_subject" id="save-template-subject">
                            <input type="hidden" name="template_message" id="save-template-message">

                            <div class="form-group">
                                <label style="color: #fff;">Sablon neve *</label>
                                <input type="text" name="template_name" id="template-name" required placeholder="pl. PunktePass bemutatkozás" style="width: 100%; padding: 12px; background: #0a1628; border: 1px solid #1f2b4d; border-radius: 8px; color: #fff; font-size: 14px;">
                            </div>

                            <div style="display: flex; gap: 10px; margin-top: 20px;">
                                <button type="submit" class="btn btn-primary" style="flex: 1;">
                                    <i class="ri-save-line"></i> Mentés
                                </button>
                                <button type="button" class="btn btn-secondary" onclick="closeSaveTemplateModal()">Mégsem</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Delete Template Form (hidden) -->
            <form method="post" id="delete-template-form" style="display: none;">
                <input type="hidden" name="delete_template" value="1">
                <input type="hidden" name="template_id" id="delete-template-id">
            </form>

            <script>
            const defaultTemplate = <?php echo json_encode($default_template); ?>;
            const defaultSubject = 'PunktePass – Digitales Kundenbindungsprogramm für Ihr Geschäft';
            const logoUrl = '<?php echo site_url('/wp-content/plugins/punktepass/assets/img/logo.webp'); ?>';

            function resetForm() {
                document.getElementById('to_email').value = '';
                document.getElementById('to_name').value = '';
                document.getElementById('notes').value = '';
                document.getElementById('subject').value = defaultSubject;
                document.getElementById('message').value = defaultTemplate;
                document.getElementById('template-select').value = '';
                document.getElementById('delete-template-btn')?.style.setProperty('display', 'none');
            }

            // Template functions
            function loadTemplate() {
                const select = document.getElementById('template-select');
                const option = select.options[select.selectedIndex];
                const deleteBtn = document.getElementById('delete-template-btn');

                if (select.value === 'default') {
                    document.getElementById('subject').value = defaultSubject;
                    document.getElementById('message').value = defaultTemplate;
                    if (deleteBtn) deleteBtn.style.display = 'none';
                } else if (select.value && option.dataset.message) {
                    document.getElementById('subject').value = option.dataset.subject || defaultSubject;
                    document.getElementById('message').value = option.dataset.message;
                    if (deleteBtn) deleteBtn.style.display = 'inline-flex';
                } else {
                    if (deleteBtn) deleteBtn.style.display = 'none';
                }
            }

            function showSaveTemplateModal() {
                document.getElementById('save-template-subject').value = document.getElementById('subject').value;
                document.getElementById('save-template-message').value = document.getElementById('message').value;
                document.getElementById('template-name').value = '';
                document.getElementById('saveTemplateModal').classList.add('active');
            }

            function closeSaveTemplateModal() {
                document.getElementById('saveTemplateModal').classList.remove('active');
            }

            function deleteSelectedTemplate() {
                const select = document.getElementById('template-select');
                if (!select.value || select.value === 'default') return;

                if (confirm('Biztosan törlöd ezt a sablont?')) {
                    document.getElementById('delete-template-id').value = select.value;
                    document.getElementById('delete-template-form').submit();
                }
            }

            // Close save template modal on overlay click
            document.getElementById('saveTemplateModal').addEventListener('click', function(e) {
                if (e.target === this) closeSaveTemplateModal();
            });

            function previewEmail() {
                const message = document.getElementById('message').value;
                const subject = document.getElementById('subject').value;

                const htmlMessage = message.replace(/\n/g, '<br>');

                const previewHtml = `
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset="UTF-8">
                        <style>
                            body { font-family: 'Segoe UI', Tahoma, sans-serif; margin: 0; padding: 0; background: #f4f4f4; }
                        </style>
                    </head>
                    <body>
                        <div style="padding: 15px; background: #1a1a2e; color: #fff; font-size: 14px;">
                            <strong>Tárgy:</strong> ${escapeHtml(subject)}
                        </div>
                        <div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
                            <div style="background: linear-gradient(135deg, #00d4ff 0%, #0099cc 100%); padding: 30px; text-align: center;">
                                <img src="${logoUrl}" alt="PunktePass" style="width: 80px; height: auto; margin-bottom: 15px;">
                                <h1 style="color: #fff; margin: 0; font-size: 1.5rem;">PunktePass</h1>
                            </div>
                            <div style="padding: 35px; line-height: 1.7;">${htmlMessage}</div>
                            <div style="background: #1a1a2e; color: #fff; padding: 25px 35px; text-align: center;">
                                <p style="margin: 5px 0;"><strong>Erik Borota</strong></p>
                                <p style="margin: 5px 0;">Erepairshop / PunktePass</p>
                                <p style="margin: 5px 0;">Tel/WhatsApp: 0176 98479520</p>
                                <p style="margin: 5px 0;">E-Mail: info@punktepass.de</p>
                            </div>
                        </div>
                    </body>
                    </html>
                `;

                const frame = document.getElementById('preview-frame');
                frame.srcdoc = previewHtml;
                document.getElementById('previewModal').classList.add('active');
            }

            function closePreview() {
                document.getElementById('previewModal').classList.remove('active');
            }

            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Close modal on overlay click
            document.getElementById('previewModal').addEventListener('click', function(e) {
                if (e.target === this) closePreview();
            });

            // Escape key to close
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closePreview();
            });
            </script>
        </body>
        </html>
        <?php
    }
}
