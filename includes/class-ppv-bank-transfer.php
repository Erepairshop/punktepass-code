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
    const PRICE_GROSS = 46.41;

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

        return new WP_REST_Response([
            'bank_name' => $bank_name,
            'iban' => $iban,
            'bic' => $bic,
            'account_holder' => $account_holder,
            'price_net' => self::PRICE_NET,
            'price_gross' => self::PRICE_GROSS,
            'vat_rate' => self::VAT_RATE * 100,
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
            "SELECT id, store_name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['error' => 'Store not found'], 404);
        }

        // Generate unique reference number
        $reference = 'PP-' . str_pad($store_id, 5, '0', STR_PAD_LEFT) . '-' . date('Ym');

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

        // Send email notification to admin
        $admin_email = get_option('admin_email', 'info@punktepass.de');
        $subject = "Neue Banküberweisung angefordert: {$store->store_name}";
        $message = "
Hallo,

{$store->store_name} hat eine Banküberweisung für das PunktePass Händler-Abo angefordert.

Store ID: {$store_id}
E-Mail: {$store->email}
Referenz: {$reference}
Betrag: " . number_format(self::PRICE_GROSS, 2, ',', '.') . " €

Bitte überprüfen Sie den Zahlungseingang und bestätigen Sie die Zahlung im Admin-Bereich.

Mit freundlichen Grüßen,
PunktePass System
";

        wp_mail($admin_email, $subject, $message);

        // Send confirmation email to handler
        $handler_subject = "Ihre Banküberweisung für PunktePass";
        $handler_message = "
Hallo {$store->store_name},

vielen Dank für Ihre Bestellung des PunktePass Händler-Abos.

Bitte überweisen Sie den folgenden Betrag auf unser Konto:

Betrag: " . number_format(self::PRICE_GROSS, 2, ',', '.') . " € (inkl. 19% MwSt)
Verwendungszweck: {$reference}

Bankverbindung:
Kontoinhaber: " . get_option('ppv_bank_account_holder', self::ACCOUNT_HOLDER) . "
IBAN: " . get_option('ppv_bank_iban', self::IBAN) . "
BIC: " . get_option('ppv_bank_bic', self::BIC) . "
Bank: " . get_option('ppv_bank_name', self::BANK_NAME) . "

Nach Zahlungseingang wird Ihr Abo innerhalb von 1-2 Werktagen aktiviert.

Mit freundlichen Grüßen,
Ihr PunktePass Team
";

        wp_mail($store->email, $handler_subject, $handler_message);

        ppv_log("💳 Bank transfer requested for store {$store_id}, reference: {$reference}");

        return new WP_REST_Response([
            'success' => true,
            'reference' => $reference,
            'amount' => self::PRICE_GROSS,
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
            "SELECT id, store_name, email, subscription_expires_at FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
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
            ],
            ['id' => $store_id]
        );

        // Send confirmation email to handler
        $subject = "PunktePass Abo aktiviert";
        $message = "
Hallo {$store->store_name},

Ihre Zahlung wurde bestätigt. Ihr PunktePass Händler-Abo ist jetzt aktiv.

Abo gültig bis: " . date('d.m.Y', strtotime($new_expires)) . "

Vielen Dank für Ihr Vertrauen!

Mit freundlichen Grüßen,
Ihr PunktePass Team
";

        wp_mail($store->email, $subject, $message);

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
