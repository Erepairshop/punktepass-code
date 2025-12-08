<?php
/**
 * POS Gateway Database Operations
 *
 * Handles all database operations for POS Gateway functionality.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPV_POS_Gateway_DB {

    /**
     * Table names
     */
    private static function gateways_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ppv_pos_gateways';
    }

    private static function transactions_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'ppv_pos_gateway_transactions';
    }

    // ========================================
    // Gateway CRUD
    // ========================================

    /**
     * Get gateway by ID
     */
    public static function get_gateway(int $gateway_id): ?array {
        global $wpdb;

        $gateway = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM " . self::gateways_table() . "
            WHERE id = %d
        ", $gateway_id), ARRAY_A);

        if ($gateway && !empty($gateway['config'])) {
            $gateway['config'] = json_decode($gateway['config'], true);
        }

        return $gateway ?: null;
    }

    /**
     * Get gateway by API key
     */
    public static function get_gateway_by_api_key(string $api_key): ?array {
        global $wpdb;

        $gateway = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM " . self::gateways_table() . "
            WHERE api_key = %s
        ", $api_key), ARRAY_A);

        if ($gateway && !empty($gateway['config'])) {
            $gateway['config'] = json_decode($gateway['config'], true);
        }

        return $gateway ?: null;
    }

    /**
     * Get all gateways for a store
     */
    public static function get_gateways_by_store(int $store_id): array {
        global $wpdb;

        $gateways = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM " . self::gateways_table() . "
            WHERE store_id = %d
            ORDER BY created_at DESC
        ", $store_id), ARRAY_A);

        foreach ($gateways as &$gateway) {
            if (!empty($gateway['config'])) {
                $gateway['config'] = json_decode($gateway['config'], true);
            }
        }

        return $gateways;
    }

    /**
     * Create new gateway
     */
    public static function create_gateway(array $data): array {
        global $wpdb;

        // Generate unique API key
        $api_key = self::generate_api_key();

        // Ensure unique
        $attempts = 0;
        while (self::get_gateway_by_api_key($api_key) && $attempts < 10) {
            $api_key = self::generate_api_key();
            $attempts++;
        }

        $insert_data = [
            'store_id' => (int) $data['store_id'],
            'gateway_type' => sanitize_text_field($data['gateway_type'] ?? 'generic'),
            'gateway_name' => sanitize_text_field($data['gateway_name'] ?? ''),
            'api_key' => $api_key,
            'verification_token' => !empty($data['verification_token'])
                ? sanitize_text_field($data['verification_token'])
                : self::generate_verification_token(),
            'config' => !empty($data['config']) ? json_encode($data['config']) : null,
            'active' => 1,
            'created_at' => current_time('mysql')
        ];

        $result = $wpdb->insert(self::gateways_table(), $insert_data);

        if ($result === false) {
            return [
                'success' => false,
                'message' => 'Database insert failed: ' . $wpdb->last_error
            ];
        }

        $gateway_id = $wpdb->insert_id;

        return [
            'success' => true,
            'gateway' => [
                'id' => $gateway_id,
                'api_key' => $api_key,
                'verification_token' => $insert_data['verification_token'],
                'gateway_type' => $insert_data['gateway_type'],
                'gateway_name' => $insert_data['gateway_name']
            ]
        ];
    }

    /**
     * Update gateway
     */
    public static function update_gateway(int $gateway_id, array $data): bool {
        global $wpdb;

        $update_data = [];

        if (isset($data['gateway_name'])) {
            $update_data['gateway_name'] = sanitize_text_field($data['gateway_name']);
        }
        if (isset($data['gateway_type'])) {
            $update_data['gateway_type'] = sanitize_text_field($data['gateway_type']);
        }
        if (isset($data['verification_token'])) {
            $update_data['verification_token'] = sanitize_text_field($data['verification_token']);
        }
        if (isset($data['config'])) {
            $update_data['config'] = is_array($data['config'])
                ? json_encode($data['config'])
                : $data['config'];
        }
        if (isset($data['active'])) {
            $update_data['active'] = (int) $data['active'];
        }

        if (empty($update_data)) {
            return false;
        }

        $result = $wpdb->update(
            self::gateways_table(),
            $update_data,
            ['id' => $gateway_id]
        );

        return $result !== false;
    }

    /**
     * Delete gateway
     */
    public static function delete_gateway(int $gateway_id): bool {
        global $wpdb;

        // Soft delete - just deactivate
        // Or hard delete if preferred
        $result = $wpdb->delete(
            self::gateways_table(),
            ['id' => $gateway_id]
        );

        return $result !== false;
    }

    /**
     * Regenerate API key for gateway
     */
    public static function regenerate_api_key(int $gateway_id): ?string {
        global $wpdb;

        $new_key = self::generate_api_key();

        $result = $wpdb->update(
            self::gateways_table(),
            ['api_key' => $new_key],
            ['id' => $gateway_id]
        );

        return $result !== false ? $new_key : null;
    }

    /**
     * Update last activity timestamp
     */
    public static function update_last_activity(int $gateway_id): void {
        global $wpdb;

        $wpdb->update(
            self::gateways_table(),
            ['last_activity_at' => current_time('mysql')],
            ['id' => $gateway_id]
        );
    }

    // ========================================
    // Transactions
    // ========================================

    /**
     * Get transactions for gateway
     */
    public static function get_transactions(int $gateway_id, int $limit = 50, int $offset = 0): array {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("
            SELECT
                t.*,
                u.display_name as customer_name,
                u.email as customer_email
            FROM " . self::transactions_table() . " t
            LEFT JOIN {$wpdb->prefix}ppv_users u ON u.id = t.user_id
            WHERE t.gateway_id = %d
            ORDER BY t.created_at DESC
            LIMIT %d OFFSET %d
        ", $gateway_id, $limit, $offset), ARRAY_A);
    }

    /**
     * Get transaction by external ID
     */
    public static function get_transaction_by_external_id(string $external_id, int $gateway_id): ?array {
        global $wpdb;

        return $wpdb->get_row($wpdb->prepare("
            SELECT * FROM " . self::transactions_table() . "
            WHERE external_transaction_id = %s AND gateway_id = %d
        ", $external_id, $gateway_id), ARRAY_A);
    }

    /**
     * Get gateway statistics
     */
    public static function get_gateway_stats(int $gateway_id, string $period = 'today'): array {
        global $wpdb;

        $date_filter = '';
        switch ($period) {
            case 'today':
                $date_filter = "AND DATE(created_at) = CURDATE()";
                break;
            case 'week':
                $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
                break;
            case 'month':
                $date_filter = "AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
                break;
        }

        $stats = $wpdb->get_row($wpdb->prepare("
            SELECT
                COUNT(*) as total_transactions,
                COUNT(DISTINCT user_id) as unique_customers,
                COALESCE(SUM(total), 0) as total_revenue,
                COALESCE(SUM(discount_amount), 0) as total_discounts,
                COALESCE(SUM(points_earned), 0) as total_points_earned,
                COALESCE(SUM(points_spent), 0) as total_points_spent
            FROM " . self::transactions_table() . "
            WHERE gateway_id = %d AND status = 'completed' {$date_filter}
        ", $gateway_id), ARRAY_A);

        return $stats ?: [
            'total_transactions' => 0,
            'unique_customers' => 0,
            'total_revenue' => 0,
            'total_discounts' => 0,
            'total_points_earned' => 0,
            'total_points_spent' => 0
        ];
    }

    // ========================================
    // Table Creation (for plugin activation)
    // ========================================

    /**
     * Create database tables
     */
    public static function create_tables(): void {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $gateways_sql = "CREATE TABLE IF NOT EXISTS " . self::gateways_table() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            store_id BIGINT UNSIGNED NOT NULL,
            gateway_type VARCHAR(50) NOT NULL DEFAULT 'generic',
            gateway_name VARCHAR(100),
            api_key VARCHAR(64) NOT NULL UNIQUE,
            verification_token VARCHAR(64),
            config JSON,
            active TINYINT(1) DEFAULT 1,
            last_activity_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_store (store_id),
            INDEX idx_api_key (api_key),
            INDEX idx_type (gateway_type)
        ) {$charset_collate};";

        $transactions_sql = "CREATE TABLE IF NOT EXISTS " . self::transactions_table() . " (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            gateway_id BIGINT UNSIGNED NOT NULL,
            store_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED,
            external_transaction_id VARCHAR(100),
            subtotal DECIMAL(10,2),
            discount_amount DECIMAL(10,2) DEFAULT 0,
            total DECIMAL(10,2),
            currency VARCHAR(3) DEFAULT 'EUR',
            reward_id BIGINT UNSIGNED,
            points_earned INT DEFAULT 0,
            points_spent INT DEFAULT 0,
            status VARCHAR(20) DEFAULT 'completed',
            raw_request JSON,
            raw_response JSON,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_gateway (gateway_id),
            INDEX idx_store (store_id),
            INDEX idx_user (user_id),
            INDEX idx_external (external_transaction_id),
            INDEX idx_created (created_at)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($gateways_sql);
        dbDelta($transactions_sql);
    }

    // ========================================
    // Helpers
    // ========================================

    /**
     * Generate API key
     * Format: pk_live_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (40 chars total)
     */
    private static function generate_api_key(): string {
        $prefix = 'pk_live_';
        $random = bin2hex(random_bytes(16)); // 32 hex chars
        return $prefix . $random;
    }

    /**
     * Generate verification token
     * Format: vt_XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX (35 chars total)
     */
    private static function generate_verification_token(): string {
        $prefix = 'vt_';
        $random = bin2hex(random_bytes(16)); // 32 hex chars
        return $prefix . $random;
    }
}
