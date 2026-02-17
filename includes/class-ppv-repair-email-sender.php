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

        // Stats - single query instead of 3 separate COUNT queries
        $stats = $wpdb->get_row("SELECT
            SUM(status = 'sent') AS total_sent,
            SUM(status = 'sent' AND DATE(sent_at) = CURDATE()) AS today_sent,
            SUM(status = 'failed') AS failed_count
            FROM {$wpdb->prefix}ppv_repair_email_logs");
        $total_sent = $stats ? (int)$stats->total_sent : 0;
        $today_sent = $stats ? (int)$stats->today_sent : 0;
        $failed_count = $stats ? (int)$stats->failed_count : 0;

        self::render_html($logs, $filter, $search, $total_sent, $today_sent, $failed_count);
    }

    /**
     * Create tables if not exists
     */
    private static function maybe_create_table() {
        global $wpdb;

        // Use option flag to skip repeated schema checks
        if (get_option('ppv_email_db_version', '0') === '1') {
            return;
        }

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

        // Add composite index for duplicate check + stats queries
        $idx = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'status_email'");
        if (empty($idx)) {
            $wpdb->query("ALTER TABLE $table ADD INDEX status_email (status, recipient_email)");
        }
        $idx2 = $wpdb->get_results("SHOW INDEX FROM $table WHERE Key_name = 'status_sent_at'");
        if (empty($idx2)) {
            $wpdb->query("ALTER TABLE $table ADD INDEX status_sent_at (status, sent_at)");
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

        update_option('ppv_email_db_version', '1', true);
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
        // Custom kses: allow <a> tags with href/target/style (wp_kses_post may strip them)
        $allowed_html = wp_kses_allowed_html('post');
        $allowed_html['a'] = array(
            'href'   => true,
            'target' => true,
            'rel'    => true,
            'title'  => true,
            'style'  => true,
            'class'  => true,
        );
        $message = wp_kses($_POST['message'] ?? '', $allowed_html);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $force_send = isset($_POST['force_send']);
        $email_lang = sanitize_text_field($_POST['email_lang'] ?? 'de');
        if (!in_array($email_lang, ['de', 'hu', 'ro', 'en'])) $email_lang = 'de';

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
        $html_message = self::build_html_email($message, $email_lang);

        // Headers
        $from_labels = ['de' => 'Reparaturverwaltung', 'hu' => 'Javításkezelő', 'ro' => 'Gestionare Reparații'];
        $from_label = $from_labels[$email_lang] ?? $from_labels['de'];
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: Erik Borota - ' . $from_label . ' <info@punktepass.de>',
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

        // Batch duplicate check - single query instead of one per email
        $already_sent_set = [];
        if (!$force_send && !empty($valid_emails)) {
            $placeholders = implode(',', array_fill(0, count($valid_emails), '%s'));
            $already_sent_list = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT recipient_email FROM {$wpdb->prefix}ppv_repair_email_logs WHERE status = 'sent' AND recipient_email IN ($placeholders)",
                $valid_emails
            ));
            $already_sent_set = array_flip($already_sent_list ?: []);
        }

        // Single email duplicate: show warning with re-send option
        if (!$force_send && count($valid_emails) === 1 && isset($already_sent_set[$valid_emails[0]])) {
            set_transient('ppv_repair_email_form_data', [
                'to_email' => $to_emails_raw,
                'to_name' => $to_name,
                'subject' => $subject,
                'message' => $_POST['message'] ?? '',
                'notes' => $notes,
                'email_lang' => $email_lang,
            ], 300);
            wp_redirect("/formular/email-sender?error=duplicate&email=" . urlencode($valid_emails[0]) . "&lang=" . $email_lang);
            exit;
        }

        foreach ($valid_emails as $to_email) {
            // Check duplicate from pre-fetched set
            if (!$force_send && isset($already_sent_set[$to_email])) {
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
        if ($email_lang !== 'de') {
            $redirect_params[] = "lang=$email_lang";
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
    private static function build_html_email($message, $lang = 'de') {
        // 1) Convert {{CTA:url|text}} placeholders to table-based buttons (survives wp_kses_post)
        $message = preg_replace_callback(
            '/\{\{CTA:([^|]+)\|([^}]+)\}\}/',
            function($matches) {
                $url = trim($matches[1]);
                $text = trim($matches[2]);
                return '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:10px 0;"><tr>' .
                    '<td align="center" bgcolor="#667eea" style="background-color:#667eea;border-radius:10px;">' .
                    '<a href="' . esc_url($url) . '" target="_blank" style="display:inline-block;color:#ffffff;text-decoration:none;padding:14px 28px;font-weight:600;font-size:15px;">' .
                    $text . '</a></td></tr></table>';
            },
            $message
        );
        // 2) Also convert any surviving <a href> tags to buttons (fallback)
        $message = preg_replace_callback(
            '/<a\s+href="([^"]+)"[^>]*>(.*?)<\/a>/s',
            function($matches) {
                $url = $matches[1];
                $text = $matches[2];
                return '<table cellpadding="0" cellspacing="0" border="0" role="presentation" style="margin:10px 0;"><tr>' .
                    '<td align="center" bgcolor="#667eea" style="background-color:#667eea;border-radius:10px;">' .
                    '<a href="' . $url . '" target="_blank" style="display:inline-block;color:#ffffff;text-decoration:none;padding:14px 28px;font-weight:600;font-size:15px;">' .
                    $text . '</a></td></tr></table>';
            },
            $message
        );
        $message = nl2br($message);
        $year = date('Y');

        $header_titles = [
            'de' => ['title' => 'Reparaturverwaltung', 'subtitle' => 'Digitale L&ouml;sung f&uuml;r Ihren Reparatur-Service'],
            'hu' => ['title' => 'Javításkezelő', 'subtitle' => 'Digitális megoldás az Ön javítási szolgáltatásához'],
            'ro' => ['title' => 'Gestionare Reparații', 'subtitle' => 'Soluție digitală pentru serviciul dvs. de reparații'],
            'en' => ['title' => 'Repair Management', 'subtitle' => 'Digital Solution for Your Repair Service'],
        ];
        $footer_titles = [
            'de' => 'Reparaturverwaltung &middot; PunktePass',
            'hu' => 'Javításkezelő &middot; PunktePass',
            'ro' => 'Gestionare Reparații &middot; PunktePass',
            'en' => 'Repair Management &middot; PunktePass',
        ];
        $ht = $header_titles[$lang] ?? $header_titles['de'];
        $ft = $footer_titles[$lang] ?? $footer_titles['de'];

        return '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
</head>
<body style="font-family:Arial,Helvetica,sans-serif;line-height:1.6;color:#1f2937;margin:0;padding:0;background-color:#f3f4f6;">
    <table cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f3f4f6;padding:30px 20px;" role="presentation">
    <tr><td align="center">
    <table cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;background-color:#ffffff;border-radius:16px;overflow:hidden;" role="presentation">
        <!-- Header -->
        <tr>
            <td align="center" bgcolor="#667eea" style="background-color:#667eea;padding:24px 35px;">
                <p style="color:#ffffff;font-size:18px;font-weight:700;margin:0;font-family:Arial,Helvetica,sans-serif;">' . $ht['title'] . '</p>
                <p style="color:#c7d2fe;font-size:13px;margin:4px 0 0 0;font-family:Arial,Helvetica,sans-serif;">' . $ht['subtitle'] . '</p>
            </td>
        </tr>
        <!-- Body -->
        <tr>
            <td style="padding:35px;font-size:15px;color:#374151;line-height:1.8;font-family:Arial,Helvetica,sans-serif;">' . $message . '</td>
        </tr>
        <!-- Footer -->
        <tr>
            <td bgcolor="#1e293b" style="background-color:#1e293b;padding:24px 35px;">
                <table cellpadding="0" cellspacing="0" border="0" width="100%" role="presentation">
                    <tr>
                        <td style="vertical-align:middle;">
                            <p style="font-size:15px;font-weight:600;color:#ffffff;margin:0 0 2px 0;font-family:Arial,Helvetica,sans-serif;">Erik Borota</p>
                            <p style="font-size:12px;color:#94a3b8;margin:0;font-family:Arial,Helvetica,sans-serif;">' . $ft . '</p>
                        </td>
                        <td align="right" style="vertical-align:middle;">
                            <a href="https://wa.me/4917698479520" title="WhatsApp" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background-color:#334155;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:14px;margin-left:8px;">&#9993;</a>
                            <a href="https://punktepass.de/formular" title="Website" style="display:inline-block;width:32px;height:32px;line-height:32px;text-align:center;background-color:#334155;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:14px;margin-left:8px;">&#9679;</a>
                        </td>
                    </tr>
                </table>
                <table cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-top:16px;padding-top:16px;border-top:1px solid #334155;" role="presentation">
                    <tr>
                        <td style="font-size:12px;color:#64748b;font-family:Arial,Helvetica,sans-serif;">
                            <a href="tel:+4917698479520" style="color:#94a3b8;text-decoration:none;margin-right:16px;">+49 176 98479520</a>
                            <a href="mailto:info@punktepass.de" style="color:#94a3b8;text-decoration:none;margin-right:16px;">info@punktepass.de</a>
                            <span style="color:#64748b;">Siedlungsring 51, 89415 Lauingen</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
    </td></tr>
    </table>
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

        // Pre-filled from URL or from lead finder export (transient)
        $prefill_to = '';
        if (isset($_GET['export_key'])) {
            $emails = get_transient(sanitize_text_field($_GET['export_key']));
            if (!empty($emails) && is_array($emails)) {
                $prefill_to = implode("\n", $emails);
                delete_transient(sanitize_text_field($_GET['export_key']));
            }
        } elseif (isset($_GET['to'])) {
            $prefill_to = sanitize_textarea_field($_GET['to']);
        }
        $prefill_name = isset($_GET['name']) ? sanitize_text_field($_GET['name']) : '';

        // Selected language (persisted via URL param)
        $selected_lang = isset($_GET['lang']) ? sanitize_text_field($_GET['lang']) : 'de';
        if (!in_array($selected_lang, ['de', 'hu', 'ro', 'en', 'it'])) $selected_lang = 'de';

        // Default template for Repair Form promotion
        $default_template = 'Guten Tag,

ich habe Ihr Gesch&auml;ft online gefunden und gesehen, dass Sie Reparaturen anbieten.

Kurze Frage: Nutzen Sie aktuell noch Papier-Formulare f&uuml;r die Reparaturannahme, oder haben Sie das schon digital gel&ouml;st?

Ich habe ein kostenloses Tool gebaut, mit dem Werkst&auml;tten ihre Reparaturannahme in 2 Minuten digitalisieren k&ouml;nnen &ndash; inklusive Rechnungen, Kundenverwaltung und DATEV-Export.

Falls das interessant klingt, zeige ich Ihnen gerne kurz, wie es funktioniert.

{{CTA:https://punktepass.de/formular|&#128073; Kostenlos starten}}

Viele Gr&uuml;&szlig;e
Erik Borota';

        $default_subject_de = 'Kurze Frage zu Ihrem Reparatur-Service';

        // Hungarian template
        $default_template_hu = 'J&oacute; napot,

megtal&aacute;ltam az &Ouml;n &uuml;zlet&eacute;t online, &eacute;s l&aacute;ttam, hogy jav&iacute;t&aacute;sokat is v&aacute;llalnak.

Gyors k&eacute;rd&eacute;s: Jelenleg m&eacute;g pap&iacute;r-&uuml;rlapokat haszn&aacute;lnak a jav&iacute;t&aacute;s felv&eacute;tel&eacute;hez, vagy m&aacute;r digitálisan oldott&aacute;k meg?

&Eacute;p&iacute;tettem egy ingyenes eszk&ouml;zt, amellyel a m&uuml;helyek 2 perc alatt digitaliz&aacute;lhatj&aacute;k a jav&iacute;t&aacute;s-felv&eacute;teli folyamatot &ndash; sz&aacute;ml&aacute;z&aacute;ssal, &uuml;gyf&eacute;lkezel&eacute;ssel &eacute;s export funkci&oacute;kkal egy&uuml;tt.

Ha ez &eacute;rdekesnek hangzik, sz&iacute;vesen megmutatom r&ouml;viden, hogyan m&uuml;k&ouml;dik.

{{CTA:https://punktepass.de/formular|&#128073; Ingyenes ind&iacute;t&aacute;s}}

&Uuml;dv&ouml;zlettel,
Erik Borota';

        $default_subject_hu = 'Gyors kérdés a javítási szolgáltatásukról';

        // Romanian template
        $default_template_ro = 'Bun&#259; ziua,

am g&#259;sit magazinul dvs. online &#537;i am v&#259;zut c&#259; oferi&#539;i servicii de repara&#539;ii.

O &icirc;ntrebare rapid&#259;: Folosi&#539;i &icirc;nc&#259; formulare pe h&acirc;rtie pentru recep&#539;ia repara&#539;iilor, sau a&#539;i rezolvat deja digital?

Am construit un instrument gratuit cu care atelierele &icirc;&#537;i pot digitaliza procesul de recep&#539;ie a repara&#539;iilor &icirc;n 2 minute &ndash; inclusiv facturi, gestionarea clien&#539;ilor &#537;i export de date.

Dac&#259; sun&#259; interesant, v&#259; ar&#259;t cu pl&#259;cere pe scurt cum func&#539;ioneaz&#259;.

{{CTA:https://punktepass.de/formular|&#128073; &Icirc;ncepe&#539;i gratuit}}

Cu stim&#259;,
Erik Borota';

        $default_subject_ro = 'Scurtă întrebare despre serviciul dvs. de reparații';

        // Italian promo template
        $default_template_it = 'Buongiorno,

ho trovato il Suo negozio online e ho visto che offrite servizi di riparazione.

Una breve domanda: utilizza ancora moduli cartacei per l&rsquo;accettazione delle riparazioni, oppure ha gi&agrave; risolto in modo digitale?

Ho creato uno strumento gratuito con cui le officine possono digitalizzare il processo di accettazione delle riparazioni in 2 minuti &ndash; incluse fatture, gestione clienti ed esportazione dati.

Se Le sembra interessante, Le mostro volentieri brevemente come funziona.

{{CTA:https://punktepass.de/formular|&#128073; Inizia gratis}}

Cordiali saluti,
Erik Borota';

        $default_subject_it = 'Breve domanda sul Suo servizio di riparazione';

        // English promo template
        $default_template_en = 'Good day,

I found your shop online and noticed that you offer repair services.

Quick question: Are you still using paper forms for repair intake, or have you already gone digital?

I&rsquo;ve built a free tool that lets workshops digitize their repair intake process in 2 minutes &ndash; including invoices, customer management and data export.

If that sounds interesting, I&rsquo;d be happy to briefly show you how it works.

{{CTA:https://punktepass.de/formular|&#128073; Start for free}}

Best regards,
Erik Borota';

        $default_subject_en = 'Quick question about your repair service';

        // Partnership / Kooperation template (DE)
        $partner_template_de = 'Sehr geehrte Damen und Herren,

mein Name ist Erik Borota.

Mit dem &bdquo;Reparaturpass&ldquo; haben wir eine <strong>digitale Reparaturverwaltung f&uuml;r Handy-Werkst&auml;tten</strong> entwickelt. Das System erm&ouml;glicht es Werkst&auml;tten, Reparaturauftr&auml;ge digital zu erfassen, Rechnungen zu erstellen und den Reparaturstatus per QR-Code bereitzustellen.

<strong>Das vollst&auml;ndige Konzept finden Sie hier:</strong>
{{CTA:https://punktepass.de/formular/partner|&#128073; Konzept ansehen}}

Gerne w&uuml;rde ich mit Ihnen pr&uuml;fen, ob eine Partnerschaft sinnvoll sein k&ouml;nnte &ndash; beispielsweise als zus&auml;tzlicher Mehrwert f&uuml;r Ihre Werkstattkunden oder im Rahmen einer strategischen Kooperation.

Falls diese Anfrage nicht an die richtige Stelle gerichtet ist, w&auml;re ich Ihnen sehr dankbar, wenn Sie diese an den zust&auml;ndigen Ansprechpartner weiterleiten k&ouml;nnten.

Ich freue mich &uuml;ber eine kurze R&uuml;ckmeldung.

Mit freundlichen Gr&uuml;&szlig;en
Erik Borota
<strong>PunktePass / Reparaturpass</strong>
info@punktepass.com
www.punktepass.de/formular';

        $partner_subject_de = 'Partnerschaft: Digitale Reparaturverwaltung für Werkstätten';

        // Partnership / Cooperation template (EN)
        $partner_template_en = 'Dear Sir or Madam,

my name is Erik Borota.

With <strong>&ldquo;Reparaturpass&rdquo;</strong>, we have developed a <strong>digital repair management system for mobile phone workshops</strong>. The system enables workshops to digitally record repair orders, create invoices, and provide repair status updates via QR code.

<strong>You can find the full concept here:</strong>
{{CTA:https://punktepass.de/formular/partner|&#128073; View concept}}

I would be happy to explore with you whether a partnership could be beneficial &ndash; for example, as an added value for your workshop customers or as part of a strategic cooperation.

If this inquiry has not reached the right contact, I would be very grateful if you could forward it to the appropriate person.

I look forward to hearing from you.

Kind regards,
Erik Borota
<strong>PunktePass / Reparaturpass</strong>
info@punktepass.com
www.punktepass.de/formular';

        $partner_subject_en = 'Partnership: Digital Repair Management for Workshops';

        // Partnership / Partnerschaft template (HU)
        $partner_template_hu = 'Tisztelt Hölgyem/Uram!

Nevem Erik Borota.

A <strong>&bdquo;Reparaturpass&rdquo;</strong> rendszerrel egy <strong>digitális javításkezelő rendszert fejlesztettünk mobiltelefon-szervizek számára</strong>. A rendszer lehetővé teszi a szervizek számára, hogy digitálisan rögzítsék a javítási megrendeléseket, számlákat készítsenek, és QR-kódon keresztül biztosítsák a javítás állapotának nyomon követését.

<strong>A teljes koncepciót itt tekintheti meg:</strong>
{{CTA:https://punktepass.de/formular/partner|&#128073; Koncepció megtekintése}}

Szívesen egyeztetnék Önnel, hogy egy partnerség kölcsönösen előnyös lehetne-e &ndash; például az Ön szervizügyfeleinek nyújtott többletszolgáltatásként, vagy egy stratégiai együttműködés keretében.

Amennyiben ez a megkeresés nem a megfelelő személyhez érkezett, nagyon hálás lennék, ha továbbítaná az illetékes kollégának.

Várom szíves visszajelzését!

Tisztelettel,
Erik Borota
<strong>PunktePass / Reparaturpass</strong>
info@punktepass.com
www.punktepass.de/formular';

        $partner_subject_hu = 'Partnerség: Digitális javításkezelés szervizek számára';

        // Partnership / Parteneriat template (RO)
        $partner_template_ro = 'Stimate Doamne/Domn,

Numele meu este Erik Borota.

Cu <strong>&bdquo;Reparaturpass&rdquo;</strong>, am dezvoltat un <strong>sistem digital de gestionare a reparațiilor pentru ateliere de telefoane mobile</strong>. Sistemul permite atelierelor să înregistreze digital comenzile de reparații, să creeze facturi și să ofere urmărirea stării reparației prin cod QR.

<strong>Puteți vedea conceptul complet aici:</strong>
{{CTA:https://punktepass.de/formular/partner|&#128073; Vezi conceptul}}

Aș fi bucuros să explorez împreună cu dvs. dacă un parteneriat ar putea fi benefic &ndash; de exemplu, ca un serviciu cu valoare adăugată pentru clienții atelierului dvs. sau ca parte a unei cooperări strategice.

Dacă această solicitare nu a ajuns la persoana potrivită, v-aș fi foarte recunoscător dacă ați putea-o redirecționa către colegul responsabil.

Aștept cu interes răspunsul dvs.

Cu stimă,
Erik Borota
<strong>PunktePass / Reparaturpass</strong>
info@punktepass.com
www.punktepass.de/formular';

        $partner_subject_ro = 'Parteneriat: Gestionare digitală a reparațiilor pentru ateliere';

        // Partnership / Partnership template (IT)
        $partner_template_it = 'Gentile Signora/Signore,

mi chiamo Erik Borota.

Con <strong>&ldquo;Reparaturpass&rdquo;</strong>, abbiamo sviluppato un <strong>sistema digitale di gestione delle riparazioni per laboratori di telefonia mobile</strong>. Il sistema consente ai laboratori di registrare digitalmente gli ordini di riparazione, creare fatture e fornire il monitoraggio dello stato della riparazione tramite codice QR.

<strong>Può consultare il concetto completo qui:</strong>
{{CTA:https://punktepass.de/formular/partner|&#128073; Visualizza il concetto}}

Sarei lieto di esplorare con Lei se una partnership potesse essere vantaggiosa &ndash; ad esempio, come valore aggiunto per i clienti del Suo laboratorio o nell&rsquo;ambito di una cooperazione strategica.

Se questa richiesta non è giunta alla persona competente, Le sarei molto grato se potesse inoltrarla al collega responsabile.

Attendo con piacere un Suo gentile riscontro.

Cordiali saluti,
Erik Borota
<strong>PunktePass / Reparaturpass</strong>
info@punktepass.com
www.punktepass.de/formular';

        $partner_subject_it = 'Partnership: Gestione digitale delle riparazioni per laboratori';

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
        .lang-btn {
            background: #f1f5f9;
            color: #475569;
            border: 2px solid transparent;
            font-weight: 500;
            transition: all 0.2s;
        }
        .lang-btn:hover {
            background: #e2e8f0;
        }
        .lang-btn.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #fff;
            border-color: transparent;
        }
        /* Template type cards */
        .tpl-type-card {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 6px;
            padding: 14px 12px;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }
        .tpl-type-card:hover {
            background: #f1f5f9;
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }
        .tpl-type-card.active {
            background: linear-gradient(135deg, rgba(102,126,234,0.08), rgba(118,75,162,0.08));
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.15);
        }
        .tpl-type-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
        }
        .tpl-type-desc {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 400;
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
    <?php elseif ($error === 'duplicate' && $saved_form_data): ?>
        <div class="alert alert-warning">
            <i class="ri-alert-line"></i> An diese Adresse wurde bereits eine Email gesendet: <strong><?php echo esc_html($duplicate_email); ?></strong>
            <form method="post" enctype="multipart/form-data" style="display:inline;margin-left:12px;">
                <input type="hidden" name="to_email" value="<?php echo esc_attr($saved_form_data['to_email'] ?? ''); ?>">
                <input type="hidden" name="to_name" value="<?php echo esc_attr($saved_form_data['to_name'] ?? ''); ?>">
                <input type="hidden" name="subject" value="<?php echo esc_attr($saved_form_data['subject'] ?? ''); ?>">
                <input type="hidden" name="message" value="<?php echo esc_attr($saved_form_data['message'] ?? ''); ?>">
                <input type="hidden" name="notes" value="<?php echo esc_attr($saved_form_data['notes'] ?? ''); ?>">
                <input type="hidden" name="email_lang" value="<?php echo esc_attr($saved_form_data['email_lang'] ?? 'de'); ?>">
                <input type="hidden" name="force_send" value="1">
                <button type="submit" name="send_email" class="btn btn-sm btn-primary">Trotzdem senden</button>
            </form>
        </div>
    <?php elseif ($error === 'bulk_partial'): ?>
        <div class="alert alert-warning">
            <i class="ri-alert-line"></i>
            <?php
            $skipped = intval($_GET['skipped'] ?? 0);
            $sent_n = intval($_GET['sent'] ?? 0);
            $failed_n = intval($_GET['failed'] ?? 0);
            if ($sent_n > 0) echo "&#10004; $sent_n erfolgreich gesendet &bull; ";
            if ($failed_n > 0) echo "&#10060; $failed_n fehlgeschlagen &bull; ";
            if ($skipped > 0) echo "&#9888; $skipped &uuml;bersprungen (bereits gesendet)";
            ?>
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
                    <input type="hidden" name="email_lang" id="email-lang" value="<?php echo esc_attr($selected_lang); ?>">
                    <input type="hidden" id="email-tpl-type" value="promo">

                    <!-- Template Type Selector -->
                    <div class="form-group">
                        <label style="margin-bottom:10px"><i class="ri-layout-4-line"></i> Vorlage</label>
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:14px;">
                            <button type="button" class="tpl-type-card active" data-type="promo" onclick="switchTemplateType('promo')">
                                <i class="ri-tools-line" style="font-size:22px;color:#667eea"></i>
                                <span class="tpl-type-title">Reparaturverwaltung</span>
                                <span class="tpl-type-desc">Werkstatt-Promotion</span>
                            </button>
                            <button type="button" class="tpl-type-card" data-type="partner" onclick="switchTemplateType('partner')">
                                <i class="ri-handshake-line" style="font-size:22px;color:#f59e0b"></i>
                                <span class="tpl-type-title">Partnerschaft</span>
                                <span class="tpl-type-desc">Kooperation &amp; B2B</span>
                            </button>
                        </div>
                    </div>

                    <!-- Language Template Selector -->
                    <div class="form-group" id="lang-selector-group">
                        <label><i class="ri-translate-2"></i> Sprache / Nyelv / Limb&#259; / Lingua</label>
                        <div style="display:flex;gap:8px;margin-bottom:8px;">
                            <button type="button" class="btn btn-sm lang-btn <?php echo $selected_lang === 'de' ? 'active' : ''; ?>" data-lang="de" onclick="loadLangTemplate('de')">
                                DE Deutsch
                            </button>
                            <button type="button" class="btn btn-sm lang-btn <?php echo $selected_lang === 'hu' ? 'active' : ''; ?>" data-lang="hu" onclick="loadLangTemplate('hu')">
                                HU Magyar
                            </button>
                            <button type="button" class="btn btn-sm lang-btn <?php echo $selected_lang === 'ro' ? 'active' : ''; ?>" data-lang="ro" onclick="loadLangTemplate('ro')">
                                RO Rom&#226;n&#259;
                            </button>
                            <button type="button" class="btn btn-sm lang-btn <?php echo $selected_lang === 'it' ? 'active' : ''; ?>" data-lang="it" onclick="loadLangTemplate('it')">
                                IT Italiano
                            </button>
                            <button type="button" class="btn btn-sm lang-btn <?php echo $selected_lang === 'en' ? 'active' : ''; ?>" data-lang="en" onclick="loadLangTemplate('en')" style="display:none">
                                EN English
                            </button>
                        </div>
                    </div>

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
                        <?php
                        $lang_subjects = ['de' => $default_subject_de, 'hu' => $default_subject_hu, 'ro' => $default_subject_ro, 'it' => $default_subject_it, 'en' => $default_subject_en];
                        $lang_templates = ['de' => $default_template, 'hu' => $default_template_hu, 'ro' => $default_template_ro, 'it' => $default_template_it, 'en' => $default_template_en];
                        $current_subject = $saved_form_data['subject'] ?? $lang_subjects[$selected_lang];
                        $current_template = $saved_form_data['message'] ?? $lang_templates[$selected_lang];
                        ?>
                        <input type="text" name="subject" id="email-subject" class="form-control" required
                               value="<?php echo esc_attr($current_subject); ?>"
                               placeholder="Betreff eingeben...">
                    </div>

                    <div class="form-group">
                        <label>Nachricht * (HTML erlaubt)</label>
                        <textarea name="message" id="email-message" class="form-control" required placeholder="Nachricht eingeben..."><?php echo esc_textarea($current_template); ?></textarea>
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

                    <div style="margin-bottom:16px">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer">
                            <input type="checkbox" name="force_send" value="1">
                            <span style="font-size:13px;color:#64748b">Auch senden, wenn bereits gesendet (Duplikatschutz überspringen)</span>
                        </label>
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
var langTemplates = {
    promo: {
        de: {
            subject: <?php echo json_encode($default_subject_de); ?>,
            message: <?php echo json_encode($default_template); ?>
        },
        hu: {
            subject: <?php echo json_encode($default_subject_hu); ?>,
            message: <?php echo json_encode($default_template_hu); ?>
        },
        ro: {
            subject: <?php echo json_encode($default_subject_ro); ?>,
            message: <?php echo json_encode($default_template_ro); ?>
        },
        it: {
            subject: <?php echo json_encode($default_subject_it); ?>,
            message: <?php echo json_encode($default_template_it); ?>
        },
        en: {
            subject: <?php echo json_encode($default_subject_en); ?>,
            message: <?php echo json_encode($default_template_en); ?>
        }
    },
    partner: {
        de: {
            subject: <?php echo json_encode($partner_subject_de); ?>,
            message: <?php echo json_encode($partner_template_de); ?>
        },
        en: {
            subject: <?php echo json_encode($partner_subject_en); ?>,
            message: <?php echo json_encode($partner_template_en); ?>
        },
        hu: {
            subject: <?php echo json_encode($partner_subject_hu); ?>,
            message: <?php echo json_encode($partner_template_hu); ?>
        },
        ro: {
            subject: <?php echo json_encode($partner_subject_ro); ?>,
            message: <?php echo json_encode($partner_template_ro); ?>
        },
        it: {
            subject: <?php echo json_encode($partner_subject_it); ?>,
            message: <?php echo json_encode($partner_template_it); ?>
        }
    }
};

// Which languages each template type supports
var tplLangs = {
    promo: ['de', 'hu', 'ro', 'it', 'en'],
    partner: ['de', 'en', 'hu', 'ro', 'it']
};

function switchTemplateType(type) {
    document.getElementById('email-tpl-type').value = type;
    // Update cards
    document.querySelectorAll('.tpl-type-card').forEach(function(c) {
        c.classList.toggle('active', c.getAttribute('data-type') === type);
    });
    // Show lang selector, but update available buttons
    var langGroup = document.getElementById('lang-selector-group');
    langGroup.style.display = '';
    var langs = tplLangs[type] || ['de'];
    document.querySelectorAll('.lang-btn').forEach(function(btn) {
        var lang = btn.getAttribute('data-lang');
        btn.style.display = langs.indexOf(lang) !== -1 ? '' : 'none';
    });
    // Pick first available language or keep current if valid
    var currentLang = document.getElementById('email-lang').value;
    var targetLang = langs.indexOf(currentLang) !== -1 ? currentLang : langs[0];
    loadLangTemplate(targetLang);
    // Reset saved template selector
    var sel = document.getElementById('template-select');
    if (sel) sel.value = '';
}

function loadLangTemplate(lang) {
    var type = document.getElementById('email-tpl-type').value || 'promo';
    var tpl = (langTemplates[type] && langTemplates[type][lang]) || langTemplates.promo[lang];
    if (!tpl) return;
    document.getElementById('email-subject').value = tpl.subject;
    document.getElementById('email-message').value = tpl.message;
    document.getElementById('email-lang').value = lang;
    // Update active button
    document.querySelectorAll('.lang-btn').forEach(function(btn) {
        btn.classList.toggle('active', btn.getAttribute('data-lang') === lang);
    });
    // Reset saved template selector
    var sel = document.getElementById('template-select');
    if (sel) sel.value = '';
}

function loadTemplate(id) {
    if (!id) return;
    var select = document.getElementById('template-select');
    var option = select.options[select.selectedIndex];
    document.getElementById('email-subject').value = option.getAttribute('data-subject') || '';
    document.getElementById('email-message').value = option.getAttribute('data-message') || '';
    // Deactivate lang buttons & type cards
    document.querySelectorAll('.lang-btn').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.querySelectorAll('.tpl-type-card').forEach(function(c) {
        c.classList.remove('active');
    });
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
