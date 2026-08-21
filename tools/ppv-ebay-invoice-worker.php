<?php
/** CLI worker for the eRepairShop eBay invoice bridge. */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$wp_load = dirname(__DIR__, 4) . '/wp-load.php';
if (!is_file($wp_load)) {
    fwrite(STDERR, "wp-load.php not found\n");
    exit(2);
}
require_once $wp_load;
require_once dirname(__DIR__) . '/includes/class-ppv-ebay-invoice.php';

$command = $argv[1] ?? '--run';
try {
    switch ($command) {
        case '--install':
            PPV_Ebay_Invoice::install_schema();
            echo "schema=ok\n";
            break;
        case '--smtp-test':
            PPV_Ebay_Invoice::smtp_auth_test();
            echo "smtp=ok\n";
            break;
        case '--status':
            echo wp_json_encode(PPV_Ebay_Invoice::status_summary(), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--topic':
            echo wp_json_encode(PPV_Ebay_Invoice::notification_topic(), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--notification-status':
            echo wp_json_encode(PPV_Ebay_Invoice::notification_status(), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--setup-notifications':
            echo wp_json_encode(PPV_Ebay_Invoice::setup_notifications(), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--test-notification':
            if (empty($argv[2])) throw new InvalidArgumentException('Subscription ID required.');
            PPV_Ebay_Invoice::test_notification($argv[2]);
            echo "notification_test=sent\n";
            break;
        case '--dry-run-order':
            if (empty($argv[2])) throw new InvalidArgumentException('Order ID required.');
            echo wp_json_encode(PPV_Ebay_Invoice::dry_run_order($argv[2]), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--dry-run-latest':
            echo wp_json_encode(PPV_Ebay_Invoice::dry_run_latest_order(), JSON_PRETTY_PRINT) . "\n";
            break;
        case '--run':
            $queued = PPV_Ebay_Invoice::reconcile_orders();
            $processed = PPV_Ebay_Invoice::process_queue(20);
            echo wp_json_encode(['reconciled' => $queued, 'queue' => $processed]) . "\n";
            break;
        default:
            throw new InvalidArgumentException('Unknown command.');
    }
} catch (Throwable $e) {
    fwrite(STDERR, preg_replace('/[\r\n]+/', ' ', $e->getMessage()) . "\n");
    exit(1);
}
