<?php
/**
 * PunktePass Image Optimizer
 *
 * Converts PNG images to WebP and creates multiple sizes
 * Run: php tools/optimize-images.php
 */

// Configuration
$source_dir = __DIR__ . '/../assets/img';
$output_dir = __DIR__ . '/../assets/img/optimized';

// Images to optimize with their target sizes
$images_to_optimize = [
    'logo.png' => [512, 256, 128, 64, 32],
    'logo-light.png' => [512, 256, 128, 64, 32],
    'store-default.png' => [256, 128, 64, 48],
    'punktepass-poster-logo.png' => [512, 256],
    'logo-transparent.png' => [512, 256, 128],
];

// WebP quality (0-100)
$webp_quality = 85;

// Create output directory
if (!is_dir($output_dir)) {
    mkdir($output_dir, 0755, true);
    echo "Created output directory: $output_dir\n";
}

echo "\n========================================\n";
echo "PunktePass Image Optimizer\n";
echo "========================================\n\n";

$total_original = 0;
$total_optimized = 0;

foreach ($images_to_optimize as $filename => $sizes) {
    $source_path = "$source_dir/$filename";

    if (!file_exists($source_path)) {
        echo "SKIP: $filename (not found)\n";
        continue;
    }

    $original_size = filesize($source_path);
    $total_original += $original_size;

    echo "Processing: $filename (" . format_bytes($original_size) . ")\n";

    // Load source image
    $source_image = imagecreatefrompng($source_path);
    if (!$source_image) {
        echo "  ERROR: Could not load image\n";
        continue;
    }

    // Enable alpha channel
    imagealphablending($source_image, false);
    imagesavealpha($source_image, true);

    $orig_width = imagesx($source_image);
    $orig_height = imagesy($source_image);

    $base_name = pathinfo($filename, PATHINFO_FILENAME);

    foreach ($sizes as $size) {
        // Calculate new dimensions (maintain aspect ratio)
        if ($orig_width >= $orig_height) {
            $new_width = $size;
            $new_height = (int)($orig_height * ($size / $orig_width));
        } else {
            $new_height = $size;
            $new_width = (int)($orig_width * ($size / $orig_height));
        }

        // Create resized image
        $resized = imagecreatetruecolor($new_width, $new_height);

        // Preserve transparency
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        // High quality resize
        imagecopyresampled(
            $resized, $source_image,
            0, 0, 0, 0,
            $new_width, $new_height,
            $orig_width, $orig_height
        );

        // Save as WebP
        $output_filename = "{$base_name}-{$size}.webp";
        $output_path = "$output_dir/$output_filename";

        if (imagewebp($resized, $output_path, $webp_quality)) {
            $new_size = filesize($output_path);
            $total_optimized += $new_size;
            $savings = round((1 - $new_size / $original_size) * 100, 1);
            echo "  Created: $output_filename (" . format_bytes($new_size) . ") - {$savings}% smaller\n";
        } else {
            echo "  ERROR: Failed to create $output_filename\n";
        }

        imagedestroy($resized);
    }

    // Also create full-size WebP
    $output_filename = "{$base_name}.webp";
    $output_path = "$output_dir/$output_filename";

    if (imagewebp($source_image, $output_path, $webp_quality)) {
        $new_size = filesize($output_path);
        $total_optimized += $new_size;
        $savings = round((1 - $new_size / $original_size) * 100, 1);
        echo "  Created: $output_filename (full size: " . format_bytes($new_size) . ") - {$savings}% smaller\n";
    }

    imagedestroy($source_image);
    echo "\n";
}

echo "========================================\n";
echo "SUMMARY\n";
echo "========================================\n";
echo "Original total:  " . format_bytes($total_original) . "\n";
echo "Optimized total: " . format_bytes($total_optimized) . "\n";
echo "Total savings:   " . format_bytes($total_original - $total_optimized) . " (" . round((1 - $total_optimized / $total_original) * 100, 1) . "%)\n";
echo "\nOptimized images saved to: $output_dir\n";

function format_bytes($bytes) {
    if ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' B';
}
