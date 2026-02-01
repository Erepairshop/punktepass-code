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

        $net_amount = max(0, $subtotal - $discount_value);

        if ($vat_enabled) {
            // MwSt-pflichtig: net + VAT = total
            $vat_amount = round($net_amount * ($vat_rate_pct / 100), 2);
            $total = round($net_amount + $vat_amount, 2);
            $stored_vat_rate = $vat_rate_pct;
        } else {
            // Kleinunternehmer §19 UStG: no VAT
            $vat_amount = 0;
            $total = $net_amount;
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

        $where = "store_id = %d";
        $params = [$store_id];

        if ($date_from) { $where .= " AND DATE(created_at) >= %s"; $params[] = $date_from; }
        if ($date_to) { $where .= " AND DATE(created_at) <= %s"; $params[] = $date_to; }

        $total = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$prefix}ppv_repair_invoices WHERE {$where}", ...$params));

        $p = $params;
        $p[] = $per_page;
        $p[] = $offset;
        $invoices = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$prefix}ppv_repair_invoices WHERE {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d",
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
                if ($new_status === 'paid') $update['paid_at'] = current_time('mysql');
            }
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

            // Recalculate VAT
            $net = max(0, $subtotal - $discount);
            $update['net_amount'] = $net;

            if ($invoice->is_kleinunternehmer) {
                $update['vat_amount'] = 0;
                $update['total'] = $net;
            } else {
                $vat_rate = floatval($invoice->vat_rate ?: 19);
                $vat = round($net * ($vat_rate / 100), 2);
                $update['vat_amount'] = $vat;
                $update['total'] = round($net + $vat, 2);
            }
        }
        if (isset($_POST['notes'])) {
            $update['notes'] = sanitize_textarea_field($_POST['notes']);
        }

        if (!empty($update)) {
            $wpdb->update($wpdb->prefix . 'ppv_repair_invoices', $update, ['id' => $invoice_id]);
        }

        wp_send_json_success(['message' => 'Rechnung aktualisiert']);
    }

    /**
     * Build the HTML for invoice PDF
     */
    private static function build_invoice_html($store, $invoice) {
        $color = esc_attr($store->repair_color ?: '#667eea');
        $company = esc_html($store->repair_company_name ?: $store->name);
        $owner = esc_html($store->repair_owner_name ?: '');
        $addr = esc_html($store->address ?: '');
        $plz_city = esc_html(trim(($store->plz ?: '') . ' ' . ($store->city ?: '')));
        $phone = esc_html($store->phone ?: '');
        $email = esc_html($store->email ?: '');
        $tax = esc_html($store->repair_tax_id ?: '');
        $logo_url = esc_url($store->logo ?: '');

        $inv_nr = esc_html($invoice->invoice_number);
        $inv_date = date('d.m.Y', strtotime($invoice->created_at));
        $cust_name = esc_html($invoice->customer_name);
        $cust_email = esc_html($invoice->customer_email);
        $cust_phone = esc_html($invoice->customer_phone ?: '');
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
        $tax_line = $tax ? '<br>USt-IdNr.: ' . $tax : '';
        $phone_line = $phone ? '<br>Tel: ' . $phone : '';
        $cust_phone_line = $cust_phone ? '<br>Tel: ' . $cust_phone : '';

        $discount_row = '';
        if ($has_discount) {
            $discount_row = '<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;">PunktePass Belohnung: ' . $discount_desc . '</td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;color:#059669;font-weight:600;">-' . $discount_val . ' &euro;</td></tr>';
        }

        $reward_notice = '';
        if ($invoice->punktepass_reward_applied) {
            $reward_notice = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:12px 16px;margin-top:16px;font-size:12px;color:#065f46;"><strong>PunktePass Belohnung eingelöst!</strong><br>' . $discount_desc . ' (' . intval($invoice->points_used) . ' Punkte verwendet)</div>';
        }

        $notes_section = $notes ? '<div style="margin-top:16px;"><strong style="font-size:12px;color:#6b7280;">Anmerkungen:</strong><p style="font-size:12px;color:#374151;margin-top:4px;">' . $notes . '</p></div>' : '';

        // Build line items rows
        $line_items = json_decode($invoice->line_items ?? '', true);
        $items_html = '';
        if (!empty($line_items) && is_array($line_items)) {
            foreach ($line_items as $item) {
                $item_desc = esc_html($item['description'] ?? 'Position');
                $item_amt = number_format(floatval($item['amount'] ?? 0), 2, ',', '.');
                $items_html .= '<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;">' . $item_desc . '</td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $item_amt . ' &euro;</td></tr>';
            }
            if (count($line_items) > 1) {
                $items_html .= '<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;"><strong>Zwischensumme</strong></td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;"><strong>' . $subtotal . ' &euro;</strong></td></tr>';
            }
        } else {
            // Fallback: single line for legacy invoices
            $items_html = '<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;"><strong>Reparatur' . ($device ? ': ' . $device : '') . '</strong><br><span style="font-size:12px;color:#6b7280;">' . $desc . '</span></td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $subtotal . ' &euro;</td></tr>';
        }

        return '<!DOCTYPE html><html lang="de"><head><meta charset="UTF-8"><style>
body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#1f2937;font-size:13px;line-height:1.5;margin:0;padding:0}
.inv-wrap{max-width:700px;margin:0 auto;padding:32px}
.inv-header{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:32px;padding-bottom:20px;border-bottom:3px solid ' . $color . '}
.inv-logo{display:flex;align-items:center;gap:12px}
.inv-company{font-size:20px;font-weight:700;color:#111827}
.inv-meta{text-align:right}
.inv-nr{font-size:22px;font-weight:700;color:' . $color . '}
.inv-date{font-size:13px;color:#6b7280;margin-top:4px}
.inv-parties{display:flex;justify-content:space-between;margin-bottom:28px}
.inv-from,.inv-to{width:48%}
.inv-label{font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:0.5px;color:#6b7280;margin-bottom:6px}
.inv-addr{font-size:13px;color:#374151;line-height:1.6}
.inv-table{width:100%;border-collapse:collapse;margin-bottom:20px}
.inv-table th{background:' . $color . ';color:#fff;padding:10px 12px;text-align:left;font-size:12px;font-weight:600}
.inv-table th:last-child{text-align:right}
.inv-table td{padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:13px}
.inv-total-row td{font-weight:700;font-size:15px;border-top:2px solid #111827;border-bottom:none}
.inv-footer{text-align:center;margin-top:40px;padding-top:16px;border-top:1px solid #e5e7eb;font-size:11px;color:#9ca3af}
</style></head><body>
<div class="inv-wrap">
<div class="inv-header">
<div class="inv-logo">' . $logo_html . '<div><div class="inv-company">' . $company . '</div><div style="font-size:12px;color:#6b7280;">' . $owner . '</div></div></div>
<div class="inv-meta"><div class="inv-nr">' . $inv_nr . '</div><div class="inv-date">Datum: ' . $inv_date . '</div></div>
</div>
<div class="inv-parties">
<div class="inv-from"><div class="inv-label">Von</div><div class="inv-addr"><strong>' . $company . '</strong><br>' . $owner . '<br>' . $addr . '<br>' . $plz_city . $phone_line . '<br>E-Mail: ' . $email . $tax_line . '</div></div>
<div class="inv-to"><div class="inv-label">An</div><div class="inv-addr"><strong>' . $cust_name . '</strong><br>E-Mail: ' . $cust_email . $cust_phone_line . '</div></div>
</div>
<table class="inv-table">
<tr><th>Beschreibung</th><th>Betrag</th></tr>
' . $items_html . '
' . $discount_row . '
<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;"><strong>Nettobetrag</strong></td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $net_amount . ' &euro;</td></tr>
' . ($is_klein
    ? '<tr><td colspan="2" style="padding:10px 12px;border-bottom:1px solid #e5e7eb;font-size:12px;color:#6b7280;">Gem&auml;&szlig; &sect;19 UStG wird keine Umsatzsteuer berechnet (Kleinunternehmer).</td></tr>'
    : '<tr><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;">MwSt ' . number_format($vat_rate, 0) . '%</td><td style="padding:10px 12px;border-bottom:1px solid #e5e7eb;text-align:right;">' . $vat_amount . ' &euro;</td></tr>'
) . '
<tr class="inv-total-row"><td style="padding:10px 12px;border-top:2px solid #111827;border-bottom:none;"><strong>' . ($is_klein ? 'Gesamt' : 'Bruttobetrag') . '</strong></td><td style="padding:10px 12px;border-top:2px solid #111827;border-bottom:none;text-align:right;">' . $total . ' &euro;</td></tr>
</table>
' . $reward_notice . '
' . $notes_section . '
<div class="inv-footer">
' . $company . ' &middot; ' . $addr . ' &middot; ' . $plz_city . '<br>
E-Mail: ' . $email . ($tax ? ' &middot; USt-IdNr.: ' . $tax : '') . '<br><br>
Powered by PunktePass &middot; punktepass.de
</div>
</div>
</body></html>';
    }
}
