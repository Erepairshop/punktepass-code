<?php
/**
 * Bank Transfer Subscription for PunktePass Händler
 *
 * Price: 39€ net + 19% VAT = 46.41€ gross/month
 * Manual payment verification by admin
 */

if (!defined('ABSPATH')) exit;

class PPV_Bank_Transfer {

    // Bank details
    const BANK_NAME = 'Kreis- und Stadtsparkasse Dillingen a.d. Donau';
    const IBAN = 'DE57 7225 1520 0010 3435 55';
    const BIC = 'BYLADEM1DLG';
    const ACCOUNT_HOLDER = 'Erik Borota';

    // Subscription price
    const PRICE_NET = 39.00;
    const VAT_RATE = 0.19;
    const PRICE_GROSS = 46.41; // DE only

    /**
     * Get pricing based on store country (VAT only for DE)
     */
    public static function get_pricing($store_id) {
        global $wpdb;
        $country = $wpdb->get_var($wpdb->prepare(
            "SELECT country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
        $is_domestic = (strtoupper($country ?: 'DE') === 'DE');
        $vat_rate = $is_domestic ? self::VAT_RATE : 0.00;
        $vat = round(self::PRICE_NET * $vat_rate, 2);
        return [
            'price_net'   => self::PRICE_NET,
            'vat_rate'    => $vat_rate,
            'vat'         => $vat,
            'price_gross' => round(self::PRICE_NET + $vat, 2),
            'is_domestic' => $is_domestic,
        ];
    }

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Request bank transfer subscription
        register_rest_route('punktepass/v1', '/bank-transfer/request', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'request_subscription'],
            'permission_callback' => [__CLASS__, 'check_handler_permission'],
        ]);

        // Admin: Confirm payment received
        register_rest_route('punktepass/v1', '/bank-transfer/confirm', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'confirm_payment'],
            'permission_callback' => [__CLASS__, 'check_admin_permission'],
        ]);

        // Get bank details
        register_rest_route('punktepass/v1', '/bank-transfer/details', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_bank_details'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Check if user is logged in handler
     */
    public static function check_handler_permission() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['ppv_vendor_store_id']);
    }

    /**
     * Check if user is admin
     */
    public static function check_admin_permission() {
        return current_user_can('manage_options');
    }

    /**
     * Get bank details for display
     */
    public static function get_bank_details(WP_REST_Request $request) {
        // Allow custom bank details from wp_options
        $bank_name = get_option('ppv_bank_name', self::BANK_NAME);
        $iban = get_option('ppv_bank_iban', self::IBAN);
        $bic = get_option('ppv_bank_bic', self::BIC);
        $account_holder = get_option('ppv_bank_account_holder', self::ACCOUNT_HOLDER);

        // Get store-specific pricing
        if (session_status() === PHP_SESSION_NONE) session_start();
        $store_id = $_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_repair_store_id'] ?? 0;
        $pricing = $store_id ? self::get_pricing($store_id) : [
            'price_net' => self::PRICE_NET, 'price_gross' => self::PRICE_GROSS,
            'vat_rate' => self::VAT_RATE, 'vat' => self::PRICE_GROSS - self::PRICE_NET,
            'is_domestic' => true,
        ];

        return new WP_REST_Response([
            'bank_name' => $bank_name,
            'iban' => $iban,
            'bic' => $bic,
            'account_holder' => $account_holder,
            'price_net' => $pricing['price_net'],
            'price_gross' => $pricing['price_gross'],
            'vat_rate' => $pricing['vat_rate'] * 100,
            'is_domestic' => $pricing['is_domestic'],
        ], 200);
    }

    /**
     * Request bank transfer subscription
     */
    public static function request_subscription(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_vendor_store_id'] ?? 0;
        if (!$store_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }

        global $wpdb;

        // Get store info
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_name, email, country FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['error' => 'Store not found'], 404);
        }

        // Generate unique reference number
        $reference = 'PP-' . str_pad($store_id, 5, '0', STR_PAD_LEFT) . '-' . date('Ym');

        // Get country-based pricing
        $pricing = self::get_pricing($store_id);
        $amount = $pricing['price_gross'];

        // Detect language from store country
        $lang = strtolower($store->country ?? 'de');
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = 'de';
        }

        $vat_texts = [
            'de' => $pricing['is_domestic']
                ? number_format($amount, 2, ',', '.') . " € (inkl. 19% MwSt)"
                : number_format($amount, 2, ',', '.') . " € (netto, ohne MwSt)",
            'en' => $pricing['is_domestic']
                ? number_format($amount, 2, '.', ',') . " € (incl. 19% VAT)"
                : number_format($amount, 2, '.', ',') . " € (net, excl. VAT)",
            'hu' => $pricing['is_domestic']
                ? number_format($amount, 2, ',', '.') . " € (bruttó, 19% ÁFA-val)"
                : number_format($amount, 2, ',', '.') . " € (nettó, ÁFA nélkül)",
            'ro' => $pricing['is_domestic']
                ? number_format($amount, 2, ',', '.') . " € (cu 19% TVA)"
                : number_format($amount, 2, ',', '.') . " € (net, fără TVA)",
        ];
        $vat_text = $vat_texts[$lang] ?? $vat_texts['de'];

        // Update store with pending bank transfer
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'payment_method' => 'bank_transfer',
                'bank_transfer_reference' => $reference,
                'bank_transfer_requested_at' => current_time('mysql'),
                'subscription_status' => 'pending_payment',
            ],
            ['id' => $store_id]
        );

        // Send email notification to admin (always German)
        $admin_email = get_option('admin_email', 'info@punktepass.de');
        $admin_subject = "Neue Banküberweisung angefordert: {$store->store_name}";
        $admin_message = "
