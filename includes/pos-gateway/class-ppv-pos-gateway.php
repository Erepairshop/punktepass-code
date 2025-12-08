<?php
/**
 * POS Gateway Main Class
 *
 * Main entry point for the POS Gateway functionality.
 * Manages adapters, initialization, and provides a unified interface.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPV_POS_Gateway {

    /**
     * @var array Registered adapters
     */
    private static array $adapters = [];

    /**
     * @var bool Initialization flag
     */
    private static bool $initialized = false;

    /**
     * Initialize the POS Gateway module
     */
    public static function init(): void {
        if (self::$initialized) {
            return;
        }

        // Load dependencies
        self::load_dependencies();

        // Register default adapters
        self::register_default_adapters();

        // Initialize REST API
        PPV_POS_Gateway_REST::hooks();

        // Add admin hooks if in admin
        if (is_admin()) {
            add_action('admin_menu', [__CLASS__, 'add_admin_menu']);
        }

        self::$initialized = true;

        ppv_log("✅ [PPV_POS_Gateway] Initialized");
    }

    /**
     * Load required files
     */
    private static function load_dependencies(): void {
        $base_path = dirname(__FILE__);

        // Core classes
        require_once $base_path . '/class-ppv-pos-gateway-db.php';
        require_once $base_path . '/class-ppv-pos-gateway-rest.php';

        // Adapter interface and implementations
        require_once $base_path . '/adapters/interface-pos-adapter.php';
        require_once $base_path . '/adapters/class-adapter-generic.php';

        // Load additional adapters if they exist
        $adapter_files = glob($base_path . '/adapters/class-adapter-*.php');
        foreach ($adapter_files as $file) {
            if (basename($file) !== 'class-adapter-generic.php') {
                require_once $file;
            }
        }
    }

    /**
     * Register default adapters
     */
    private static function register_default_adapters(): void {
        // Generic adapter (base)
        self::register_adapter('generic', new PPV_POS_Adapter_Generic());

        // Register specific adapters if their classes exist
        if (class_exists('PPV_POS_Adapter_SumUp')) {
            self::register_adapter('sumup', new PPV_POS_Adapter_SumUp());
        }

        if (class_exists('PPV_POS_Adapter_Ready2Order')) {
            self::register_adapter('ready2order', new PPV_POS_Adapter_Ready2Order());
        }

        if (class_exists('PPV_POS_Adapter_Zettle')) {
            self::register_adapter('zettle', new PPV_POS_Adapter_Zettle());
        }

        /**
         * Action hook to register custom adapters
         *
         * @since 1.0.0
         * @example
         * add_action('ppv_pos_gateway_register_adapters', function() {
         *     PPV_POS_Gateway::register_adapter('my_pos', new My_POS_Adapter());
         * });
         */
        do_action('ppv_pos_gateway_register_adapters');
    }

    /**
     * Register an adapter
     *
     * @param string $type Adapter type identifier
     * @param PPV_POS_Adapter_Interface $adapter Adapter instance
     */
    public static function register_adapter(string $type, PPV_POS_Adapter_Interface $adapter): void {
        self::$adapters[$type] = $adapter;
        ppv_log("🔌 [PPV_POS_Gateway] Registered adapter: {$type}");
    }

    /**
     * Get adapter by type
     *
     * @param string $type Adapter type
     * @return PPV_POS_Adapter_Interface
     */
    public static function get_adapter(string $type): PPV_POS_Adapter_Interface {
        if (isset(self::$adapters[$type])) {
            return self::$adapters[$type];
        }

        // Fallback to generic adapter
        return self::$adapters['generic'] ?? new PPV_POS_Adapter_Generic();
    }

    /**
     * Get all registered adapter types
     *
     * @return array List of adapter type identifiers
     */
    public static function get_available_adapters(): array {
        return array_keys(self::$adapters);
    }

    /**
     * Get adapter info for admin UI
     *
     * @return array Adapter information
     */
    public static function get_adapters_info(): array {
        $info = [];

        $labels = [
            'generic' => [
                'name' => 'Generic POS',
                'description' => 'Universal adapter for any POS system',
                'icon' => 'dashicons-cart'
            ],
            'sumup' => [
                'name' => 'SumUp',
                'description' => 'SumUp POS Third-Party Loyalty Gateway',
                'icon' => 'dashicons-money-alt'
            ],
            'ready2order' => [
                'name' => 'ready2order',
                'description' => 'ready2order REST API integration',
                'icon' => 'dashicons-store'
            ],
            'zettle' => [
                'name' => 'Zettle (PayPal)',
                'description' => 'Zettle by PayPal integration',
                'icon' => 'dashicons-admin-site'
            ],
            'lightspeed' => [
                'name' => 'Lightspeed',
                'description' => 'Lightspeed POS integration',
                'icon' => 'dashicons-superhero'
            ]
        ];

        foreach (self::$adapters as $type => $adapter) {
            $info[$type] = $labels[$type] ?? [
                'name' => ucfirst($type),
                'description' => "Custom adapter: {$type}",
                'icon' => 'dashicons-admin-generic'
            ];
            $info[$type]['type'] = $type;
            $info[$type]['supported_rewards'] = $adapter->get_supported_reward_types();
            $info[$type]['config_schema'] = $adapter->get_config_schema();
        }

        return $info;
    }

    /**
     * Add admin menu (placeholder for future admin UI)
     */
    public static function add_admin_menu(): void {
        // This will be expanded for WordPress admin UI if needed
        // For now, handler dashboard is the primary management interface
    }

    // ========================================
    // Utility Methods
    // ========================================

    /**
     * Quick lookup - get customer by QR
     * Convenience method for direct use
     *
     * @param string $qr_code QR code
     * @param int $store_id Store ID
     * @param string $adapter_type Adapter type (default: generic)
     * @return array|null Customer data or null
     */
    public static function lookup_customer(string $qr_code, int $store_id, string $adapter_type = 'generic'): ?array {
        $adapter = self::get_adapter($adapter_type);
        return $adapter->lookup_by_qr($qr_code, $store_id);
    }

    /**
     * Quick method - process a transaction
     *
     * @param array $transaction_data Transaction data
     * @param int $gateway_id Gateway ID
     * @param string $adapter_type Adapter type
     * @return array Result
     */
    public static function process_transaction(array $transaction_data, int $gateway_id, string $adapter_type = 'generic'): array {
        $adapter = self::get_adapter($adapter_type);
        return $adapter->process_transaction($transaction_data, $gateway_id);
    }

    /**
     * Create a test/demo gateway for a store
     * Useful for testing integrations
     *
     * @param int $store_id Store ID
     * @return array Gateway credentials
     */
    public static function create_demo_gateway(int $store_id): array {
        return PPV_POS_Gateway_DB::create_gateway([
            'store_id' => $store_id,
            'gateway_type' => 'generic',
            'gateway_name' => 'Demo Gateway (Test)',
            'config' => ['demo_mode' => true]
        ]);
    }

    /**
     * Get API documentation URL
     *
     * @return string Documentation URL
     */
    public static function get_docs_url(): string {
        return site_url('/api-docs/pos-gateway/');
    }

    /**
     * Generate sample API request for documentation
     *
     * @param string $endpoint Endpoint name
     * @return array Sample request data
     */
    public static function get_sample_request(string $endpoint): array {
        $samples = [
            'qr_lookup' => [
                'url' => rest_url('punktepass/v1/pos-gateway/customer/qr-lookup'),
                'method' => 'POST',
                'headers' => [
                    'X-POS-API-Key' => 'pk_live_your_api_key_here',
                    'Content-Type' => 'application/json'
                ],
                'body' => [
                    'qr_code' => 'PP-U-abc123xyz'
                ]
            ],
            'transaction_complete' => [
                'url' => rest_url('punktepass/v1/pos-gateway/transaction/complete'),
                'method' => 'POST',
                'headers' => [
                    'X-POS-API-Key' => 'pk_live_your_api_key_here',
                    'X-Verification-Token' => 'vt_your_verification_token',
                    'Content-Type' => 'application/json'
                ],
                'body' => [
                    'transaction_id' => 'pos_tx_12345',
                    'customer_id' => '12345',
                    'subtotal' => 24.50,
                    'discount_applied' => [
                        'reward_id' => '1',
                        'amount' => 5.00
                    ],
                    'total' => 19.50,
                    'currency' => 'EUR',
                    'timestamp' => date('c')
                ]
            ],
            'get_rewards' => [
                'url' => rest_url('punktepass/v1/pos-gateway/customer/{id}/rewards'),
                'method' => 'GET',
                'headers' => [
                    'X-POS-API-Key' => 'pk_live_your_api_key_here'
                ],
                'params' => [
                    'cart_total' => 25.00
                ]
            ]
        ];

        return $samples[$endpoint] ?? [];
    }
}

// ========================================
// Initialize on plugins_loaded
// ========================================
add_action('plugins_loaded', ['PPV_POS_Gateway', 'init'], 20);
