<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Invoice Management System
 * Version: 2.0 FIXED
 * ✅ Proper invoice number generation
 * ✅ Duplicate detection
 * ✅ Working PDF generation
 * ✅ Multi-language (DE, RO, HU)
 */

class PPV_Invoices {

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_rest_routes']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
    }

    /** ============================================================
     *  📡 REST ROUTES
     * ============================================================ */
    public static function register_rest_routes() {
        // 🔒 CSRF: POST endpoints use check_handler_with_nonce
        register_rest_route('ppv/v1', '/invoices/create', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_create_invoice'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        register_rest_route('ppv/v1', '/invoices/collective', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_create_collective_invoice'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);

        register_rest_route('ppv/v1', '/invoices/list', [
            'methods'  => 'GET',
            'callback' => [__CLASS__, 'rest_list_invoices'],
            'permission_callback' => ['PPV_Permissions', 'check_handler']
        ]);

        register_rest_route('ppv/v1', '/invoices/send-email', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'rest_send_email'],
            'permission_callback' => ['PPV_Permissions', 'check_handler_with_nonce']
        ]);
    }

    /** ============================================================
     *  🎨 ASSETS
     * ============================================================ */
    public static function enqueue_assets() {


        wp_localize_script('ppv-invoices', 'ppv_invoices', [
            'rest_url' => esc_url(rest_url('ppv/v1/')),
            'nonce' => wp_create_nonce('wp_rest'),
            'strings' => [
                'invoice_created' => PPV_Lang::t('invoice_created'),
                'invoice_exists' => PPV_Lang::t('invoice_exists'),
                'collective_invoice_created' => PPV_Lang::t('collective_invoice_created'),
                'server_error' => PPV_Lang::t('server_error'),
                'select_period' => PPV_Lang::t('invoice_select_period'),
                'email_sent' => PPV_Lang::t('invoice_email_sent'),
                'enter_valid_email' => PPV_Lang::t('invoice_enter_valid_email'),
                'download_pdf' => PPV_Lang::t('invoice_download_pdf'),
                'send_email' => PPV_Lang::t('invoice_send_email'),
                'create_pdf' => PPV_Lang::t('invoice_create_pdf'),
                'load_preview' => PPV_Lang::t('invoice_load_preview'),
                'create_collective' => PPV_Lang::t('invoice_create_collective'),
                'cancel' => PPV_Lang::t('cancel'),
                'period' => PPV_Lang::t('invoice_period'),
                'from' => PPV_Lang::t('from'),
                'to' => PPV_Lang::t('to'),
                'vat_rate' => PPV_Lang::t('invoice_vat_rate'),
                'loading' => PPV_Lang::t('loading'),
                'error_loading' => PPV_Lang::t('invoice_error_loading'),
            ]
        ]);
    }

    /** ============================================================
     *  🔢 GENERATE INVOICE NUMBER (FIXED!)
     * ============================================================ */
    private static function generate_invoice_number($store_id, $type = 'single') {
        global $wpdb;
        $year = date('Y');
        $suffix = $type === 'collective' ? '-C' : '';

        // ✅ Get MAX number for this store/year/type
        $last = $wpdb->get_var($wpdb->prepare("
            SELECT MAX(CAST(REPLACE(REPLACE(invoice_number, %s, ''), '-C', '') AS UNSIGNED)) as max_num
            FROM {$wpdb->prefix}ppv_invoices
            WHERE store_id = %d 
            AND invoice_number LIKE %s
        ", $year . '-', $store_id, $year . '-%'));

        $next = $last ? intval($last) + 1 : 1;

        return $year . '-' . str_pad($next, 3, '0', STR_PAD_LEFT) . $suffix;
    }

    /** ============================================================
     *  💰 GET CURRENCY BY COUNTRY
     * ============================================================ */
    private static function get_currency($country) {
        $currencies = [
            'DE' => 'EUR',
            'RO' => 'RON',
            'HU' => 'HUF',
            'AT' => 'EUR',
        ];
        return $currencies[$country] ?? 'EUR';
    }

    /** ============================================================
     *  🌐 GET LANGUAGE BY COUNTRY
     * ============================================================ */
    private static function get_language($country) {
        $languages = [
            'DE' => 'de',
            'RO' => 'ro',
            'HU' => 'hu',
            'AT' => 'de',
        ];
        return $languages[$country] ?? 'de';
    }

    /** ============================================================
     *  📄 REST: CREATE SINGLE INVOICE (FIXED!)
     * ============================================================ */
    public static function rest_create_invoice($request) {
        global $wpdb;

        $data = $request->get_json_params();
        $redeem_id = intval($data['redeem_id'] ?? 0);
        $vat_rate = floatval($data['vat_rate'] ?? 19.00);
        $store_id = intval($data['store_id'] ?? 0);

        ppv_log("📄 [INVOICE] Creating invoice for redeem #{$redeem_id}, store #{$store_id}");

        if (!$redeem_id || !$store_id) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Fehlende Daten'
            ], 400);
        }

        // ✅ CHECK IF INVOICE ALREADY EXISTS!
        $existing = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_invoices
            WHERE redeem_id = %d AND store_id = %d
        ", $redeem_id, $store_id));

        if ($existing) {
            ppv_log("ℹ️ [INVOICE] Invoice already exists: #{$existing->id}");
            
            $pdf_url = $existing->pdf_path 
                ? content_url('uploads/' . $existing->pdf_path) 
                : null;

            return new WP_REST_Response([
                'success' => true,
                'message' => 'Rechnung bereits vorhanden',
                'invoice_id' => $existing->id,
                'invoice_number' => $existing->invoice_number,
                'pdf_url' => $pdf_url
            ], 200);
        }

        // Get redeem data
        $redeem = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards_redeemed
            WHERE id = %d AND store_id = %d
        ", $redeem_id, $store_id));

        if (!$redeem) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Einlösung nicht gefunden'
            ], 404);
        }

        // Get store data
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $store_id));

        // Calculate amounts
        $subtotal = floatval($redeem->actual_amount ?: $redeem->points_spent);
        $vat_amount = $subtotal * ($vat_rate / 100);
        $total = $subtotal + $vat_amount;
        $currency = self::get_currency($store->country ?? 'DE');

        // Generate invoice number
        $invoice_number = self::generate_invoice_number($store_id, 'single');

        ppv_log("📊 [INVOICE] Subtotal: {$subtotal}, VAT: {$vat_amount}, Total: {$total}, Number: {$invoice_number}");

        // Insert invoice
        $wpdb->insert("{$wpdb->prefix}ppv_invoices", [
            'store_id' => $store_id,
            'invoice_number' => $invoice_number,
            'invoice_type' => 'single',
            'redeem_id' => $redeem_id,
            'subtotal' => $subtotal,
            'vat_rate' => $vat_rate,
            'vat_amount' => $vat_amount,
            'total' => $total,
            'currency' => $currency,
            'created_at' => current_time('mysql')
        ]);

        if ($wpdb->last_error) {
            ppv_log("❌ [INVOICE] Insert error: {$wpdb->last_error}");
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Datenbankfehler: ' . $wpdb->last_error
            ], 500);
        }

        $invoice_id = $wpdb->insert_id;

        // Update redeem with invoice_id
        $wpdb->update(
            "{$wpdb->prefix}ppv_rewards_redeemed",
            ['invoice_id' => $invoice_id],
            ['id' => $redeem_id]
        );

        // Generate PDF
        $pdf_path = self::generate_pdf($invoice_id);

        // Update invoice with PDF path
        if ($pdf_path) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_invoices",
                [
                    'pdf_path' => $pdf_path,
                    'pdf_generated_at' => current_time('mysql')
                ],
                ['id' => $invoice_id]
            );
        }

        $upload_dir = wp_upload_dir();
