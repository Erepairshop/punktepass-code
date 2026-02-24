<?php
if (!defined('ABSPATH')) exit;

/**
 * PunktePass – Google Wallet Loyalty Pass Integration
 *
 * Creates and manages Google Wallet Loyalty passes for customers.
 *
 * Setup:
 *   1. Add to wp-config.php:
 *      define('PPV_GOOGLE_WALLET_SA', '/path/to/service-account-key.json');
 *   2. Google Wallet API must be enabled in Google Cloud Console
 *   3. Service account must be added as admin in Google Pay & Wallet Console
 *
 * Issuer ID: BCR2DN5TW257N6ZT
 */
class PPV_Google_Wallet {

    const ISSUER_ID   = 'BCR2DN5TW257N6ZT';
    const CLASS_SUFFIX = 'PunktePass_Loyalty';
    const API_BASE     = 'https://walletobjects.googleapis.com/walletobjects/v1';

    private static $service_account = null;
    private static $access_token    = null;
    private static $token_expiry    = 0;

    public static function hooks() {
        add_action('rest_api_init', [__CLASS__, 'register_routes']);
        // Hook into points changes for auto-update
        add_action('ppv_points_changed', [__CLASS__, 'on_points_changed']);
    }

    /**
     * Check if Google Wallet integration is available
     */
    public static function is_available() {
        $sa_path = defined('PPV_GOOGLE_WALLET_SA') ? PPV_GOOGLE_WALLET_SA : (defined('PPV_FCM_SERVICE_ACCOUNT') ? PPV_FCM_SERVICE_ACCOUNT : '');
        return !empty($sa_path) && file_exists($sa_path);
    }

    /**
     * Get service account data
     */
    private static function get_service_account() {
        if (self::$service_account !== null) {
            return self::$service_account;
        }

        $sa_path = defined('PPV_GOOGLE_WALLET_SA') ? PPV_GOOGLE_WALLET_SA : (defined('PPV_FCM_SERVICE_ACCOUNT') ? PPV_FCM_SERVICE_ACCOUNT : '');
        if (empty($sa_path) || !file_exists($sa_path)) {
            return null;
        }

        $json = file_get_contents($sa_path);
        self::$service_account = json_decode($json, true);
        return self::$service_account;
    }

