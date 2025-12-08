<?php
/**
 * POS Gateway Adapter Interface
 *
 * Defines the contract for all POS system adapters.
 * Each POS system (SumUp, ready2order, Zettle, etc.) implements this interface.
 *
 * @package PunktePass
 * @subpackage POS_Gateway
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PPV_POS_Adapter_Interface {

    /**
     * Get adapter type identifier
     *
     * @return string Adapter type (e.g., 'sumup', 'ready2order', 'generic')
     */
    public function get_type(): string;

    /**
     * Search customers by name or email
     *
     * @param string $query Search query (name, email, or phone)
     * @param int $store_id Store ID to search within
     * @param int $limit Maximum results to return
     * @return array List of matching customers
     */
    public function search_customer(string $query, int $store_id, int $limit = 10): array;

    /**
     * Lookup customer by QR code
     *
     * @param string $qr_code Raw QR code data (e.g., "PP-U-abc123")
     * @param int $store_id Store ID for context
     * @return array|null Customer data or null if not found
     */
    public function lookup_by_qr(string $qr_code, int $store_id): ?array;

    /**
     * Get customer's current point balance
     *
     * @param int $user_id PunktePass user ID
     * @param int $store_id Store ID
     * @return array Balance info with points, VIP level, etc.
     */
    public function get_balance(int $user_id, int $store_id): array;

    /**
     * Get available rewards for a customer
     *
     * @param int $user_id PunktePass user ID
     * @param int $store_id Store ID
     * @param float|null $cart_total Optional cart total to filter applicable rewards
     * @return array List of available rewards
     */
    public function get_available_rewards(int $user_id, int $store_id, ?float $cart_total = null): array;

    /**
     * Apply a reward/discount to a transaction
     *
     * @param int $user_id PunktePass user ID
     * @param int $reward_id Reward ID to apply
     * @param float $cart_total Current cart total
     * @param int $store_id Store ID
     * @return array Result with discount_amount, final_total, points_to_deduct
     */
    public function apply_reward(int $user_id, int $reward_id, float $cart_total, int $store_id): array;

    /**
     * Process completed transaction
     * Handles point earning and reward redemption
     *
     * @param array $transaction_data Transaction details from POS
     * @param int $gateway_id Gateway ID that sent the transaction
     * @return array Result with points_earned, points_spent, new_balance
     */
    public function process_transaction(array $transaction_data, int $gateway_id): array;

    /**
     * Cancel/reverse a transaction
     *
     * @param string $external_transaction_id POS transaction ID
     * @param int $gateway_id Gateway ID
     * @return array Result of cancellation
     */
    public function cancel_transaction(string $external_transaction_id, int $gateway_id): array;

    /**
     * Format response data for POS system
     * Converts internal format to POS-specific format
     *
     * @param array $data Internal response data
     * @param string $endpoint Endpoint name for context
     * @return array Formatted response for POS
     */
    public function format_response(array $data, string $endpoint = ''): array;

    /**
     * Parse incoming request from POS system
     * Converts POS-specific format to internal format
     *
     * @param array $raw_request Raw request data from POS
     * @param string $endpoint Endpoint name for context
     * @return array Normalized request data
     */
    public function parse_request(array $raw_request, string $endpoint = ''): array;

    /**
     * Validate incoming request signature/token
     *
     * @param WP_REST_Request $request The incoming request
     * @param array $gateway Gateway configuration
     * @return bool True if valid, false otherwise
     */
    public function validate_request(WP_REST_Request $request, array $gateway): bool;

    /**
     * Get supported reward types for this POS
     *
     * @return array List of supported reward types (e.g., ['money_off', 'free_product', 'percentage'])
     */
    public function get_supported_reward_types(): array;

    /**
     * Get adapter-specific configuration schema
     * Used for admin UI to show configuration options
     *
     * @return array Configuration schema
     */
    public function get_config_schema(): array;
}
