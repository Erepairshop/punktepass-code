<?php
/**
 * PunktePass Localize Patch – Safe AutoFix
 * Author: ChatGPT & Erik Borota
 * Date: 2025-10-18
 */

$baseDir = __DIR__ . '/includes';
$backupDir = __DIR__ . '/backup_localize_fix';
$logFile = __DIR__ . '/patch_log.txt';

if (!is_dir($backupDir)) mkdir($backupDir);

$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
$log = "=== PunktePass Localize Patch Log ===\n\n";

foreach ($iterator as $file) {
    if (!$file->isFile() || strpos($file->getFilename(), '.php') === false) continue;

    $path = $file->getPathname();
    $content = file_get_contents($path);

    if (strpos($content, 'wp_localize_script') === false) continue;

    $log .= "🔧 Fixing: {$path}\n";

    // Biztonsági mentés
    copy($path, $backupDir . '/' . basename($path) . '.bak');

    // Regex keresés és csere
    $pattern = '/wp_localize_script\s*\(\s*([\'"])(.*?)\1\s*,\s*([\'"])(.*?)\3\s*,\s*(.*?)\);/s';
    $replacement = <<<'PHP'
$__data = is_array($5 ?? null) ? $5 : [];
$__json = wp_json_encode($__data);
wp_add_inline_script('$2', "window.$4 = {$__json};", 'before');
PHP;

    $newContent = preg_replace($pattern, $replacement, $content, -1, $count);

    if ($count > 0) {
        file_put_contents($path, $newContent);
        $log .= "✅ Patched ({$count} occurrences)\n\n";
    } else {
        $log .= "⚠️ No match found for replacement\n\n";
    }
}

file_put_contents($logFile, $log);
echo nl2br(htmlspecialchars($log));
