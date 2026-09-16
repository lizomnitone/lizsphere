<?php
/**
 * generate-paint-swatches.php
 * ────────────────────────────
 * Generates Paint Swatches.json by categorizing images by their prefix (DE, DEA, DEB, etc.)
 * Run this after generate-manifest.php
 * Visit: http://localhost:8000/generate-paint-swatches.php
 */

header('Content-Type: application/json');

$baseDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/CMF/Colour-Library';

if (!is_dir($baseDir)) {
    http_response_code(404);
    echo json_encode(['ok' => false, 'error' => 'Colour-Library folder not found']);
    exit;
}

$categorizations = [];

// Get all image files
$files = scandir($baseDir);
foreach ($files as $file) {
    if ($file[0] === '.') continue;
    if (!is_file($baseDir . '/' . $file)) continue;

    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

    // Extract prefix (everything before the first digit)
    preg_match('/^([A-Z]+)/', $file, $matches);
    $prefix = $matches[1] ?? 'UNKNOWN';

    $categorizations[$file] = [$prefix];
}

// Sort by filename
ksort($categorizations);

$outputFile = $baseDir . '/paint-swatches-categorizations.json';
$written = file_put_contents(
    $outputFile,
    json_encode($categorizations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write paint-swatches-categorizations.json']);
    exit;
}

// Also save to categorizations folder for consistency
$categorizationDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/categorizations';
if (!is_dir($categorizationDir)) {
    mkdir($categorizationDir, 0755, true);
}

$categorizationFile = $categorizationDir . '/Paint Swatches.json';
file_put_contents($categorizationFile, json_encode($categorizations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

echo json_encode([
    'ok' => true,
    'message' => 'Paint Swatches categorizations generated successfully',
    'totalImages' => count($categorizations),
    'prefixes' => array_unique(array_merge(...array_values($categorizations)))
]);
?>
