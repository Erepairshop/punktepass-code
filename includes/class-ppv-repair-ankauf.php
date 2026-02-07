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
                ankauf_type VARCHAR(50) DEFAULT 'handy',

                -- Verkäufer (Customer selling the item)
                seller_name VARCHAR(255) NOT NULL,
                seller_address VARCHAR(500),
                seller_plz VARCHAR(20),
                seller_city VARCHAR(100),
                seller_email VARCHAR(255),
                seller_phone VARCHAR(100),
                seller_id_type VARCHAR(50) DEFAULT 'personalausweis',
                seller_id_number VARCHAR(100),
                seller_signature LONGTEXT,

                -- Item (Handy/KFZ/Sonstiges)
                device_brand VARCHAR(100),
                device_model VARCHAR(100),
                device_imei VARCHAR(50),
                device_color VARCHAR(50),
                device_condition VARCHAR(50) DEFAULT 'gut',
                device_accessories TEXT,
                device_notes TEXT,

                -- KFZ specific
                kfz_kennzeichen VARCHAR(20),
                kfz_vin VARCHAR(50),
                kfz_km_stand INT,
                kfz_erstzulassung VARCHAR(20),
                kfz_tuev VARCHAR(20),
                kfz_hu_au VARCHAR(20),

                -- Sonstiges
                item_description TEXT,

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

        // Insert data array
        $insert_data = [
            'store_id'          => $store_id,
            'ankauf_number'     => $ankauf_number,
            'ankauf_type'       => sanitize_text_field($_POST['ankauf_type'] ?? 'handy'),
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
        ];

        // Add KFZ specific fields
        if (isset($_POST['kfz_kennzeichen'])) {
            $insert_data['kfz_kennzeichen'] = sanitize_text_field($_POST['kfz_kennzeichen']);
        }
        if (isset($_POST['kfz_vin'])) {
            $insert_data['kfz_vin'] = sanitize_text_field($_POST['kfz_vin']);
        }
        if (isset($_POST['kfz_km_stand'])) {
            $insert_data['kfz_km_stand'] = intval($_POST['kfz_km_stand']);
        }
        if (isset($_POST['kfz_erstzulassung'])) {
            $insert_data['kfz_erstzulassung'] = sanitize_text_field($_POST['kfz_erstzulassung']);
        }
        if (isset($_POST['kfz_tuev'])) {
            $insert_data['kfz_tuev'] = sanitize_text_field($_POST['kfz_tuev']);
        }
        if (isset($_POST['kfz_hu_au'])) {
            $insert_data['kfz_hu_au'] = sanitize_text_field($_POST['kfz_hu_au']);
        }

        // Add Sonstiges specific field
        if (isset($_POST['item_description'])) {
            $insert_data['item_description'] = sanitize_textarea_field($_POST['item_description']);
        }

        // Insert
        $inserted = $wpdb->insert($table, $insert_data);

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
        $ankauf_type = $ankauf->ankauf_type ?: 'handy';

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
        $id_type_labels = ['personalausweis' => 'Personalausweis', 'reisepass' => 'Reisepass', 'fuehrerschein' => 'Führerschein'];
        $seller_id_type = $id_type_labels[$ankauf->seller_id_type] ?? 'Personalausweis';
        $seller_id_number = esc_html($ankauf->seller_id_number ?: '');

        // Item data
        $device = esc_html(trim(($ankauf->device_brand ?: '') . ' ' . ($ankauf->device_model ?: '')));
        $device_color = esc_html($ankauf->device_color ?: '-');
        $condition_labels = [
            'neu' => 'Neu / Originalverpackt',
            'sehr_gut' => 'Sehr gut (minimale Gebrauchsspuren)',
            'gut' => 'Gut (leichte Gebrauchsspuren)',
            'akzeptabel' => 'Akzeptabel (deutliche Gebrauchsspuren)',
            'defekt' => 'Defekt / Nicht funktionsfähig'
        ];
        $condition = esc_html($condition_labels[$ankauf->device_condition] ?? $ankauf->device_condition);
        $device_notes = esc_html($ankauf->device_notes ?: '');

        // Build item section based on type
        $item_section = '';
        $item_type_label = '';
        $legal_item_name = 'Kaufgegenstand';

        if ($ankauf_type === 'kfz') {
            $item_type_label = 'Fahrzeug (KFZ)';
            $legal_item_name = 'Fahrzeug';
            $kennzeichen = esc_html($ankauf->kfz_kennzeichen ?: '-');
            $vin = esc_html($ankauf->kfz_vin ?: '-');
            $km = $ankauf->kfz_km_stand ? number_format($ankauf->kfz_km_stand, 0, ',', '.') . ' km' : '-';
            $erstzulassung = esc_html($ankauf->kfz_erstzulassung ?: '-');
            $tuev = esc_html($ankauf->kfz_tuev ?: '-');
            $huau = esc_html($ankauf->kfz_hu_au ?: '-');

            $item_section = '
            <div class="two-col">
                <div>
                    <div class="field"><span class="field-label">Fahrzeug:</span> <span class="field-value"><strong>' . $device . '</strong></span></div>
                    <div class="field"><span class="field-label">Kennzeichen:</span> <span class="field-value" style="font-family:monospace;color:' . $color . ';font-weight:bold;">' . $kennzeichen . '</span></div>
                    <div class="field"><span class="field-label">FIN/VIN:</span> <span class="field-value" style="font-family:monospace;font-size:9px;">' . $vin . '</span></div>
                    <div class="field"><span class="field-label">Farbe:</span> <span class="field-value">' . $device_color . '</span></div>
                </div>
                <div>
                    <div class="field"><span class="field-label">Km-Stand:</span> <span class="field-value">' . $km . '</span></div>
                    <div class="field"><span class="field-label">Erstzulassung:</span> <span class="field-value">' . $erstzulassung . '</span></div>
                    <div class="field"><span class="field-label">TÜV bis:</span> <span class="field-value">' . $tuev . '</span></div>
                    <div class="field"><span class="field-label">HU/AU bis:</span> <span class="field-value">' . $huau . '</span></div>
                    <div class="field"><span class="field-label">Zustand:</span> <span class="field-value">' . $condition . '</span></div>
                </div>
            </div>';

        } elseif ($ankauf_type === 'sonstiges') {
            $item_type_label = 'Artikel (Sonstiges)';
            $legal_item_name = 'Artikel';
            $serial = esc_html($ankauf->device_imei ?: '-');
            $description = esc_html($ankauf->item_description ?: '');

            $item_section = '
            <div class="two-col">
                <div>
                    <div class="field"><span class="field-label">Artikel:</span> <span class="field-value"><strong>' . $device . '</strong></span></div>
                    <div class="field"><span class="field-label">Seriennr.:</span> <span class="field-value" style="font-family:monospace;">' . $serial . '</span></div>
                </div>
                <div>
                    <div class="field"><span class="field-label">Zustand:</span> <span class="field-value">' . $condition . '</span></div>
                </div>
            </div>
            ' . ($description ? '<div class="field" style="margin-top:8px;"><span class="field-label">Beschreibung:</span> <span class="field-value">' . nl2br($description) . '</span></div>' : '');

        } else {
            // Handy/Smartphone (default)
            $item_type_label = 'Kaufgegenstand (Gerät)';
            $legal_item_name = 'Gerät';
            $imei = esc_html($ankauf->device_imei ?: '-');

            $accessories = json_decode($ankauf->device_accessories ?: '[]', true);
            $acc_labels = [
                'ladekabel' => 'Ladekabel',
                'netzteil' => 'Netzteil',
                'originalverpackung' => 'Originalverpackung',
                'kopfhoerer' => 'Kopfhörer',
                'huelle' => 'Hülle/Case',
                'rechnung' => 'Kaufrechnung'
            ];
            $acc_html = 'Keine';
            if (!empty($accessories)) {
                $acc_names = array_map(function($a) use ($acc_labels) {
                    return $acc_labels[$a] ?? $a;
                }, $accessories);
                $acc_html = esc_html(implode(', ', $acc_names));
            }

            $item_section = '
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
            </div>';
        }

        // Add notes if present
        if ($device_notes && $ankauf_type !== 'sonstiges') {
            $item_section .= '<div class="field" style="margin-top:8px;"><span class="field-label">Hinweise:</span> <span class="field-value">' . $device_notes . '</span></div>';
        }

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

        // Legal text varies by type
        $stolen_text = $ankauf_type === 'kfz'
            ? 'Der Verkäufer versichert, dass das Fahrzeug nicht als gestohlen gemeldet ist und keine Pfandrechte bestehen.'
            : 'Der Verkäufer versichert, dass der ' . $legal_item_name . ' nicht als gestohlen gemeldet ist.';

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
            <div class="section-title">' . $item_type_label . '</div>
            ' . $item_section . '
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
            <li>Der Verkäufer versichert, dass er der rechtmäßige Eigentümer des ' . $legal_item_name . 's ist und dieses frei von Rechten Dritter ist.</li>
            <li>' . $stolen_text . '</li>
            <li>Der ' . $legal_item_name . ' wird "wie besehen" ohne Gewährleistung verkauft.</li>
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
     * Render Standalone Ankauf Page
     */
    public static function render_standalone() {
        self::ensure_table();

        global $wpdb;
        $stores_table = $wpdb->prefix . 'ppv_stores';
        $store_id = PPV_Repair_Core::get_current_store_id();
        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM $stores_table WHERE id = %d", $store_id));

        $color = esc_attr($store->repair_color ?: '#667eea');
        $company = esc_html($store->repair_company_name ?: $store->name);

        // Check if editing existing
        $ankauf_id = intval($_GET['id'] ?? 0);
        $ankauf = null;
        if ($ankauf_id) {
            $table = $wpdb->prefix . 'ppv_repair_ankauf';
            $ankauf = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d AND store_id = %d",
                $ankauf_id, $store_id
            ));
        }

        $current_type = $ankauf ? ($ankauf->ankauf_type ?: 'handy') : 'handy';

        ?><!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Neuer Ankauf - <?php echo $company; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/remixicon@3.5.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        :root {
            --accent: <?php echo $color; ?>;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --success: #22c55e;
            --warning: #f59e0b;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); min-height: 100vh; }

        .header { background: var(--card); border-bottom: 1px solid var(--border); padding: 16px 24px; display: flex; align-items: center; justify-content: space-between; position: sticky; top: 0; z-index: 100; }
        .header-left { display: flex; align-items: center; gap: 16px; }
        .header h1 { font-size: 20px; color: var(--accent); display: flex; align-items: center; gap: 8px; }
        .header h1 i { font-size: 24px; }
        .header-actions { display: flex; gap: 10px; }

        .btn { padding: 10px 20px; border-radius: 8px; font-size: 14px; font-weight: 500; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; border: none; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: #fff; }
        .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-secondary { background: var(--border); color: var(--text); }
        .btn-secondary:hover { background: #d1d5db; }
        .btn-success { background: var(--success); color: #fff; }
        .btn-success:hover { opacity: 0.9; }

        .container { max-width: 900px; margin: 0 auto; padding: 24px; }

        .type-selector { background: var(--card); border-radius: 12px; padding: 20px; margin-bottom: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .type-selector label { display: block; font-weight: 600; margin-bottom: 12px; color: var(--text); }
        .type-tabs { display: flex; gap: 10px; flex-wrap: wrap; }
        .type-tab { padding: 12px 24px; border-radius: 8px; border: 2px solid var(--border); background: var(--card); cursor: pointer; font-size: 14px; font-weight: 500; transition: all 0.2s; display: flex; align-items: center; gap: 8px; }
        .type-tab:hover { border-color: var(--accent); }
        .type-tab.active { border-color: var(--accent); background: var(--accent); color: #fff; }
        .type-tab i { font-size: 18px; }

        .form-card { background: var(--card); border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .form-card h2 { font-size: 16px; color: var(--accent); margin-bottom: 20px; display: flex; align-items: center; gap: 8px; padding-bottom: 12px; border-bottom: 1px solid var(--border); }
        .form-card h2 i { font-size: 20px; }

        .form-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
        .form-grid.three-col { grid-template-columns: repeat(3, 1fr); }
        .form-group { display: flex; flex-direction: column; gap: 6px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-group label { font-size: 13px; font-weight: 500; color: var(--muted); }
        .form-group input, .form-group select, .form-group textarea { padding: 12px 14px; border: 1px solid var(--border); border-radius: 8px; font-size: 14px; transition: all 0.2s; }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(102,126,234,0.1); }
        .form-group textarea { resize: vertical; min-height: 80px; }

        .checkbox-group { display: flex; flex-wrap: wrap; gap: 12px; }
        .checkbox-item { display: flex; align-items: center; gap: 6px; padding: 8px 12px; background: var(--bg); border-radius: 6px; cursor: pointer; }
        .checkbox-item input { width: 16px; height: 16px; accent-color: var(--accent); }
        .checkbox-item span { font-size: 13px; }

        .price-box { background: linear-gradient(135deg, var(--accent), #764ba2); color: #fff; border-radius: 12px; padding: 24px; text-align: center; }
        .price-box label { display: block; font-size: 14px; opacity: 0.9; margin-bottom: 8px; }
        .price-box input { font-size: 32px; font-weight: bold; text-align: center; background: rgba(255,255,255,0.2); border: none; color: #fff; padding: 12px; border-radius: 8px; width: 200px; }
        .price-box input::placeholder { color: rgba(255,255,255,0.6); }
        .price-box input:focus { outline: none; background: rgba(255,255,255,0.3); }
        .price-suffix { font-size: 24px; margin-left: 8px; }

        .payment-options { display: flex; justify-content: center; gap: 16px; margin-top: 16px; }
        .payment-option { display: flex; align-items: center; gap: 6px; padding: 8px 16px; background: rgba(255,255,255,0.2); border-radius: 6px; cursor: pointer; }
        .payment-option input { accent-color: #fff; }
        .payment-option span { font-size: 14px; }

        .signature-section { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .sig-box { text-align: center; }
        .sig-box label { display: block; font-size: 13px; color: var(--muted); margin-bottom: 8px; }
        .sig-canvas { width: 100%; height: 120px; border: 2px solid var(--border); border-radius: 8px; background: #fff; cursor: crosshair; touch-action: none; }
        .sig-canvas.has-signature { border-color: var(--success); }
        .sig-clear { font-size: 12px; color: var(--accent); cursor: pointer; margin-top: 6px; display: inline-block; }
        .sig-clear:hover { text-decoration: underline; }

        .section-hidden { display: none; }

        .toast { position: fixed; bottom: 24px; right: 24px; background: var(--success); color: #fff; padding: 14px 24px; border-radius: 8px; font-size: 14px; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); transform: translateY(100px); opacity: 0; transition: all 0.3s; z-index: 1000; }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast.error { background: #ef4444; }

        .loading { pointer-events: none; opacity: 0.7; }
        .loading::after { content: ""; position: fixed; top: 50%; left: 50%; width: 40px; height: 40px; border: 3px solid var(--border); border-top-color: var(--accent); border-radius: 50%; animation: spin 0.8s linear infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }

        @media (max-width: 768px) {
            .form-grid, .form-grid.three-col { grid-template-columns: 1fr; }
            .signature-section { grid-template-columns: 1fr; }
            .type-tabs { flex-direction: column; }
            .header { flex-direction: column; gap: 12px; }
            .header-actions { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <h1><i class="ri-shopping-basket-line"></i> Neuer Ankauf</h1>
        </div>
        <div class="header-actions">
            <button class="btn btn-secondary" onclick="window.close()"><i class="ri-close-line"></i> Schließen</button>
            <button class="btn btn-primary" id="btn-save"><i class="ri-save-line"></i> Speichern</button>
            <button class="btn btn-success" id="btn-save-pdf"><i class="ri-file-pdf-line"></i> Speichern & PDF</button>
        </div>
    </div>

    <div class="container">
        <!-- Type Selector -->
        <div class="type-selector">
            <label>Was wird angekauft?</label>
            <div class="type-tabs">
                <div class="type-tab <?php echo $current_type === 'handy' ? 'active' : ''; ?>" data-type="handy">
                    <i class="ri-smartphone-line"></i> Handy / Smartphone
                </div>
                <div class="type-tab <?php echo $current_type === 'kfz' ? 'active' : ''; ?>" data-type="kfz">
                    <i class="ri-car-line"></i> KFZ / Fahrzeug
                </div>
                <div class="type-tab <?php echo $current_type === 'sonstiges' ? 'active' : ''; ?>" data-type="sonstiges">
                    <i class="ri-box-3-line"></i> Sonstiges
                </div>
            </div>
            <input type="hidden" id="ankauf-type" value="<?php echo esc_attr($current_type); ?>">
        </div>

        <!-- Verkäufer (Seller) -->
        <div class="form-card">
            <h2><i class="ri-user-line"></i> Verkäufer</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label>Name *</label>
                    <input type="text" id="seller-name" required value="<?php echo esc_attr($ankauf->seller_name ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="tel" id="seller-phone" value="<?php echo esc_attr($ankauf->seller_phone ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Straße & Hausnummer</label>
                    <input type="text" id="seller-address" value="<?php echo esc_attr($ankauf->seller_address ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>E-Mail</label>
                    <input type="email" id="seller-email" value="<?php echo esc_attr($ankauf->seller_email ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>PLZ</label>
                    <input type="text" id="seller-plz" value="<?php echo esc_attr($ankauf->seller_plz ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Ort</label>
                    <input type="text" id="seller-city" value="<?php echo esc_attr($ankauf->seller_city ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Ausweistyp</label>
                    <select id="seller-id-type">
                        <option value="personalausweis" <?php echo ($ankauf->seller_id_type ?? '') === 'personalausweis' ? 'selected' : ''; ?>>Personalausweis</option>
                        <option value="reisepass" <?php echo ($ankauf->seller_id_type ?? '') === 'reisepass' ? 'selected' : ''; ?>>Reisepass</option>
                        <option value="fuehrerschein" <?php echo ($ankauf->seller_id_type ?? '') === 'fuehrerschein' ? 'selected' : ''; ?>>Führerschein</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Ausweisnummer</label>
                    <input type="text" id="seller-id-number" value="<?php echo esc_attr($ankauf->seller_id_number ?? ''); ?>">
                </div>
            </div>
        </div>

        <!-- Handy Section -->
        <div class="form-card section-handy <?php echo $current_type !== 'handy' ? 'section-hidden' : ''; ?>">
            <h2><i class="ri-smartphone-line"></i> Gerät (Handy/Smartphone)</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label>Marke</label>
                    <input type="text" id="device-brand" placeholder="z.B. Apple, Samsung" value="<?php echo esc_attr($ankauf->device_brand ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Modell</label>
                    <input type="text" id="device-model" placeholder="z.B. iPhone 14 Pro" value="<?php echo esc_attr($ankauf->device_model ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>IMEI</label>
                    <input type="text" id="device-imei" placeholder="15-stellige IMEI" value="<?php echo esc_attr($ankauf->device_imei ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Farbe</label>
                    <input type="text" id="device-color" value="<?php echo esc_attr($ankauf->device_color ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Zustand</label>
                    <select id="device-condition">
                        <option value="neu" <?php echo ($ankauf->device_condition ?? '') === 'neu' ? 'selected' : ''; ?>>Neu / Originalverpackt</option>
                        <option value="sehr_gut" <?php echo ($ankauf->device_condition ?? '') === 'sehr_gut' ? 'selected' : ''; ?>>Sehr gut (minimale Gebrauchsspuren)</option>
                        <option value="gut" <?php echo ($ankauf->device_condition ?? 'gut') === 'gut' ? 'selected' : ''; ?>>Gut (leichte Gebrauchsspuren)</option>
                        <option value="akzeptabel" <?php echo ($ankauf->device_condition ?? '') === 'akzeptabel' ? 'selected' : ''; ?>>Akzeptabel (deutliche Gebrauchsspuren)</option>
                        <option value="defekt" <?php echo ($ankauf->device_condition ?? '') === 'defekt' ? 'selected' : ''; ?>>Defekt / Nicht funktionsfähig</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Zubehör</label>
                    <div class="checkbox-group">
                        <?php
                        $accessories = $ankauf ? json_decode($ankauf->device_accessories ?: '[]', true) : [];
                        $acc_options = ['ladekabel' => 'Ladekabel', 'netzteil' => 'Netzteil', 'originalverpackung' => 'Originalverpackung', 'kopfhoerer' => 'Kopfhörer', 'huelle' => 'Hülle/Case', 'rechnung' => 'Kaufrechnung'];
                        foreach ($acc_options as $val => $label):
                        ?>
                        <label class="checkbox-item">
                            <input type="checkbox" name="device-accessories" value="<?php echo $val; ?>" <?php echo in_array($val, $accessories) ? 'checked' : ''; ?>>
                            <span><?php echo $label; ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group full">
                    <label>Hinweise / Mängel</label>
                    <textarea id="device-notes" placeholder="Kratzer, Displayschäden, etc."><?php echo esc_textarea($ankauf->device_notes ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- KFZ Section -->
        <div class="form-card section-kfz <?php echo $current_type !== 'kfz' ? 'section-hidden' : ''; ?>">
            <h2><i class="ri-car-line"></i> Fahrzeug (KFZ)</h2>
            <div class="form-grid three-col">
                <div class="form-group">
                    <label>Marke</label>
                    <input type="text" id="kfz-brand" placeholder="z.B. VW, BMW" value="<?php echo esc_attr($ankauf->device_brand ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Modell</label>
                    <input type="text" id="kfz-model" placeholder="z.B. Golf, 3er" value="<?php echo esc_attr($ankauf->device_model ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Farbe</label>
                    <input type="text" id="kfz-color" value="<?php echo esc_attr($ankauf->device_color ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Kennzeichen</label>
                    <input type="text" id="kfz-kennzeichen" placeholder="AB-CD 1234" value="<?php echo esc_attr($ankauf->kfz_kennzeichen ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>FIN / VIN</label>
                    <input type="text" id="kfz-vin" placeholder="Fahrzeug-ID-Nr." value="<?php echo esc_attr($ankauf->kfz_vin ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Kilometerstand</label>
                    <input type="number" id="kfz-km" placeholder="km" value="<?php echo esc_attr($ankauf->kfz_km_stand ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Erstzulassung</label>
                    <input type="text" id="kfz-erstzulassung" placeholder="MM/YYYY" value="<?php echo esc_attr($ankauf->kfz_erstzulassung ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>TÜV bis</label>
                    <input type="text" id="kfz-tuev" placeholder="MM/YYYY" value="<?php echo esc_attr($ankauf->kfz_tuev ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>HU/AU bis</label>
                    <input type="text" id="kfz-huau" placeholder="MM/YYYY" value="<?php echo esc_attr($ankauf->kfz_hu_au ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Zustand</label>
                    <select id="kfz-condition">
                        <option value="neu">Neu / Unfall-frei</option>
                        <option value="sehr_gut">Sehr gut</option>
                        <option value="gut" selected>Gut</option>
                        <option value="akzeptabel">Akzeptabel</option>
                        <option value="defekt">Defekt / Nicht fahrbereit</option>
                    </select>
                </div>
            </div>
            <div class="form-grid" style="margin-top:16px;">
                <div class="form-group full">
                    <label>Hinweise / Mängel / Unfallschäden</label>
                    <textarea id="kfz-notes" placeholder="Bekannte Mängel, Unfallschäden, etc."><?php echo esc_textarea($ankauf->device_notes ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Sonstiges Section -->
        <div class="form-card section-sonstiges <?php echo $current_type !== 'sonstiges' ? 'section-hidden' : ''; ?>">
            <h2><i class="ri-box-3-line"></i> Artikel (Sonstiges)</h2>
            <div class="form-grid">
                <div class="form-group">
                    <label>Artikelbezeichnung</label>
                    <input type="text" id="item-name" placeholder="z.B. Laptop, Kamera" value="<?php echo esc_attr($ankauf->device_brand ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Marke / Modell</label>
                    <input type="text" id="item-model" value="<?php echo esc_attr($ankauf->device_model ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Seriennummer</label>
                    <input type="text" id="item-serial" value="<?php echo esc_attr($ankauf->device_imei ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Zustand</label>
                    <select id="item-condition">
                        <option value="neu">Neu</option>
                        <option value="sehr_gut">Sehr gut</option>
                        <option value="gut" selected>Gut</option>
                        <option value="akzeptabel">Akzeptabel</option>
                        <option value="defekt">Defekt</option>
                    </select>
                </div>
                <div class="form-group full">
                    <label>Detaillierte Beschreibung</label>
                    <textarea id="item-description" placeholder="Genaue Beschreibung des Artikels, Zustand, Zubehör, etc."><?php echo esc_textarea($ankauf->item_description ?? ''); ?></textarea>
                </div>
            </div>
        </div>

        <!-- Price -->
        <div class="form-card price-box">
            <label>Ankaufspreis *</label>
            <div>
                <input type="number" id="ankauf-price" step="0.01" min="0" placeholder="0,00" value="<?php echo esc_attr($ankauf->ankauf_price ?? ''); ?>">
                <span class="price-suffix">€</span>
            </div>
            <div class="payment-options">
                <label class="payment-option">
                    <input type="radio" name="payment-method" value="bar" <?php echo ($ankauf->payment_method ?? 'bar') === 'bar' ? 'checked' : ''; ?>>
                    <span>Bar</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment-method" value="ueberweisung" <?php echo ($ankauf->payment_method ?? '') === 'ueberweisung' ? 'checked' : ''; ?>>
                    <span>Überweisung</span>
                </label>
                <label class="payment-option">
                    <input type="radio" name="payment-method" value="paypal" <?php echo ($ankauf->payment_method ?? '') === 'paypal' ? 'checked' : ''; ?>>
                    <span>PayPal</span>
                </label>
            </div>
        </div>

        <!-- Signatures -->
        <div class="form-card">
            <h2><i class="ri-pen-nib-line"></i> Unterschriften</h2>
            <div class="signature-section">
                <div class="sig-box">
                    <label>Unterschrift Verkäufer</label>
                    <canvas class="sig-canvas" id="seller-sig-canvas"></canvas>
                    <span class="sig-clear" onclick="clearSignature('seller')">Löschen</span>
                    <input type="hidden" id="seller-signature">
                </div>
                <div class="sig-box">
                    <label>Unterschrift Käufer (Händler)</label>
                    <canvas class="sig-canvas" id="buyer-sig-canvas"></canvas>
                    <span class="sig-clear" onclick="clearSignature('buyer')">Löschen</span>
                    <input type="hidden" id="buyer-signature">
                </div>
            </div>
        </div>
    </div>

    <div class="toast" id="toast"></div>

    <script>
    (function(){
        var nonce = '<?php echo wp_create_nonce('ppv_repair_admin'); ?>';
        var ajaxUrl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var ankaufId = <?php echo $ankauf_id ?: 0; ?>;

        // Type selector
        document.querySelectorAll('.type-tab').forEach(function(tab){
            tab.addEventListener('click', function(){
                document.querySelectorAll('.type-tab').forEach(function(t){ t.classList.remove('active'); });
                this.classList.add('active');
                var type = this.dataset.type;
                document.getElementById('ankauf-type').value = type;

                document.querySelectorAll('.section-handy, .section-kfz, .section-sonstiges').forEach(function(s){
                    s.classList.add('section-hidden');
                });
                document.querySelector('.section-' + type).classList.remove('section-hidden');
            });
        });

        // Signature canvases
        var sellerCanvas = document.getElementById('seller-sig-canvas');
        var buyerCanvas = document.getElementById('buyer-sig-canvas');
        var sellerCtx, buyerCtx;
        var drawing = {seller: false, buyer: false};

        function initCanvas(canvas, type) {
            var ctx = canvas.getContext('2d');
            canvas.width = canvas.offsetWidth * 2;
            canvas.height = canvas.offsetHeight * 2;
            ctx.scale(2, 2);
            ctx.strokeStyle = '#1e293b';
            ctx.lineWidth = 2;
            ctx.lineCap = 'round';
            ctx.lineJoin = 'round';

            if (type === 'seller') sellerCtx = ctx;
            else buyerCtx = ctx;

            function getPos(e) {
                var rect = canvas.getBoundingClientRect();
                var x, y;
                if (e.touches) {
                    x = e.touches[0].clientX - rect.left;
                    y = e.touches[0].clientY - rect.top;
                } else {
                    x = e.clientX - rect.left;
                    y = e.clientY - rect.top;
                }
                return {x: x, y: y};
            }

            function startDraw(e) {
                e.preventDefault();
                drawing[type] = true;
                var pos = getPos(e);
                ctx.beginPath();
                ctx.moveTo(pos.x, pos.y);
            }

            function doDraw(e) {
                if (!drawing[type]) return;
                e.preventDefault();
                var pos = getPos(e);
                ctx.lineTo(pos.x, pos.y);
                ctx.stroke();
            }

            function endDraw() {
                if (drawing[type]) {
                    drawing[type] = false;
                    canvas.classList.add('has-signature');
                    document.getElementById(type + '-signature').value = canvas.toDataURL('image/png');
                }
            }

            canvas.addEventListener('mousedown', startDraw);
            canvas.addEventListener('mousemove', doDraw);
            canvas.addEventListener('mouseup', endDraw);
            canvas.addEventListener('mouseleave', endDraw);
            canvas.addEventListener('touchstart', startDraw, {passive: false});
            canvas.addEventListener('touchmove', doDraw, {passive: false});
            canvas.addEventListener('touchend', endDraw);
        }

        window.clearSignature = function(type) {
            var canvas = document.getElementById(type + '-sig-canvas');
            var ctx = type === 'seller' ? sellerCtx : buyerCtx;
            ctx.clearRect(0, 0, canvas.width, canvas.height);
            canvas.classList.remove('has-signature');
            document.getElementById(type + '-signature').value = '';
        };

        setTimeout(function(){
            initCanvas(sellerCanvas, 'seller');
            initCanvas(buyerCanvas, 'buyer');
        }, 100);

        // Toast
        function showToast(msg, isError) {
            var toast = document.getElementById('toast');
            toast.textContent = msg;
            toast.className = 'toast show' + (isError ? ' error' : '');
            setTimeout(function(){ toast.classList.remove('show'); }, 3000);
        }

        // Collect form data
        function collectData() {
            var type = document.getElementById('ankauf-type').value;
            var data = {
                action: 'ppv_repair_ankauf_create',
                nonce: nonce,
                ankauf_type: type,
                seller_name: document.getElementById('seller-name').value,
                seller_phone: document.getElementById('seller-phone').value,
                seller_address: document.getElementById('seller-address').value,
                seller_email: document.getElementById('seller-email').value,
                seller_plz: document.getElementById('seller-plz').value,
                seller_city: document.getElementById('seller-city').value,
                seller_id_type: document.getElementById('seller-id-type').value,
                seller_id_number: document.getElementById('seller-id-number').value,
                ankauf_price: document.getElementById('ankauf-price').value,
                payment_method: document.querySelector('input[name="payment-method"]:checked').value,
                seller_signature: document.getElementById('seller-signature').value,
                buyer_signature: document.getElementById('buyer-signature').value
            };

            if (type === 'handy') {
                data.device_brand = document.getElementById('device-brand').value;
                data.device_model = document.getElementById('device-model').value;
                data.device_imei = document.getElementById('device-imei').value;
                data.device_color = document.getElementById('device-color').value;
                data.device_condition = document.getElementById('device-condition').value;
                data.device_notes = document.getElementById('device-notes').value;
                var acc = [];
                document.querySelectorAll('input[name="device-accessories"]:checked').forEach(function(cb){
                    acc.push(cb.value);
                });
                data.device_accessories = JSON.stringify(acc);
            } else if (type === 'kfz') {
                data.device_brand = document.getElementById('kfz-brand').value;
                data.device_model = document.getElementById('kfz-model').value;
                data.device_color = document.getElementById('kfz-color').value;
                data.device_condition = document.getElementById('kfz-condition').value;
                data.device_notes = document.getElementById('kfz-notes').value;
                data.kfz_kennzeichen = document.getElementById('kfz-kennzeichen').value;
                data.kfz_vin = document.getElementById('kfz-vin').value;
                data.kfz_km_stand = document.getElementById('kfz-km').value;
                data.kfz_erstzulassung = document.getElementById('kfz-erstzulassung').value;
                data.kfz_tuev = document.getElementById('kfz-tuev').value;
                data.kfz_hu_au = document.getElementById('kfz-huau').value;
            } else {
                data.device_brand = document.getElementById('item-name').value;
                data.device_model = document.getElementById('item-model').value;
                data.device_imei = document.getElementById('item-serial').value;
                data.device_condition = document.getElementById('item-condition').value;
                data.item_description = document.getElementById('item-description').value;
            }

            if (ankaufId) data.ankauf_id = ankaufId;

            return data;
        }

        // Save
        function saveAnkauf(openPdf) {
            var data = collectData();

            if (!data.seller_name) {
                showToast('Verkäufer-Name ist erforderlich', true);
                return;
            }
            if (!data.ankauf_price || parseFloat(data.ankauf_price) <= 0) {
                showToast('Ankaufspreis ist erforderlich', true);
                return;
            }

            document.body.classList.add('loading');

            var formData = new FormData();
            for (var key in data) {
                formData.append(key, data[key]);
            }

            fetch(ajaxUrl, {
                method: 'POST',
                body: formData
            })
            .then(function(r){ return r.json(); })
            .then(function(resp){
                document.body.classList.remove('loading');
                if (resp.success) {
                    showToast('Ankauf gespeichert: ' + resp.data.ankauf_number);
                    ankaufId = resp.data.ankauf_id;

                    if (openPdf) {
                        window.open(ajaxUrl + '?action=ppv_repair_ankauf_pdf&nonce=' + nonce + '&ankauf_id=' + ankaufId, '_blank');
                    }

                    // Notify parent window
                    if (window.opener && window.opener.refreshAnkaufList) {
                        window.opener.refreshAnkaufList();
                    }
                } else {
                    showToast(resp.data.message || 'Fehler beim Speichern', true);
                }
            })
            .catch(function(err){
                document.body.classList.remove('loading');
                showToast('Netzwerkfehler', true);
            });
        }

        document.getElementById('btn-save').addEventListener('click', function(){ saveAnkauf(false); });
        document.getElementById('btn-save-pdf').addEventListener('click', function(){ saveAnkauf(true); });

    })();
    </script>
</body>
</html>
        <?php
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
