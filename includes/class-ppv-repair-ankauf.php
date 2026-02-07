<?php
/**
 * PunktePass Repair - Ankauf (Purchase) Management
 * Kaufvertrag for buying phones from customers
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Ankauf {

    /**
     * Ensure Ankauf table exists
     */
    public static function ensure_table() {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") !== $table) {
            $sql = "CREATE TABLE $table (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                ankauf_number VARCHAR(50) NOT NULL,

                -- Verkäufer (Customer selling the device)
                seller_name VARCHAR(255) NOT NULL,
                seller_address VARCHAR(500),
                seller_plz VARCHAR(20),
                seller_city VARCHAR(100),
                seller_email VARCHAR(255),
                seller_phone VARCHAR(100),
                seller_id_type VARCHAR(50) DEFAULT 'personalausweis',
                seller_id_number VARCHAR(100),
                seller_signature LONGTEXT,

                -- Gerät
                device_brand VARCHAR(100),
                device_model VARCHAR(100),
                device_imei VARCHAR(50),
                device_color VARCHAR(50),
                device_condition VARCHAR(50) DEFAULT 'gut',
                device_accessories TEXT,
                device_notes TEXT,

                -- Preis
                ankauf_price DECIMAL(10,2) NOT NULL DEFAULT 0,
                payment_method VARCHAR(50) DEFAULT 'bar',

                -- Shop signature
                buyer_signature LONGTEXT,

                -- Meta
                status VARCHAR(20) DEFAULT 'completed',
                notes TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                INDEX idx_store (store_id),
                INDEX idx_ankauf_number (ankauf_number),
                INDEX idx_imei (device_imei),
                INDEX idx_created (created_at)
            ) $charset;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
        }
    }

    /**
     * AJAX: List Ankäufe
     */
    public static function ajax_list() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        self::ensure_table();

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';

        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;

        // Search
        $search = sanitize_text_field($_POST['search'] ?? '');
        $where = "store_id = %d";
        $params = [$store_id];

        if ($search) {
            $where .= " AND (seller_name LIKE %s OR device_imei LIKE %s OR device_brand LIKE %s OR device_model LIKE %s OR ankauf_number LIKE %s)";
            $like = '%' . $wpdb->esc_like($search) . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }

        $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table WHERE $where", $params));

        $params[] = $per_page;
        $params[] = $offset;
        $items = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE $where ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $params
        ));

        wp_send_json_success([
            'items' => $items,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => $page
        ]);
    }

    /**
     * AJAX: Create Ankauf
     */
    public static function ajax_create() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        self::ensure_table();

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';
        $stores_table = $wpdb->prefix . 'ppv_stores';

        // Get store for prefix
        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stores_table WHERE id = %d", $store_id));

        // Generate Ankauf number
        $prefix = 'ANK-';
        $last_num = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(CAST(SUBSTRING(ankauf_number, 5) AS UNSIGNED)) FROM $table WHERE store_id = %d AND ankauf_number LIKE %s",
            $store_id, 'ANK-%'
        ));
        $next_num = max(1, intval($last_num) + 1);
        $ankauf_number = $prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        // Validate
        $seller_name = sanitize_text_field($_POST['seller_name'] ?? '');
        if (empty($seller_name)) {
            wp_send_json_error(['message' => 'Verkäufer-Name ist erforderlich']);
        }

        $ankauf_price = floatval($_POST['ankauf_price'] ?? 0);
        if ($ankauf_price <= 0) {
            wp_send_json_error(['message' => 'Ankaufspreis ist erforderlich']);
        }

        // Accessories array to JSON
        $accessories = [];
        if (!empty($_POST['device_accessories'])) {
            $acc = $_POST['device_accessories'];
            if (is_array($acc)) {
                $accessories = array_map('sanitize_text_field', $acc);
            } elseif (is_string($acc)) {
                $decoded = json_decode(stripslashes($acc), true);
                if (is_array($decoded)) {
                    $accessories = array_map('sanitize_text_field', $decoded);
                }
            }
        }

        // Insert
        $inserted = $wpdb->insert($table, [
            'store_id'          => $store_id,
            'ankauf_number'     => $ankauf_number,
            'seller_name'       => $seller_name,
            'seller_address'    => sanitize_text_field($_POST['seller_address'] ?? ''),
            'seller_plz'        => sanitize_text_field($_POST['seller_plz'] ?? ''),
            'seller_city'       => sanitize_text_field($_POST['seller_city'] ?? ''),
            'seller_email'      => sanitize_email($_POST['seller_email'] ?? ''),
            'seller_phone'      => sanitize_text_field($_POST['seller_phone'] ?? ''),
            'seller_id_type'    => sanitize_text_field($_POST['seller_id_type'] ?? 'personalausweis'),
            'seller_id_number'  => sanitize_text_field($_POST['seller_id_number'] ?? ''),
            'seller_signature'  => $_POST['seller_signature'] ?? '',
            'device_brand'      => sanitize_text_field($_POST['device_brand'] ?? ''),
            'device_model'      => sanitize_text_field($_POST['device_model'] ?? ''),
            'device_imei'       => sanitize_text_field($_POST['device_imei'] ?? ''),
            'device_color'      => sanitize_text_field($_POST['device_color'] ?? ''),
            'device_condition'  => sanitize_text_field($_POST['device_condition'] ?? 'gut'),
            'device_accessories'=> wp_json_encode($accessories),
            'device_notes'      => sanitize_textarea_field($_POST['device_notes'] ?? ''),
            'ankauf_price'      => $ankauf_price,
            'payment_method'    => sanitize_text_field($_POST['payment_method'] ?? 'bar'),
            'buyer_signature'   => $_POST['buyer_signature'] ?? '',
            'notes'             => sanitize_textarea_field($_POST['notes'] ?? ''),
            'created_at'        => current_time('mysql'),
        ]);

        if (!$inserted) {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }

        $ankauf_id = $wpdb->insert_id;

        wp_send_json_success([
            'message' => 'Ankauf erstellt',
            'ankauf_id' => $ankauf_id,
            'ankauf_number' => $ankauf_number
        ]);
    }

    /**
     * AJAX: Delete Ankauf
     */
    public static function ajax_delete() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $ankauf_id = intval($_POST['ankauf_id'] ?? 0);
        if (!$ankauf_id) wp_send_json_error(['message' => 'Ungültige ID']);

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';

        $deleted = $wpdb->delete($table, [
            'id' => $ankauf_id,
            'store_id' => $store_id
        ]);

        if ($deleted) {
            wp_send_json_success(['message' => 'Ankauf gelöscht']);
        } else {
            wp_send_json_error(['message' => 'Ankauf nicht gefunden']);
        }
    }

    /**
     * AJAX: Download Kaufvertrag PDF
     */
    public static function ajax_pdf() {
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_die('Sicherheitsfehler');
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_die('Nicht autorisiert');

        $ankauf_id = intval($_GET['ankauf_id'] ?? 0);
        if (!$ankauf_id) wp_die('Ungültige ID');

        self::ensure_table();

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';
        $stores_table = $wpdb->prefix . 'ppv_stores';

        $ankauf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND store_id = %d",
            $ankauf_id, $store_id
        ));
        if (!$ankauf) wp_die('Ankauf nicht gefunden');

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $stores_table WHERE id = %d",
            $store_id
        ));

        $html = self::build_kaufvertrag_html($store, $ankauf);

        // Generate PDF with DomPDF
        require_once PPV_PLUGIN_DIR . 'vendor/autoload.php';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'Kaufvertrag-' . $ankauf->ankauf_number . '.pdf';
        $dompdf->stream($filename, ['Attachment' => true]);
        exit;
    }

    /**
     * Build Kaufvertrag HTML
     */
    private static function build_kaufvertrag_html($store, $ankauf) {
        $color = esc_attr($store->repair_color ?: '#667eea');

        // Shop data (Käufer)
        $company = esc_html($store->repair_company_name ?: $store->name);
        $owner = esc_html($store->repair_owner_name ?: '');
        $shop_address = esc_html($store->address ?: '');
        $shop_plz_city = esc_html(trim(($store->plz ?: '') . ' ' . ($store->city ?: '')));
        $shop_phone = esc_html($store->phone ?: '');
        $shop_email = esc_html($store->email ?: '');
        $tax_id = esc_html($store->repair_tax_id ?: '');

        // Verkäufer data
        $seller_name = esc_html($ankauf->seller_name);
        $seller_address = esc_html($ankauf->seller_address ?: '');
        $seller_plz_city = esc_html(trim(($ankauf->seller_plz ?: '') . ' ' . ($ankauf->seller_city ?: '')));
        $seller_phone = esc_html($ankauf->seller_phone ?: '');
        $seller_email = esc_html($ankauf->seller_email ?: '');
        $seller_id_type = $ankauf->seller_id_type === 'reisepass' ? 'Reisepass' : 'Personalausweis';
        $seller_id_number = esc_html($ankauf->seller_id_number ?: '');

        // Device
        $device = esc_html(trim(($ankauf->device_brand ?: '') . ' ' . ($ankauf->device_model ?: '')));
        $imei = esc_html($ankauf->device_imei ?: '-');
        $device_color = esc_html($ankauf->device_color ?: '-');
        $condition_labels = [
            'neu' => 'Neu / Originalverpackt',
            'sehr_gut' => 'Sehr gut (minimale Gebrauchsspuren)',
            'gut' => 'Gut (leichte Gebrauchsspuren)',
            'akzeptabel' => 'Akzeptabel (deutliche Gebrauchsspuren)',
            'defekt' => 'Defekt / Nicht funktionsfähig'
        ];
        $condition = esc_html($condition_labels[$ankauf->device_condition] ?? $ankauf->device_condition);

        $accessories = json_decode($ankauf->device_accessories ?: '[]', true);
        $acc_labels = [
            'ladekabel' => 'Ladekabel',
            'netzteil' => 'Netzteil',
            'originalverpackung' => 'Originalverpackung',
            'kopfhoerer' => 'Kopfhörer',
            'huelle' => 'Hülle/Case',
            'rechnung' => 'Kaufrechnung'
        ];
        $acc_html = '';
        if (!empty($accessories)) {
            $acc_names = array_map(function($a) use ($acc_labels) {
                return $acc_labels[$a] ?? $a;
            }, $accessories);
            $acc_html = esc_html(implode(', ', $acc_names));
        } else {
            $acc_html = 'Keine';
        }

        $device_notes = esc_html($ankauf->device_notes ?: '');

        // Price & Payment
        $price = number_format($ankauf->ankauf_price, 2, ',', '.') . ' €';
        $payment_labels = ['bar' => 'Bar', 'ueberweisung' => 'Überweisung', 'paypal' => 'PayPal'];
        $payment = esc_html($payment_labels[$ankauf->payment_method] ?? $ankauf->payment_method);

        // Date
        $date = date('d.m.Y', strtotime($ankauf->created_at));
        $time = date('H:i', strtotime($ankauf->created_at));

        // Signatures
        $seller_sig = '';
        if (!empty($ankauf->seller_signature) && strpos($ankauf->seller_signature, 'data:image') === 0) {
            $seller_sig = '<img src="' . $ankauf->seller_signature . '" style="max-height:50px;max-width:200px;">';
        }
        $buyer_sig = '';
        if (!empty($ankauf->buyer_signature) && strpos($ankauf->buyer_signature, 'data:image') === 0) {
            $buyer_sig = '<img src="' . $ankauf->buyer_signature . '" style="max-height:50px;max-width:200px;">';
        }

        return '<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Kaufvertrag ' . esc_html($ankauf->ankauf_number) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.4; padding: 20px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 3px solid ' . $color . '; padding-bottom: 15px; margin-bottom: 20px; }
        .logo { font-size: 20px; font-weight: bold; color: ' . $color . '; }
        .logo-sub { font-size: 11px; color: #6b7280; font-weight: normal; }
        .header-right { text-align: right; font-size: 10px; color: #6b7280; }
        .title { text-align: center; background: ' . $color . '; color: #fff; padding: 12px; border-radius: 6px; margin-bottom: 20px; }
        .title h1 { font-size: 16px; margin: 0; }
        .title p { font-size: 11px; margin-top: 4px; opacity: 0.9; }
        .section { margin-bottom: 15px; }
        .section-title { font-size: 10px; font-weight: bold; color: ' . $color . '; text-transform: uppercase; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 10px; }
        .two-col { display: flex; gap: 20px; }
        .two-col > div { flex: 1; }
        .box { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px; }
        .field { margin-bottom: 6px; }
        .field-label { font-weight: 600; color: #6b7280; font-size: 10px; display: inline-block; width: 80px; }
        .field-value { color: #1f2937; }
        .highlight { background: #fef3c7; border-color: #f59e0b; }
        .price-box { text-align: center; padding: 15px; background: linear-gradient(135deg, ' . $color . ', #764ba2); color: #fff; border-radius: 8px; margin: 15px 0; }
        .price-box .label { font-size: 11px; opacity: 0.9; }
        .price-box .amount { font-size: 28px; font-weight: bold; }
        .legal { font-size: 9px; color: #6b7280; margin: 15px 0; padding: 10px; background: #f9fafb; border-radius: 6px; }
        .legal h4 { font-size: 10px; color: #374151; margin-bottom: 6px; }
        .legal ul { padding-left: 15px; }
        .legal li { margin-bottom: 3px; }
        .signatures { display: flex; gap: 30px; margin-top: 20px; padding-top: 15px; border-top: 1px dashed #d1d5db; }
        .sig-box { flex: 1; }
        .sig-box label { display: block; font-size: 9px; color: #6b7280; margin-bottom: 6px; }
        .sig-line { border-bottom: 1px solid #1f2937; height: 50px; display: flex; align-items: flex-end; padding-bottom: 5px; }
        .footer { text-align: center; margin-top: 15px; font-size: 9px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">' . $company . ($owner ? '<br><span class="logo-sub">Inh. ' . $owner . '</span>' : '') . '</div>
        <div class="header-right">
            ' . ($shop_address ? $shop_address . '<br>' : '') . '
            ' . ($shop_plz_city ? $shop_plz_city . '<br>' : '') . '
            ' . ($shop_phone ? 'Tel: ' . $shop_phone . '<br>' : '') . '
            ' . ($shop_email ? $shop_email . '<br>' : '') . '
            ' . ($tax_id ? 'USt-IdNr.: ' . $tax_id : '') . '
        </div>
    </div>

    <div class="title">
        <h1>KAUFVERTRAG (Ankauf)</h1>
        <p>Nr. ' . esc_html($ankauf->ankauf_number) . ' | Datum: ' . $date . ' ' . $time . '</p>
    </div>

    <div class="two-col section">
        <div class="box">
            <div class="section-title">Käufer (Händler)</div>
            <div class="field"><span class="field-label">Firma:</span> <span class="field-value">' . $company . '</span></div>
            ' . ($owner ? '<div class="field"><span class="field-label">Inhaber:</span> <span class="field-value">' . $owner . '</span></div>' : '') . '
            ' . ($shop_address ? '<div class="field"><span class="field-label">Adresse:</span> <span class="field-value">' . $shop_address . '</span></div>' : '') . '
            ' . ($shop_plz_city ? '<div class="field"><span class="field-label"></span> <span class="field-value">' . $shop_plz_city . '</span></div>' : '') . '
            ' . ($shop_phone ? '<div class="field"><span class="field-label">Telefon:</span> <span class="field-value">' . $shop_phone . '</span></div>' : '') . '
        </div>
        <div class="box highlight">
            <div class="section-title">Verkäufer</div>
            <div class="field"><span class="field-label">Name:</span> <span class="field-value"><strong>' . $seller_name . '</strong></span></div>
            ' . ($seller_address ? '<div class="field"><span class="field-label">Adresse:</span> <span class="field-value">' . $seller_address . '</span></div>' : '') . '
            ' . ($seller_plz_city ? '<div class="field"><span class="field-label"></span> <span class="field-value">' . $seller_plz_city . '</span></div>' : '') . '
            ' . ($seller_phone ? '<div class="field"><span class="field-label">Telefon:</span> <span class="field-value">' . $seller_phone . '</span></div>' : '') . '
            ' . ($seller_id_number ? '<div class="field"><span class="field-label">' . $seller_id_type . ':</span> <span class="field-value">' . $seller_id_number . '</span></div>' : '') . '
        </div>
    </div>

    <div class="section">
        <div class="box">
            <div class="section-title">Kaufgegenstand (Gerät)</div>
            <div class="two-col">
                <div>
                    <div class="field"><span class="field-label">Gerät:</span> <span class="field-value"><strong>' . $device . '</strong></span></div>
                    <div class="field"><span class="field-label">IMEI:</span> <span class="field-value" style="font-family:monospace;color:' . $color . ';font-weight:bold;">' . $imei . '</span></div>
                    <div class="field"><span class="field-label">Farbe:</span> <span class="field-value">' . $device_color . '</span></div>
                </div>
                <div>
                    <div class="field"><span class="field-label">Zustand:</span> <span class="field-value">' . $condition . '</span></div>
                    <div class="field"><span class="field-label">Zubehör:</span> <span class="field-value">' . $acc_html . '</span></div>
                </div>
            </div>
            ' . ($device_notes ? '<div class="field" style="margin-top:8px;"><span class="field-label">Hinweise:</span> <span class="field-value">' . $device_notes . '</span></div>' : '') . '
        </div>
    </div>

    <div class="price-box">
        <div class="label">Ankaufspreis</div>
        <div class="amount">' . $price . '</div>
        <div class="label">Zahlungsart: ' . $payment . '</div>
    </div>

    <div class="legal">
        <h4>Rechtliche Hinweise</h4>
        <ul>
            <li>Der Verkäufer versichert, dass er der rechtmäßige Eigentümer des Geräts ist und dieses frei von Rechten Dritter ist.</li>
            <li>Der Verkäufer versichert, dass das Gerät nicht als gestohlen gemeldet ist.</li>
            <li>Das Gerät wird "wie besehen" ohne Gewährleistung verkauft.</li>
            <li>Der Kaufpreis wurde bei Vertragsabschluss ' . ($ankauf->payment_method === 'bar' ? 'bar ausgezahlt' : 'per ' . $payment . ' überwiesen') . '.</li>
            <li>Mit der Unterschrift bestätigt der Verkäufer die Richtigkeit aller Angaben und den Erhalt des Kaufpreises.</li>
        </ul>
    </div>

    <div class="signatures">
        <div class="sig-box">
            <label>Unterschrift Verkäufer (Erhalt des Kaufpreises)</label>
            <div class="sig-line">' . $seller_sig . '</div>
            <div style="font-size:9px;color:#6b7280;margin-top:4px;">' . $seller_name . '</div>
        </div>
        <div class="sig-box">
            <label>Unterschrift Käufer (Händler)</label>
            <div class="sig-line">' . $buyer_sig . '</div>
            <div style="font-size:9px;color:#6b7280;margin-top:4px;">' . $company . '</div>
        </div>
    </div>

    <div class="footer">
        ' . $company . ' | ' . $shop_address . ' ' . $shop_plz_city . ' | Tel: ' . $shop_phone . ' | ' . $shop_email . '
    </div>
</body>
</html>';
    }

    /**
     * AJAX: Send Kaufvertrag via Email
     */
    public static function ajax_email() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $ankauf_id = intval($_POST['ankauf_id'] ?? 0);
        if (!$ankauf_id) wp_send_json_error(['message' => 'Ungültige ID']);

        self::ensure_table();

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_repair_ankauf';
        $stores_table = $wpdb->prefix . 'ppv_stores';

        $ankauf = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE id = %d AND store_id = %d",
            $ankauf_id, $store_id
        ));
        if (!$ankauf) wp_send_json_error(['message' => 'Ankauf nicht gefunden']);
        if (empty($ankauf->seller_email)) wp_send_json_error(['message' => 'Keine E-Mail-Adresse vorhanden']);

        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stores_table WHERE id = %d", $store_id));

        // Generate PDF
        $html = self::build_kaufvertrag_html($store, $ankauf);
        require_once PPV_PLUGIN_DIR . 'vendor/autoload.php';
        $options = new \Dompdf\Options();
        $options->set('isRemoteEnabled', true);
        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_content = $dompdf->output();

        // Save temp file
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/kaufvertrag-' . $ankauf->ankauf_number . '.pdf';
        file_put_contents($pdf_path, $pdf_content);

        $company_name = $store->repair_company_name ?: $store->name;
        $subject = "Kaufvertrag {$ankauf->ankauf_number} - {$company_name}";

        $body = "Sehr geehrte/r {$ankauf->seller_name},\n\n";
        $body .= "anbei erhalten Sie Ihren Kaufvertrag als PDF-Dokument.\n\n";
        $body .= "Kaufvertrag: {$ankauf->ankauf_number}\n";
        $body .= "Gerät: " . trim(($ankauf->device_brand ?: '') . ' ' . ($ankauf->device_model ?: '')) . "\n";
        $body .= "IMEI: {$ankauf->device_imei}\n";
        $body .= "Betrag: " . number_format($ankauf->ankauf_price, 2, ',', '.') . " €\n\n";
        $body .= "Mit freundlichen Grüßen,\n{$company_name}";

        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            "From: {$company_name} <noreply@punktepass.de>"
        ];
        if ($store->email) {
            $headers[] = "Reply-To: {$store->email}";
        }

        $sent = wp_mail($ankauf->seller_email, $subject, $body, $headers, [$pdf_path]);

        // Clean up temp file
        @unlink($pdf_path);

        if ($sent) {
            wp_send_json_success(['message' => 'E-Mail gesendet']);
        } else {
            wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden']);
        }
    }
}
