<?php
/**
 * Generic POS Gateway Adapter
 *
 * Base adapter implementation that works with any POS system.
 * Other adapters (SumUp, ready2order) extend this class.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPV_POS_Adapter_Generic implements PPV_POS_Adapter_Interface {

    /**
     * @var string Adapter type identifier
     */
    protected string $type = 'generic';

    /**
     * @var array Supported reward types
     */
    protected array $supported_reward_types = ['money_off', 'percentage', 'free_product'];

    /**
     * Get adapter type
     */
    public function get_type(): string {
        return $this->type;
    }

    /**
     * Search customers by name or email
     */
    public function search_customer(string $query, int $store_id, int $limit = 10): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $like_query = '%' . $wpdb->esc_like($query) . '%';

        $users = $wpdb->get_results($wpdb->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.display_name,
                u.email,
                COALESCE(SUM(p.points), 0) as points_balance
            FROM {$prefix}ppv_users u
            LEFT JOIN {$prefix}ppv_points p ON p.user_id = u.id AND p.store_id = %d
            WHERE u.active = 1
              AND u.user_type = 'customer'
              AND (
                  u.display_name LIKE %s
                  OR u.first_name LIKE %s
                  OR u.last_name LIKE %s
                  OR u.email LIKE %s
                  OR CONCAT(u.first_name, ' ', u.last_name) LIKE %s
              )
            GROUP BY u.id
            ORDER BY u.display_name ASC
            LIMIT %d
        ", $store_id, $like_query, $like_query, $like_query, $like_query, $like_query, $limit), ARRAY_A);

        $results = [];
        foreach ($users as $user) {
            $results[] = [
                'id' => (string) $user['id'],
                'name' => $user['display_name'] ?: trim($user['first_name'] . ' ' . $user['last_name']),
                'email' => $user['email'],
                'points_balance' => (int) $user['points_balance']
            ];
        }

        return $results;
    }

    /**
     * Lookup customer by QR code
     */
    public function lookup_by_qr(string $qr_code, int $store_id): ?array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Decode QR code to user ID
        $user_id = $this->decode_qr_code($qr_code);
        if (!$user_id) {
            return null;
        }

        // Get user data with points balance for this store
        $user = $wpdb->get_row($wpdb->prepare("
            SELECT
                u.id,
                u.first_name,
                u.last_name,
                u.display_name,
                u.email,
                u.lifetime_points,
                COALESCE(SUM(p.points), 0) as points_balance
            FROM {$prefix}ppv_users u
            LEFT JOIN {$prefix}ppv_points p ON p.user_id = u.id AND p.store_id = %d
            WHERE u.id = %d
              AND u.active = 1
            GROUP BY u.id
            LIMIT 1
        ", $store_id, $user_id), ARRAY_A);

        if (!$user) {
            return null;
        }

        // Get VIP level
        $vip_level = $this->calculate_vip_level($user['lifetime_points'], $store_id);

        // Get available rewards
        $rewards = $this->get_available_rewards($user_id, $store_id);

        return [
            'id' => (string) $user['id'],
            'name' => $user['display_name'] ?: trim($user['first_name'] . ' ' . $user['last_name']),
            'email' => $user['email'],
            'points_balance' => (int) $user['points_balance'],
            'lifetime_points' => (int) $user['lifetime_points'],
            'vip_level' => $vip_level,
            'available_rewards' => $rewards
        ];
    }

    /**
     * Get customer balance
     */
    public function get_balance(int $user_id, int $store_id): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $data = $wpdb->get_row($wpdb->prepare("
            SELECT
                u.lifetime_points,
                COALESCE(SUM(p.points), 0) as points_balance
            FROM {$prefix}ppv_users u
            LEFT JOIN {$prefix}ppv_points p ON p.user_id = u.id AND p.store_id = %d
            WHERE u.id = %d
            GROUP BY u.id
        ", $store_id, $user_id), ARRAY_A);

        if (!$data) {
            return [
                'points_balance' => 0,
                'lifetime_points' => 0,
                'vip_level' => null
            ];
        }

        return [
            'points_balance' => (int) $data['points_balance'],
            'lifetime_points' => (int) $data['lifetime_points'],
            'vip_level' => $this->calculate_vip_level($data['lifetime_points'], $store_id)
        ];
    }

    /**
     * Get available rewards for customer
     */
    public function get_available_rewards(int $user_id, int $store_id, ?float $cart_total = null): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get user's current point balance
        $points_balance = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0)
            FROM {$prefix}ppv_points
            WHERE user_id = %d AND store_id = %d
        ", $user_id, $store_id));

        // Get active rewards for this store
        $rewards = $wpdb->get_results($wpdb->prepare("
            SELECT
                id,
                title,
                description,
                action_type,
                action_value,
                required_points
            FROM {$prefix}ppv_rewards
            WHERE store_id = %d
              AND active = 1
              AND (start_date IS NULL OR start_date <= NOW())
              AND (end_date IS NULL OR end_date >= NOW())
              AND required_points <= %d
            ORDER BY required_points ASC
        ", $store_id, $points_balance), ARRAY_A);

        $available = [];
        foreach ($rewards as $reward) {
            $reward_data = [
                'id' => (string) $reward['id'],
                'title' => $reward['title'],
                'description' => $reward['description'],
                'required_points' => (int) $reward['required_points'],
                'type' => $this->map_reward_type($reward['action_type']),
                'value' => $this->parse_reward_value($reward['action_type'], $reward['action_value'])
            ];

            // Filter by cart total if provided
            if ($cart_total !== null) {
                if ($reward_data['type'] === 'money_off' && $reward_data['value'] > $cart_total) {
                    continue; // Skip rewards that exceed cart total
                }
            }

            $available[] = $reward_data;
        }

        return $available;
    }

    /**
     * Apply reward to transaction
     */
    public function apply_reward(int $user_id, int $reward_id, float $cart_total, int $store_id): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get reward details
        $reward = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}ppv_rewards
            WHERE id = %d AND store_id = %d AND active = 1
        ", $reward_id, $store_id), ARRAY_A);

        if (!$reward) {
            return [
                'success' => false,
                'error' => 'reward_not_found',
                'message' => 'Reward not found or inactive'
            ];
        }

        // Check user has enough points
        $points_balance = (int) $wpdb->get_var($wpdb->prepare("
            SELECT COALESCE(SUM(points), 0)
            FROM {$prefix}ppv_points
            WHERE user_id = %d AND store_id = %d
        ", $user_id, $store_id));

        if ($points_balance < $reward['required_points']) {
            return [
                'success' => false,
                'error' => 'insufficient_points',
                'message' => 'Not enough points',
                'required' => (int) $reward['required_points'],
                'available' => $points_balance
            ];
        }

        // Calculate discount
        $discount_amount = 0;
        $reward_type = $this->map_reward_type($reward['action_type']);

        switch ($reward_type) {
            case 'money_off':
                $discount_amount = (float) $reward['action_value'];
                break;
            case 'percentage':
                $percentage = (float) $reward['action_value'];
                $discount_amount = round($cart_total * ($percentage / 100), 2);
                break;
            case 'free_product':
                // Free product - discount is handled by POS via SKU
                $discount_amount = 0;
                break;
        }

        // Ensure discount doesn't exceed cart total
        $discount_amount = min($discount_amount, $cart_total);
        $final_total = $cart_total - $discount_amount;

        return [
            'success' => true,
            'reward_id' => (string) $reward_id,
            'reward_title' => $reward['title'],
            'reward_type' => $reward_type,
            'discount_amount' => $discount_amount,
            'final_total' => $final_total,
            'points_to_deduct' => (int) $reward['required_points'],
            'sku' => $reward_type === 'free_product' ? $this->parse_sku_list($reward['action_value']) : null
        ];
    }

    /**
     * Process completed transaction
     */
    public function process_transaction(array $transaction_data, int $gateway_id): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $user_id = (int) ($transaction_data['customer_id'] ?? 0);
        $store_id = (int) ($transaction_data['store_id'] ?? 0);
        $subtotal = (float) ($transaction_data['subtotal'] ?? 0);
        $total = (float) ($transaction_data['total'] ?? $subtotal);
        $reward_id = isset($transaction_data['discount_applied']['reward_id'])
            ? (int) $transaction_data['discount_applied']['reward_id']
            : null;
        $discount_amount = (float) ($transaction_data['discount_applied']['amount'] ?? 0);
        $external_id = $transaction_data['transaction_id'] ?? null;
        $currency = $transaction_data['currency'] ?? 'EUR';

        // Start transaction
        $wpdb->query('START TRANSACTION');

        try {
            $points_earned = 0;
            $points_spent = 0;

            // Calculate points to earn (1 point per EUR spent, before discount)
            if ($user_id > 0 && $subtotal > 0) {
                $store_config = $this->get_store_config($store_id);
                $points_per_unit = $store_config['points_per_unit'] ?? 1;
                $points_earned = (int) floor($subtotal * $points_per_unit);

                // Apply bonus multiplier if active
                $multiplier = $this->get_active_bonus_multiplier($store_id);
                $points_earned = (int) floor($points_earned * $multiplier);

                // Add points
                if ($points_earned > 0) {
                    $wpdb->insert("{$prefix}ppv_points", [
                        'user_id' => $user_id,
                        'store_id' => $store_id,
                        'points' => $points_earned,
                        'type' => 'sale',
                        'reference' => 'POS Gateway: ' . ($external_id ?? 'N/A'),
                        'created' => current_time('mysql')
                    ]);

                    // Update lifetime points
                    $wpdb->query($wpdb->prepare("
                        UPDATE {$prefix}ppv_users
                        SET lifetime_points = lifetime_points + %d
                        WHERE id = %d
                    ", $points_earned, $user_id));
                }
            }

            // Deduct points for reward redemption
            if ($user_id > 0 && $reward_id) {
                $reward = $wpdb->get_row($wpdb->prepare("
                    SELECT required_points FROM {$prefix}ppv_rewards WHERE id = %d
                ", $reward_id));

                if ($reward) {
                    $points_spent = (int) $reward->required_points;

                    // Deduct points
                    $wpdb->insert("{$prefix}ppv_points", [
                        'user_id' => $user_id,
                        'store_id' => $store_id,
                        'points' => -$points_spent,
                        'type' => 'redeem',
                        'reference' => 'Reward #' . $reward_id,
                        'created' => current_time('mysql')
                    ]);
                }
            }

            // Get new balance
            $new_balance = (int) $wpdb->get_var($wpdb->prepare("
                SELECT COALESCE(SUM(points), 0)
                FROM {$prefix}ppv_points
                WHERE user_id = %d AND store_id = %d
            ", $user_id, $store_id));

            // Log transaction
            $wpdb->insert("{$prefix}ppv_pos_gateway_transactions", [
                'gateway_id' => $gateway_id,
                'store_id' => $store_id,
                'user_id' => $user_id ?: null,
                'external_transaction_id' => $external_id,
                'subtotal' => $subtotal,
                'discount_amount' => $discount_amount,
                'total' => $total,
                'currency' => $currency,
                'reward_id' => $reward_id,
                'points_earned' => $points_earned,
                'points_spent' => $points_spent,
                'status' => 'completed',
                'raw_request' => json_encode($transaction_data),
                'created_at' => current_time('mysql')
            ]);

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'points_earned' => $points_earned,
                'points_spent' => $points_spent,
                'new_balance' => $new_balance,
                'transaction_logged' => true
            ];

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return [
                'success' => false,
                'error' => 'transaction_failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Cancel transaction
     */
    public function cancel_transaction(string $external_transaction_id, int $gateway_id): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Find the transaction
        $transaction = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}ppv_pos_gateway_transactions
            WHERE external_transaction_id = %s AND gateway_id = %d AND status = 'completed'
        ", $external_transaction_id, $gateway_id), ARRAY_A);

        if (!$transaction) {
            return [
                'success' => false,
                'error' => 'transaction_not_found',
                'message' => 'Transaction not found or already cancelled'
            ];
        }

        $wpdb->query('START TRANSACTION');

        try {
            $user_id = (int) $transaction['user_id'];
            $store_id = (int) $transaction['store_id'];

            // Reverse points earned
            if ($transaction['points_earned'] > 0 && $user_id > 0) {
                $wpdb->insert("{$prefix}ppv_points", [
                    'user_id' => $user_id,
                    'store_id' => $store_id,
                    'points' => -$transaction['points_earned'],
                    'type' => 'cancel',
                    'reference' => 'Cancel: ' . $external_transaction_id,
                    'created' => current_time('mysql')
                ]);

                // Update lifetime points
                $wpdb->query($wpdb->prepare("
                    UPDATE {$prefix}ppv_users
                    SET lifetime_points = GREATEST(0, lifetime_points - %d)
                    WHERE id = %d
                ", $transaction['points_earned'], $user_id));
            }

            // Refund points spent
            if ($transaction['points_spent'] > 0 && $user_id > 0) {
                $wpdb->insert("{$prefix}ppv_points", [
                    'user_id' => $user_id,
                    'store_id' => $store_id,
                    'points' => $transaction['points_spent'],
                    'type' => 'refund',
                    'reference' => 'Refund: ' . $external_transaction_id,
                    'created' => current_time('mysql')
                ]);
            }

            // Update transaction status
            $wpdb->update(
                "{$prefix}ppv_pos_gateway_transactions",
                ['status' => 'cancelled'],
                ['id' => $transaction['id']]
            );

            $wpdb->query('COMMIT');

            return [
                'success' => true,
                'points_refunded' => (int) $transaction['points_spent'],
                'points_reversed' => (int) $transaction['points_earned']
            ];

        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            return [
                'success' => false,
                'error' => 'cancel_failed',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Format response (can be overridden by specific adapters)
     */
    public function format_response(array $data, string $endpoint = ''): array {
        return $data;
    }

    /**
     * Parse request (can be overridden by specific adapters)
     */
    public function parse_request(array $raw_request, string $endpoint = ''): array {
        return $raw_request;
    }

    /**
     * Validate request
     */
    public function validate_request(WP_REST_Request $request, array $gateway): bool {
        // Check verification token if provided
        $verification_token = $request->get_header('X-Verification-Token');
        if (!empty($gateway['verification_token']) && $verification_token !== $gateway['verification_token']) {
            return false;
        }
        return true;
    }

    /**
     * Get supported reward types
     */
    public function get_supported_reward_types(): array {
        return $this->supported_reward_types;
    }

    /**
     * Get config schema
     */
    public function get_config_schema(): array {
        return [
            'verification_token' => [
                'type' => 'string',
                'label' => 'Verification Token',
                'description' => 'Optional token for webhook verification',
                'required' => false
            ],
            'ip_whitelist' => [
                'type' => 'array',
                'label' => 'IP Whitelist',
                'description' => 'Allowed IP addresses (empty = all allowed)',
                'required' => false
            ]
        ];
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Decode QR code to user ID
     */
    protected function decode_qr_code(string $qr_code): ?int {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Try direct qr_token match
        $user_id = $wpdb->get_var($wpdb->prepare("
            SELECT id FROM {$prefix}ppv_users
            WHERE qr_token = %s AND active = 1
            LIMIT 1
        ", $qr_code));

        if ($user_id) {
            return (int) $user_id;
        }

        // Try PP-U-{base64} format
        if (preg_match('/^PP-U-(.+)$/i', $qr_code, $matches)) {
            $decoded = base64_decode($matches[1]);
            if ($decoded && is_numeric($decoded)) {
                return (int) $decoded;
            }
        }

        // Try numeric ID directly
        if (is_numeric($qr_code)) {
            $exists = $wpdb->get_var($wpdb->prepare("
                SELECT id FROM {$prefix}ppv_users WHERE id = %d AND active = 1
            ", (int) $qr_code));
            if ($exists) {
                return (int) $qr_code;
            }
        }

        return null;
    }

    /**
     * Calculate VIP level based on lifetime points
     */
    protected function calculate_vip_level(int $lifetime_points, int $store_id): ?string {
        global $wpdb;
        $prefix = $wpdb->prefix;

        // Get store VIP configuration
        $store = $wpdb->get_row($wpdb->prepare("
            SELECT vip_enabled, vip_bronze_threshold, vip_silver_threshold,
                   vip_gold_threshold, vip_platinum_threshold
            FROM {$prefix}ppv_stores
            WHERE id = %d
        ", $store_id));

        if (!$store || !$store->vip_enabled) {
            return null;
        }

        if ($lifetime_points >= ($store->vip_platinum_threshold ?? 10000)) {
            return 'platinum';
        } elseif ($lifetime_points >= ($store->vip_gold_threshold ?? 5000)) {
            return 'gold';
        } elseif ($lifetime_points >= ($store->vip_silver_threshold ?? 1000)) {
            return 'silver';
        } elseif ($lifetime_points >= ($store->vip_bronze_threshold ?? 100)) {
            return 'bronze';
        }

        return null;
    }

    /**
     * Map internal reward type to standard type
     */
    protected function map_reward_type(string $action_type): string {
        $map = [
            'discount' => 'money_off',
            'discount_percent' => 'percentage',
            'percentage' => 'percentage',
            'free_product' => 'free_product',
            'free' => 'free_product',
            'info' => 'info'
        ];

        return $map[$action_type] ?? 'money_off';
    }

    /**
     * Parse reward value based on type
     */
    protected function parse_reward_value(string $action_type, string $action_value) {
        switch ($action_type) {
            case 'discount':
            case 'discount_percent':
            case 'percentage':
                return (float) $action_value;
            case 'free_product':
            case 'free':
                return $this->parse_sku_list($action_value);
            default:
                return $action_value;
        }
    }

    /**
     * Parse SKU list from string
     */
    protected function parse_sku_list(string $value): array {
        // Handle comma-separated or JSON array
        if (strpos($value, '[') === 0) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        return array_map('trim', explode(',', $value));
    }

    /**
     * Get store configuration
     */
    protected function get_store_config(int $store_id): array {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $store = $wpdb->get_row($wpdb->prepare("
            SELECT * FROM {$prefix}ppv_stores WHERE id = %d
        ", $store_id), ARRAY_A);

        return $store ?: [];
    }

    /**
     * Get active bonus multiplier
     */
    protected function get_active_bonus_multiplier(int $store_id): float {
        global $wpdb;
        $prefix = $wpdb->prefix;

        $bonus = $wpdb->get_var($wpdb->prepare("
            SELECT multiplier FROM {$prefix}ppv_bonus_days
            WHERE store_id = %d
              AND active = 1
              AND DATE(NOW()) BETWEEN start_date AND end_date
            ORDER BY multiplier DESC
            LIMIT 1
        ", $store_id));

        return $bonus ? (float) $bonus : 1.0;
    }
}
