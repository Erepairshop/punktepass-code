<?php

/** CLI worker that exports eRepairShop WooCommerce orders for the standalone admin. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$shop_wp_load = '/var/www/shop.erepairshop.de/public/wp-load.php';
$target = '/var/lib/punktepass/webshop-orders.json';
if (!is_file($shop_wp_load)) {
    fwrite(STDERR, "Shop wp-load.php not found\n");
    exit(2);
}

define('WP_USE_THEMES', false);
require_once $shop_wp_load;
if (!function_exists('wc_get_orders')) {
    fwrite(STDERR, "WooCommerce is not available\n");
    exit(3);
}

$orders = wc_get_orders([
    'limit' => 500,
    'orderby' => 'date',
    'order' => 'DESC',
    'return' => 'objects',
    'date_created' => '>=' . gmdate('Y-m-d', time() - (180 * DAY_IN_SECONDS)),
]);

$rows = [];
foreach ($orders as $order) {
    if (!$order instanceof WC_Order) continue;
    $items = [];
    foreach ($order->get_items('line_item') as $item) {
        $product = $item->get_product();
        $meta = [];
        foreach ($item->get_formatted_meta_data('') as $entry) {
            $meta[] = trim(wp_strip_all_tags($entry->display_key . ': ' . $entry->display_value));
        }
        $items[] = [
            'name' => $item->get_name(),
            'quantity' => (int)$item->get_quantity(),
            'sku' => $product ? (string)$product->get_sku() : '',
            'meta' => $meta,
            'total' => (float)$item->get_total() + (float)$item->get_total_tax(),
        ];
    }
    $shipping_methods = [];
    foreach ($order->get_shipping_methods() as $shipping_item) {
        $shipping_methods[] = $shipping_item->get_name();
    }
    $created = $order->get_date_created();
    $modified = $order->get_date_modified();
    $rows[] = [
        'id' => (int)$order->get_id(),
        'number' => (string)$order->get_order_number(),
        'createdAt' => $created ? $created->date('Y-m-d H:i:s') : '',
        'modifiedAt' => $modified ? $modified->date('Y-m-d H:i:s') : '',
        'status' => (string)$order->get_status(),
        'paymentMethod' => (string)$order->get_payment_method_title(),
        'shippingMethods' => $shipping_methods,
        'customerNote' => (string)$order->get_customer_note(),
        'shipping' => [
            'firstName' => (string)$order->get_shipping_first_name(),
            'lastName' => (string)$order->get_shipping_last_name(),
            'company' => (string)$order->get_shipping_company(),
            'address1' => (string)$order->get_shipping_address_1(),
            'address2' => (string)$order->get_shipping_address_2(),
            'postcode' => (string)$order->get_shipping_postcode(),
            'city' => (string)$order->get_shipping_city(),
            'country' => (string)$order->get_shipping_country(),
            'phone' => (string)$order->get_billing_phone(),
            'email' => (string)$order->get_billing_email(),
        ],
        'items' => $items,
        'total' => (float)$order->get_total(),
        'shippingTotal' => (float)$order->get_shipping_total() + (float)$order->get_shipping_tax(),
        'currency' => (string)$order->get_currency(),
    ];
}

$payload = wp_json_encode([
    'generatedAt' => current_time('mysql'),
    'count' => count($rows),
    'orders' => $rows,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
if (!is_string($payload)) {
    fwrite(STDERR, "Snapshot encoding failed\n");
    exit(4);
}

$directory = dirname($target);
if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
    fwrite(STDERR, "Snapshot directory cannot be created\n");
    exit(5);
}
$temporary = $target . '.tmp.' . getmypid();
if (file_put_contents($temporary, $payload, LOCK_EX) === false || !rename($temporary, $target)) {
    @unlink($temporary);
    fwrite(STDERR, "Snapshot cannot be written\n");
    exit(6);
}
chmod($target, 0640);
echo wp_json_encode(['orders' => count($rows), 'target' => $target]) . PHP_EOL;

