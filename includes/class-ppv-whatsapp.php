<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass - WhatsApp Cloud API Integration
 *
 * Provides WhatsApp Business messaging for:
 * - Marketing automation (birthday, comeback campaigns)
 * - Customer support chat
 * - Reward notifications
 *
 * Uses Meta's WhatsApp Cloud API
 * Documentation: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class PPV_WhatsApp {

    const API_VERSION = 'v18.0';
    const API_BASE_URL = 'https://graph.facebook.com/';

    /**
     * Initialize hooks
     */
    public static function hooks() {
        // Webhook endpoint for incoming messages
        add_action('rest_api_init', [__CLASS__, 'register_webhook_endpoint']);

        // Cron for automated campaigns
        add_action('init', [__CLASS__, 'schedule_cron']);
        add_action('ppv_whatsapp_campaigns', [__CLASS__, 'process_campaigns']);

        // AJAX handlers
        add_action('wp_ajax_ppv_whatsapp_send_test', [__CLASS__, 'ajax_send_test']);
        add_action('wp_ajax_ppv_whatsapp_verify_connection', [__CLASS__, 'ajax_verify_connection']);
        add_action('wp_ajax_ppv_whatsapp_send_message', [__CLASS__, 'ajax_send_message']);
        add_action('wp_ajax_ppv_whatsapp_get_conversations', [__CLASS__, 'ajax_get_conversations']);
        add_action('wp_ajax_ppv_whatsapp_get_messages', [__CLASS__, 'ajax_get_messages']);
    }

    /**
     * Schedule daily cron for campaigns
     */
    public static function schedule_cron() {
        if (!wp_next_scheduled('ppv_whatsapp_campaigns')) {
            $next_run = strtotime('today 09:00:00');
            if ($next_run < time()) {
                $next_run = strtotime('tomorrow 09:00:00');
            }
            wp_schedule_event($next_run, 'daily', 'ppv_whatsapp_campaigns');
            ppv_log("[WhatsApp] Cron scheduled for: " . date('Y-m-d H:i:s', $next_run));
        }
    }

    /**
     * Register REST API endpoint for webhook
     */
    public static function register_webhook_endpoint() {
        register_rest_route('punktepass/v1', '/whatsapp-webhook', [
            [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'verify_webhook'],
                'permission_callback' => '__return_true'
            ],
            [
                'methods' => 'POST',
                'callback' => [__CLASS__, 'handle_webhook'],
                'permission_callback' => '__return_true'
            ]
        ]);

        // Admin API endpoints for standalone admin panel
        register_rest_route('punktepass/v1', '/whatsapp/verify', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_verify_connection'],
            'permission_callback' => [__CLASS__, 'check_admin_permission']
        ]);

        register_rest_route('punktepass/v1', '/whatsapp/test', [
            'methods' => 'POST',
            'callback' => [__CLASS__, 'rest_send_test'],
            'permission_callback' => [__CLASS__, 'check_admin_permission']
        ]);
    }

    /**
     * Check if user has admin permission (standalone admin session)
     */
    public static function check_admin_permission() {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        return !empty($_SESSION['ppv_admin_logged_in']);
    }

    /**
     * REST API: Verify WhatsApp connection
     */
    public static function rest_verify_connection(WP_REST_Request $request) {
        $data = $request->get_json_params();
        $store_id = intval($data['store_id'] ?? 0);

        if (!$store_id) {
            return new WP_REST_Response(['success' => false, 'message' => 'Missing store_id'], 400);
        }

        $config = self::get_store_config($store_id);
        if (!$config) {
            return new WP_REST_Response(['success' => false, 'message' => 'Store not found'], 404);
        }

        $access_token = self::decrypt_token($config->whatsapp_access_token);
        if (empty($access_token) || empty($config->whatsapp_phone_id)) {
            return new WP_REST_Response(['success' => false, 'message' => 'WhatsApp nicht konfiguriert'], 400);
        }

        // Test API connection
        $url = self::API_BASE_URL . self::API_VERSION . '/' . $config->whatsapp_phone_id;

        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            return new WP_REST_Response(['success' => false, 'message' => $response->get_error_message()], 500);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return new WP_REST_Response([
                'success' => false,
                'message' => $body['error']['message'] ?? 'Verbindung fehlgeschlagen'
            ], $code);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => 'Verbindung erfolgreich!',
                'phone_number' => $body['display_phone_number'] ?? 'Unknown',
                'verified_name' => $body['verified_name'] ?? 'Unknown'
            ]
        ]);
    }

    /**
     * REST API: Send test message
     */
    public static function rest_send_test(WP_REST_Request $request) {
        $data = $request->get_json_params();
        $store_id = intval($data['store_id'] ?? 0);
        $phone = sanitize_text_field($data['phone'] ?? '');

        if (!$store_id || !$phone) {
            return new WP_REST_Response(['success' => false, 'message' => 'Fehlende Parameter'], 400);
        }

        // Send test template
        $result = self::send_template(
            $store_id,
            $phone,
            'hello_world', // Meta's default test template
            [],
            'en'
        );

        if (is_wp_error($result)) {
            return new WP_REST_Response(['success' => false, 'message' => $result->get_error_message()], 500);
        }

        return new WP_REST_Response([
            'success' => true,
            'data' => [
                'message' => 'Testnachricht gesendet!',
                'wa_message_id' => $result['messages'][0]['id'] ?? 'Unknown'
            ]
        ]);
    }

    // ============================================================
    // API METHODS
    // ============================================================

    /**
     * Send a template message
     *
     * @param int $store_id Store ID
     * @param string $phone Phone number (with country code, e.g., 491234567890)
     * @param string $template_name Meta-approved template name
     * @param array $components Template components (header, body, buttons)
     * @param string $language Template language (default: de)
     * @return array|WP_Error Response or error
     */
    public static function send_template($store_id, $phone, $template_name, $components = [], $language = 'de') {
        $config = self::get_store_config($store_id);

        if (!$config || empty($config->whatsapp_enabled)) {
            return new WP_Error('disabled', 'WhatsApp not enabled for this store');
        }

        $phone = self::normalize_phone($phone);
        if (!$phone) {
            return new WP_Error('invalid_phone', 'Invalid phone number');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template_name,
                'language' => ['code' => $language]
            ]
        ];

        if (!empty($components)) {
            $payload['template']['components'] = $components;
        }

        $result = self::api_request($config, 'messages', $payload);

        // Log the message
        self::log_message($store_id, $phone, 'outbound', 'template', [
            'template_name' => $template_name,
            'components' => $components,
            'language' => $language
        ], $result);

        return $result;
    }

    /**
     * Send a text message (only works within 24h window after customer message)
     */
    public static function send_text($store_id, $phone, $text) {
        $config = self::get_store_config($store_id);

        if (!$config || empty($config->whatsapp_enabled)) {
            return new WP_Error('disabled', 'WhatsApp not enabled for this store');
        }

        $phone = self::normalize_phone($phone);
        if (!$phone) {
            return new WP_Error('invalid_phone', 'Invalid phone number');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['body' => $text]
        ];

        $result = self::api_request($config, 'messages', $payload);

        // Log the message
        self::log_message($store_id, $phone, 'outbound', 'text', [
            'text' => $text
        ], $result);

        return $result;
    }

    /**
     * Send interactive button message
     */
    public static function send_interactive($store_id, $phone, $body_text, $buttons) {
        $config = self::get_store_config($store_id);

        if (!$config || empty($config->whatsapp_enabled)) {
            return new WP_Error('disabled', 'WhatsApp not enabled for this store');
        }

        $phone = self::normalize_phone($phone);

        $formatted_buttons = [];
        foreach ($buttons as $id => $title) {
            $formatted_buttons[] = [
                'type' => 'reply',
                'reply' => [
                    'id' => $id,
                    'title' => substr($title, 0, 20) // Max 20 chars
                ]
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'interactive',
            'interactive' => [
                'type' => 'button',
                'body' => ['text' => $body_text],
                'action' => ['buttons' => $formatted_buttons]
            ]
        ];

        $result = self::api_request($config, 'messages', $payload);

        self::log_message($store_id, $phone, 'outbound', 'interactive', [
            'body' => $body_text,
            'buttons' => $buttons
        ], $result);

        return $result;
    }

    /**
     * Make API request to WhatsApp Cloud API
     */
    private static function api_request($config, $endpoint, $payload) {
        $access_token = self::decrypt_token($config->whatsapp_access_token);

        if (empty($access_token) || empty($config->whatsapp_phone_id)) {
            return new WP_Error('config_error', 'WhatsApp not properly configured');
        }

        $url = self::API_BASE_URL . self::API_VERSION . '/' . $config->whatsapp_phone_id . '/' . $endpoint;

        ppv_log("[WhatsApp] API Request to: {$url}");
        ppv_log("[WhatsApp] Payload: " . json_encode($payload));

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $access_token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload),
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            ppv_log("[WhatsApp] API Error: " . $response->get_error_message());
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        ppv_log("[WhatsApp] API Response ({$code}): " . json_encode($body));

        if ($code !== 200) {
            $error_msg = $body['error']['message'] ?? 'Unknown error';
            return new WP_Error('api_error', $error_msg, $body);
        }

        return $body;
    }

    // ============================================================
    // WEBHOOK HANDLERS
    // ============================================================

    /**
     * Verify webhook (GET request from Meta)
     */
    public static function verify_webhook(WP_REST_Request $request) {
        $mode = $request->get_param('hub_mode');
        $token = $request->get_param('hub_verify_token');
        $challenge = $request->get_param('hub_challenge');

        ppv_log("[WhatsApp] Webhook verification: mode={$mode}, token={$token}");

        // Get verify token from any store (or global setting)
        $verify_token = get_option('ppv_whatsapp_verify_token', 'punktepass_whatsapp_2024');

        if ($mode === 'subscribe' && $token === $verify_token) {
            ppv_log("[WhatsApp] Webhook verified successfully");
            return new WP_REST_Response($challenge, 200);
        }

        ppv_log("[WhatsApp] Webhook verification failed");
        return new WP_REST_Response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook (POST from Meta)
     */
    public static function handle_webhook(WP_REST_Request $request) {
        $body = $request->get_json_params();

        ppv_log("[WhatsApp] Webhook received: " . json_encode($body));

        // Always respond 200 immediately
        if (empty($body['entry'])) {
            return new WP_REST_Response('OK', 200);
        }

        foreach ($body['entry'] as $entry) {
            if (empty($entry['changes'])) continue;

            foreach ($entry['changes'] as $change) {
                if ($change['field'] !== 'messages') continue;

                $value = $change['value'];

                // Handle incoming messages
                if (!empty($value['messages'])) {
                    foreach ($value['messages'] as $message) {
                        self::process_incoming_message($value, $message);
                    }
                }

                // Handle status updates
                if (!empty($value['statuses'])) {
                    foreach ($value['statuses'] as $status) {
                        self::process_status_update($status);
                    }
                }
            }
        }

        return new WP_REST_Response('OK', 200);
    }

    /**
     * Process incoming message
     */
    private static function process_incoming_message($value, $message) {
        $phone_id = $value['metadata']['phone_number_id'] ?? '';
        $from = $message['from'] ?? '';
        $msg_id = $message['id'] ?? '';
        $timestamp = $message['timestamp'] ?? time();
        $type = $message['type'] ?? 'unknown';

        // Find store by phone_id
        global $wpdb;
        $store = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE whatsapp_phone_id = %s",
            $phone_id
        ));

        if (!$store) {
            ppv_log("[WhatsApp] No store found for phone_id: {$phone_id}");
            return;
        }

        // Get message content
        $content = '';
        switch ($type) {
            case 'text':
                $content = $message['text']['body'] ?? '';
                break;
            case 'button':
                $content = $message['button']['text'] ?? '';
                break;
            case 'interactive':
                $content = $message['interactive']['button_reply']['title'] ??
                           $message['interactive']['list_reply']['title'] ?? '';
                break;
            default:
                $content = "[{$type}]";
        }

        // Get contact name
        $contact_name = '';
        if (!empty($value['contacts'][0]['profile']['name'])) {
            $contact_name = $value['contacts'][0]['profile']['name'];
        }

        // Find or link user
        $user_id = self::find_user_by_phone($from, $store->id);

        // Log incoming message
        $wpdb->insert($wpdb->prefix . 'ppv_whatsapp_messages', [
            'store_id' => $store->id,
            'user_id' => $user_id,
            'phone_number' => $from,
            'direction' => 'inbound',
            'message_type' => $type,
            'message_content' => $content,
            'wa_message_id' => $msg_id,
            'status' => 'delivered',
            'created_at' => date('Y-m-d H:i:s', $timestamp)
        ], ['%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);

        // Update or create conversation
        self::update_conversation($store->id, $from, $contact_name, $content, $user_id);

        // Auto-reply if support enabled
        if (!empty($store->whatsapp_support_enabled)) {
            self::handle_support_message($store, $from, $content, $type, $message);
        }

        ppv_log("[WhatsApp] Processed incoming message from {$from}: {$content}");
    }

    /**
     * Process message status update
     */
    private static function process_status_update($status) {
        global $wpdb;

        $msg_id = $status['id'] ?? '';
        $new_status = $status['status'] ?? '';
        $timestamp = $status['timestamp'] ?? time();

        $update_data = ['status' => $new_status];

        if ($new_status === 'delivered') {
            $update_data['delivered_at'] = date('Y-m-d H:i:s', $timestamp);
        } elseif ($new_status === 'read') {
            $update_data['read_at'] = date('Y-m-d H:i:s', $timestamp);
        }

        $wpdb->update(
            $wpdb->prefix . 'ppv_whatsapp_messages',
            $update_data,
            ['wa_message_id' => $msg_id]
        );

        ppv_log("[WhatsApp] Status update: {$msg_id} -> {$new_status}");
    }

    /**
     * Update or create conversation
     */
    private static function update_conversation($store_id, $phone, $name, $last_message, $user_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'ppv_whatsapp_conversations';

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE store_id = %d AND phone_number = %s",
            $store_id, $phone
        ));

        if ($existing) {
            $wpdb->update($table, [
                'last_message_at' => current_time('mysql'),
                'last_message_preview' => substr($last_message, 0, 255),
                'unread_count' => $existing->unread_count + 1,
                'customer_name' => $name ?: $existing->customer_name,
                'user_id' => $user_id ?: $existing->user_id,
                'status' => 'active'
            ], ['id' => $existing->id]);
        } else {
            $wpdb->insert($table, [
                'store_id' => $store_id,
                'user_id' => $user_id,
                'phone_number' => $phone,
                'customer_name' => $name,
                'status' => 'active',
                'last_message_at' => current_time('mysql'),
                'last_message_preview' => substr($last_message, 0, 255),
                'unread_count' => 1
            ]);
        }
    }

    /**
     * Handle support message with auto-reply
     */
    private static function handle_support_message($store, $phone, $content, $type, $raw_message) {
        // Check if this is a new conversation (first message)
        global $wpdb;
        $msg_count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ppv_whatsapp_messages
             WHERE store_id = %d AND phone_number = %s AND direction = 'inbound'",
            $store->id, $phone
        ));

        // Send welcome message for new conversations
        if ($msg_count <= 1) {
            $store_name = $store->company_name ?: $store->name;
            $welcome = "Hallo! Willkommen beim Support von {$store_name}. Wie können wir Ihnen helfen?";

            // Send as text (within 24h window)
            self::send_text($store->id, $phone, $welcome);
        }
    }

    // ============================================================
    // CAMPAIGN PROCESSING
    // ============================================================

    /**
     * Process automated campaigns (called by cron)
     */
    public static function process_campaigns() {
        global $wpdb;

        ppv_log("[WhatsApp] Starting campaign processing...");

        // Get all stores with WhatsApp marketing enabled
        $stores = $wpdb->get_results("
            SELECT * FROM {$wpdb->prefix}ppv_stores
            WHERE whatsapp_enabled = 1
              AND whatsapp_marketing_enabled = 1
              AND subscription_status IN ('active', 'trial')
              AND parent_store_id IS NULL
        ");

        foreach ($stores as $store) {
            // Process birthday campaigns
            if (!empty($store->birthday_bonus_enabled)) {
                self::process_birthday_campaign($store);
            }

            // Process comeback campaigns
            if (!empty($store->comeback_enabled)) {
                self::process_comeback_campaign($store);
            }
        }

        ppv_log("[WhatsApp] Campaign processing complete");
    }

    /**
     * Process birthday campaign for a store
     */
    private static function process_birthday_campaign($store) {
        global $wpdb;

        // Find users with birthday today who have WhatsApp consent
        $today = date('m-d');

        $users = $wpdb->get_results($wpdb->prepare("
            SELECT u.*,
                   COALESCE(SUM(p.points), 0) as total_points
            FROM {$wpdb->prefix}ppv_users u
            INNER JOIN {$wpdb->prefix}ppv_points p ON p.user_id = u.id
            WHERE p.store_id IN (
                SELECT id FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d OR parent_store_id = %d
            )
            AND DATE_FORMAT(u.birthday, '%%m-%%d') = %s
            AND u.whatsapp_consent = 1
            AND u.phone_number IS NOT NULL
            AND u.phone_number != ''
            GROUP BY u.id
        ", $store->id, $store->id, $today));

        $store_name = $store->company_name ?: $store->name;

        foreach ($users as $user) {
            // Check if already sent today
            $already_sent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_whatsapp_messages
                WHERE store_id = %d
                  AND phone_number = %s
                  AND campaign_type = 'birthday'
                  AND DATE(created_at) = CURDATE()
            ", $store->id, $user->phone_number));

            if ($already_sent > 0) continue;

            // Send birthday template
            $result = self::send_template(
                $store->id,
                $user->phone_number,
                'punktepass_birthday', // Must be pre-approved by Meta
                [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->first_name ?: $user->display_name],
                            ['type' => 'text', 'text' => $store_name]
                        ]
                    ]
                ],
                self::get_language_from_country($store->country)
            );

            // Update campaign type in log
            if (!is_wp_error($result)) {
                $wpdb->update(
                    $wpdb->prefix . 'ppv_whatsapp_messages',
                    ['campaign_type' => 'birthday'],
                    ['store_id' => $store->id, 'phone_number' => $user->phone_number],
                    ['%s'],
                    ['%d', '%s']
                );
            }

            ppv_log("[WhatsApp] Birthday message sent to {$user->phone_number} for store {$store->id}");
        }
    }

    /**
     * Process comeback campaign for a store
     */
    private static function process_comeback_campaign($store) {
        global $wpdb;

        $days = intval($store->comeback_days ?? 30);
        $cutoff_date = date('Y-m-d', strtotime("-{$days} days"));

        // Find inactive users with WhatsApp consent
        $users = $wpdb->get_results($wpdb->prepare("
            SELECT u.*,
                   MAX(p.created_at) as last_activity
            FROM {$wpdb->prefix}ppv_users u
            INNER JOIN {$wpdb->prefix}ppv_points p ON p.user_id = u.id
            WHERE p.store_id IN (
                SELECT id FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d OR parent_store_id = %d
            )
            AND u.whatsapp_consent = 1
            AND u.phone_number IS NOT NULL
            AND u.phone_number != ''
            GROUP BY u.id
            HAVING last_activity < %s
        ", $store->id, $store->id, $cutoff_date));

        $store_name = $store->company_name ?: $store->name;

        foreach ($users as $user) {
            // Check if already sent in last 30 days
            $already_sent = $wpdb->get_var($wpdb->prepare("
                SELECT COUNT(*) FROM {$wpdb->prefix}ppv_whatsapp_messages
                WHERE store_id = %d
                  AND phone_number = %s
                  AND campaign_type = 'comeback'
                  AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ", $store->id, $user->phone_number));

            if ($already_sent > 0) continue;

            // Send comeback template
            $result = self::send_template(
                $store->id,
                $user->phone_number,
                'punktepass_comeback', // Must be pre-approved by Meta
                [
                    [
                        'type' => 'body',
                        'parameters' => [
                            ['type' => 'text', 'text' => $user->first_name ?: $user->display_name],
                            ['type' => 'text', 'text' => $store_name]
                        ]
                    ]
                ],
                self::get_language_from_country($store->country)
            );

            if (!is_wp_error($result)) {
                $wpdb->update(
                    $wpdb->prefix . 'ppv_whatsapp_messages',
                    ['campaign_type' => 'comeback'],
                    ['store_id' => $store->id, 'phone_number' => $user->phone_number],
                    ['%s'],
                    ['%d', '%s']
                );
            }

            ppv_log("[WhatsApp] Comeback message sent to {$user->phone_number} for store {$store->id}");
        }
    }

    // ============================================================
    // AJAX HANDLERS
    // ============================================================

    /**
     * Verify WhatsApp connection
     */
    public static function ajax_verify_connection() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Missing store_id']);
        }

        $config = self::get_store_config($store_id);
        if (!$config) {
            wp_send_json_error(['message' => 'Store not found']);
        }

        $access_token = self::decrypt_token($config->whatsapp_access_token);
        if (empty($access_token) || empty($config->whatsapp_phone_id)) {
            wp_send_json_error(['message' => 'WhatsApp not configured']);
        }

        // Test API connection
        $url = self::API_BASE_URL . self::API_VERSION . '/' . $config->whatsapp_phone_id;

        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Bearer ' . $access_token],
            'timeout' => 15
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            wp_send_json_error([
                'message' => $body['error']['message'] ?? 'Connection failed',
                'code' => $code
            ]);
        }

        wp_send_json_success([
            'message' => 'Connection successful!',
            'phone_number' => $body['display_phone_number'] ?? 'Unknown',
            'verified_name' => $body['verified_name'] ?? 'Unknown'
        ]);
    }

    /**
     * Send test message
     */
    public static function ajax_send_test() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        $phone = sanitize_text_field($_POST['phone'] ?? '');

        if (!$store_id || !$phone) {
            wp_send_json_error(['message' => 'Missing parameters']);
        }

        // Send test template
        $result = self::send_template(
            $store_id,
            $phone,
            'hello_world', // Meta's default test template
            [],
            'en'
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Test message sent!',
            'wa_message_id' => $result['messages'][0]['id'] ?? 'Unknown'
        ]);
    }

    /**
     * Send message from support chat
     */
    public static function ajax_send_message() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        $phone = sanitize_text_field($_POST['phone'] ?? '');
        $message = sanitize_textarea_field($_POST['message'] ?? '');

        if (!$store_id || !$phone || !$message) {
            wp_send_json_error(['message' => 'Missing parameters']);
        }

        // Send as text (within 24h window)
        $result = self::send_text($store_id, $phone, $message);

        if (is_wp_error($result)) {
            // If 24h window expired, try template
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'hint' => 'Das 24-Stunden-Fenster ist möglicherweise abgelaufen. Verwenden Sie eine Vorlage.'
            ]);
        }

        wp_send_json_success([
            'message' => 'Message sent!',
            'wa_message_id' => $result['messages'][0]['id'] ?? 'Unknown'
        ]);
    }

    /**
     * Get conversations for support dashboard
     */
    public static function ajax_get_conversations() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        if (!$store_id) {
            wp_send_json_error(['message' => 'Missing store_id']);
        }

        global $wpdb;
        $conversations = $wpdb->get_results($wpdb->prepare("
            SELECT c.*, u.display_name as user_name, u.email as user_email
            FROM {$wpdb->prefix}ppv_whatsapp_conversations c
            LEFT JOIN {$wpdb->prefix}ppv_users u ON c.user_id = u.id
            WHERE c.store_id = %d
            ORDER BY c.last_message_at DESC
            LIMIT 50
        ", $store_id));

        wp_send_json_success(['conversations' => $conversations]);
    }

    /**
     * Get messages for a conversation
     */
    public static function ajax_get_messages() {
        check_ajax_referer('ppv_whatsapp_nonce', 'nonce');

        $store_id = intval($_POST['store_id'] ?? 0);
        $phone = sanitize_text_field($_POST['phone'] ?? '');

        if (!$store_id || !$phone) {
            wp_send_json_error(['message' => 'Missing parameters']);
        }

        global $wpdb;

        // Get messages
        $messages = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$wpdb->prefix}ppv_whatsapp_messages
            WHERE store_id = %d AND phone_number = %s
            ORDER BY created_at ASC
            LIMIT 100
        ", $store_id, $phone));

        // Mark as read
        $wpdb->update(
            $wpdb->prefix . 'ppv_whatsapp_conversations',
            ['unread_count' => 0],
            ['store_id' => $store_id, 'phone_number' => $phone]
        );

        wp_send_json_success(['messages' => $messages]);
    }

    // ============================================================
    // HELPER METHODS
    // ============================================================

    /**
     * Get store WhatsApp config
     */
    public static function get_store_config($store_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}ppv_stores WHERE id = %d",
            $store_id
        ));
    }

    /**
     * Normalize phone number (remove spaces, add country code if needed)
     */
    public static function normalize_phone($phone) {
        // Remove all non-numeric chars except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);

        // Remove leading +
        $phone = ltrim($phone, '+');

        // If starts with 0, assume German number
        if (substr($phone, 0, 1) === '0') {
            $phone = '49' . substr($phone, 1);
        }

        // Basic validation: must be 10-15 digits
        if (strlen($phone) < 10 || strlen($phone) > 15) {
            return false;
        }

        return $phone;
    }

    /**
     * Find user by phone number
     */
    private static function find_user_by_phone($phone, $store_id) {
        global $wpdb;

        $normalized = self::normalize_phone($phone);

        return $wpdb->get_var($wpdb->prepare("
            SELECT u.id FROM {$wpdb->prefix}ppv_users u
            INNER JOIN {$wpdb->prefix}ppv_points p ON p.user_id = u.id
            WHERE p.store_id IN (
                SELECT id FROM {$wpdb->prefix}ppv_stores
                WHERE id = %d OR parent_store_id = %d
            )
            AND (u.phone_number = %s OR u.phone_number = %s)
            LIMIT 1
        ", $store_id, $store_id, $phone, $normalized));
    }

    /**
     * Log outbound message
     */
    private static function log_message($store_id, $phone, $direction, $type, $content, $result) {
        global $wpdb;

        $wa_message_id = null;
        $status = 'pending';
        $error = null;

        if (is_wp_error($result)) {
            $status = 'failed';
            $error = $result->get_error_message();
        } elseif (!empty($result['messages'][0]['id'])) {
            $wa_message_id = $result['messages'][0]['id'];
            $status = 'sent';
        }

        $wpdb->insert($wpdb->prefix . 'ppv_whatsapp_messages', [
            'store_id' => $store_id,
            'phone_number' => $phone,
            'direction' => $direction,
            'message_type' => $type,
            'message_content' => is_array($content) ? json_encode($content) : $content,
            'wa_message_id' => $wa_message_id,
            'status' => $status,
            'error_message' => $error,
            'template_name' => $content['template_name'] ?? null
        ], ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']);
    }

    /**
     * Encrypt access token for storage
     */
    public static function encrypt_token($token) {
        if (empty($token)) return '';

        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);

        return base64_encode(openssl_encrypt($token, 'AES-256-CBC', $key, 0, $iv));
    }

    /**
     * Decrypt access token
     */
    public static function decrypt_token($encrypted) {
        if (empty($encrypted)) return '';

        $key = wp_salt('auth');
        $iv = substr(md5($key), 0, 16);

        return openssl_decrypt(base64_decode($encrypted), 'AES-256-CBC', $key, 0, $iv);
    }

    /**
     * Get language code from country
     */
    private static function get_language_from_country($country) {
        $country = strtolower(trim($country ?? ''));

        if (in_array($country, ['hungary', 'magyarorszag', 'ungarn', 'hu'])) {
            return 'hu';
        }

        if (in_array($country, ['romania', 'rumanien', 'ro'])) {
            return 'ro';
        }

        return 'de';
    }
}

// Initialize
PPV_WhatsApp::hooks();
