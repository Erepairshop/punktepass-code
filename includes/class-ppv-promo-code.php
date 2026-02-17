<?php
/**
 * PunktePass Promo Code System
 *
 * Supports promotional codes for free subscription periods.
 * Currently: PRODUCTHUNT = 3 months free.
 */

if (!defined('ABSPATH')) exit;

class PPV_Promo_Code {

    /**
     * Active promo codes: code => [months, max_uses (0=unlimited), description]
     */
    const CODES = [
        'PRODUCTHUNT' => ['months' => 3, 'max_uses' => 0, 'desc' => 'Product Hunt Launch - 3 Monate gratis'],
    ];

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    public static function register_routes() {
        // Validate promo code (check without redeeming)
        register_rest_route('punktepass/v1', '/promo/validate', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'validate_code'],
            'permission_callback' => '__return_true',
        ]);

        // Redeem promo code (activate subscription)
        register_rest_route('punktepass/v1', '/promo/redeem', [
            'methods'  => 'POST',
            'callback' => [__CLASS__, 'redeem_code'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Validate a promo code (without redeeming)
     */
    public static function validate_code(WP_REST_Request $request) {
        $code = strtoupper(trim($request->get_param('code') ?? ''));

        if (empty($code)) {
            return new WP_REST_Response(['valid' => false, 'error' => 'Kein Code eingegeben'], 400);
        }

        if (!isset(self::CODES[$code])) {
            return new WP_REST_Response(['valid' => false, 'error' => 'Ungültiger Promo-Code'], 400);
        }

        $promo = self::CODES[$code];

        // Check max uses
        if ($promo['max_uses'] > 0) {
            $used = (int) get_option('ppv_promo_used_' . $code, 0);
            if ($used >= $promo['max_uses']) {
                return new WP_REST_Response(['valid' => false, 'error' => 'Dieser Code wurde bereits eingelöst'], 400);
            }
        }

        return new WP_REST_Response([
            'valid'  => true,
            'months' => $promo['months'],
            'desc'   => $promo['desc'],
        ], 200);
    }

    /**
     * Redeem a promo code - activate subscription for free
     */
    public static function redeem_code(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_vendor_store_id'] ?? $_SESSION['ppv_repair_store_id'] ?? 0;
        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'error' => 'Nicht angemeldet'], 401);
        }

        $code = strtoupper(trim($request->get_param('code') ?? ''));

        if (empty($code) || !isset(self::CODES[$code])) {
            return new WP_REST_Response(['success' => false, 'error' => 'Ungültiger Promo-Code'], 400);
        }

        $promo = self::CODES[$code];

        // Check max uses
        if ($promo['max_uses'] > 0) {
            $used = (int) get_option('ppv_promo_used_' . $code, 0);
            if ($used >= $promo['max_uses']) {
                return new WP_REST_Response(['success' => false, 'error' => 'Dieser Code wurde bereits eingelöst'], 400);
            }
        }

        global $wpdb;
        $table = $wpdb->prefix . 'ppv_stores';

        // Check store exists
        $store = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $store_id));
        if (!$store) {
            return new WP_REST_Response(['success' => false, 'error' => 'Shop nicht gefunden'], 404);
        }

        // Check if this store already used a promo code
        if ($store->payment_method === 'promo_code') {
            return new WP_REST_Response(['success' => false, 'error' => 'Sie haben bereits einen Promo-Code eingelöst'], 400);
        }

        // Activate subscription
        $expires = date('Y-m-d H:i:s', strtotime('+' . $promo['months'] . ' months'));

        $updated = $wpdb->update(
            $table,
            [
                'subscription_status'    => 'active',
                'subscription_expires_at' => $expires,
                'payment_method'         => 'promo_code',
                'active'                 => 1,
                'repair_premium'         => 1,
                'max_filialen'           => 5,
            ],
            ['id' => $store_id]
        );

        if ($updated === false) {
            return new WP_REST_Response(['success' => false, 'error' => 'Datenbankfehler'], 500);
        }

        // Increment usage counter
        $used = (int) get_option('ppv_promo_used_' . $code, 0);
        update_option('ppv_promo_used_' . $code, $used + 1);

        // Log redemption
        ppv_log("🎟️ Promo code {$code} redeemed by store {$store_id} - {$promo['months']} months free until {$expires}");

        return new WP_REST_Response([
            'success' => true,
            'months'  => $promo['months'],
            'expires' => $expires,
        ], 200);
    }
}
