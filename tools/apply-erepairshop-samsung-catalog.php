<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI only\n");
    exit(1);
}

if ($argc !== 3) {
    fwrite(STDERR, "Usage: php apply-erepairshop-samsung-catalog.php <generated-json> <backup-dir>\n");
    exit(1);
}

$payloadPath = $argv[1];
$backupDir = rtrim($argv[2], DIRECTORY_SEPARATOR);
if (!is_file($payloadPath) || !is_readable($payloadPath)) {
    fwrite(STDERR, "Generated catalog is not readable\n");
    exit(1);
}
if (!is_dir($backupDir) && !mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "Backup directory cannot be created\n");
    exit(1);
}

define('WP_USE_THEMES', false);
require '/var/www/punktepass.de/wp-load.php';

global $wpdb;
$table = $wpdb->prefix . 'ppv_stores';
$row = $wpdb->get_row($wpdb->prepare(
    "SELECT id, store_slug, widget_ai_knowledge FROM {$table} WHERE store_slug = %s LIMIT 1",
    'erepairshop'
));
if (!$row) {
    fwrite(STDERR, "eRepairShop store row was not found\n");
    exit(1);
}

$knowledge = json_decode((string) $row->widget_ai_knowledge, true);
$payload = json_decode((string) file_get_contents($payloadPath), true);
$services = $payload['services'] ?? null;
if (!is_array($knowledge) || !is_array($services) || count($services) < 1000) {
    fwrite(STDERR, "Invalid current knowledge or generated services payload\n");
    exit(1);
}

$backupPath = $backupDir . DIRECTORY_SEPARATOR . 'erepairshop-widget-ai-knowledge.before.json';
if (file_put_contents($backupPath, (string) $row->widget_ai_knowledge, LOCK_EX) === false) {
    fwrite(STDERR, "Catalog backup could not be written\n");
    exit(1);
}
chmod($backupPath, 0600);

$knowledge['services'] = array_values($services);
$encoded = wp_json_encode($knowledge, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
if (!is_string($encoded)) {
    fwrite(STDERR, "Updated catalog JSON could not be encoded\n");
    exit(1);
}

$updated = $wpdb->update(
    $table,
    ['widget_ai_knowledge' => $encoded],
    ['id' => (int) $row->id],
    ['%s'],
    ['%d']
);
if ($updated === false) {
    fwrite(STDERR, "Database update failed: {$wpdb->last_error}\n");
    exit(1);
}

$samsungRows = array_values(array_filter($services, static function (array $service): bool {
    return str_starts_with((string) ($service['name'] ?? ''), 'Samsung ')
        && in_array((string) ($service['category'] ?? ''), [
            'Displaytausch Original',
            'Außendisplaytausch Original',
            'Akku Original',
            'Ladebuchse Austausch',
            'Backcover/Rückseite Austausch',
            'Hauptkamera Austausch',
        ], true);
}));
$missingPrices = array_values(array_filter($samsungRows, static fn(array $service): bool => empty($service['price'])));
$categoryCounts = [];
foreach ($samsungRows as $service) {
    $category = (string) ($service['category'] ?? '');
    $categoryCounts[$category] = ($categoryCounts[$category] ?? 0) + 1;
}

echo wp_json_encode([
    'ok' => true,
    'storeId' => (int) $row->id,
    'services' => count($services),
    'samsungManagedRows' => count($samsungRows),
    'samsungCategoryCounts' => $categoryCounts,
    'missingSamsungPrices' => count($missingPrices),
    'backup' => $backupPath,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;
