<?php
/**
 * SumUp POS Gateway Adapter
 *
 * Adapter for SumUp Third-Party Loyalty Gateway integration.
 * Documentation: https://apidoc.thegoodtill.com/#api-ThirdPartyLoyalty
 *
 * SumUp sends requests to our endpoints and expects specific response formats.
 * This adapter translates between SumUp's format and PunktePass internal format.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class PPV_POS_Adapter_SumUp extends PPV_POS_Adapter_Generic {

    /**
     * @var string Adapter type identifier
     */
    protected string $type = 'sumup';

    /**
     * @var array Supported reward types (SumUp only supports these two)
     */
    protected array $supported_reward_types = ['money_off', 'free_product'];

    /**
     * Format response for SumUp's expected structure
     *
     * SumUp expects specific field names in responses:
     * - Customer lookup: account_code, first_name, last_name, points_balance, rewards
     * - Rewards: id, name, description, type ("money_off"|"free_product"), value/product_skus
     */
    public function format_response(array $data, string $endpoint = ''): array {
        switch ($endpoint) {
            case 'qr_lookup':
            case 'customer_search':
                return $this->format_customer_response($data);

            case 'rewards':
                return $this->format_rewards_response($data);

            case 'apply_reward':
                return $this->format_apply_reward_response($data);

            case 'transaction_complete':
                return $this->format_transaction_response($data);

            default:
                return $data;
        }
    }

    /**
     * Parse SumUp request format to internal format
     */
    public function parse_request(array $raw_request, string $endpoint = ''): array {
        switch ($endpoint) {
            case 'transaction_complete':
                return $this->parse_transaction_request($raw_request);

            default:
                return $raw_request;
        }
    }

    /**
     * Format customer data for SumUp
     *
     * SumUp expects:
     * {
     *   "account_code": "123",
     *   "first_name": "John",
     *   "last_name": "Doe",
     *   "points_balance": 500,
     *   "rewards": [...]
     * }
     */
    private function format_customer_response(array $customer): array {
        // Split name if we only have display_name
        $name_parts = $this->split_name($customer['name'] ?? '');

        $response = [
            'account_code' => (string) $customer['id'],
            'first_name' => $name_parts['first_name'],
            'last_name' => $name_parts['last_name'],
            'email' => $customer['email'] ?? '',
            'points_balance' => (int) ($customer['points_balance'] ?? 0),
        ];

        // Include rewards if available
        if (isset($customer['available_rewards'])) {
            $response['rewards'] = $this->format_rewards_response($customer['available_rewards']);
        }

        // Include VIP level if present
        if (!empty($customer['vip_level'])) {
            $response['tier'] = strtoupper($customer['vip_level']);
        }

        return $response;
    }

    /**
     * Format rewards for SumUp
     *
     * SumUp expects rewards in this format:
     * {
     *   "id": "1",
     *   "name": "€5 off",
     *   "description": "Get €5 off your purchase",
     *   "type": "money_off",
     *   "value": 5.00
     * }
     *
     * For free products:
     * {
     *   "type": "free_product",
     *   "product_skus": ["SKU1", "SKU2"]
     * }
     */
    private function format_rewards_response(array $rewards): array {
        $formatted = [];

        foreach ($rewards as $reward) {
            $type = $reward['type'] ?? 'money_off';

            // SumUp only supports money_off and free_product
            if ($type === 'percentage') {
                // Convert percentage to money_off estimate (they don't support %)
                // Skip percentage rewards for SumUp
                continue;
            }

            $formatted_reward = [
                'id' => (string) $reward['id'],
                'name' => $reward['title'] ?? $reward['name'] ?? '',
                'description' => $reward['description'] ?? '',
                'type' => $type,
                'points_required' => (int) ($reward['required_points'] ?? 0)
            ];

            if ($type === 'money_off') {
                $formatted_reward['value'] = (float) ($reward['value'] ?? 0);
            } elseif ($type === 'free_product') {
                $formatted_reward['product_skus'] = is_array($reward['value'])
                    ? $reward['value']
                    : [$reward['value']];
            }

            $formatted[] = $formatted_reward;
        }

        return $formatted;
    }

    /**
     * Format apply reward response for SumUp
     */
    private function format_apply_reward_response(array $result): array {
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'reward_id' => $result['reward_id'],
            'reward_name' => $result['reward_title'],
            'discount_type' => $result['reward_type'],
            'discount_value' => $result['discount_amount'],
            'new_total' => $result['final_total'],
            'points_deducted' => $result['points_to_deduct'],
            'product_skus' => $result['sku'] ?? null
        ];
    }

    /**
     * Format transaction complete response for SumUp
     */
    private function format_transaction_response(array $result): array {
        if (!$result['success']) {
            return $result;
        }

        return [
            'success' => true,
            'points_earned' => $result['points_earned'],
            'points_redeemed' => $result['points_spent'],
            'new_points_balance' => $result['new_balance']
        ];
    }

    /**
     * Parse SumUp transaction request format
     *
     * SumUp sends:
     * {
     *   "sale_id": "abc123",
     *   "account_code": "123",
     *   "subtotal": 25.00,
     *   "total": 20.00,
     *   "discount": {
     *     "reward_id": "1",
     *     "amount": 5.00
     *   },
     *   "currency": "EUR"
     * }
     */
    private function parse_transaction_request(array $request): array {
        return [
            'transaction_id' => $request['sale_id'] ?? $request['transaction_id'] ?? null,
            'customer_id' => $request['account_code'] ?? $request['customer_id'] ?? null,
            'subtotal' => (float) ($request['subtotal'] ?? 0),
            'total' => (float) ($request['total'] ?? $request['subtotal'] ?? 0),
            'discount_applied' => isset($request['discount']) ? [
                'reward_id' => $request['discount']['reward_id'] ?? null,
                'amount' => (float) ($request['discount']['amount'] ?? 0)
            ] : ($request['discount_applied'] ?? null),
            'currency' => $request['currency'] ?? 'EUR',
            'timestamp' => $request['timestamp'] ?? current_time('c')
        ];
    }

    /**
     * Validate SumUp request with Verification-Token
     */
    public function validate_request(WP_REST_Request $request, array $gateway): bool {
        // SumUp sends Verification-Token header
        $verification_token = $request->get_header('Verification-Token')
            ?? $request->get_header('X-Verification-Token');

        if (!empty($gateway['verification_token'])) {
            if ($verification_token !== $gateway['verification_token']) {
                ppv_log("⚠️ [SumUp Adapter] Invalid verification token");
                return false;
            }
        }

        return true;
    }

    /**
     * Get config schema specific to SumUp
     */
    public function get_config_schema(): array {
        return [
            'verification_token' => [
                'type' => 'string',
                'label' => 'Verification Token (SumUp Back Office)',
                'description' => 'Copy this token from your SumUp POS Back Office → Integrations → Loyalty',
                'required' => true
            ],
            'location_id' => [
                'type' => 'string',
                'label' => 'SumUp Location ID',
                'description' => 'Your SumUp location identifier (optional)',
                'required' => false
            ]
        ];
    }

    /**
     * Split full name into first and last name
     */
    private function split_name(string $full_name): array {
        $parts = explode(' ', trim($full_name), 2);
        return [
            'first_name' => $parts[0] ?? '',
            'last_name' => $parts[1] ?? ''
        ];
    }
}
