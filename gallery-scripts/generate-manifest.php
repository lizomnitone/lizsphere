<?php
/**
 * generate-manifest.php
 * ─────────────────────
 * Run this after adding new image folders or files.
 * Visit: http://localhost:8000/generate-manifest.php
 *
 * Scans design-gallery-images/Design Inspo Images/ and generates manifest.json
 */

header('Content-Type: application/json');

$baseDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images';

if (!is_dir($baseDir)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Design Inspo Images folder not found']);
    exit;
}

$manifest = [];
$dirs = scandir($baseDir);

foreach ($dirs as $folder) {
    $folderPath = $baseDir . '/' . $folder;

    // Skip hidden folders and non-directories
    if ($folder[0] === '.' || !is_dir($folderPath)) {
        continue;
    }

    // Get all image files in this folder (and subfolders)
    $images = [];
    $dirIterator = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
    $iterator = new RecursiveIteratorIterator($dirIterator);

    foreach ($iterator as $file) {
        if (in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $relativePath = substr($file->getPathname(), strlen($folderPath) + 1);
            $images[] = $relativePath;
        }
    }

    if (!empty($images)) {
        sort($images);
        $manifest[$folder] = $images;
    }
}

ksort($manifest);

$outputFile = $baseDir . '/manifest.json';
$written = file_put_contents(
    $outputFile,
    json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write manifest.json']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'Manifest generated successfully',
    'boards' => array_keys($manifest),
    'totalImages' => array_sum(array_map('count', $manifest))
]);
?>
