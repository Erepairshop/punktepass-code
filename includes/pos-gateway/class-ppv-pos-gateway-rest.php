<?php
/**
 * POS Gateway REST API Endpoints
 *
 * Provides REST API endpoints for external POS systems to integrate
 * with PunktePass loyalty program.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPV_POS_Gateway_REST {

    /**
     * API namespace
     */
    const NAMESPACE = 'punktepass/v1';

    /**
     * Route prefix
     */
    const PREFIX = '/pos-gateway';

    /**
     * Register hooks
     */
    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
    }

    /**
     * Register REST routes
     */
    public static function register_routes() {
        $ns = self::NAMESPACE;
        $prefix = self::PREFIX;

        // ================================================
        // Public POS Endpoints (called by external POS systems)
        // ================================================

        // Customer search by name
        register_rest_route($ns, $prefix . '/customer/search', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_customer_search'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Customer lookup by QR code
        register_rest_route($ns, $prefix . '/customer/qr-lookup', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_qr_lookup'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Get customer balance
        register_rest_route($ns, $prefix . '/customer/(?P<id>\d+)/balance', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_balance'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Get available rewards for customer
        register_rest_route($ns, $prefix . '/customer/(?P<id>\d+)/rewards', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_rewards'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Apply reward to transaction
        register_rest_route($ns, $prefix . '/reward/apply', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_apply_reward'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Transaction complete webhook
        register_rest_route($ns, $prefix . '/transaction/complete', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_transaction_complete'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // Transaction cancel
        register_rest_route($ns, $prefix . '/transaction/cancel', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_transaction_cancel'],
            'permission_callback' => [__CLASS__, 'check_api_key'],
        ]);

        // ================================================
        // Admin Endpoints (for Handler Dashboard)
        // ================================================

        // List registered gateways
        register_rest_route($ns, $prefix . '/admin/gateways', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_list_gateways'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        // Register new gateway
        register_rest_route($ns, $prefix . '/admin/gateways', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_create_gateway'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        // Update gateway
        register_rest_route($ns, $prefix . '/admin/gateways/(?P<id>\d+)', [
            'methods' => 'PUT',
            'callback' => [__CLASS__, 'handle_update_gateway'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        // Delete gateway
        register_rest_route($ns, $prefix . '/admin/gateways/(?P<id>\d+)', [
            'methods' => 'DELETE',
            'callback' => [__CLASS__, 'handle_delete_gateway'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        // Regenerate API key
        register_rest_route($ns, $prefix . '/admin/gateways/(?P<id>\d+)/regenerate-key', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'handle_regenerate_key'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        // Get gateway transactions/logs
        register_rest_route($ns, $prefix . '/admin/gateways/(?P<id>\d+)/transactions', [
            'methods' => 'GET',
            'callback' => [__CLASS__, 'handle_get_transactions'],
            'permission_callback' => ['PPV_Permissions', 'check_handler'],
        ]);

        ppv_log("✅ [PPV_POS_Gateway_REST] Routes registered");
    }

    // ========================================
    // Permission Callback
    // ========================================

    /**
     * Check API key permission
     */
    public static function check_api_key(WP_REST_Request $request): bool {
        $api_key = $request->get_header('X-POS-API-Key');

        if (empty($api_key)) {
            // Also check query param for simple integrations
            $api_key = $request->get_param('api_key');
        }

        if (empty($api_key)) {
            return false;
        }

        // Validate API key
        $gateway = PPV_POS_Gateway_DB::get_gateway_by_api_key($api_key);

        if (!$gateway || !$gateway['active']) {
            return false;
        }

        // Store gateway info in request for later use
        $request->set_param('_gateway', $gateway);

        // Check rate limit
        $rate_check = PPV_Permissions::check_rate_limit('pos_gateway_' . $gateway['id'], 60, 60);
        if (is_wp_error($rate_check)) {
            return false;
        }
        PPV_Permissions::increment_rate_limit('pos_gateway_' . $gateway['id'], 60);

        // Update last activity
        PPV_POS_Gateway_DB::update_last_activity($gateway['id']);

        return true;
    }

    // ========================================
    // POS API Handlers
    // ========================================

    /**
     * Handle customer search
     */
    public static function handle_customer_search(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $params = $request->get_json_params();
        $query = sanitize_text_field($params['query'] ?? '');
        $limit = (int) ($params['limit'] ?? 10);

        if (empty($query)) {
            return self::error_response('missing_query', 'Search query is required', 400);
        }

        $results = $adapter->search_customer($query, (int) $gateway['store_id'], $limit);

        return self::success_response([
            'customers' => $adapter->format_response($results, 'customer_search')
        ]);
    }

    /**
     * Handle QR code lookup
     */
    public static function handle_qr_lookup(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $params = $request->get_json_params();
        $qr_code = sanitize_text_field($params['qr_code'] ?? '');

        if (empty($qr_code)) {
            return self::error_response('missing_qr_code', 'QR code is required', 400);
        }

        // Validate request signature if adapter requires it
        if (!$adapter->validate_request($request, $gateway)) {
            return self::error_response('invalid_signature', 'Request validation failed', 403);
        }

        $customer = $adapter->lookup_by_qr($qr_code, (int) $gateway['store_id']);

        if (!$customer) {
            return self::error_response('customer_not_found', 'No customer found for this QR code', 404);
        }

        return self::success_response([
            'customer' => $adapter->format_response($customer, 'qr_lookup')
        ], self::get_welcome_message($customer, $request));
    }

    /**
     * Handle get balance
     */
    public static function handle_get_balance(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $user_id = (int) $request->get_param('id');

        $balance = $adapter->get_balance($user_id, (int) $gateway['store_id']);

        return self::success_response([
            'balance' => $adapter->format_response($balance, 'balance')
        ]);
    }

    /**
     * Handle get rewards
     */
    public static function handle_get_rewards(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $user_id = (int) $request->get_param('id');
        $cart_total = $request->get_param('cart_total');

        $rewards = $adapter->get_available_rewards(
            $user_id,
            (int) $gateway['store_id'],
            $cart_total !== null ? (float) $cart_total : null
        );

        return self::success_response([
            'rewards' => $adapter->format_response($rewards, 'rewards')
        ]);
    }

    /**
     * Handle apply reward
     */
    public static function handle_apply_reward(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $params = $request->get_json_params();
        $user_id = (int) ($params['customer_id'] ?? 0);
        $reward_id = (int) ($params['reward_id'] ?? 0);
        $cart_total = (float) ($params['cart_total'] ?? 0);

        if (!$user_id || !$reward_id || $cart_total <= 0) {
            return self::error_response('missing_params', 'customer_id, reward_id and cart_total are required', 400);
        }

        $result = $adapter->apply_reward($user_id, $reward_id, $cart_total, (int) $gateway['store_id']);

        if (!$result['success']) {
            return self::error_response($result['error'], $result['message'], 400);
        }

        return self::success_response($adapter->format_response($result, 'apply_reward'));
    }

    /**
     * Handle transaction complete webhook
     */
    public static function handle_transaction_complete(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        // Validate webhook signature
        if (!$adapter->validate_request($request, $gateway)) {
            return self::error_response('invalid_signature', 'Webhook validation failed', 403);
        }

        $params = $request->get_json_params();

        // Parse request through adapter (handles POS-specific formats)
        $transaction_data = $adapter->parse_request($params, 'transaction_complete');
        $transaction_data['store_id'] = (int) $gateway['store_id'];

        $result = $adapter->process_transaction($transaction_data, (int) $gateway['id']);

        if (!$result['success']) {
            return self::error_response($result['error'], $result['message'], 500);
        }

        return self::success_response(
            $adapter->format_response($result, 'transaction_complete'),
            self::get_thank_you_message($result, $request)
        );
    }

    /**
     * Handle transaction cancel
     */
    public static function handle_transaction_cancel(WP_REST_Request $request): WP_REST_Response {
        $gateway = $request->get_param('_gateway');
        $adapter = PPV_POS_Gateway::get_adapter($gateway['gateway_type']);

        $params = $request->get_json_params();
        $external_id = sanitize_text_field($params['transaction_id'] ?? '');

        if (empty($external_id)) {
            return self::error_response('missing_transaction_id', 'transaction_id is required', 400);
        }

        $result = $adapter->cancel_transaction($external_id, (int) $gateway['id']);

        if (!$result['success']) {
            return self::error_response($result['error'], $result['message'], 400);
        }

        return self::success_response($adapter->format_response($result, 'transaction_cancel'));
    }

    // ========================================
    // Admin Handlers
    // ========================================

    /**
     * List gateways for store
     */
    public static function handle_list_gateways(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return self::error_response('no_store', 'Store not found', 403);
        }

        $gateways = PPV_POS_Gateway_DB::get_gateways_by_store($store_id);

        // Hide full API keys, show only last 8 chars
        foreach ($gateways as &$gw) {
            $gw['api_key_preview'] = '••••••••' . substr($gw['api_key'], -8);
            unset($gw['api_key']);
        }

        return self::success_response(['gateways' => $gateways]);
    }

    /**
     * Create new gateway
     */
    public static function handle_create_gateway(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        if (!$store_id) {
            return self::error_response('no_store', 'Store not found', 403);
        }

        $params = $request->get_json_params();
        $gateway_type = sanitize_text_field($params['gateway_type'] ?? 'generic');
        $gateway_name = sanitize_text_field($params['gateway_name'] ?? '');

        if (empty($gateway_name)) {
            return self::error_response('missing_name', 'Gateway name is required', 400);
        }

        // Validate gateway type
        $valid_types = ['generic', 'sumup', 'ready2order', 'zettle', 'lightspeed'];
        if (!in_array($gateway_type, $valid_types)) {
            $gateway_type = 'generic';
        }

        $result = PPV_POS_Gateway_DB::create_gateway([
            'store_id' => $store_id,
            'gateway_type' => $gateway_type,
            'gateway_name' => $gateway_name,
            'config' => $params['config'] ?? null,
            'verification_token' => $params['verification_token'] ?? null
        ]);

        if (!$result['success']) {
            return self::error_response('create_failed', $result['message'], 500);
        }

        return self::success_response([
            'gateway' => $result['gateway'],
            'message' => 'Gateway created successfully'
        ]);
    }

    /**
     * Update gateway
     */
    public static function handle_update_gateway(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        $gateway_id = (int) $request->get_param('id');

        // Verify ownership
        $gateway = PPV_POS_Gateway_DB::get_gateway($gateway_id);
        if (!$gateway || (int) $gateway['store_id'] !== $store_id) {
            return self::error_response('not_found', 'Gateway not found', 404);
        }

        $params = $request->get_json_params();
        $update_data = [];

        if (isset($params['gateway_name'])) {
            $update_data['gateway_name'] = sanitize_text_field($params['gateway_name']);
        }
        if (isset($params['active'])) {
            $update_data['active'] = (bool) $params['active'] ? 1 : 0;
        }
        if (isset($params['config'])) {
            $update_data['config'] = $params['config'];
        }
        if (isset($params['verification_token'])) {
            $update_data['verification_token'] = sanitize_text_field($params['verification_token']);
        }

        $result = PPV_POS_Gateway_DB::update_gateway($gateway_id, $update_data);

        return self::success_response(['updated' => $result]);
    }

    /**
     * Delete gateway
     */
    public static function handle_delete_gateway(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        $gateway_id = (int) $request->get_param('id');

        // Verify ownership
        $gateway = PPV_POS_Gateway_DB::get_gateway($gateway_id);
        if (!$gateway || (int) $gateway['store_id'] !== $store_id) {
            return self::error_response('not_found', 'Gateway not found', 404);
        }

        $result = PPV_POS_Gateway_DB::delete_gateway($gateway_id);

        return self::success_response(['deleted' => $result]);
    }

    /**
     * Regenerate API key
     */
    public static function handle_regenerate_key(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        $gateway_id = (int) $request->get_param('id');

        // Verify ownership
        $gateway = PPV_POS_Gateway_DB::get_gateway($gateway_id);
        if (!$gateway || (int) $gateway['store_id'] !== $store_id) {
            return self::error_response('not_found', 'Gateway not found', 404);
        }

        $new_key = PPV_POS_Gateway_DB::regenerate_api_key($gateway_id);

        if (!$new_key) {
            return self::error_response('regenerate_failed', 'Failed to regenerate API key', 500);
        }

        return self::success_response([
            'api_key' => $new_key,
            'message' => 'API key regenerated. Please update your POS configuration.'
        ]);
    }

    /**
     * Get gateway transactions
     */
    public static function handle_get_transactions(WP_REST_Request $request): WP_REST_Response {
        $store_id = self::get_handler_store_id();
        $gateway_id = (int) $request->get_param('id');

        // Verify ownership
        $gateway = PPV_POS_Gateway_DB::get_gateway($gateway_id);
        if (!$gateway || (int) $gateway['store_id'] !== $store_id) {
            return self::error_response('not_found', 'Gateway not found', 404);
        }

        $limit = (int) ($request->get_param('limit') ?? 50);
        $offset = (int) ($request->get_param('offset') ?? 0);

        $transactions = PPV_POS_Gateway_DB::get_transactions($gateway_id, $limit, $offset);

        return self::success_response(['transactions' => $transactions]);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Get handler's store ID from session
     */
    private static function get_handler_store_id(): ?int {
        if (!empty($_SESSION['ppv_current_filiale_id'])) {
            return (int) $_SESSION['ppv_current_filiale_id'];
        }
        if (!empty($_SESSION['ppv_store_id'])) {
            return (int) $_SESSION['ppv_store_id'];
        }
        if (!empty($_SESSION['ppv_vendor_store_id'])) {
            return (int) $_SESSION['ppv_vendor_store_id'];
        }
        return null;
    }

    /**
     * Create success response
     */
    private static function success_response(array $data, ?string $message = null): WP_REST_Response {
        $response = ['success' => true] + $data;
        if ($message) {
            $response['message'] = $message;
        }
        return new WP_REST_Response($response, 200);
    }

    /**
     * Create error response
     */
    private static function error_response(string $code, string $message, int $status = 400): WP_REST_Response {
        return new WP_REST_Response([
            'success' => false,
            'error' => $code,
            'message' => $message
        ], $status);
    }

    /**
     * Get welcome message based on language
     */
    private static function get_welcome_message(array $customer, WP_REST_Request $request): string {
        $lang = self::get_language($request);
        $name = $customer['name'] ?? '';

        $messages = [
            'de' => "Willkommen zurück, {$name}!",
            'ro' => "Bine ai revenit, {$name}!",
            'en' => "Welcome back, {$name}!",
            'hu' => "Üdv újra, {$name}!"
        ];

        return $messages[$lang] ?? $messages['de'];
    }

    /**
     * Get thank you message based on language
     */
    private static function get_thank_you_message(array $result, WP_REST_Request $request): string {
        $lang = self::get_language($request);
        $points = $result['points_earned'] ?? 0;

        $messages = [
            'de' => "Vielen Dank! Sie haben {$points} Punkte gesammelt.",
            'ro' => "Mulțumim! Ai acumulat {$points} puncte.",
            'en' => "Thank you! You earned {$points} points.",
            'hu' => "Köszönjük! {$points} pontot szereztél."
        ];

        return $messages[$lang] ?? $messages['de'];
    }

    /**
     * Get language from request
     */
    private static function get_language(WP_REST_Request $request): string {
        $accept_lang = $request->get_header('Accept-Language');

        if ($accept_lang) {
            if (stripos($accept_lang, 'ro') !== false) return 'ro';
            if (stripos($accept_lang, 'hu') !== false) return 'hu';
            if (stripos($accept_lang, 'en') !== false) return 'en';
        }

        return 'de'; // Default German
    }
}
