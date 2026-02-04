<?php
/**
 * PayPal Subscription Integration for PunktePass Händler
 *
 * Price: 39€ net + 19% VAT = 46.41€ gross/month
 * Monthly, cancellable anytime
 */

if (!defined('ABSPATH')) exit;

class PPV_PayPal_Subscription {

    // PayPal API endpoints
    const SANDBOX_API = 'https://api-m.sandbox.paypal.com';
    const LIVE_API = 'https://api-m.paypal.com';

    // Subscription price
    const PRICE_NET = 39.00;
    const VAT_RATE = 0.19;
    const PRICE_GROSS = 46.41; // 39 * 1.19

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        add_action('init', [__CLASS__, 'handle_webhook'], 5);
    }

    /**
     * Register REST API routes
     */
    public static function register_routes() {
        // Create PayPal subscription
        register_rest_route('punktepass/v1', '/paypal/create-subscription', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'create_subscription'],
            'permission_callback' => '__return_true',
        ]);

        // Cancel subscription
        register_rest_route('punktepass/v1', '/paypal/cancel-subscription', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'cancel_subscription'],
            'permission_callback' => [__CLASS__, 'check_handler_permission'],
        ]);

        // Subscription status
        register_rest_route('punktepass/v1', '/paypal/subscription-status', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'get_subscription_status'],
            'permission_callback' => [__CLASS__, 'check_handler_permission'],
        ]);

        // Activate subscription (after PayPal approval)
        register_rest_route('punktepass/v1', '/paypal/activate', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'activate_subscription'],
            'permission_callback' => '__return_true',
        ]);

        // Generic subscription cancellation (for repair admin)
        register_rest_route('punktepass/v1', '/repair/cancel-subscription', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'cancel_repair_subscription'],
            'permission_callback' => [__CLASS__, 'check_repair_admin_permission'],
        ]);
    }

    /**
     * Check if user is logged in as repair admin
     */
    public static function check_repair_admin_permission() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        return !empty($_SESSION['ppv_repair_store_id']);
    }

    /**
     * Cancel subscription from repair admin
     */
    public static function cancel_repair_subscription(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_repair_store_id'] ?? 0;
        if (!$store_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT payment_method, paypal_subscription_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['error' => 'Store not found'], 404);
        }

        // If PayPal subscription, cancel it via API
        if ($store->payment_method === 'paypal' && !empty($store->paypal_subscription_id)) {
            $access_token = self::get_access_token();
            if ($access_token) {
                wp_remote_post(
                    self::get_api_base() . '/v1/billing/subscriptions/' . $store->paypal_subscription_id . '/cancel',
                    [
                        'headers' => [
                            'Authorization' => 'Bearer ' . $access_token,
                            'Content-Type' => 'application/json',
                        ],
                        'body' => json_encode(['reason' => 'Customer requested cancellation']),
                        'timeout' => 30,
                    ]
                );
            }
        }

        // Update local subscription status
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['subscription_status' => 'canceled'],
            ['id' => $store_id]
        );

        ppv_log("🛑 Subscription cancelled by handler for store {$store_id}");

        return new WP_REST_Response(['success' => true, 'message' => 'Subscription cancelled'], 200);
    }

    /**
     * Activate subscription after PayPal approval
     */
    public static function activate_subscription(WP_REST_Request $request) {
        $subscription_id = $request->get_param('subscription_id');
        $store_id = $request->get_param('store_id');

        if (empty($subscription_id) || empty($store_id)) {
            return new WP_REST_Response(['error' => 'Missing parameters'], 400);
        }

        global $wpdb;

        // Update store with active subscription
        $updated = $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'subscription_status' => 'active',
                'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
                'paypal_subscription_id' => $subscription_id,
                'payment_method' => 'paypal',
                'active' => 1,
                'repair_premium' => 1,
            ],
            ['id' => $store_id]
        );

        if ($updated === false) {
            return new WP_REST_Response(['error' => 'Database error'], 500);
        }

        ppv_log("✅ PayPal subscription activated via JS SDK: {$subscription_id} for store {$store_id}");

        return new WP_REST_Response(['success' => true], 200);
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
     * Get PayPal API base URL
     */
    private static function get_api_base() {
        $sandbox = defined('PAYPAL_SANDBOX') ? PAYPAL_SANDBOX : true;
        return $sandbox ? self::SANDBOX_API : self::LIVE_API;
    }

    /**
     * Get PayPal access token
     */
    private static function get_access_token() {
        $client_id = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : '';
        $client_secret = defined('PAYPAL_CLIENT_SECRET') ? PAYPAL_CLIENT_SECRET : '';

        if (empty($client_id) || empty($client_secret)) {
            ppv_log('❌ PayPal credentials missing');
            return false;
        }

        $response = wp_remote_post(self::get_api_base() . '/v1/oauth2/token', [
            'headers' => [
                'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret),
                'Content-Type' => 'application/x-www-form-urlencoded',
            ],
            'body' => 'grant_type=client_credentials',
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            ppv_log('❌ PayPal token error: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['access_token'] ?? false;
    }

    /**
     * Create PayPal subscription
     */
    public static function create_subscription(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_vendor_store_id'] ?? $request->get_param('store_id');

        if (empty($store_id)) {
            return new WP_REST_Response(['error' => 'Store ID required'], 400);
        }

        $plan_id = defined('PAYPAL_PLAN_ID') ? PAYPAL_PLAN_ID : '';
        if (empty($plan_id)) {
            return new WP_REST_Response(['error' => 'PayPal plan not configured'], 500);
        }

        $access_token = self::get_access_token();
        if (!$access_token) {
            return new WP_REST_Response(['error' => 'PayPal authentication failed'], 500);
        }

        // Get store info for custom_id
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT id, store_name, email FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (!$store) {
            return new WP_REST_Response(['error' => 'Store not found'], 404);
        }

        $return_url = site_url('/handler_dashboard?payment=success&method=paypal');
        $cancel_url = site_url('/handler_dashboard?payment=cancel&method=paypal');

        $subscription_data = [
            'plan_id' => $plan_id,
            'subscriber' => [
                'name' => [
                    'given_name' => $store->store_name,
                ],
                'email_address' => $store->email,
            ],
            'application_context' => [
                'brand_name' => 'PunktePass',
                'locale' => 'de-DE',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'payment_method' => [
                    'payer_selected' => 'PAYPAL',
                    'payee_preferred' => 'IMMEDIATE_PAYMENT_REQUIRED',
                ],
                'return_url' => $return_url,
                'cancel_url' => $cancel_url,
            ],
            'custom_id' => 'store_' . $store_id,
        ];

        $response = wp_remote_post(self::get_api_base() . '/v1/billing/subscriptions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json',
                'PayPal-Request-Id' => 'ppv-sub-' . $store_id . '-' . time(),
            ],
            'body' => json_encode($subscription_data),
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            ppv_log('❌ PayPal subscription error: ' . $response->get_error_message());
            return new WP_REST_Response(['error' => 'PayPal request failed'], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $status_code = wp_remote_retrieve_response_code($response);

        if ($status_code !== 201) {
            ppv_log('❌ PayPal subscription failed: ' . json_encode($body));
            return new WP_REST_Response(['error' => $body['message'] ?? 'Subscription creation failed'], 400);
        }

        // Find approval link
        $approve_url = '';
        foreach ($body['links'] as $link) {
            if ($link['rel'] === 'approve') {
                $approve_url = $link['href'];
                break;
            }
        }

        // Save pending subscription ID
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            [
                'paypal_subscription_id' => $body['id'],
                'payment_method' => 'paypal',
            ],
            ['id' => $store_id]
        );

        ppv_log("✅ PayPal subscription created: {$body['id']} for store {$store_id}");

        return new WP_REST_Response([
            'subscription_id' => $body['id'],
            'approve_url' => $approve_url,
        ], 200);
    }

    /**
     * Handle PayPal webhook
     */
    public static function handle_webhook() {
        if (!isset($_GET['ppv_paypal_webhook'])) {
            return;
        }

        $payload = file_get_contents('php://input');
        $data = json_decode($payload, true);

        if (empty($data['event_type'])) {
            http_response_code(400);
            exit;
        }

        ppv_log("📩 PayPal Webhook: {$data['event_type']}");

        global $wpdb;

        switch ($data['event_type']) {
            case 'BILLING.SUBSCRIPTION.ACTIVATED':
                $subscription_id = $data['resource']['id'] ?? '';
                $custom_id = $data['resource']['custom_id'] ?? '';
                $store_id = str_replace('store_', '', $custom_id);

                if ($store_id) {
                    $wpdb->update(
                        "{$wpdb->prefix}ppv_stores",
                        [
                            'subscription_status' => 'active',
                            'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
                            'paypal_subscription_id' => $subscription_id,
                            'payment_method' => 'paypal',
                            'active' => 1,
                            'repair_premium' => 1,
                        ],
                        ['id' => $store_id]
                    );
                    ppv_log("✅ PayPal subscription activated for store {$store_id}");
                }
                break;

            case 'BILLING.SUBSCRIPTION.CANCELLED':
            case 'BILLING.SUBSCRIPTION.EXPIRED':
                $subscription_id = $data['resource']['id'] ?? '';
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE paypal_subscription_id = %s",
                    $subscription_id
                ));

                if ($store) {
                    $wpdb->update(
                        "{$wpdb->prefix}ppv_stores",
                        ['subscription_status' => 'canceled'],
                        ['id' => $store->id]
                    );
                    ppv_log("⚠️ PayPal subscription cancelled for store {$store->id}");
                }
                break;

            case 'PAYMENT.SALE.COMPLETED':
                // Recurring payment received - extend subscription
                $subscription_id = $data['resource']['billing_agreement_id'] ?? '';
                $store = $wpdb->get_row($wpdb->prepare(
                    "SELECT id FROM {$wpdb->prefix}ppv_stores WHERE paypal_subscription_id = %s",
                    $subscription_id
                ));

                if ($store) {
                    $wpdb->update(
                        "{$wpdb->prefix}ppv_stores",
                        [
                            'subscription_status' => 'active',
                            'subscription_expires_at' => date('Y-m-d H:i:s', strtotime('+1 month')),
                        ],
                        ['id' => $store->id]
                    );
                    ppv_log("💰 PayPal payment received for store {$store->id}");
                }
                break;
        }

        http_response_code(200);
        exit;
    }

    /**
     * Cancel subscription
     */
    public static function cancel_subscription(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_vendor_store_id'] ?? 0;
        if (!$store_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT paypal_subscription_id FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        if (empty($store->paypal_subscription_id)) {
            return new WP_REST_Response(['error' => 'No active PayPal subscription'], 400);
        }

        $access_token = self::get_access_token();
        if (!$access_token) {
            return new WP_REST_Response(['error' => 'PayPal authentication failed'], 500);
        }

        $response = wp_remote_post(
            self::get_api_base() . '/v1/billing/subscriptions/' . $store->paypal_subscription_id . '/cancel',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type' => 'application/json',
                ],
                'body' => json_encode(['reason' => 'Customer requested cancellation']),
                'timeout' => 30,
            ]
        );

        if (is_wp_error($response)) {
            return new WP_REST_Response(['error' => 'Cancellation failed'], 500);
        }

        // Update local status
        $wpdb->update(
            "{$wpdb->prefix}ppv_stores",
            ['subscription_status' => 'canceled'],
            ['id' => $store_id]
        );

        ppv_log("🛑 PayPal subscription cancelled by user for store {$store_id}");

        return new WP_REST_Response(['success' => true, 'message' => 'Subscription cancelled'], 200);
    }

    /**
     * Get subscription status
     */
    public static function get_subscription_status(WP_REST_Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $store_id = $_SESSION['ppv_vendor_store_id'] ?? 0;
        if (!$store_id) {
            return new WP_REST_Response(['error' => 'Not authenticated'], 401);
        }

        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT subscription_status, subscription_expires_at, payment_method, paypal_subscription_id
             FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));

        return new WP_REST_Response([
            'status' => $store->subscription_status,
            'expires_at' => $store->subscription_expires_at,
            'payment_method' => $store->payment_method,
            'has_paypal' => !empty($store->paypal_subscription_id),
        ], 200);
    }
}
