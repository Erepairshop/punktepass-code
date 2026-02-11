<?php
/**
 * PunktePass - Repair Invoice System
 * Generates invoices on repair completion, PDF export, CSV export
 * Integrates with PunktePass reward system
 *
 * Author: Erik Borota / PunktePass
 */

if (!defined('ABSPATH')) exit;

class PPV_Repair_Invoice {

    /**
     * Generate invoice when repair is marked as done
     * Checks if customer reached PunktePass reward threshold
     * Auto-applies discount/free product on invoice
     *
     * @param object $store  - Store row from ppv_stores
     * @param object $repair - Repair row from ppv_repairs
     * @param float  $amount - The repair cost (from final_cost POST or estimated_cost)
     * @return int|null - Invoice ID or null on failure
     */
    public static function generate_invoice($store, $repair, $amount = 0, $line_items = []) {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Check if invoice already exists for this repair
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$prefix}ppv_repair_invoices WHERE repair_id = %d AND store_id = %d",
            $repair->id, $store->id
        ));
        if ($existing) return (int)$existing;

        // Generate invoice number
        $inv_prefix = $store->repair_invoice_prefix ?: 'RE-';
        $next_num = max(1, intval($store->repair_invoice_next_number ?: 1));

        // Auto-detect highest existing invoice number from database (only matching current prefix)
        $all_invoice_numbers = $wpdb->get_col($wpdb->prepare(
            "SELECT invoice_number FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL) AND invoice_number LIKE %s",
            $store->id,
            $wpdb->esc_like($inv_prefix) . '%'
        ));
        $detected_max = 0;
        foreach ($all_invoice_numbers as $inv_num) {
            if (preg_match('/(\d+)$/', $inv_num, $m)) {
                $num = intval($m[1]);
                if ($num > $detected_max) {
                    $detected_max = $num;
                }
            }
        }
        // Use the higher of stored counter or detected max + 1
        if ($detected_max >= $next_num) {
            $next_num = $detected_max + 1;
        }

        $invoice_number = $inv_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        // Device info
        $device = trim(($repair->device_brand ?: '') . ' ' . ($repair->device_model ?: ''));

        // Check PunktePass reward eligibility
        $discount_type = 'none';
        $discount_value = 0;
        $discount_desc = '';
        $reward_applied = 0;
        $points_used = 0;
        $subtotal = max(0, $amount);

        $pp_enabled = isset($store->repair_punktepass_enabled) ? intval($store->repair_punktepass_enabled) : 1;

        if ($pp_enabled && !empty($repair->user_id)) {
            $required = intval($store->repair_required_points ?: 4);
            $total_points = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COALESCE(SUM(points),0) FROM {$prefix}ppv_points WHERE user_id=%d AND store_id=%d",
                $repair->user_id, $store->id
            ));

            if ($total_points >= $required) {
                $reward_type = $store->repair_reward_type ?: 'discount_fixed';
                $reward_value = floatval($store->repair_reward_value ?: 10);
                $reward_name = $store->repair_reward_name ?: '10 Euro Rabatt';

                $discount_type = $reward_type;
                $reward_applied = 1;
                $points_used = $required;

                switch ($reward_type) {
                    case 'discount_fixed':
                        $discount_value = min($reward_value, $subtotal);
                        $discount_desc = $reward_name . ' (-' . number_format($discount_value, 2, ',', '.') . ' €)';
                        break;
                    case 'discount_percent':
                        $discount_value = round($subtotal * ($reward_value / 100), 2);
                        $discount_desc = $reward_name . ' (-' . intval($reward_value) . '%)';
                        break;
                    case 'free_product':
                        $product_name = $store->repair_reward_product ?: $reward_name;
                        $discount_value = $reward_value; // value of the free product
                        $discount_desc = 'Gratis: ' . $product_name;
                        break;
                }

                // Deduct points
                $wpdb->insert("{$prefix}ppv_points", [
                    'user_id' => $repair->user_id,
                    'store_id' => $store->id,
                    'points' => -$required,
                    'type' => 'redeem',
                    'reference' => 'Eingelöst: ' . $reward_name . ' (Rechnung ' . $invoice_number . ')',
                    'created' => current_time('mysql'),
                ]);
            }
        }

        // VAT calculation
        $vat_enabled = isset($store->repair_vat_enabled) ? intval($store->repair_vat_enabled) : 1;
        $vat_rate_pct = floatval($store->repair_vat_rate ?: 19.00);
        $is_klein = $vat_enabled ? 0 : 1;

        // Amount entered is brutto (gross) - reverse-calculate net from brutto
        $brutto_after_discount = max(0, $subtotal - $discount_value);

        if ($vat_enabled) {
            // MwSt-pflichtig: brutto includes VAT, calculate net backwards
            $net_amount = round($brutto_after_discount / (1 + $vat_rate_pct / 100), 2);
            $vat_amount = round($brutto_after_discount - $net_amount, 2);
            $total = $brutto_after_discount;
            $stored_vat_rate = $vat_rate_pct;
        } else {
            // Kleinunternehmer §19 UStG: no VAT, brutto = net
            $net_amount = $brutto_after_discount;
            $vat_amount = 0;
            $total = $brutto_after_discount;
            $stored_vat_rate = 0;
        }

        // Insert invoice
        $wpdb->insert("{$prefix}ppv_repair_invoices", [
            'store_id'                  => $store->id,
            'repair_id'                 => $repair->id,
            'invoice_number'            => $invoice_number,
            'customer_name'             => $repair->customer_name,
            'customer_email'            => $repair->customer_email,
            'customer_phone'            => $repair->customer_phone ?: '',
            'device_info'               => $device,
            'description'               => $repair->problem_description,
            'line_items'                => !empty($line_items) ? wp_json_encode($line_items) : null,
            'subtotal'                  => $subtotal,
            'discount_type'             => $discount_type,
            'discount_value'            => $discount_value,
            'discount_description'      => $discount_desc,
            'net_amount'                => $net_amount,
            'vat_rate'                  => $stored_vat_rate,
            'vat_amount'                => $vat_amount,
            'total'                     => $total,
            'is_kleinunternehmer'       => $is_klein,
            'punktepass_reward_applied'  => $reward_applied,
            'points_used'               => $points_used,
            'status'                    => 'draft',
            'created_at'                => current_time('mysql'),
        ]);

        $invoice_id = $wpdb->insert_id;

        // Increment next invoice number
        if ($invoice_id) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}ppv_stores SET repair_invoice_next_number = %d WHERE id = %d",
                $next_num + 1, $store->id
            ));

            // Update repair with final cost
            if ($amount > 0) {
                $wpdb->update("{$prefix}ppv_repairs", ['final_cost' => $amount], ['id' => $repair->id]);
            }
        }

        return $invoice_id ?: null;
    }

    /**
     * AJAX: Download invoice as PDF
     */
    public static function ajax_download_pdf() {
        if (!wp_verify_nonce($_GET['nonce'] ?? $_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_die('Sicherheitsfehler');
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_die('Nicht autorisiert');

        global $wpdb;
        $invoice_id = intval($_GET['invoice_id'] ?? $_POST['invoice_id'] ?? 0);
        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
            $invoice_id, $store_id
        ));
        if (!$invoice) wp_die('Rechnung nicht gefunden');

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id
        ));

        $html = self::build_invoice_html($store, $invoice);

        require_once(PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php');
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($invoice->invoice_number) . '.pdf"');
        echo $dompdf->output();
        exit;
    }

    /**
     * AJAX: Export invoices as CSV
     */
    public static function ajax_export_csv() {
        if (!wp_verify_nonce($_GET['nonce'] ?? $_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_die('Sicherheitsfehler');
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_die('Nicht autorisiert');

        global $wpdb;
        $prefix = $wpdb->prefix;
        $date_from = sanitize_text_field($_GET['date_from'] ?? $_POST['date_from'] ?? '');
        $date_to   = sanitize_text_field($_GET['date_to'] ?? $_POST['date_to'] ?? '');

        $where = "store_id = %d";
        $params = [$store_id];

        if ($date_from) {
            $where .= " AND DATE(created_at) >= %s";
            $params[] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(created_at) <= %s";
            $params[] = $date_to;
        }

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices WHERE {$where} ORDER BY created_at DESC",
            ...$params
        ));

        $filename = 'rechnungen-' . ($date_from ?: 'alle') . '-bis-' . ($date_to ?: date('Y-m-d')) . '.csv';

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . sanitize_file_name($filename) . '"');

        $out = fopen('php://output', 'w');
        // BOM for Excel UTF-8
        fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($out, ['Rechnungsnr.', 'Datum', 'Kunde', 'E-Mail', 'Telefon', 'Gerät', 'Zwischensumme', 'Rabatt', 'Rabattbeschreibung', 'Netto', 'MwSt %', 'MwSt Betrag', 'Gesamt', 'Kleinunternehmer', 'PunktePass', 'Status'], ';');

        foreach ($invoices as $inv) {
            fputcsv($out, [
                $inv->invoice_number,
                date('d.m.Y', strtotime($inv->created_at)),
                $inv->customer_name,
                $inv->customer_email,
                $inv->customer_phone,
                $inv->device_info,
                number_format($inv->subtotal, 2, ',', '.'),
                number_format($inv->discount_value, 2, ',', '.'),
                $inv->discount_description ?: '-',
                number_format($inv->net_amount, 2, ',', '.'),
                $inv->is_kleinunternehmer ? '-' : number_format($inv->vat_rate, 2, ',', '.') . '%',
                $inv->is_kleinunternehmer ? '-' : number_format($inv->vat_amount, 2, ',', '.'),
                number_format($inv->total, 2, ',', '.'),
                $inv->is_kleinunternehmer ? 'Ja' : 'Nein',
                $inv->punktepass_reward_applied ? 'Ja' : 'Nein',
                ucfirst($inv->status),
            ], ';');
        }

        fclose($out);
        exit;
    }

    /**
     * AJAX: List invoices (for admin panel)
     */
    public static function ajax_list_invoices() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to   = sanitize_text_field($_POST['date_to'] ?? '');
        $doc_type  = sanitize_text_field($_POST['doc_type'] ?? '');
        $sort_by   = sanitize_text_field($_POST['sort_by'] ?? 'created_at');
        $sort_dir  = strtoupper(sanitize_text_field($_POST['sort_dir'] ?? 'DESC'));

        // Whitelist sortable columns
        $allowed_sort = ['invoice_number', 'created_at', 'customer_name', 'net_amount', 'total', 'status'];
        if (!in_array($sort_by, $allowed_sort)) $sort_by = 'created_at';
        if (!in_array($sort_dir, ['ASC', 'DESC'])) $sort_dir = 'DESC';

        $where = "store_id = %d";
        $params = [$store_id];

        if ($date_from) { $where .= " AND DATE(created_at) >= %s"; $params[] = $date_from; }
        if ($date_to) { $where .= " AND DATE(created_at) <= %s"; $params[] = $date_to; }
        if ($doc_type && in_array($doc_type, ['rechnung', 'angebot'])) {
            $where .= " AND (doc_type = %s OR (doc_type IS NULL AND %s = 'rechnung'))";
            $params[] = $doc_type;
            $params[] = $doc_type;
        }

        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}ppv_repair_invoices WHERE {$where}", ...$params));

        $p = $params;
        $p[] = $per_page;
        $p[] = $offset;
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices WHERE {$where} ORDER BY {$sort_by} {$sort_dir} LIMIT %d OFFSET %d",
            ...$p
        ));

        // Summary
        $summary = $wpdb->get_row($wpdb->prepare(
            "SELECT COALESCE(SUM(total),0) as revenue, COALESCE(SUM(discount_value),0) as discounts, COUNT(*) as count FROM {$prefix}ppv_repair_invoices WHERE {$where}",
            ...$params
        ));

        wp_send_json_success([
            'invoices' => $invoices,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => $page,
            'summary' => $summary,
        ]);
    }

    /**
     * AJAX: Update invoice (status, amount, notes)
     */
    public static function ajax_update_invoice() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $invoice_id = intval($_POST['invoice_id'] ?? 0);

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
            $invoice_id, $store_id
        ));
        if (!$invoice) wp_send_json_error(['message' => 'Rechnung nicht gefunden']);

        $update = [];
        if (isset($_POST['status'])) {
            $valid = ['draft', 'sent', 'paid', 'cancelled'];
            $new_status = sanitize_text_field($_POST['status']);
            if (in_array($new_status, $valid)) {
                $update['status'] = $new_status;
                if ($new_status === 'paid') {
                    // Set paid_at if not already set or if provided
                    if (!empty($_POST['paid_at'])) {
                        $update['paid_at'] = sanitize_text_field($_POST['paid_at']);
                    } elseif (empty($invoice->paid_at)) {
                        $update['paid_at'] = current_time('mysql');
                    }
                    // Set payment method if provided
                    if (!empty($_POST['payment_method'])) {
                        $update['payment_method'] = sanitize_text_field($_POST['payment_method']);
                    }
                }
            }
        }
        // Allow updating payment_method independently
        if (isset($_POST['payment_method']) && !isset($_POST['status'])) {
            $update['payment_method'] = sanitize_text_field($_POST['payment_method']);
        }
        if (isset($_POST['subtotal'])) {
            $subtotal = max(0, round(floatval($_POST['subtotal']), 2));
            $update['subtotal'] = $subtotal;

            // Recalculate discount
            $discount = floatval($invoice->discount_value);
            if ($invoice->discount_type === 'discount_percent') {
                $pct = floatval($wpdb->get_var($wpdb->prepare(
                    "SELECT repair_reward_value FROM {$wpdb->prefix}ppv_stores WHERE id = %d", $store_id
                )) ?: 10);
                $discount = round($subtotal * ($pct / 100), 2);
                $update['discount_value'] = $discount;
            }

            // Recalculate from brutto (subtotal is brutto)
            $brutto = max(0, $subtotal - $discount);
            $update['total'] = $brutto;

            if ($invoice->is_kleinunternehmer) {
                $update['net_amount'] = $brutto;
                $update['vat_amount'] = 0;
            } else {
                $vat_rate = floatval($invoice->vat_rate ?: 19);
                $net = round($brutto / (1 + $vat_rate / 100), 2);
                $update['net_amount'] = $net;
                $update['vat_amount'] = round($brutto - $net, 2);
            }
        }
        if (isset($_POST['line_items'])) {
            $items = json_decode(stripslashes($_POST['line_items']), true);
            if (is_array($items)) {
                $update['line_items'] = wp_json_encode($items);
            }
        }
        if (isset($_POST['notes'])) {
            $update['notes'] = sanitize_textarea_field($_POST['notes']);
        }

        // Customer fields
        if (isset($_POST['customer_name'])) {
            $update['customer_name'] = sanitize_text_field($_POST['customer_name']);
        }
        if (isset($_POST['customer_email'])) {
            $update['customer_email'] = sanitize_email($_POST['customer_email']);
        }
        if (isset($_POST['customer_phone'])) {
            $update['customer_phone'] = sanitize_text_field($_POST['customer_phone']);
        }
        if (isset($_POST['customer_company'])) {
            $update['customer_company'] = sanitize_text_field($_POST['customer_company']);
        }
        if (isset($_POST['customer_tax_id'])) {
            $update['customer_tax_id'] = sanitize_text_field($_POST['customer_tax_id']);
        }
        if (isset($_POST['customer_address'])) {
            $update['customer_address'] = sanitize_text_field($_POST['customer_address']);
        }
        if (isset($_POST['customer_plz'])) {
            $update['customer_plz'] = sanitize_text_field($_POST['customer_plz']);
        }
        if (isset($_POST['customer_city'])) {
            $update['customer_city'] = sanitize_text_field($_POST['customer_city']);
        }
        // Allow updating invoice number
        if (isset($_POST['invoice_number'])) {
            $new_invoice_number = sanitize_text_field($_POST['invoice_number']);
            if (!empty($new_invoice_number)) {
                $update['invoice_number'] = $new_invoice_number;
            }
        }

        // Differenzbesteuerung
        if (isset($_POST['is_differenzbesteuerung'])) {
            $is_differenz = intval($_POST['is_differenzbesteuerung']) ? 1 : 0;
            $update['is_differenzbesteuerung'] = $is_differenz;

            // Recalculate VAT if differenzbesteuerung changed
            if (isset($update['total'])) {
                $brutto = $update['total'];
            } else {
                $brutto = floatval($invoice->total);
            }

            if ($is_differenz || $invoice->is_kleinunternehmer) {
                $update['net_amount'] = $brutto;
                $update['vat_amount'] = 0;
                $update['vat_rate'] = 0;
            }
        }

        if (!empty($update)) {
            $wpdb->update($wpdb->prefix . 'ppv_repair_invoices', $update, ['id' => $invoice_id]);
        }

        wp_send_json_success(['message' => 'Rechnung aktualisiert']);
    }

    /**
     * AJAX: Delete invoice
     */
    public static function ajax_delete_invoice() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) wp_send_json_error(['message' => 'Ungültige Rechnung']);

        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'ppv_repair_invoices', [
            'id' => $invoice_id,
            'store_id' => $store_id,
        ]);

        if ($deleted) {
            wp_send_json_success(['message' => 'Rechnung gelöscht']);
        } else {
            wp_send_json_error(['message' => 'Rechnung nicht gefunden']);
        }
    }

    /**
     * AJAX: Create standalone invoice (without repair)
     */
    public static function ajax_create_invoice() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if (!$store) wp_send_json_error(['message' => 'Store nicht gefunden']);

        // Customer data
        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : null;
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $customer_company = sanitize_text_field($_POST['customer_company'] ?? '');
        $customer_tax_id = sanitize_text_field($_POST['customer_tax_id'] ?? '');
        $customer_address = sanitize_text_field($_POST['customer_address'] ?? '');
        $customer_plz = sanitize_text_field($_POST['customer_plz'] ?? '');
        $customer_city = sanitize_text_field($_POST['customer_city'] ?? '');

        if (empty($customer_name)) {
            wp_send_json_error(['message' => 'Kundenname ist erforderlich']);
        }

        // Save customer if requested
        $save_customer = !empty($_POST['save_customer']);
        if ($save_customer && !$customer_id) {
            $wpdb->insert("{$prefix}ppv_repair_customers", [
                'store_id' => $store_id,
                'name' => $customer_name,
                'email' => $customer_email ?: null,
                'phone' => $customer_phone ?: null,
                'company_name' => $customer_company ?: null,
                'tax_id' => $customer_tax_id ?: null,
                'address' => $customer_address ?: null,
                'plz' => $customer_plz ?: null,
                'city' => $customer_city ?: null,
            ]);
            $customer_id = $wpdb->insert_id;
        }

        // Invoice data
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $line_items = [];
        if (!empty($_POST['line_items'])) {
            $items = json_decode(stripslashes($_POST['line_items']), true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $line_items[] = [
                        'description' => sanitize_text_field($item['description'] ?? ''),
                        'amount' => floatval($item['amount'] ?? 0),
                    ];
                }
            }
        }

        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        // Generate invoice number (or use custom if provided)
        $custom_invoice_number = sanitize_text_field($_POST['invoice_number'] ?? '');
        if (!empty($custom_invoice_number)) {
            $invoice_number = $custom_invoice_number;
            // Don't increment counter when using custom number
            $should_increment = false;
        } else {
            $inv_prefix = $store->repair_invoice_prefix ?: 'RE-';
            $next_num = max(1, intval($store->repair_invoice_next_number ?: 1));

            // Auto-detect highest existing invoice number from database (only matching current prefix)
            $all_invoice_numbers = $wpdb->get_col($wpdb->prepare(
                "SELECT invoice_number FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND (doc_type = 'rechnung' OR doc_type IS NULL) AND invoice_number LIKE %s",
                $store_id,
                $wpdb->esc_like($inv_prefix) . '%'
            ));
            $detected_max = 0;
            foreach ($all_invoice_numbers as $inv_num) {
                if (preg_match('/(\d+)$/', $inv_num, $m)) {
                    $num = intval($m[1]);
                    if ($num > $detected_max) {
                        $detected_max = $num;
                    }
                }
            }
            // Use the higher of stored counter or detected max + 1
            if ($detected_max >= $next_num) {
                $next_num = $detected_max + 1;
            }

            $invoice_number = $inv_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);
            $should_increment = true;
        }

        // VAT calculation
        $vat_enabled = isset($store->repair_vat_enabled) ? intval($store->repair_vat_enabled) : 1;
        $vat_rate_pct = floatval($store->repair_vat_rate ?: 19.00);
        $is_klein = $vat_enabled ? 0 : 1;
        $is_differenz = !empty($_POST['is_differenzbesteuerung']) ? 1 : 0;

        // Calculate from brutto (no VAT if Kleinunternehmer or Differenzbesteuerung)
        if ($vat_enabled && !$is_differenz) {
            $net_amount = round($subtotal / (1 + $vat_rate_pct / 100), 2);
            $vat_amount = round($subtotal - $net_amount, 2);
            $total = $subtotal;
            $stored_vat_rate = $vat_rate_pct;
        } else {
            $net_amount = $subtotal;
            $vat_amount = 0;
            $total = $subtotal;
            $stored_vat_rate = 0;
        }

        // Insert invoice
        $wpdb->insert("{$prefix}ppv_repair_invoices", [
            'store_id'          => $store_id,
            'repair_id'         => null, // Standalone invoice
            'customer_id'       => $customer_id,
            'invoice_number'    => $invoice_number,
            'customer_name'     => $customer_name,
            'customer_email'    => $customer_email,
            'customer_phone'    => $customer_phone,
            'customer_company'  => $customer_company,
            'customer_tax_id'   => $customer_tax_id,
            'customer_address'  => $customer_address,
            'customer_plz'      => $customer_plz,
            'customer_city'     => $customer_city,
            'device_info'       => '',
            'description'       => $description,
            'line_items'        => !empty($line_items) ? wp_json_encode($line_items) : null,
            'subtotal'          => $subtotal,
            'discount_type'     => 'none',
            'discount_value'    => 0,
            'discount_description' => '',
            'net_amount'        => $net_amount,
            'vat_rate'          => $stored_vat_rate,
            'vat_amount'        => $vat_amount,
            'total'             => $total,
            'is_kleinunternehmer' => $is_klein,
            'is_differenzbesteuerung' => $is_differenz,
            'punktepass_reward_applied' => 0,
            'points_used'       => 0,
            'notes'             => $notes,
            'status'            => 'draft',
            'created_at'        => current_time('mysql'),
        ]);

        $invoice_id = $wpdb->insert_id;

        // Increment next invoice number (only if auto-generated)
        if ($invoice_id && $should_increment) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}ppv_stores SET repair_invoice_next_number = %d WHERE id = %d",
                $next_num + 1, $store_id
            ));
        }

        wp_send_json_success([
            'message' => 'Rechnung erstellt',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
        ]);
    }

    /**
     * AJAX: Create Angebot (quote)
     */
    public static function ajax_create_angebot() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if (!$store) wp_send_json_error(['message' => 'Store nicht gefunden']);

        // Customer data
        $customer_name = sanitize_text_field($_POST['customer_name'] ?? '');
        $customer_email = sanitize_email($_POST['customer_email'] ?? '');
        $customer_phone = sanitize_text_field($_POST['customer_phone'] ?? '');
        $customer_company = sanitize_text_field($_POST['customer_company'] ?? '');
        $customer_address = sanitize_text_field($_POST['customer_address'] ?? '');
        $customer_plz = sanitize_text_field($_POST['customer_plz'] ?? '');
        $customer_city = sanitize_text_field($_POST['customer_city'] ?? '');

        if (empty($customer_name)) {
            wp_send_json_error(['message' => 'Kundenname ist erforderlich']);
        }

        // Line items
        $line_items = [];
        if (!empty($_POST['line_items'])) {
            $items = json_decode(stripslashes($_POST['line_items']), true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    $line_items[] = [
                        'description' => sanitize_text_field($item['description'] ?? ''),
                        'amount' => floatval($item['amount'] ?? 0),
                    ];
                }
            }
        }

        $subtotal = floatval($_POST['subtotal'] ?? 0);
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');
        $valid_until = sanitize_text_field($_POST['valid_until'] ?? '');

        // Generate angebot number
        $ang_prefix = $store->repair_angebot_prefix ?: 'AG-';
        $next_num = max(1, intval($store->repair_angebot_next_number ?: 1));
        $angebot_number = $ang_prefix . str_pad($next_num, 4, '0', STR_PAD_LEFT);

        // VAT calculation
        $vat_enabled = isset($store->repair_vat_enabled) ? intval($store->repair_vat_enabled) : 1;
        $vat_rate_pct = floatval($store->repair_vat_rate ?: 19.00);
        $is_klein = $vat_enabled ? 0 : 1;

        if ($vat_enabled) {
            $net_amount = round($subtotal / (1 + $vat_rate_pct / 100), 2);
            $vat_amount = round($subtotal - $net_amount, 2);
            $total = $subtotal;
            $stored_vat_rate = $vat_rate_pct;
        } else {
            $net_amount = $subtotal;
            $vat_amount = 0;
            $total = $subtotal;
            $stored_vat_rate = 0;
        }

        // Insert angebot (using same table as invoices but with doc_type = 'angebot')
        $wpdb->insert("{$prefix}ppv_repair_invoices", [
            'store_id'          => $store_id,
            'repair_id'         => null,
            'customer_id'       => null,
            'invoice_number'    => $angebot_number,
            'doc_type'          => 'angebot',
            'customer_name'     => $customer_name,
            'customer_email'    => $customer_email,
            'customer_phone'    => $customer_phone,
            'customer_company'  => $customer_company,
            'customer_tax_id'   => '',
            'customer_address'  => $customer_address,
            'customer_plz'      => $customer_plz,
            'customer_city'     => $customer_city,
            'device_info'       => '',
            'description'       => '',
            'line_items'        => !empty($line_items) ? wp_json_encode($line_items) : null,
            'subtotal'          => $subtotal,
            'discount_type'     => 'none',
            'discount_value'    => 0,
            'discount_description' => '',
            'net_amount'        => $net_amount,
            'vat_rate'          => $stored_vat_rate,
            'vat_amount'        => $vat_amount,
            'total'             => $total,
            'is_kleinunternehmer' => $is_klein,
            'punktepass_reward_applied' => 0,
            'points_used'       => 0,
            'notes'             => $notes,
            'status'            => 'draft',
            'valid_until'       => $valid_until ?: null,
            'created_at'        => current_time('mysql'),
        ]);

        $angebot_id = $wpdb->insert_id;

        // Increment next angebot number
        if ($angebot_id) {
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}ppv_stores SET repair_angebot_next_number = %d WHERE id = %d",
                $next_num + 1, $store_id
            ));
        }

        wp_send_json_success([
            'message' => 'Angebot erstellt',
            'angebot_id' => $angebot_id,
            'angebot_number' => $angebot_number,
        ]);
    }

    /**
     * AJAX: Search customers
     * Searches both saved customers (ppv_repair_customers) and repair form customers (ppv_repairs)
     */
    public static function ajax_customer_search() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $search = sanitize_text_field($_POST['search'] ?? '');

        if (strlen($search) < 2) {
            wp_send_json_success(['customers' => []]);
        }

        $like = '%' . $wpdb->esc_like($search) . '%';

        // 1. Search saved customers
        $saved_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, email, phone, company_name, tax_id, address, plz, city, notes, 'saved' as source
             FROM {$prefix}ppv_repair_customers
             WHERE store_id = %d
               AND (name LIKE %s OR email LIKE %s OR phone LIKE %s OR company_name LIKE %s)
             ORDER BY name ASC
             LIMIT 15",
            $store_id, $like, $like, $like, $like
        ));

        // Collect emails to exclude duplicates from repair customers
        $saved_emails = [];
        foreach ($saved_customers as $c) {
            if (!empty($c->email)) {
                $saved_emails[] = strtolower($c->email);
            }
        }

        // 2. Search unique customers from repairs
        $repair_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT customer_name as name, customer_email as email, customer_phone as phone,
                    NULL as company_name, NULL as tax_id, NULL as address, NULL as plz, NULL as city, NULL as notes,
                    'repair' as source, MAX(id) as repair_id
             FROM {$prefix}ppv_repairs
             WHERE store_id = %d
               AND (customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)
               AND customer_name IS NOT NULL AND customer_name != ''
             GROUP BY customer_email, customer_name, customer_phone
             ORDER BY MAX(created_at) DESC
             LIMIT 15",
            $store_id, $like, $like, $like
        ));

        // Filter out duplicates (by email) and merge
        $customers = $saved_customers;
        foreach ($repair_customers as $rc) {
            // Skip if email already exists in saved customers
            if (!empty($rc->email) && in_array(strtolower($rc->email), $saved_emails)) {
                continue;
            }
            // Use negative ID for repair-sourced customers (to distinguish from saved)
            $rc->id = -intval($rc->repair_id);
            unset($rc->repair_id);
            $customers[] = $rc;
        }

        // Sort by name and limit
        usort($customers, function($a, $b) {
            return strcasecmp($a->name, $b->name);
        });
        $customers = array_slice($customers, 0, 20);

        wp_send_json_success(['customers' => $customers]);
    }

    /**
     * AJAX: Save customer
     */
    public static function ajax_customer_save() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $customer_id = !empty($_POST['customer_id']) ? intval($_POST['customer_id']) : 0;
        $data = [
            'name' => sanitize_text_field($_POST['name'] ?? ''),
            'email' => sanitize_email($_POST['email'] ?? '') ?: null,
            'phone' => sanitize_text_field($_POST['phone'] ?? '') ?: null,
            'company_name' => sanitize_text_field($_POST['company_name'] ?? '') ?: null,
            'tax_id' => sanitize_text_field($_POST['tax_id'] ?? '') ?: null,
            'address' => sanitize_text_field($_POST['address'] ?? '') ?: null,
            'plz' => sanitize_text_field($_POST['plz'] ?? '') ?: null,
            'city' => sanitize_text_field($_POST['city'] ?? '') ?: null,
            'notes' => sanitize_textarea_field($_POST['notes'] ?? '') ?: null,
        ];

        if (empty($data['name'])) {
            wp_send_json_error(['message' => 'Name ist erforderlich']);
        }

        if ($customer_id) {
            // Update existing
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$prefix}ppv_repair_customers WHERE id = %d AND store_id = %d",
                $customer_id, $store_id
            ));
            if (!$exists) {
                wp_send_json_error(['message' => 'Kunde nicht gefunden']);
            }
            $wpdb->update("{$prefix}ppv_repair_customers", $data, ['id' => $customer_id]);
            wp_send_json_success(['message' => 'Kunde aktualisiert', 'customer_id' => $customer_id]);
        } else {
            // Create new
            $data['store_id'] = $store_id;
            $wpdb->insert("{$prefix}ppv_repair_customers", $data);
            wp_send_json_success(['message' => 'Kunde gespeichert', 'customer_id' => $wpdb->insert_id]);
        }
    }

    /**
     * AJAX: List customers
     * Lists both saved customers (ppv_repair_customers) and unique customers from repairs (ppv_repairs)
     */
    public static function ajax_customers_list() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $page = max(1, intval($_POST['page'] ?? 1));
        $per_page = 50;
        $offset = ($page - 1) * $per_page;
        $search = sanitize_text_field($_POST['search'] ?? '');

        // Build search conditions
        $search_cond_saved = '';
        $search_cond_repair = '';
        $search_params_saved = [$store_id];
        $search_params_repair = [$store_id];

        if ($search) {
            $like = '%' . $wpdb->esc_like($search) . '%';
            $search_cond_saved = " AND (name LIKE %s OR email LIKE %s OR company_name LIKE %s)";
            $search_params_saved[] = $like;
            $search_params_saved[] = $like;
            $search_params_saved[] = $like;

            $search_cond_repair = " AND (customer_name LIKE %s OR customer_email LIKE %s OR customer_phone LIKE %s)";
            $search_params_repair[] = $like;
            $search_params_repair[] = $like;
            $search_params_repair[] = $like;
        }

        // 1. Get saved customers
        $saved_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT id, name, email, phone, company_name, tax_id, address, plz, city, notes, created_at, 'saved' as source
             FROM {$prefix}ppv_repair_customers
             WHERE store_id = %d {$search_cond_saved}
             ORDER BY name ASC",
            ...$search_params_saved
        ));

        // Collect emails to exclude duplicates
        $saved_emails = [];
        foreach ($saved_customers as $c) {
            if (!empty($c->email)) {
                $saved_emails[] = strtolower($c->email);
            }
        }

        // 2. Get unique customers from repairs (grouped by email+name)
        $repair_customers = $wpdb->get_results($wpdb->prepare(
            "SELECT customer_name as name, customer_email as email, customer_phone as phone,
                    NULL as company_name, NULL as tax_id, NULL as address, NULL as plz, NULL as city, NULL as notes,
                    MAX(created_at) as created_at, 'repair' as source, MAX(id) as repair_id,
                    COUNT(*) as repair_count
             FROM {$prefix}ppv_repairs
             WHERE store_id = %d
               AND customer_name IS NOT NULL AND customer_name != ''
               {$search_cond_repair}
             GROUP BY customer_email, customer_name, customer_phone
             ORDER BY MAX(created_at) DESC",
            ...$search_params_repair
        ));

        // Filter out duplicates (by email) and merge
        $all_customers = [];
        foreach ($saved_customers as $c) {
            $c->repair_count = 0;
            $all_customers[] = $c;
        }

        foreach ($repair_customers as $rc) {
            // Skip if email already exists in saved customers
            if (!empty($rc->email) && in_array(strtolower($rc->email), $saved_emails)) {
                continue;
            }
            // Use negative ID for repair-sourced customers
            $rc->id = -intval($rc->repair_id);
            unset($rc->repair_id);
            $all_customers[] = $rc;
        }

        // Sort by name
        usort($all_customers, function($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        $total = count($all_customers);

        // Paginate
        $customers = array_slice($all_customers, $offset, $per_page);

        wp_send_json_success([
            'customers' => $customers,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'page' => $page,
        ]);
    }

    /**
     * AJAX: Delete customer
     */
    public static function ajax_customer_delete() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $customer_id = intval($_POST['customer_id'] ?? 0);
        if (!$customer_id) wp_send_json_error(['message' => 'Ungültiger Kunde']);

        global $wpdb;
        $deleted = $wpdb->delete($wpdb->prefix . 'ppv_repair_customers', [
            'id' => $customer_id,
            'store_id' => $store_id,
        ]);

        if ($deleted) {
            wp_send_json_success(['message' => 'Kunde gelöscht']);
        } else {
            wp_send_json_error(['message' => 'Kunde nicht gefunden']);
        }
    }

    /**
     * Build the HTML for invoice PDF
     */
    private static function build_invoice_html($store, $invoice) {
        // Load repair translations for PDF
        PPV_Lang::load_extra('ppv-repair-lang');

        $color = esc_attr($store->repair_color ?: '#667eea');
        $company = esc_html($store->repair_company_name ?: $store->name);
        $owner = esc_html($store->repair_owner_name ?: '');
        $addr = esc_html($store->address ?: '');
        $plz = esc_html($store->plz ?: '');
        $city = esc_html($store->city ?: '');
        $plz_city = esc_html(trim($plz . ' ' . $city));
        $phone = esc_html($store->phone ?: '');
        $email = esc_html($store->email ?: '');
        $tax = esc_html($store->repair_tax_id ?: '');
        $logo_url = esc_url($store->logo ?: '');

        // Additional store settings
        $steuernummer = esc_html($store->repair_steuernummer ?? '');
        $website = esc_html($store->repair_website_url ?? '');
        $bank_name = esc_html($store->repair_bank_name ?? '');
        $bank_iban = esc_html($store->repair_bank_iban ?? '');
        $bank_bic = esc_html($store->repair_bank_bic ?? '');
        $paypal = esc_html($store->repair_paypal_email ?? '');
        $is_differenz = intval($invoice->is_differenzbesteuerung ?? 0);

        $inv_nr = esc_html($invoice->invoice_number);
        $inv_date = date('d.m.Y', strtotime($invoice->created_at));
        $doc_type = $invoice->doc_type ?? 'rechnung';
        $is_angebot = ($doc_type === 'angebot');
        $doc_type_label = $is_angebot ? PPV_Lang::t('repair_pdf_quote') : PPV_Lang::t('repair_pdf_invoice');
        $valid_until = ($is_angebot && !empty($invoice->valid_until)) ? date('d.m.Y', strtotime($invoice->valid_until)) : '';
        $cust_name = esc_html($invoice->customer_name);
        $cust_email = esc_html($invoice->customer_email);
        $cust_phone = esc_html($invoice->customer_phone ?: '');
        $cust_company = esc_html($invoice->customer_company ?? '');
        $cust_tax_id = esc_html($invoice->customer_tax_id ?? '');
        $cust_address = esc_html($invoice->customer_address ?? '');
        $cust_plz = esc_html($invoice->customer_plz ?? '');
        $cust_city = esc_html($invoice->customer_city ?? '');
        $device = esc_html($invoice->device_info ?: '');
        $desc = nl2br(esc_html($invoice->description ?: ''));
        $notes = $invoice->notes ? nl2br(esc_html($invoice->notes)) : '';

        $subtotal = number_format($invoice->subtotal, 2, ',', '.');
        $net_amount = number_format($invoice->net_amount, 2, ',', '.');
        $vat_rate = floatval($invoice->vat_rate);
        $vat_amount = number_format($invoice->vat_amount, 2, ',', '.');
        $total = number_format($invoice->total, 2, ',', '.');
        $is_klein = intval($invoice->is_kleinunternehmer);
        $discount_val = number_format($invoice->discount_value, 2, ',', '.');
        $discount_desc = esc_html($invoice->discount_description ?: '');
        $has_discount = $invoice->discount_type !== 'none' && $invoice->discount_value > 0;

        $logo_html = $logo_url ? '<img src="' . $logo_url . '" style="height:50px;border-radius:6px;">' : '';

        // Customer address formatting
        $cust_plz_city = trim($cust_plz . ' ' . $cust_city);

        // Translated labels
        $t_reward = PPV_Lang::t('repair_pdf_reward');
        $t_reward_redeemed = PPV_Lang::t('repair_pdf_reward_redeemed');
        $t_points_used = PPV_Lang::t('repair_pdf_points_used');
        $t_notes = PPV_Lang::t('repair_pdf_notes');
        $t_paid = PPV_Lang::t('repair_pdf_paid');
        $t_paid_on = PPV_Lang::t('repair_pdf_paid_on');
        $t_paid_via = PPV_Lang::t('repair_pdf_paid_via');

        $discount_row = '';
        if ($has_discount) {
            $discount_row = '<tr><td></td><td colspan="3" style="color:#059669">' . $t_reward . ': ' . $discount_desc . '</td><td style="color:#059669;font-weight:600">-' . $discount_val . ' &euro;</td></tr>';
        }

        // (reward notice and payment section built in template below)

        // Build line items rows
        $line_items = json_decode($invoice->line_items ?? '', true);
        $items_html = '';
        $pos = 1;
        if (!empty($line_items) && is_array($line_items)) {
            foreach ($line_items as $item) {
                $item_desc = esc_html($item['description'] ?? PPV_Lang::t('repair_pdf_position'));
                $item_amt = number_format(floatval($item['amount'] ?? 0), 2, ',', '.');
                $items_html .= '<tr><td>' . $pos . '</td><td>' . $item_desc . '</td><td>1,00</td><td>' . $item_amt . ' &euro;</td><td>' . $item_amt . ' &euro;</td></tr>';
                $pos++;
            }
        } else {
            // Fallback: single line for legacy invoices
            $item_label = $device ? $device : PPV_Lang::t('repair_pdf_repair');
            $items_html = '<tr><td>1</td><td>' . $item_label . ($desc ? '<br><span style="font-size:8pt;color:#718096">' . $desc . '</span>' : '') . '</td><td>1,00</td><td>' . $subtotal . ' &euro;</td><td>' . $subtotal . ' &euro;</td></tr>';
        }

        // Determine VAT text based on settings
        $vat_text = '';
        if ($is_klein) {
            $vat_text = '<div style="font-size:10px;color:#6b7280;margin-top:8px">' . esc_html(PPV_Lang::t('repair_pdf_small_biz')) . '</div>';
        } elseif ($is_differenz) {
            $vat_text = '<div style="font-size:10px;color:#6b7280;margin-top:8px">' . esc_html(PPV_Lang::t('repair_pdf_diff_tax')) . '</div>';
        }

        // Build footer columns
        $t_country = PPV_Lang::t('repair_pdf_country');
        $t_tel = PPV_Lang::t('repair_pdf_tel');
        $t_email_label = PPV_Lang::t('repair_pdf_email');
        $t_web = PPV_Lang::t('repair_pdf_web');
        $t_paypal_addr = PPV_Lang::t('repair_pdf_paypal_addr');
        $t_vat_id = PPV_Lang::t('repair_pdf_vat_id');
        $t_tax_nr = PPV_Lang::t('repair_pdf_tax_nr');
        $t_owner = PPV_Lang::t('repair_pdf_owner');

        // Translated labels for template
        $t_date = PPV_Lang::t('repair_pdf_date');
        $t_nr = PPV_Lang::t('repair_pdf_nr');
        $t_valid_until = PPV_Lang::t('repair_pdf_valid_until');
        $t_your_vat_id = PPV_Lang::t('repair_pdf_your_vat_id');
        $t_greeting = PPV_Lang::t('repair_pdf_greeting');
        $t_intro = $is_angebot ? PPV_Lang::t('repair_pdf_intro_quote') : PPV_Lang::t('repair_pdf_intro_invoice');
        $t_pos = PPV_Lang::t('repair_pdf_pos');
        $t_desc = PPV_Lang::t('repair_pdf_description');
        $t_qty = PPV_Lang::t('repair_pdf_quantity');
        $t_unit_price = PPV_Lang::t('repair_pdf_unit_price');
        $t_total_col = PPV_Lang::t('repair_pdf_total');
        $t_subtotal_net = PPV_Lang::t('repair_pdf_subtotal_net');
        $t_vat_label = PPV_Lang::t('repair_pdf_vat');
        $t_total_amount = PPV_Lang::t('repair_pdf_total_amount');
        $t_small_biz = PPV_Lang::t('repair_pdf_small_biz');
        $t_diff_tax = PPV_Lang::t('repair_pdf_diff_tax');
        $t_contact = PPV_Lang::t('repair_pdf_contact');
        $t_tax_data = PPV_Lang::t('repair_pdf_tax_data');
        $t_bank = PPV_Lang::t('repair_pdf_bank');
        $lang_code = PPV_Lang::current() ?: 'de';

        // Payment section with new design
        $payment_html = '';
        if ($invoice->status === 'paid') {
            $payment_method = esc_html($invoice->payment_method ?? '');
            $paid_at_date = $invoice->paid_at ? date('d.m.Y', strtotime($invoice->paid_at)) : '';
            $payment_method_labels = [
                'bar' => PPV_Lang::t('repair_pdf_pay_cash'),
                'ec' => PPV_Lang::t('repair_pdf_pay_ec'),
                'kreditkarte' => PPV_Lang::t('repair_pdf_pay_credit'),
                'ueberweisung' => PPV_Lang::t('repair_pdf_pay_transfer'),
                'paypal' => PPV_Lang::t('repair_pdf_pay_paypal'),
                'andere' => PPV_Lang::t('repair_pdf_pay_other'),
            ];
            $method_display = $payment_method_labels[$payment_method] ?? $payment_method;
            if ($method_display || $paid_at_date) {
                $payment_html = '<div class="info-box payment"><strong>' . $t_paid . '</strong>';
                if ($paid_at_date) $payment_html .= $t_paid_on . $paid_at_date;
                if ($method_display) $payment_html .= $t_paid_via . $method_display;
                $payment_html .= '</div>';
            }
        }

        return '<!DOCTYPE html><html lang="' . $lang_code . '"><head><meta charset="UTF-8"><style>
@page{margin:10mm 15mm 28mm 15mm;size:A4}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:"Helvetica Neue",Helvetica,Arial,sans-serif;color:#1a202c;font-size:9.5pt;line-height:1.5}
.invoice{padding:0;max-width:100%}

/* Accent bar */
.accent-bar{background:' . $color . ';height:4mm;margin:0 -15mm;margin-top:-10mm}

/* Header */
.header{display:table;width:100%;padding:6mm 0 5mm}
.header-left{display:table-cell;vertical-align:middle;width:55%}
.header-right{display:table-cell;vertical-align:middle;width:45%;text-align:right}
.logo{height:44px;margin-bottom:2mm}
.company-name{font-size:18pt;font-weight:700;color:' . $color . ';letter-spacing:-0.3px}
.company-owner{font-size:9pt;color:#64748b;margin-top:1mm}
.header-contact{font-size:8.5pt;color:#64748b;line-height:1.7}
.header-divider{height:0.5mm;background:#e2e8f0;margin-bottom:6mm}

/* Address Section */
.address-section{display:table;width:100%;margin-bottom:6mm}
.address-left{display:table-cell;vertical-align:top;width:55%}
.address-right{display:table-cell;vertical-align:top;width:45%}
.sender-line{font-size:6.5pt;color:#94a3b8;border-bottom:0.5px solid #cbd5e1;padding-bottom:1mm;margin-bottom:3mm;display:inline-block;text-transform:uppercase;letter-spacing:0.3px}
.customer-address{font-size:10pt;line-height:1.7;color:#1e293b}
.customer-name{font-weight:700;font-size:11pt;color:#0f172a}

/* Invoice Details Box */
.invoice-details{background:#f8fafc;border-left:3px solid ' . $color . ';padding:4mm 5mm}
.invoice-details table{width:100%;border-collapse:collapse}
.invoice-details td{padding:1.5mm 0;font-size:9.5pt}
.invoice-details td:first-child{color:#64748b;width:48%}
.invoice-details td:last-child{text-align:right;font-weight:500;color:#1e293b}
.invoice-number{font-size:14pt;font-weight:800;color:' . $color . ';letter-spacing:-0.5px}

/* Title & Intro */
.doc-title{font-size:20pt;font-weight:700;color:#0f172a;margin:5mm 0 1mm;letter-spacing:-0.5px}
.doc-title-line{width:30mm;height:1mm;background:' . $color . ';margin-bottom:4mm}
.intro-text{font-size:9.5pt;color:#475569;margin-bottom:5mm;line-height:1.6}

/* Items Table */
.items-table{width:100%;border-collapse:collapse;margin-bottom:5mm}
.items-table th{background:' . $color . ';color:#fff;padding:3mm 3mm;font-size:8pt;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;text-align:left}
.items-table th:nth-child(1){width:7%;text-align:center}
.items-table th:nth-child(2){width:51%}
.items-table th:nth-child(3){width:12%;text-align:center}
.items-table th:nth-child(4){width:15%;text-align:right}
.items-table th:nth-child(5){width:15%;text-align:right}
.items-table td{padding:3.5mm 3mm;border-bottom:0.5px solid #e2e8f0;font-size:9.5pt;vertical-align:top;color:#334155}
.items-table td:nth-child(1){text-align:center;color:#94a3b8}
.items-table td:nth-child(3){text-align:center}
.items-table td:nth-child(4),.items-table td:nth-child(5){text-align:right;font-weight:500}
.items-table tr:last-child td{border-bottom:2px solid ' . $color . '}

/* Summary */
.summary-wrapper{display:table;width:100%;margin-top:3mm}
.summary-spacer{display:table-cell;width:50%}
.summary-section{display:table-cell;width:50%}
.summary-row{display:table;width:100%;padding:1.5mm 0;font-size:9.5pt}
.summary-row span:first-child{display:table-cell;text-align:left;color:#64748b}
.summary-row span:last-child{display:table-cell;text-align:right;color:#1e293b;font-weight:500}
.summary-divider{border-top:0.5px solid #e2e8f0;margin:1mm 0}
.summary-row.total{border-top:2px solid #0f172a;margin-top:2mm;padding-top:3mm;font-weight:800;font-size:13pt}
.summary-row.total span:first-child{color:#0f172a}
.summary-row.total span:last-child{color:' . $color . '}

/* Info Boxes */
.info-box{padding:3mm 4mm;margin-top:4mm;font-size:9pt}
.info-box.payment{background:#f0f9ff;border-left:3px solid ' . $color . ';color:#0c4a6e}
.info-box.reward{background:#f0fdf4;border-left:3px solid #22c55e;color:#14532d}
.info-box strong{font-weight:700}
.vat-notice{font-size:8pt;color:#94a3b8;margin-top:3mm;font-style:italic}

/* Notes */
.notes-section{margin-top:5mm;padding-top:3mm;border-top:0.5px solid #e2e8f0}
.notes-label{font-size:8pt;color:#94a3b8;text-transform:uppercase;letter-spacing:0.5px;font-weight:600}
.notes-text{font-size:9pt;color:#475569;margin-top:1mm;line-height:1.5}

/* Footer */
.footer{position:fixed;bottom:0;left:0;right:0;padding:4mm 15mm;border-top:2px solid ' . $color . ';background:#f8fafc;font-size:7.5pt;color:#64748b}
.footer-grid{display:table;width:100%;table-layout:fixed}
.footer-col{display:table-cell;vertical-align:top;padding:0 2mm;line-height:1.6}
.footer-col:first-child{padding-left:0}
.footer-col:last-child{padding-right:0}
.footer-col strong{color:#1e293b;font-weight:700;font-size:7pt}
.footer-label{color:#94a3b8;font-size:6pt;text-transform:uppercase;letter-spacing:0.5px;font-weight:600;margin-bottom:1mm}
</style></head><body>

<div class="accent-bar"></div>

<div class="invoice">

<div class="header">
<div class="header-left">
' . ($logo_url ? '<img src="' . $logo_url . '" class="logo" alt="">' : '') . '
<div class="company-name">' . $company . '</div>
' . ($owner ? '<div class="company-owner">' . $owner . '</div>' : '') . '
</div>
<div class="header-right">
<div class="header-contact">
' . $addr . '<br>
' . $plz_city . '<br>
' . ($phone ? $t_tel . ': ' . $phone . '<br>' : '') . '
' . $email . '
</div>
</div>
</div>

<div class="header-divider"></div>

<div class="address-section">
<div class="address-left">
<div class="sender-line">' . $company . ' &middot; ' . $addr . ' &middot; ' . $plz_city . '</div>
<div class="customer-address">
' . ($cust_company ? $cust_company . '<br>' : '') . '
<span class="customer-name">' . $cust_name . '</span><br>
' . ($cust_address ? $cust_address . '<br>' : '') . '
' . ($cust_plz_city ? $cust_plz_city : '') . '
</div>
</div>
<div class="address-right">
<div class="invoice-details">
<table>
<tr><td>' . $doc_type_label . ' ' . $t_nr . ':</td><td><span class="invoice-number">' . $inv_nr . '</span></td></tr>
<tr><td>' . $t_date . ':</td><td>' . $inv_date . '</td></tr>
' . ($valid_until ? '<tr><td>' . $t_valid_until . ':</td><td>' . $valid_until . '</td></tr>' : '') . '
' . ($cust_tax_id ? '<tr><td>' . $t_your_vat_id . ':</td><td>' . $cust_tax_id . '</td></tr>' : '') . '
</table>
</div>
</div>
</div>

<div class="doc-title">' . $doc_type_label . '</div>
<div class="doc-title-line"></div>
<p class="intro-text">' . $t_greeting . '<br>' . $t_intro . '</p>

<table class="items-table">
<tr><th>' . $t_pos . '</th><th>' . $t_desc . '</th><th>' . $t_qty . '</th><th>' . $t_unit_price . '</th><th>' . $t_total_col . '</th></tr>
' . $items_html . '
' . $discount_row . '
</table>

<div class="summary-wrapper">
<div class="summary-spacer"></div>
<div class="summary-section">
<div class="summary-row"><span>' . $t_subtotal_net . '</span><span>' . $net_amount . ' &euro;</span></div>
<div class="summary-divider"></div>
' . (!$is_klein && !$is_differenz ? '<div class="summary-row"><span>' . $t_vat_label . ' ' . number_format($vat_rate, 0) . '%</span><span>' . $vat_amount . ' &euro;</span></div>' : '') . '
<div class="summary-row total"><span>' . $t_total_amount . '</span><span>' . $total . ' &euro;</span></div>
</div>
</div>

' . ($is_klein ? '<p class="vat-notice">' . esc_html($t_small_biz) . '</p>' : '') . '
' . ($is_differenz ? '<p class="vat-notice">' . esc_html($t_diff_tax) . '</p>' : '') . '

' . $payment_html . '

' . ($invoice->punktepass_reward_applied ? '<div class="info-box reward"><strong>' . $t_reward_redeemed . '</strong> ' . $discount_desc . '</div>' : '') . '

' . ($notes ? '<div class="notes-section"><div class="notes-label">' . $t_notes . ':</div><p class="notes-text">' . $notes . '</p></div>' : '') . '

</div>

<div class="footer">
<div class="footer-grid">
<div class="footer-col">
<strong>' . $company . '</strong><br>
' . $addr . '<br>
' . $plz_city . '
</div>
<div class="footer-col">
<span class="footer-label">' . $t_contact . '</span><br>
' . ($phone ? $t_tel . ': ' . $phone . '<br>' : '') . '
' . $email . '
' . ($website ? '<br>' . $website : '') . '
</div>
<div class="footer-col">
<span class="footer-label">' . $t_tax_data . '</span><br>
' . ($tax ? $t_vat_id . ': ' . $tax . '<br>' : '') . '
' . ($steuernummer ? $t_tax_nr . ': ' . $steuernummer . '<br>' : '') . '
' . ($owner ? $t_owner . ': ' . $owner : '') . '
</div>
' . ($bank_iban ? '<div class="footer-col">
<span class="footer-label">' . $t_bank . '</span><br>
' . ($bank_name ? $bank_name . '<br>' : '') . '
IBAN: ' . $bank_iban . '
' . ($bank_bic ? '<br>BIC: ' . $bank_bic : '') . '
</div>' : '') . '
</div>
</div>

</body></html>';
    }

    /**
     * AJAX: Send invoice via email
     */
    public static function ajax_send_email() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;
        $invoice_id = intval($_POST['invoice_id'] ?? 0);

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
            $invoice_id, $store_id
        ));
        if (!$invoice) wp_send_json_error(['message' => 'Rechnung nicht gefunden']);

        if (empty($invoice->customer_email)) {
            wp_send_json_error(['message' => 'Keine Kunden-E-Mail vorhanden']);
        }

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if (!$store) wp_send_json_error(['message' => 'Store nicht gefunden']);

        // Build PDF
        $html = self::build_invoice_html($store, $invoice);
        require_once(PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php');
        $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdf_content = $dompdf->output();

        // Save PDF temporarily
        $upload_dir = wp_upload_dir();
        $pdf_path = $upload_dir['basedir'] . '/ppv-invoices/';
        if (!file_exists($pdf_path)) {
            wp_mkdir_p($pdf_path);
        }
        $pdf_file = $pdf_path . sanitize_file_name($invoice->invoice_number) . '.pdf';
        file_put_contents($pdf_file, $pdf_content);

        // Prepare email
        $company_name = $store->repair_company_name ?: $store->name;
        $invoice_date = date('d.m.Y', strtotime($invoice->created_at));
        $doc_type = $invoice->doc_type ?? 'rechnung';
        $is_angebot = ($doc_type === 'angebot');
        $doc_type_label = $is_angebot ? 'Angebot' : 'Rechnung';
        $doc_type_label_lower = $is_angebot ? 'Angebot' : 'Rechnung';

        // Get email template from store settings - use document type
        PPV_Lang::load_extra('ppv-repair-lang');
        $default_subject = $is_angebot ? PPV_Lang::t('repair_pdf_email_subj_quote') : PPV_Lang::t('repair_pdf_email_subj_inv');
        $default_body = $is_angebot ? PPV_Lang::t('repair_pdf_email_body_quote') : PPV_Lang::t('repair_pdf_email_body_inv');
        $subject = $store->repair_invoice_email_subject ?: $default_subject;
        $body = $store->repair_invoice_email_body ?: $default_body;

        // Replace placeholders
        $replacements = [
            '{customer_name}' => $invoice->customer_name,
            '{invoice_number}' => $invoice->invoice_number,
            '{invoice_date}' => $invoice_date,
            '{total}' => number_format($invoice->total, 2, ',', '.'),
            '{company_name}' => $company_name,
        ];
        $subject = str_replace(array_keys($replacements), array_values($replacements), $subject);
        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        // Send email from store's email address
        $store_email = $store->email ?: get_option('admin_email');
        $headers = [
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $company_name . ' <' . $store_email . '>',
            'Reply-To: ' . $company_name . ' <' . $store_email . '>',
            'Return-Path: ' . $store_email,
        ];

        $sent = wp_mail(
            $invoice->customer_email,
            $subject,
            $body,
            $headers,
            [$pdf_file]
        );

        // Clean up temp file
        @unlink($pdf_file);

        if ($sent) {
            // Update invoice status to 'sent' if it was draft
            if ($invoice->status === 'draft') {
                $wpdb->update($prefix . 'ppv_repair_invoices', ['status' => 'sent'], ['id' => $invoice_id]);
            }
            wp_send_json_success(['message' => $doc_type_label . ' wurde an ' . $invoice->customer_email . ' gesendet']);
        } else {
            wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden']);
        }
    }

    /**
     * AJAX: Send payment reminder email
     */
    public static function ajax_send_reminder() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $invoice_id = intval($_POST['invoice_id'] ?? 0);
        if (!$invoice_id) wp_send_json_error(['message' => 'Ungültige Rechnung']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $invoice = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
            $invoice_id, $store_id
        ));
        if (!$invoice) wp_send_json_error(['message' => 'Rechnung nicht gefunden']);
        if (empty($invoice->customer_email)) wp_send_json_error(['message' => 'Keine E-Mail-Adresse vorhanden']);
        if ($invoice->status === 'paid') wp_send_json_error(['message' => 'Rechnung ist bereits bezahlt']);

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));

        $company_name = $store->repair_company_name ?: $store->name;
        $company_email = $store->repair_company_email ?: $store->email ?: '';
        $company_phone = $store->repair_company_phone ?: $store->phone ?: '';
        $invoice_date = date('d.m.Y', strtotime($invoice->created_at));
        $days_overdue = floor((time() - strtotime($invoice->created_at)) / 86400);

        $subject = "Zahlungserinnerung: Rechnung {$invoice->invoice_number}";

        $body = "Sehr geehrte/r {$invoice->customer_name},\n\n";
        $body .= "wir möchten Sie freundlich daran erinnern, dass die folgende Rechnung noch offen ist:\n\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
        $body .= "RECHNUNGSDETAILS\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $body .= "📋 Rechnungsnummer: {$invoice->invoice_number}\n";
        $body .= "📅 Rechnungsdatum: {$invoice_date}\n";
        $body .= "💰 Offener Betrag: " . number_format($invoice->total, 2, ',', '.') . " €\n";
        if ($days_overdue > 0) {
            $body .= "⏰ Überfällig seit: {$days_overdue} Tagen\n";
        }
        $body .= "\n";
        $body .= "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";
        $body .= "Bitte überweisen Sie den offenen Betrag zeitnah.\n\n";
        $body .= "Falls Sie die Zahlung bereits veranlasst haben, betrachten Sie diese E-Mail bitte als gegenstandslos.\n\n";
        $body .= "Bei Fragen stehen wir Ihnen gerne zur Verfügung:\n";
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

        $sent = wp_mail($invoice->customer_email, $subject, $body, $headers);

        if ($sent) {
            // Update reminder_sent_at timestamp
            $wpdb->update($prefix . 'ppv_repair_invoices',
                ['notes' => trim(($invoice->notes ?: '') . "\n[" . current_time('d.m.Y H:i') . "] Zahlungserinnerung gesendet")],
                ['id' => $invoice_id]
            );
            wp_send_json_success(['message' => 'Zahlungserinnerung wurde an ' . $invoice->customer_email . ' gesendet']);
        } else {
            wp_send_json_error(['message' => 'E-Mail konnte nicht gesendet werden']);
        }
    }

    /**
     * AJAX: Bulk operation on invoices
     */
    public static function ajax_bulk_operation() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        $operation = sanitize_text_field($_POST['operation'] ?? '');
        $invoice_ids = json_decode(stripslashes($_POST['invoice_ids'] ?? '[]'), true);

        if (empty($invoice_ids) || !is_array($invoice_ids)) {
            wp_send_json_error(['message' => 'Keine Rechnungen ausgewählt']);
        }

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));

        $success_count = 0;
        $error_count = 0;

        foreach ($invoice_ids as $invoice_id) {
            $invoice_id = intval($invoice_id);
            $invoice = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
                $invoice_id, $store_id
            ));
            if (!$invoice) {
                $error_count++;
                continue;
            }

            switch ($operation) {
                case 'mark_paid':
                    $wpdb->update($prefix . 'ppv_repair_invoices', [
                        'status' => 'paid',
                        'paid_at' => current_time('mysql')
                    ], ['id' => $invoice_id]);
                    $success_count++;
                    break;

                case 'send_email':
                    if (empty($invoice->customer_email)) {
                        $error_count++;
                        continue 2;
                    }
                    // Use existing send_email logic (simplified version without PDF for bulk)
                    $_POST['invoice_id'] = $invoice_id;
                    // Generate and send - this is a simplified approach
                    $company_name = $store->repair_company_name ?: $store->name;
                    $invoice_date = date('d.m.Y', strtotime($invoice->created_at));
                    $subject = str_replace('{invoice_number}', $invoice->invoice_number,
                        $store->repair_invoice_email_subject ?: 'Ihre Rechnung {invoice_number}');
                    $body = str_replace(
                        ['{customer_name}', '{invoice_number}', '{invoice_date}', '{total}', '{company_name}'],
                        [$invoice->customer_name, $invoice->invoice_number, $invoice_date, number_format($invoice->total, 2, ',', '.'), $company_name],
                        $store->repair_invoice_email_body ?: "Sehr geehrte/r {customer_name},\n\nanbei erhalten Sie Ihre Rechnung {invoice_number}.\n\nGesamtbetrag: {total} €\n\nMit freundlichen Grüßen,\n{company_name}"
                    );
                    $headers = ['Content-Type: text/plain; charset=UTF-8', "From: {$company_name} <noreply@punktepass.de>"];
                    if (wp_mail($invoice->customer_email, $subject, $body, $headers)) {
                        if ($invoice->status === 'draft') {
                            $wpdb->update($prefix . 'ppv_repair_invoices', ['status' => 'sent'], ['id' => $invoice_id]);
                        }
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    break;

                case 'send_reminder':
                    if (empty($invoice->customer_email) || $invoice->status === 'paid') {
                        $error_count++;
                        continue 2;
                    }
                    $company_name = $store->repair_company_name ?: $store->name;
                    $company_email = $store->repair_company_email ?: '';
                    $company_phone = $store->repair_company_phone ?: '';
                    $invoice_date = date('d.m.Y', strtotime($invoice->created_at));
                    $days_overdue = floor((time() - strtotime($invoice->created_at)) / 86400);

                    $subject = "Zahlungserinnerung: Rechnung {$invoice->invoice_number}";
                    $body = "Sehr geehrte/r {$invoice->customer_name},\n\n";
                    $body .= "wir möchten Sie freundlich daran erinnern, dass die folgende Rechnung noch offen ist:\n\n";
                    $body .= "Rechnungsnummer: {$invoice->invoice_number}\n";
                    $body .= "Rechnungsdatum: {$invoice_date}\n";
                    $body .= "Offener Betrag: " . number_format($invoice->total, 2, ',', '.') . " €\n";
                    if ($days_overdue > 0) $body .= "Überfällig seit: {$days_overdue} Tagen\n";
                    $body .= "\nBitte überweisen Sie den offenen Betrag zeitnah.\n\n";
                    $body .= "Mit freundlichen Grüßen,\n{$company_name}\n";

                    $headers = ['Content-Type: text/plain; charset=UTF-8', "From: {$company_name} <noreply@punktepass.de>"];
                    if (wp_mail($invoice->customer_email, $subject, $body, $headers)) {
                        $wpdb->update($prefix . 'ppv_repair_invoices',
                            ['notes' => trim(($invoice->notes ?: '') . "\n[" . current_time('d.m.Y H:i') . "] Zahlungserinnerung gesendet")],
                            ['id' => $invoice_id]
                        );
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    break;

                case 'delete':
                    // Delete the invoice
                    $deleted = $wpdb->delete($prefix . 'ppv_repair_invoices', ['id' => $invoice_id, 'store_id' => $store_id]);
                    if ($deleted) {
                        $success_count++;
                    } else {
                        $error_count++;
                    }
                    break;

                case 'export':
                    // Export is handled separately after the loop
                    $success_count++;
                    break;

                default:
                    wp_send_json_error(['message' => 'Unbekannte Operation']);
            }
        }

        // Handle export operation separately (needs all invoices at once)
        if ($operation === 'export') {
            $format = sanitize_text_field($_POST['format'] ?? 'csv');
            $invoices = [];
            foreach ($invoice_ids as $invoice_id) {
                $invoice_id = intval($invoice_id);
                $invoice = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$prefix}ppv_repair_invoices WHERE id = %d AND store_id = %d",
                    $invoice_id, $store_id
                ));
                if ($invoice) {
                    $invoices[] = $invoice;
                }
            }
            if (empty($invoices)) {
                wp_send_json_error(['message' => 'Keine Rechnungen gefunden']);
            }
            return self::handle_bulk_export($invoices, $format, $store);
        }

        $message = "{$success_count} erfolgreich verarbeitet";
        if ($error_count > 0) {
            $message .= ", {$error_count} fehlgeschlagen";
        }

        wp_send_json_success(['message' => $message, 'success' => $success_count, 'errors' => $error_count]);
    }

    /**
     * Handle bulk export of invoices
     */
    private static function handle_bulk_export($invoices, $format, $store) {
        $upload_dir = wp_upload_dir();
        $export_dir = $upload_dir['basedir'] . '/ppv-exports/';
        if (!file_exists($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        $timestamp = date('Y-m-d_His');
        $company = sanitize_file_name($store->repair_company_name ?: $store->name);

        switch ($format) {
            case 'datev':
                return self::export_datev($invoices, $export_dir, $timestamp, $company, $store);

            case 'csv':
                return self::export_csv($invoices, $export_dir, $timestamp, $company);

            case 'excel':
                return self::export_excel($invoices, $export_dir, $timestamp, $company);

            case 'pdf':
                return self::export_pdf_zip($invoices, $export_dir, $timestamp, $company, $store);

            case 'json':
                return self::export_json($invoices, $export_dir, $timestamp, $company);

            default:
                wp_send_json_error(['message' => 'Unbekanntes Export-Format']);
        }
    }

    /**
     * Export DATEV format (German accounting standard)
     */
    private static function export_datev($invoices, $export_dir, $timestamp, $company, $store) {
        $filename = "DATEV_Export_{$company}_{$timestamp}.csv";
        $filepath = $export_dir . $filename;

        // DATEV header
        $header = [
            'Umsatz (ohne Soll/Haben-Kz)',
            'Soll/Haben-Kennzeichen',
            'WKZ Umsatz',
            'Kurs',
            'Basis-Umsatz',
            'WKZ Basis-Umsatz',
            'Konto',
            'Gegenkonto (ohne BU-Schlüssel)',
            'BU-Schlüssel',
            'Belegdatum',
            'Belegfeld 1',
            'Belegfeld 2',
            'Skonto',
            'Buchungstext',
            'Postensperre',
            'Diverse Adressnummer',
            'Geschäftspartnerbank',
            'Sachverhalt',
            'Zinssperre',
            'Beleglink',
            'Beleginfo - Art 1',
            'Beleginfo - Inhalt 1',
            'Kostenstelle',
            'Kostenträger',
            'Kost-Menge',
            'EU-Land u. UStID',
            'EU-Steuersatz',
            'Abw. Versteuerungsart',
            'Sachverhalt L+L',
            'Funktionsergänzung L+L',
            'BU 49 Hauptfunktionstyp',
            'BU 49 Hauptfunktionsnummer',
            'BU 49 Funktionsergänzung',
            'Zusatzinformation - Art 1',
            'Zusatzinformation - Inhalt 1'
        ];

        $rows = [];
        foreach ($invoices as $inv) {
            $date = date('dmY', strtotime($inv->created_at)); // DATEV date format
            $amount = number_format($inv->total, 2, ',', '');
            $net = number_format($inv->net_amount, 2, ',', '');
            $vat = number_format($inv->vat_amount, 2, ',', '');

            // Main revenue booking
            $row = array_fill(0, count($header), '');
            $row[0] = $amount; // Umsatz
            $row[1] = 'S'; // Soll
            $row[2] = 'EUR';
            $row[6] = '1000'; // Debitor account (generic)
            $row[7] = '8400'; // Revenue account
            $row[8] = $inv->vat_rate > 0 ? '3' : '0'; // Tax code (3 = 19% MwSt)
            $row[9] = $date;
            $row[10] = $inv->invoice_number;
            $row[13] = $inv->customer_name;

            $rows[] = $row;
        }

        // Write CSV with BOM for Excel compatibility
        $fp = fopen($filepath, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($fp, $header, ';');
        foreach ($rows as $row) {
            fputcsv($fp, $row, ';');
        }
        fclose($fp);

        $upload_url = $upload_dir = wp_upload_dir();
        wp_send_json_success([
            'download_url' => $upload_url['baseurl'] . '/ppv-exports/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export standard CSV
     */
    private static function export_csv($invoices, $export_dir, $timestamp, $company) {
        $filename = "Rechnungen_{$company}_{$timestamp}.csv";
        $filepath = $export_dir . $filename;

        $header = [
            'Rechnungsnummer',
            'Typ',
            'Datum',
            'Kunde',
            'E-Mail',
            'Telefon',
            'Adresse',
            'PLZ/Ort',
            'Nettobetrag',
            'MwSt-Satz',
            'MwSt-Betrag',
            'Gesamtbetrag',
            'Status',
            'Bezahlt am',
            'Zahlungsart',
            'Anmerkungen'
        ];

        $fp = fopen($filepath, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($fp, $header, ';');

        foreach ($invoices as $inv) {
            $row = [
                $inv->invoice_number,
                $inv->doc_type === 'angebot' ? 'Angebot' : 'Rechnung',
                date('d.m.Y', strtotime($inv->created_at)),
                $inv->customer_name,
                $inv->customer_email,
                $inv->customer_phone,
                $inv->customer_address,
                $inv->customer_plz_city,
                number_format($inv->net_amount, 2, ',', ''),
                number_format($inv->vat_rate, 0) . '%',
                number_format($inv->vat_amount, 2, ',', ''),
                number_format($inv->total, 2, ',', ''),
                $inv->status === 'paid' ? 'Bezahlt' : ($inv->status === 'sent' ? 'Gesendet' : 'Entwurf'),
                $inv->paid_at ? date('d.m.Y', strtotime($inv->paid_at)) : '',
                $inv->payment_method ?: '',
                $inv->notes ?: ''
            ];
            fputcsv($fp, $row, ';');
        }
        fclose($fp);

        $upload_url = wp_upload_dir();
        wp_send_json_success([
            'download_url' => $upload_url['baseurl'] . '/ppv-exports/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export Excel format (CSV with Excel dialect)
     */
    private static function export_excel($invoices, $export_dir, $timestamp, $company) {
        // For true .xlsx we would need PhpSpreadsheet, so we create a TSV that Excel opens well
        $filename = "Rechnungen_{$company}_{$timestamp}.xlsx.csv";
        $filepath = $export_dir . $filename;

        $header = [
            'Rechnungsnummer',
            'Dokumenttyp',
            'Rechnungsdatum',
            'Kundenname',
            'Firma',
            'E-Mail',
            'Telefon',
            'Straße',
            'PLZ/Ort',
            'USt-IdNr.',
            'Nettobetrag (EUR)',
            'MwSt-Satz (%)',
            'MwSt-Betrag (EUR)',
            'Bruttobetrag (EUR)',
            'Status',
            'Bezahldatum',
            'Zahlungsart',
            'Kleinunternehmer',
            'Differenzbesteuerung',
            'Positionen (JSON)',
            'Anmerkungen',
            'Erstellt am'
        ];

        $fp = fopen($filepath, 'w');
        fprintf($fp, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        fputcsv($fp, $header, "\t");

        foreach ($invoices as $inv) {
            $row = [
                $inv->invoice_number,
                $inv->doc_type === 'angebot' ? 'Angebot' : 'Rechnung',
                date('d.m.Y', strtotime($inv->created_at)),
                $inv->customer_name,
                $inv->customer_company ?: '',
                $inv->customer_email,
                $inv->customer_phone,
                $inv->customer_address,
                $inv->customer_plz_city,
                $inv->customer_tax_id ?: '',
                number_format($inv->net_amount, 2, ',', ''),
                number_format($inv->vat_rate, 0),
                number_format($inv->vat_amount, 2, ',', ''),
                number_format($inv->total, 2, ',', ''),
                $inv->status === 'paid' ? 'Bezahlt' : ($inv->status === 'sent' ? 'Gesendet' : 'Entwurf'),
                $inv->paid_at ? date('d.m.Y', strtotime($inv->paid_at)) : '',
                $inv->payment_method ?: '',
                $inv->is_kleinunternehmer ? 'Ja' : 'Nein',
                $inv->is_differenzbesteuerung ? 'Ja' : 'Nein',
                $inv->items_json ?: '',
                str_replace(["\r", "\n"], ' ', $inv->notes ?: ''),
                date('d.m.Y H:i', strtotime($inv->created_at))
            ];
            fputcsv($fp, $row, "\t");
        }
        fclose($fp);

        $upload_url = wp_upload_dir();
        wp_send_json_success([
            'download_url' => $upload_url['baseurl'] . '/ppv-exports/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export all PDFs as ZIP
     */
    private static function export_pdf_zip($invoices, $export_dir, $timestamp, $company, $store) {
        $filename = "Rechnungen_PDF_{$company}_{$timestamp}.zip";
        $filepath = $export_dir . $filename;

        $zip = new \ZipArchive();
        if ($zip->open($filepath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            wp_send_json_error(['message' => 'ZIP-Erstellung fehlgeschlagen']);
        }

        require_once(PPV_PLUGIN_DIR . 'libs/dompdf/autoload.inc.php');

        foreach ($invoices as $inv) {
            $html = self::build_invoice_html($store, $inv);
            $dompdf = new \Dompdf\Dompdf(['isRemoteEnabled' => true]);
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            $pdf_content = $dompdf->output();

            $doc_type = $inv->doc_type === 'angebot' ? 'Angebot' : 'Rechnung';
            $pdf_name = "{$doc_type}_{$inv->invoice_number}.pdf";
            $zip->addFromString($pdf_name, $pdf_content);
        }

        $zip->close();

        $upload_url = wp_upload_dir();
        wp_send_json_success([
            'download_url' => $upload_url['baseurl'] . '/ppv-exports/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * Export JSON format (for data migration/backup)
     */
    private static function export_json($invoices, $export_dir, $timestamp, $company) {
        $filename = "Rechnungen_{$company}_{$timestamp}.json";
        $filepath = $export_dir . $filename;

        $data = [
            'export_date' => date('Y-m-d H:i:s'),
            'company' => $company,
            'count' => count($invoices),
            'invoices' => []
        ];

        foreach ($invoices as $inv) {
            $items = json_decode($inv->items_json, true) ?: [];
            $data['invoices'][] = [
                'id' => $inv->id,
                'invoice_number' => $inv->invoice_number,
                'doc_type' => $inv->doc_type ?: 'rechnung',
                'date' => date('Y-m-d', strtotime($inv->created_at)),
                'customer' => [
                    'name' => $inv->customer_name,
                    'company' => $inv->customer_company ?: null,
                    'email' => $inv->customer_email,
                    'phone' => $inv->customer_phone,
                    'address' => $inv->customer_address,
                    'plz_city' => $inv->customer_plz_city,
                    'tax_id' => $inv->customer_tax_id ?: null
                ],
                'amounts' => [
                    'net' => floatval($inv->net_amount),
                    'vat_rate' => floatval($inv->vat_rate),
                    'vat' => floatval($inv->vat_amount),
                    'total' => floatval($inv->total),
                    'discount' => floatval($inv->discount_amount ?? 0)
                ],
                'items' => $items,
                'status' => $inv->status,
                'paid_at' => $inv->paid_at ? date('Y-m-d', strtotime($inv->paid_at)) : null,
                'payment_method' => $inv->payment_method ?: null,
                'is_kleinunternehmer' => (bool)$inv->is_kleinunternehmer,
                'is_differenzbesteuerung' => (bool)$inv->is_differenzbesteuerung,
                'notes' => $inv->notes ?: null,
                'created_at' => $inv->created_at
            ];
        }

        file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $upload_url = wp_upload_dir();
        wp_send_json_success([
            'download_url' => $upload_url['baseurl'] . '/ppv-exports/' . $filename,
            'filename' => $filename
        ]);
    }

    /**
     * AJAX: Export all invoices (with optional date filter)
     */
    public static function ajax_export_all() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if (!$store) wp_send_json_error(['message' => 'Store nicht gefunden']);

        $format = sanitize_text_field($_POST['format'] ?? 'csv');
        $date_from = sanitize_text_field($_POST['date_from'] ?? '');
        $date_to = sanitize_text_field($_POST['date_to'] ?? '');
        $doc_type = sanitize_text_field($_POST['doc_type'] ?? '');

        // Build query with filters
        $where = "WHERE store_id = %d";
        $params = [$store_id];

        if ($date_from) {
            $where .= " AND DATE(created_at) >= %s";
            $params[] = $date_from;
        }
        if ($date_to) {
            $where .= " AND DATE(created_at) <= %s";
            $params[] = $date_to;
        }
        if ($doc_type && in_array($doc_type, ['rechnung', 'angebot'])) {
            $where .= " AND (doc_type = %s OR (doc_type IS NULL AND %s = 'rechnung'))";
            $params[] = $doc_type;
            $params[] = $doc_type;
        }

        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices {$where} ORDER BY created_at DESC",
            ...$params
        ));

        if (empty($invoices)) {
            wp_send_json_error(['message' => 'Keine Rechnungen gefunden']);
        }

        return self::handle_bulk_export($invoices, $format, $store);
    }

    /**
     * AJAX: Import invoices from Billbee XML export
     */
    public static function ajax_billbee_import() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        if (empty($_FILES['billbee_xml']) || $_FILES['billbee_xml']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error(['message' => 'Keine Datei hochgeladen']);
        }

        $file = $_FILES['billbee_xml'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'xml') {
            wp_send_json_error(['message' => 'Nur XML-Dateien erlaubt']);
        }

        $xml_content = file_get_contents($file['tmp_name']);
        if (empty($xml_content)) {
            wp_send_json_error(['message' => 'Datei ist leer']);
        }

        // Parse XML - handle Windows-1252 encoding
        $xml_content = preg_replace('/encoding="Windows-1252"/', 'encoding="UTF-8"', $xml_content);
        $xml_content = mb_convert_encoding($xml_content, 'UTF-8', 'Windows-1252');

        libxml_use_internal_errors(true);
        $xml = simplexml_load_string($xml_content);
        if ($xml === false) {
            $errors = libxml_get_errors();
            wp_send_json_error(['message' => 'XML-Fehler: ' . ($errors[0]->message ?? 'Unbekannt')]);
        }

        // Register namespace
        $xml->registerXPathNamespace('ns', 'http://tempuri.org/export_jk.xsd');

        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_stores WHERE id = %d", $store_id
        ));
        if (!$store) wp_send_json_error(['message' => 'Store nicht gefunden']);

        $imported = 0;
        $skipped = 0;
        $errors = [];

        // Get orders from XML
        $orders = $xml->xpath('//ns:Order') ?: $xml->xpath('//Order') ?: [];
        if (empty($orders) && isset($xml->Orders->Order)) {
            $orders = $xml->Orders->Order;
        }

        foreach ($orders as $order) {
            try {
                // Extract invoice number
                $invoice_number = (string)($order->InvoiceNumber ?? '');
                if (empty($invoice_number)) {
                    $invoice_number = 'BB-' . (string)($order->Identity['InternalId'] ?? uniqid());
                }

                // Check if already imported
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$prefix}ppv_repair_invoices WHERE store_id = %d AND invoice_number = %s",
                    $store_id, $invoice_number
                ));
                if ($exists) {
                    $skipped++;
                    continue;
                }

                // Extract customer info
                $customer_name = (string)($order->Customer->Name ?? $order->BillAddress->Company ?? 'Unbekannt');
                $customer_email = (string)($order->Customer->Email ?? '');
                $customer_company = (string)($order->BillAddress->Company ?? '');
                $customer_address = trim((string)($order->BillAddress->Street ?? '') . ' ' . (string)($order->BillAddress->Housenumber ?? ''));
                $customer_plz = (string)($order->BillAddress->Zip ?? '');
                $customer_city = (string)($order->BillAddress->City ?? '');

                // Extract dates
                $bill_date = (string)($order->BillDate ?? $order->OrderDate ?? date('Y-m-d'));
                $pay_date = (string)($order->PayDate ?? '');

                // Extract totals
                $total = floatval($order->SumOfDetails ?? 0);
                $is_negative = $total < 0;
                $total = abs($total);

                // Build line items
                $line_items = [];
                $details = $order->Details->Detail ?? [];
                foreach ($details as $detail) {
                    $desc = (string)($detail->Article->Text ?? 'Artikel');
                    $amount = abs(floatval($detail->Amount ?? 1));
                    $unit_price = floatval($detail->UnitPrice ?? 0);
                    $line_items[] = [
                        'description' => $desc,
                        'quantity' => $amount,
                        'amount' => $unit_price * $amount,
                    ];
                }

                // Calculate VAT (assume 19% included in brutto)
                $vat_rate = 19.0;
                $net_amount = round($total / 1.19, 2);
                $vat_amount = round($total - $net_amount, 2);

                // Determine status
                $status = 'paid';
                if ($is_negative) {
                    $status = 'cancelled';
                } elseif (empty($pay_date)) {
                    $status = 'sent';
                }

                // Platform info for notes
                $platform = (string)($order->Identity['Platform'] ?? '');
                $platform_id = (string)($order->Identity['PlatformId'] ?? '');
                $notes = $platform ? "Import: {$platform}" . ($platform_id ? " #{$platform_id}" : '') : 'Billbee Import';

                // Insert invoice
                $wpdb->insert("{$prefix}ppv_repair_invoices", [
                    'store_id' => $store_id,
                    'repair_id' => null,
                    'invoice_number' => $invoice_number,
                    'customer_name' => $customer_name,
                    'customer_email' => $customer_email,
                    'customer_phone' => '',
                    'customer_company' => $customer_company,
                    'customer_address' => $customer_address,
                    'customer_plz' => $customer_plz,
                    'customer_city' => $customer_city,
                    'device_info' => '',
                    'description' => $notes,
                    'line_items' => !empty($line_items) ? wp_json_encode($line_items) : null,
                    'subtotal' => $total,
                    'discount_type' => 'none',
                    'discount_value' => 0,
                    'discount_description' => '',
                    'net_amount' => $net_amount,
                    'vat_rate' => $vat_rate,
                    'vat_amount' => $vat_amount,
                    'total' => $total,
                    'is_kleinunternehmer' => 0,
                    'punktepass_reward_applied' => 0,
                    'points_used' => 0,
                    'status' => $status,
                    'payment_method' => strtolower((string)($order->PaymentTypeName ?? '')),
                    'paid_at' => !empty($pay_date) ? $pay_date : null,
                    'notes' => $notes,
                    'doc_type' => 'rechnung',
                    'created_at' => $bill_date . ' 00:00:00',
                ]);

                if ($wpdb->insert_id) {
                    $imported++;
                } else {
                    $errors[] = "Fehler bei {$invoice_number}";
                }

            } catch (Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        $message = "{$imported} Rechnungen importiert";
        if ($skipped > 0) {
            $message .= ", {$skipped} übersprungen (bereits vorhanden)";
        }
        if (!empty($errors)) {
            $message .= ". Fehler: " . implode(', ', array_slice($errors, 0, 3));
        }

        wp_send_json_success([
            'message' => $message,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ]);
    }

    /**
     * AJAX: Import repairs from erepairshop database (one-time migration)
     * Imports WITHOUT awarding any PunktePass points
     */
    public static function ajax_erepairshop_import() {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'ppv_repair_admin')) {
            wp_send_json_error(['message' => 'Sicherheitsfehler']);
        }

        $store_id = PPV_Repair_Core::get_current_store_id();
        if (!$store_id) wp_send_json_error(['message' => 'Nicht autorisiert']);

        global $wpdb;
        $prefix = $wpdb->prefix;

        // erepairshop database credentials (remote server)
        $er_host = 'srv1420.hstgr.io';
        $er_db = 'u660905446_sYOnr';
        $er_user = 'u660905446_oPLnu';
        $er_pass = 'Brtegk84047+_';

        try {
            // Connect to erepairshop database
            $er_pdo = new PDO(
                "mysql:host={$er_host};dbname={$er_db};charset=utf8mb4",
                $er_user,
                $er_pass,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // Get all entries
            $entries = $er_pdo->query("SELECT * FROM entries ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);

            // Get erledigt (done) status - by telefon
            $erledigt_list = [];
            $erledigt_rows = $er_pdo->query("SELECT telefon FROM erledigt_status")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($erledigt_rows as $tel) {
                $erledigt_list[$tel] = true;
            }

            // Get kommentare (comments) - by telefon
            $kommentare = [];
            $komm_rows = $er_pdo->query("SELECT telefon, kommentar FROM kommentare ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($komm_rows as $k) {
                if (!isset($kommentare[$k['telefon']])) {
                    $kommentare[$k['telefon']] = [];
                }
                $kommentare[$k['telefon']][] = $k['kommentar'];
            }

            $imported = 0;
            $skipped = 0;
            $errors = [];

            foreach ($entries as $entry) {
                try {
                    $telefon = $entry['telefon'] ?? '';

                    // Check if already imported (by phone + date combo to avoid duplicates)
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM {$prefix}ppv_repairs
                         WHERE store_id = %d AND customer_phone = %s AND DATE(created_at) = %s
                         LIMIT 1",
                        $store_id, $telefon, date('Y-m-d', strtotime($entry['datum'] ?? 'now'))
                    ));

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    // Map status
                    $status = 'new';
                    if (isset($erledigt_list[$telefon])) {
                        $status = 'done';
                    }

                    // Build problem description
                    $problem_parts = [];
                    if (!empty($entry['problem'])) {
                        $problem_parts[] = $entry['problem'];
                    }
                    if (!empty($entry['other'])) {
                        $problem_parts[] = $entry['other'];
                    }
                    $problem = implode("\n", $problem_parts) ?: 'Keine Beschreibung';

                    // Add kommentare as notes
                    $notes = '';
                    if (isset($kommentare[$telefon])) {
                        $notes = implode("\n---\n", $kommentare[$telefon]);
                    }

                    // Parse date
                    $created_at = $entry['created_at'] ?? null;
                    if (empty($created_at) && !empty($entry['datum'])) {
                        // Try to parse German date format (dd.mm.yyyy)
                        $parts = explode('.', $entry['datum']);
                        if (count($parts) === 3) {
                            $created_at = "{$parts[2]}-{$parts[1]}-{$parts[0]} 12:00:00";
                        } else {
                            $created_at = date('Y-m-d H:i:s', strtotime($entry['datum']));
                        }
                    }
                    if (empty($created_at)) {
                        $created_at = current_time('mysql');
                    }

                    // Generate tracking token
                    $tracking_token = bin2hex(random_bytes(16));

                    // Insert into ppv_repairs - NO POINTS!
                    $wpdb->insert("{$prefix}ppv_repairs", [
                        'store_id'            => $store_id,
                        'tracking_token'      => $tracking_token,
                        'customer_name'       => $entry['name'] ?? 'Unbekannt',
                        'customer_email'      => '', // erepairshop didn't have email
                        'customer_phone'      => $telefon,
                        'device_brand'        => $entry['marke'] ?? '',
                        'device_model'        => $entry['modell'] ?? '',
                        'device_imei'         => '',
                        'device_pattern'      => $entry['pin'] ?? '',
                        'problem_description' => $problem,
                        'accessories'         => '[]',
                        'status'              => $status,
                        'notes'               => $notes,
                        'user_id'             => null, // NO user = NO points
                        'points_awarded'      => 0,    // NO points awarded
                        'created_at'          => $created_at,
                        'updated_at'          => $created_at,
                    ]);

                    if ($wpdb->insert_id) {
                        $imported++;
                    } else {
                        $errors[] = "Insert failed for: " . ($entry['name'] ?? 'unknown');
                    }

                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }

            $message = "{$imported} Reparaturen importiert (ohne Punkte)";
            if ($skipped > 0) {
                $message .= ", {$skipped} übersprungen";
            }

            wp_send_json_success([
                'message' => $message,
                'imported' => $imported,
                'skipped' => $skipped,
                'total_entries' => count($entries),
            ]);

        } catch (PDOException $e) {
            wp_send_json_error(['message' => 'Datenbankfehler: ' . $e->getMessage()]);
        }
    }
}
