<?php
/**
 * remove-duplicates.php
 * ────────────────────
 * Remove duplicate images by file hash
 * Visit: http://localhost:8000/remove-duplicates.php?category=CMF
 */

header('Content-Type: application/json');

$category = $_GET['category'] ?? 'CMF';

// Validate category
$validCategories = ['Art', 'CMF', 'Graphics', 'Industrial Design'];
if (!in_array($category, $validCategories)) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => "Invalid category. Must be one of: " . implode(', ', $validCategories)
    ]);
    exit;
}

$baseDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/' . $category;

if (!is_dir($baseDir)) {
    http_response_code(404);
    echo json_encode([
        'ok' => false,
        'error' => "Category folder not found: $category"
    ]);
    exit;
}

// Get all image files
$imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
$files = [];

$iterator = new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS);
$fileIterator = new RecursiveIteratorIterator($iterator);

foreach ($fileIterator as $file) {
    if ($file->isFile() && in_array(strtolower($file->getExtension()), $imageExtensions)) {
        $files[] = $file->getPathname();
    }
}

// Hash files to find duplicates
$hashes = [];
$duplicates = [];

foreach ($files as $filepath) {
    $hash = md5_file($filepath);

    if (isset($hashes[$hash])) {
        // This is a duplicate
        $duplicates[] = [
            'file' => basename($filepath),
            'path' => $filepath,
            'originalFile' => basename($hashes[$hash]),
            'originalPath' => $hashes[$hash]
        ];
    } else {
        // First occurrence of this hash
        $hashes[$hash] = $filepath;
    }
}

// Remove duplicates (keep the first occurrence)
$deletedCount = 0;
foreach ($duplicates as $dup) {
    if (unlink($dup['path'])) {
        $deletedCount++;
    }
}

echo json_encode([
    'ok' => true,
    'category' => $category,
    'totalFiles' => count($files),
    'duplicatesFound' => count($duplicates),
    'duplicatesRemoved' => $deletedCount,
    'duplicatesList' => array_map(function($d) {
        return [
            'duplicate' => $d['file'],
            'kept' => $d['originalFile']
        ];
    }, $duplicates),
    'nextStep' => 'Visit http://localhost:8000/generate-manifest.php to update the gallery'
]);
?>
