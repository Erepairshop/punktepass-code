<?php
/** eBay ORDER_CONFIRMATION webhook for the eRepairShop invoice bridge. */

$wp_load = dirname(__DIR__, 3) . '/wp-load.php';
if (!is_file($wp_load)) {
    http_response_code(503);
    exit;
}
require_once $wp_load;
require_once __DIR__ . '/includes/class-ppv-ebay-invoice.php';

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['challenge_code'])) {
    try {
        header('Content-Type: application/json; charset=utf-8');
        echo wp_json_encode(['challengeResponse' => PPV_Ebay_Invoice::challenge_response($_GET['challenge_code'])]);
        exit;
    } catch (Throwable $e) {
        http_response_code(503);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

$raw = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_EBAY_SIGNATURE'] ?? '';
try {
    if (!PPV_Ebay_Invoice::verify_notification($raw, $signature)) {
        http_response_code(412);
        exit;
    }
    $payload = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
    PPV_Ebay_Invoice::enqueue_notification($payload);
    http_response_code(204);
} catch (Throwable $e) {
    error_log('PPV eBay webhook: ' . $e->getMessage());
    http_response_code(500);
}