Hallo,

{$store->store_name} hat eine Banküberweisung für das PunktePass Händler-Abo angefordert.

Store ID: {$store_id}
E-Mail: {$store->email}
Referenz: {$reference}
Betrag: {$vat_texts['de']}
Land: " . ($pricing['is_domestic'] ? 'Deutschland' : strtoupper($store->country ?? '?')) . "

Bitte überprüfen Sie den Zahlungseingang und bestätigen Sie die Zahlung im Admin-Bereich.

Mit freundlichen Grüßen,
PunktePass System
";

        wp_mail($admin_email, $admin_subject, $admin_message);

        // Send confirmation email to handler (localized)
        $bank_account_holder = get_option('ppv_bank_account_holder', self::ACCOUNT_HOLDER);
        $bank_iban = get_option('ppv_bank_iban', self::IBAN);
        $bank_bic = get_option('ppv_bank_bic', self::BIC);
        $bank_name = get_option('ppv_bank_name', self::BANK_NAME);

        $handler_emails = [
            'de' => [
                'subject' => "Ihre Banküberweisung für PunktePass",
                'body' => "
Hallo {$store->store_name},

vielen Dank für Ihre Bestellung des PunktePass Händler-Abos.

Bitte überweisen Sie den folgenden Betrag auf unser Konto:

Betrag: {$vat_text}
Verwendungszweck: {$reference}

Bankverbindung:
Kontoinhaber: {$bank_account_holder}
IBAN: {$bank_iban}
BIC: {$bank_bic}
Bank: {$bank_name}

Nach Zahlungseingang wird Ihr Abo innerhalb von 1-2 Werktagen aktiviert.

Mit freundlichen Grüßen,
Ihr PunktePass Team
",
            ],
            'en' => [
                'subject' => "Your bank transfer for PunktePass",
                'body' => "
Hello {$store->store_name},

Thank you for ordering the PunktePass Retailer Subscription.

Please transfer the following amount to our account:

Amount: {$vat_text}
Reference: {$reference}

Bank details:
Account holder: {$bank_account_holder}
IBAN: {$bank_iban}
BIC: {$bank_bic}
Bank: {$bank_name}

Your subscription will be activated within 1-2 business days after payment is received.

Best regards,
Your PunktePass Team
",
            ],
            'hu' => [
                'subject' => "Banki átutalás a PunktePass számára",
                'body' => "
Kedves {$store->store_name},

Köszönjük a PunktePass Kereskedői Előfizetés megrendelését.

Kérjük, utalja át az alábbi összeget bankszámlánkra:

Összeg: {$vat_text}
Közlemény: {$reference}

Bankadatok:
Számlatulajdonos: {$bank_account_holder}
IBAN: {$bank_iban}
BIC: {$bank_bic}
Bank: {$bank_name}

A fizetés beérkezése után az előfizetés 1-2 munkanapon belül aktiválódik.

Üdvözlettel,
A PunktePass Csapat
",
            ],
            'ro' => [
                'subject' => "Transfer bancar pentru PunktePass",
                'body' => "
Bună ziua {$store->store_name},

Vă mulțumim pentru comanda abonamentului PunktePass Comerciant.

Vă rugăm să transferați următoarea sumă în contul nostru:

Sumă: {$vat_text}
Referință: {$reference}

Date bancare:
Titular cont: {$bank_account_holder}
IBAN: {$bank_iban}
BIC: {$bank_bic}
Banca: {$bank_name}

Abonamentul va fi activat în 1-2 zile lucrătoare după primirea plății.

Cu stimă,
Echipa PunktePass
",
            ],
        ];

        $handler_email = $handler_emails[$lang] ?? $handler_emails['de'];
        wp_mail($store->email, $handler_email['subject'], $handler_email['body']);

        ppv_log("💳 Bank transfer requested for store {$store_id}, reference: {$reference}, amount: {$amount}");

        return new WP_REST_Response([
            'success' => true,
            'reference' => $reference,
            'amount' => $amount,
            'bank_details' => [
                'account_holder' => get_option('ppv_bank_account_holder', self::ACCOUNT_HOLDER),
                'iban' => get_option('ppv_bank_iban', self::IBAN),
                'bic' => get_option('ppv_bank_bic', self::BIC),
                'bank_name' => get_option('ppv_bank_name', self::BANK_NAME),
            ],
        ], 200);
    }

    /**
     * Admin: Confirm payment received
     */
    public static function confirm_payment(WP_REST_Request $request) {
        $store_id = $request->get_param('store_id');
        $months = $request->get_param('months') ?: 1;

        if (!$store_id) {
            return new WP_REST_Response(['error' => 'Store ID required'], 400);
        }

        global $wpdb;

        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_name, email, country, subscription_expires_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['error' => 'Store not found'], 404);
        }

        // Calculate new expiration date
        $current_expires = $store->subscription_expires_at;
        if ($current_expires && strtotime($current_expires) > time()) {
            // Extend from current expiration
            $new_expires = date('Y-m-d H:i:s', strtotime($current_expires . " +{$months} months"));
        } else {
            // Start fresh from now
            $new_expires = date('Y-m-d H:i:s', strtotime("+{$months} months"));
        }

        // Update store subscription
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'subscription_status' => 'active',
                'subscription_expires_at' => $new_expires,
                'bank_transfer_confirmed_at' => current_time('mysql'),
                'active' => 1,
                'repair_premium' => 1,
                'max_filialen' => 5,
            ],
            ['id' => $store_id]
        );

        // Send confirmation email to handler (localized)
        $lang = strtolower($store->country ?? 'de');
        if (!in_array($lang, ['de', 'hu', 'ro', 'en'])) {
            $lang = 'de';
        }
        $expires_fmt = date('d.m.Y', strtotime($new_expires));

        $confirm_emails = [
            'de' => [
                'subject' => "PunktePass Abo aktiviert",
                'body' => "
Hallo {$store->store_name},

Ihre Zahlung wurde bestätigt. Ihr PunktePass Händler-Abo ist jetzt aktiv.

Abo gültig bis: {$expires_fmt}

Vielen Dank für Ihr Vertrauen!

Mit freundlichen Grüßen,
Ihr PunktePass Team
",
            ],
            'en' => [
                'subject' => "PunktePass subscription activated",
                'body' => "
Hello {$store->store_name},

Your payment has been confirmed. Your PunktePass Retailer Subscription is now active.

Subscription valid until: {$expires_fmt}

Thank you for your trust!

Best regards,
Your PunktePass Team
",
            ],
            'hu' => [
                'subject' => "PunktePass előfizetés aktiválva",
                'body' => "
Kedves {$store->store_name},

Fizetését megerősítettük. PunktePass Kereskedői Előfizetése most aktív.

Előfizetés érvényes: {$expires_fmt}

Köszönjük a bizalmát!

Üdvözlettel,
A PunktePass Csapat
",
            ],
            'ro' => [
                'subject' => "Abonament PunktePass activat",
                'body' => "
Bună ziua {$store->store_name},

Plata dvs. a fost confirmată. Abonamentul PunktePass Comerciant este acum activ.

Abonament valabil până la: {$expires_fmt}

Vă mulțumim pentru încredere!

Cu stimă,
Echipa PunktePass
",
            ],
        ];

        $confirm_email = $confirm_emails[$lang] ?? $confirm_emails['de'];
        wp_mail($store->email, $confirm_email['subject'], $confirm_email['body']);

        ppv_log("✅ Bank transfer confirmed for store {$store_id}, expires: {$new_expires}");

        return new WP_REST_Response([
            'success' => true,
            'message' => 'Subscription activated',
            'expires_at' => $new_expires,
        ], 200);
    }

    /**
     * Get pending bank transfer requests (for admin)
     */
    public static function get_pending_transfers() {
        global $wpdb;

        return $wpdb->get_results("
            SELECT id, store_name, email, bank_transfer_reference, bank_transfer_requested_at
            FROM {$wpdb->prefix}ppv_stores
            WHERE payment_method = 'bank_transfer'
            AND subscription_status = 'pending_payment'
            ORDER BY bank_transfer_requested_at DESC
        ");
    }
}