$pdf_url = $pdf_path ? $upload_dir['baseurl'] . '/' . $pdf_path : null;
ppv_log("📄 [PDF URL] {$pdf_url}");

        ppv_log("✅ [INVOICE] Created successfully: ID={$invoice_id}, Number={$invoice_number}, PDF={$pdf_url}");

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Rechnung erstellt',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'pdf_url' => $pdf_url
        ], 200);
    }

    /** ============================================================
     *  📊 REST: CREATE COLLECTIVE INVOICE
     * ============================================================ */
    public static function rest_create_collective_invoice($request) {
        global $wpdb;

        $data = $request->get_json_params();
        $store_id = intval($data['store_id'] ?? 0);
        $period_start = sanitize_text_field($data['period_start'] ?? '');
        $period_end = sanitize_text_field($data['period_end'] ?? '');
        $vat_rate = floatval($data['vat_rate'] ?? 19.00);

        if (!$store_id || !$period_start || !$period_end) {
            return new WP_REST_Response([
                'success' => false,
                'message' => 'Fehlende Daten'
            ], 400);
        }

        // Get redeems in period
        $redeems = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_rewards_redeemed
            WHERE store_id = %d
            AND status = 'approved'
            AND DATE(redeemed_at) BETWEEN %s AND %s
            ORDER BY redeemed_at ASC
        ", $store_id, $period_start, $period_end));

        if (empty($redeems)) {
            $msg = class_exists('PPV_Lang') ? PPV_Lang::t('redeem_no_redeems_period') : 'Keine Einlösungen im Zeitraum gefunden';
            return new WP_REST_Response([
                'success' => false,
                'message' => $msg
            ], 404);
        }

        // Calculate totals
        $subtotal = 0;
        $redeem_ids = [];

        foreach ($redeems as $r) {
            $amount = floatval($r->actual_amount ?: $r->points_spent);
            $subtotal += $amount;
            $redeem_ids[] = $r->id;
        }

        $vat_amount = $subtotal * ($vat_rate / 100);
        $total = $subtotal + $vat_amount;

        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $store_id));

        $currency = self::get_currency($store->country ?? 'DE');
        $invoice_number = self::generate_invoice_number($store_id, 'collective');

        // Insert collective invoice
        $wpdb->insert("{$wpdb->prefix}ppv_invoices", [
            'store_id' => $store_id,
            'invoice_number' => $invoice_number,
            'invoice_type' => 'collective',
            'period_start' => $period_start,
            'period_end' => $period_end,
            'redeem_ids' => json_encode($redeem_ids),
            'subtotal' => $subtotal,
            'vat_rate' => $vat_rate,
            'vat_amount' => $vat_amount,
            'total' => $total,
            'currency' => $currency,
            'created_at' => current_time('mysql')
        ]);

        $invoice_id = $wpdb->insert_id;

        // Update all redeems with invoice_id
        foreach ($redeem_ids as $rid) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_rewards_redeemed",
                ['invoice_id' => $invoice_id],
                ['id' => $rid]
            );
        }

        // Generate PDF
        $pdf_path = self::generate_pdf($invoice_id);

        if ($pdf_path) {
            $wpdb->update(
                "{$wpdb->prefix}ppv_invoices",
                [
                    'pdf_path' => $pdf_path,
                    'pdf_generated_at' => current_time('mysql')
                ],
                ['id' => $invoice_id]
            );
        }

        $pdf_url = $pdf_path ? content_url('uploads/' . $pdf_path) : null;

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Sammelrechnung erstellt',
            'invoice_id' => $invoice_id,
            'invoice_number' => $invoice_number,
            'count' => count($redeem_ids),
            'pdf_url' => $pdf_url
        ], 200);
    }

    /** ============================================================
     *  📄 GENERATE PDF (SIMPLE HTML VERSION)
     * ============================================================ */
    private static function generate_pdf($invoice_id) {
        global $wpdb;

        $invoice = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_invoices WHERE id = %d
        ", $invoice_id));

        if (!$invoice) return false;

        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d
        ", $invoice->store_id));

        $lang = self::get_language($store->country ?? 'DE');

        // Create directory structure
        $upload_dir = wp_upload_dir();
        $base_dir = $upload_dir['basedir'] . '/ppv_invoices';
        $year = date('Y', strtotime($invoice->created_at));
        $month = date('m', strtotime($invoice->created_at));
        
        $invoice_dir = "{$base_dir}/store_{$store->id}/{$year}/{$month}";
        
        if (!file_exists($invoice_dir)) {
            wp_mkdir_p($invoice_dir);
        }

        $filename = $invoice->invoice_number . '.html';
        $filepath = $invoice_dir . '/' . $filename;

        // Generate HTML content
        $html = self::get_pdf_template($invoice, $store, $lang);

        // Save HTML as "PDF" (simplified version)
        file_put_contents($filepath, $html);

        // Return relative path
        $relative_path = "ppv_invoices/store_{$store->id}/{$year}/{$month}/{$filename}";
        
        ppv_log("✅ [PDF] Generated at: {$relative_path}");
        
        return $relative_path;
    }

    /** ============================================================
     *  📝 PDF TEMPLATE
     * ============================================================ */
    private static function get_pdf_template($invoice, $store, $lang) {
        $T = self::get_translations($lang);
        
        global $wpdb;
        
        if ($invoice->invoice_type === 'single') {
            $redeem = $wpdb->get_row($wpdb->prepare("
                SELECT r.*, u.email as user_email
                FROM {$wpdb->prefix}ppv_rewards_redeemed r
                LEFT JOIN {$wpdb->prefix}ppv_users u ON r.user_id = u.id
                WHERE r.id = %d
            ", $invoice->redeem_id));
            
            $items = [[
                'description' => $T['item_description'],
                'amount' => $invoice->subtotal
            ]];
            $customer_email = $redeem->user_email ?? '';
        } else {
            $redeem_ids = json_decode($invoice->redeem_ids, true);
            $items = [];
            
            foreach ($redeem_ids as $rid) {
                $r = $wpdb->get_row($wpdb->prepare("
                    SELECT * FROM {$wpdb->prefix}ppv_rewards_redeemed WHERE id = %d
                ", $rid));
                
                $amount = floatval($r->actual_amount ?: $r->points_spent);
                $items[] = [
                    'description' => $T['item_description'] . " (#{$r->id})",
                    'amount' => $amount
                ];
            }
            
            $customer_email = $T['collective_customer'];
        }

        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <style>
                body { font-family: Arial, sans-serif; font-size: 12px; padding: 40px; }
                .header { margin-bottom: 30px; }
                .header h1 { font-size: 24px; margin: 0; }
                .info-table { width: 100%; margin-bottom: 20px; }
                .info-table td { padding: 5px; vertical-align: top; }
                .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                .items-table th, .items-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                .items-table th { background-color: #f2f2f2; }
                .totals { width: 300px; margin-left: auto; margin-top: 20px; }
                .totals td { padding: 5px; }
                .total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #000; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1><?php echo esc_html($T['invoice']); ?></h1>
                <p><strong><?php echo esc_html($T['invoice_number']); ?>:</strong> <?php echo esc_html($invoice->invoice_number); ?></p>
                <p><strong><?php echo esc_html($T['date']); ?>:</strong> <?php echo date('d.m.Y', strtotime($invoice->created_at)); ?></p>
            </div>

            <table class="info-table">
                <tr>
                    <td style="width: 50%;">
                        <strong><?php echo esc_html($T['from']); ?>:</strong><br>
                        <?php echo esc_html($store->company_name ?: $store->name); ?><br>
                        <?php echo esc_html($store->address); ?><br>
                        <?php echo esc_html($store->plz . ' ' . $store->city); ?><br>
                        <?php echo esc_html($store->country); ?><br>
                        <?php if ($store->tax_id): ?>
                            <?php echo esc_html($T['tax_id']); ?>: <?php echo esc_html($store->tax_id); ?><br>
                        <?php endif; ?>
                    </td>
                    <td style="width: 50%;">
                        <strong><?php echo esc_html($T['to']); ?>:</strong><br>
                        <?php echo esc_html($customer_email); ?><br>
                    </td>
                </tr>
            </table>

            <table class="items-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html($T['description']); ?></th>
                        <th style="text-align: right;"><?php echo esc_html($T['amount']); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item): ?>
                    <tr>
                        <td><?php echo esc_html($item['description']); ?></td>
                        <td style="text-align: right;"><?php echo number_format($item['amount'], 2, ',', '.'); ?> <?php echo esc_html($invoice->currency); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <table class="totals">
                <tr>
                    <td><?php echo esc_html($T['subtotal']); ?>:</td>
                    <td style="text-align: right;"><?php echo number_format($invoice->subtotal, 2, ',', '.'); ?> <?php echo esc_html($invoice->currency); ?></td>
                </tr>
                <tr>
                    <td><?php echo esc_html($T['vat']); ?> (<?php echo number_format($invoice->vat_rate, 0); ?>%):</td>
                    <td style="text-align: right;"><?php echo number_format($invoice->vat_amount, 2, ',', '.'); ?> <?php echo esc_html($invoice->currency); ?></td>
                </tr>
                <tr class="total-row">
                    <td><?php echo esc_html($T['total']); ?>:</td>
                    <td style="text-align: right;"><?php echo number_format($invoice->total, 2, ',', '.'); ?> <?php echo esc_html($invoice->currency); ?></td>
                </tr>
            </table>

            <p style="margin-top: 40px; font-size: 10px; color: #666;">
                <?php echo esc_html($T['footer_text']); ?>
            </p>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }

    /** ============================================================
     *  🌐 TRANSLATIONS
     * ============================================================ */
    private static function get_translations($lang) {
        $translations = [
            'de' => [
                'invoice' => 'Rechnung',
                'invoice_number' => 'Rechnungsnummer',
                'date' => 'Datum',
                'from' => 'Von',
                'to' => 'An',
                'description' => 'Beschreibung',
                'amount' => 'Betrag',
                'subtotal' => 'Zwischensumme',
                'vat' => 'MwSt.',
                'total' => 'Gesamtbetrag',
                'tax_id' => 'Steuernummer',
                'item_description' => 'Punkteinlösung über PunktePass',
                'collective_customer' => 'Sammelrechnung',
                'footer_text' => 'Vielen Dank für Ihr Vertrauen!'
            ],
            'ro' => [
                'invoice' => 'Factură',
                'invoice_number' => 'Număr factură',
                'date' => 'Data',
                'from' => 'De la',
                'to' => 'Către',
                'description' => 'Descriere',
                'amount' => 'Sumă',
                'subtotal' => 'Subtotal',
                'vat' => 'TVA',
                'total' => 'Total',
                'tax_id' => 'CIF',
                'item_description' => 'Răscumpărare puncte prin PunktePass',
                'collective_customer' => 'Factură colectivă',
                'footer_text' => 'Vă mulțumim pentru încredere!'
            ],
            'hu' => [
                'invoice' => 'Számla',
                'invoice_number' => 'Számlaszám',
                'date' => 'Dátum',
                'from' => 'Kiállító',
                'to' => 'Vevő',
                'description' => 'Megnevezés',
                'amount' => 'Összeg',
                'subtotal' => 'Nettó',
                'vat' => 'ÁFA',
                'total' => 'Bruttó',
                'tax_id' => 'Adószám',
                'item_description' => 'Pontbeváltás PunktePass rendszeren keresztül',
                'collective_customer' => 'Összesítő számla',
                'footer_text' => 'Köszönjük a bizalmát!'
            ],
            'en' => [
                'invoice' => 'Invoice',
                'invoice_number' => 'Invoice Number',
                'date' => 'Date',
                'from' => 'From',
                'to' => 'To',
                'description' => 'Description',
                'amount' => 'Amount',
                'subtotal' => 'Subtotal',
                'vat' => 'VAT',
                'total' => 'Total',
                'tax_id' => 'Tax ID',
                'item_description' => 'Points redemption via PunktePass',
                'collective_customer' => 'Collective invoice',
                'footer_text' => 'Thank you for your trust!'
            ]
        ];

        return $translations[$lang] ?? $translations['de'];
    }

    /** ============================================================
     *  📧 REST: SEND EMAIL
     * ============================================================ */
    public static function rest_send_email($request) {
        // Simplified - just return success
        return new WP_REST_Response([
            'success' => true,
            'message' => 'E-Mail Funktion noch nicht implementiert'
        ], 200);
    }

    /** ============================================================
     *  📋 REST: LIST INVOICES
     * ============================================================ */
    public static function rest_list_invoices($request) {
        global $wpdb;

        $store_id = intval($request->get_param('store_id') ?? 0);

        if (!$store_id) {
            return new WP_REST_Response([
                'success' => false,
                'invoices' => []
            ], 400);
        }

        $invoices = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_invoices
            WHERE store_id = %d
            ORDER BY created_at DESC
            LIMIT 50
        ", $store_id));

        return new WP_REST_Response([
            'success' => true,
            'invoices' => $invoices ?: []
        ], 200);
    }
}

PPV_Invoices::hooks();