    /**
     * Get OAuth2 access token for Google Wallet API calls
     */
    private static function get_access_token() {
        if (self::$access_token && time() < self::$token_expiry - 60) {
            return self::$access_token;
        }

        $sa = self::get_service_account();
        if (!$sa) return null;

        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $now = time();
        $claims = json_encode([
            'iss'   => $sa['client_email'],
            'scope' => 'https://www.googleapis.com/auth/wallet_object.issuer',
            'aud'   => 'https://oauth2.googleapis.com/token',
            'iat'   => $now,
            'exp'   => $now + 3600,
        ]);

        $b64_header = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $b64_claims = rtrim(strtr(base64_encode($claims), '+/', '-_'), '=');
        $sig_input  = $b64_header . '.' . $b64_claims;

        $pkey = openssl_pkey_get_private($sa['private_key']);
        if (!$pkey) return null;

        openssl_sign($sig_input, $signature, $pkey, OPENSSL_ALGO_SHA256);
        $b64_sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        $jwt = $sig_input . '.' . $b64_sig;

        $response = wp_remote_post('https://oauth2.googleapis.com/token', [
            'body' => [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion'  => $jwt,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            ppv_log('[GoogleWallet] OAuth error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            ppv_log('[GoogleWallet] OAuth failed: ' . print_r($body, true));
            return null;
        }

        self::$access_token = $body['access_token'];
        self::$token_expiry = $now + ($body['expires_in'] ?? 3600);
        return self::$access_token;
    }

    // =========================================================================
    // LoyaltyClass
    // =========================================================================

    private static function get_class_id() {
        return self::ISSUER_ID . '.' . self::CLASS_SUFFIX;
    }

    /**
     * Build the LoyaltyClass definition
     */
    private static function build_loyalty_class() {
        $logo_url = home_url('/wp-content/plugins/punktepass/assets/img/punktepass-repair-logo.svg');

        return [
            'id'              => self::get_class_id(),
            'issuerName'      => 'PunktePass',
            'programName'     => 'PunktePass Treuepunkte',
            'reviewStatus'    => 'UNDER_REVIEW',
            'hexBackgroundColor' => '#667eea',
            'programLogo'     => [
                'sourceUri' => [
                    'uri' => $logo_url,
                ],
                'contentDescription' => [
                    'defaultValue' => [
                        'language' => 'de',
                        'value'    => 'PunktePass Logo',
                    ],
                ],
            ],
            'localizedProgramName' => [
                'defaultValue' => [
                    'language' => 'de',
                    'value'    => 'PunktePass Treuepunkte',
                ],
                'translatedValues' => [
                    ['language' => 'en', 'value' => 'PunktePass Loyalty Points'],
                    ['language' => 'hu', 'value' => 'PunktePass Hűségpontok'],
                    ['language' => 'ro', 'value' => 'PunktePass Puncte de Fidelitate'],
                ],
            ],
            'accountNameLabel' => 'Mitglied',
            'accountIdLabel'   => 'Kunden-Nr.',
            'localizedAccountNameLabel' => [
                'defaultValue' => ['language' => 'de', 'value' => 'Mitglied'],
                'translatedValues' => [
                    ['language' => 'en', 'value' => 'Member'],
                    ['language' => 'hu', 'value' => 'Tag'],
                ],
            ],
            'localizedAccountIdLabel' => [
                'defaultValue' => ['language' => 'de', 'value' => 'Kunden-Nr.'],
                'translatedValues' => [
                    ['language' => 'en', 'value' => 'Customer ID'],
                    ['language' => 'hu', 'value' => 'Ügyfél-szám'],
                ],
            ],
        ];
    }

    /**
     * Create or update the LoyaltyClass via API
     */
    public static function ensure_loyalty_class() {
        $token = self::get_access_token();
        if (!$token) return ['error' => 'No access token'];

        $class_id = self::get_class_id();
        $class_data = self::build_loyalty_class();

        // Try GET first to check if exists
        $get_response = wp_remote_get(self::API_BASE . '/loyaltyClass/' . $class_id, [
            'headers' => ['Authorization' => 'Bearer ' . $token],
            'timeout' => 15,
        ]);

        $status = wp_remote_retrieve_response_code($get_response);

        if ($status === 200) {
            // Class exists, update it
            $response = wp_remote_request(self::API_BASE . '/loyaltyClass/' . $class_id, [
                'method'  => 'PUT',
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($class_data),
                'timeout' => 15,
            ]);
            ppv_log('[GoogleWallet] LoyaltyClass updated');
        } else {
            // Create new
            $response = wp_remote_post(self::API_BASE . '/loyaltyClass', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => json_encode($class_data),
                'timeout' => 15,
            ]);
            ppv_log('[GoogleWallet] LoyaltyClass created');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 300) {
            return ['success' => true, 'class_id' => $class_id];
        }

        ppv_log('[GoogleWallet] LoyaltyClass error: ' . print_r($body, true));
        return ['error' => $body['error']['message'] ?? 'Unknown error', 'code' => $code];
    }

    // =========================================================================
    // LoyaltyObject (per customer)
    // =========================================================================

    private static function get_object_id($user_id) {
        return self::ISSUER_ID . '.ppuser_' . intval($user_id);
    }

    /**
     * Build LoyaltyObject for a customer
     */
    private static function build_loyalty_object($user_id) {
        global $wpdb;

        $user = get_userdata($user_id);
        if (!$user) return null;

        $points_table = $wpdb->prefix . 'ppv_points';
        $total_points = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(points), 0) FROM {$points_table} WHERE user_id = %d",
            $user_id
        ));

        // Get tier/VIP info
        $tier_info = null;
        if (class_exists('PPV_User_Level')) {
            $tier_info = PPV_User_Level::get_user_level_info($user_id, 'de');
        }

        $tier_name = $tier_info['name'] ?? 'Starter';
        $display_name = trim($user->first_name . ' ' . $user->last_name);
        if (empty($display_name)) {
            $display_name = $user->display_name ?: $user->user_email;
        }

        $object = [
            'id'       => self::get_object_id($user_id),
            'classId'  => self::get_class_id(),
            'state'    => 'ACTIVE',
            'accountName' => $display_name,
            'accountId'   => 'PP-' . $user_id,
            'loyaltyPoints' => [
                'balance' => [
                    'int' => $total_points,
                ],
                'label' => 'Punkte',
                'localizedLabel' => [
                    'defaultValue' => ['language' => 'de', 'value' => 'Punkte'],
                    'translatedValues' => [
                        ['language' => 'en', 'value' => 'Points'],
                        ['language' => 'hu', 'value' => 'Pont'],
                    ],
                ],
            ],
            'barcode' => [
                'type'          => 'QR_CODE',
                'value'         => 'ppv-user-' . $user_id,
                'alternateText' => 'PP-' . $user_id,
            ],
            'textModulesData' => [
                [
                    'header' => 'VIP-Status',
                    'body'   => $tier_name,
                    'id'     => 'tier_status',
                ],
            ],
        ];

        return $object;
    }

    /**
     * Generate JWT for "Add to Google Wallet" button (fat JWT with full object)
     */
    public static function create_save_jwt($user_id) {
        $sa = self::get_service_account();
        if (!$sa) return null;

        $object = self::build_loyalty_object($user_id);
        if (!$object) return null;

        // Ensure class exists first
        self::ensure_loyalty_class();

        $header = json_encode(['typ' => 'JWT', 'alg' => 'RS256']);
        $now = time();
        $payload = json_encode([
            'iss'     => $sa['client_email'],
            'aud'     => 'google',
            'typ'     => 'savetowallet',
            'origins' => [home_url()],
            'iat'     => $now,
            'payload' => [
                'loyaltyObjects' => [$object],
            ],
        ]);

        $b64_header  = rtrim(strtr(base64_encode($header), '+/', '-_'), '=');
        $b64_payload = rtrim(strtr(base64_encode($payload), '+/', '-_'), '=');
        $sig_input   = $b64_header . '.' . $b64_payload;

        $pkey = openssl_pkey_get_private($sa['private_key']);
        if (!$pkey) return null;

        openssl_sign($sig_input, $signature, $pkey, OPENSSL_ALGO_SHA256);
        $b64_sig = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');

        return $sig_input . '.' . $b64_sig;
    }

    /**
     * Get the full "Add to Google Wallet" save URL
     */
    public static function get_save_url($user_id) {
        $jwt = self::create_save_jwt($user_id);
        if (!$jwt) return null;

        return 'https://pay.google.com/gp/v/save/' . $jwt;
    }

    // =========================================================================
    // Auto-update pass when points change
    // =========================================================================

    /**
     * Update existing LoyaltyObject via API (called when points change)
     */
    public static function update_loyalty_object($user_id) {
        if (!self::is_available()) return;

        $token = self::get_access_token();
        if (!$token) return;

        $object_id = self::get_object_id($user_id);
        $object = self::build_loyalty_object($user_id);
        if (!$object) return;

        // Try to PATCH (update) the existing object
        $response = wp_remote_request(self::API_BASE . '/loyaltyObject/' . $object_id, [
            'method'  => 'PUT',
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body'    => json_encode($object),
            'timeout' => 15,
        ]);

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 404) {
            // Object doesn't exist yet (user hasn't added to wallet), skip silently
            return;
        }

        if ($code >= 200 && $code < 300) {
            ppv_log("[GoogleWallet] LoyaltyObject updated for user {$user_id}");
        } else {
            $body = json_decode(wp_remote_retrieve_body($response), true);
            ppv_log("[GoogleWallet] Update failed for user {$user_id}: " . ($body['error']['message'] ?? 'Unknown'));
        }
    }

    /**
     * Hook: called when points change for a user
     */
    public static function on_points_changed($user_id) {
        // Non-blocking: schedule update for next page load to avoid slowing down scan
        if (!wp_next_scheduled('ppv_google_wallet_update', [$user_id])) {
            wp_schedule_single_event(time(), 'ppv_google_wallet_update', [$user_id]);
        }
    }

    /**
     * Cron callback for deferred pass update
     */
    public static function do_deferred_update($user_id) {
        self::update_loyalty_object($user_id);
    }

    // =========================================================================
    // REST API
    // =========================================================================

    public static function register_routes() {
        register_rest_route('ppv/v1', '/google-wallet/save-url', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'rest_get_save_url'],
            'permission_callback' => [__CLASS__, 'check_permission'],
        ]);

        register_rest_route('ppv/v1', '/google-wallet/setup-class', [
            'methods'             => 'POST',
            'callback'            => [__CLASS__, 'rest_setup_class'],
            'permission_callback' => function() {
                return current_user_can('manage_options');
            },
        ]);

        // Register cron hook for deferred updates
        add_action('ppv_google_wallet_update', [__CLASS__, 'do_deferred_update']);
    }

    /**
     * Permission check (same as mypoints REST)
     */
    public static function check_permission() {
        if (get_current_user_id() > 0) return true;

        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!empty($_SESSION['ppv_user_id'])) return true;

        // Try session restore
        if (class_exists('PPV_SessionBridge') && empty($_SESSION['ppv_user_id'])) {
            PPV_SessionBridge::restore_from_token();
            if (!empty($_SESSION['ppv_user_id'])) return true;
        }

        return false;
    }

    /**
     * Get authenticated user ID (same pattern as mypoints)
     */
    private static function get_current_user_id_safe() {
        $uid = get_current_user_id();
        if ($uid > 0) return $uid;

        if (session_status() === PHP_SESSION_NONE) @session_start();
        if (!empty($_SESSION['ppv_user_id'])) return intval($_SESSION['ppv_user_id']);

        return 0;
    }

    /**
     * REST: Get save URL for "Add to Google Wallet" button
     */
    public static function rest_get_save_url($request) {
        if (!self::is_available()) {
            return new WP_REST_Response(['error' => 'Google Wallet not configured'], 503);
        }

        $user_id = self::get_current_user_id_safe();
        if ($user_id <= 0) {
            return new WP_REST_Response(['error' => 'unauthorized'], 401);
        }

        $save_url = self::get_save_url($user_id);
        if (!$save_url) {
            return new WP_REST_Response(['error' => 'Failed to generate save URL'], 500);
        }

        return new WP_REST_Response([
            'save_url' => $save_url,
            'jwt'      => self::create_save_jwt($user_id),
        ]);
    }

    /**
     * REST: Admin endpoint to setup/update the LoyaltyClass
     */
    public static function rest_setup_class($request) {
        if (!self::is_available()) {
            return new WP_REST_Response(['error' => 'Google Wallet not configured'], 503);
        }

        $result = self::ensure_loyalty_class();
        return new WP_REST_Response($result);
    }
}
