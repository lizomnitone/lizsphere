<?php
/**
 * fix-cmf-paths.php
 * ─────────────────
 * Regenerates CMF.json with correct relative paths (Colour-Library/DE5000.jpg format)
 * This ensures filenames match what's in the manifest.json
 * Visit: http://localhost:8000/fix-cmf-paths.php
 */

header('Content-Type: application/json');

$categorizationsDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/categorizations';
$cmfJsonFile = $categorizationsDir . '/CMF.json';
$colourLibraryDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/CMF/Colour-Library';
$cmfRootDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/CMF';

// Load existing CMF.json
$existingCMF = [];
if (file_exists($cmfJsonFile)) {
    $existingCMF = json_decode(file_get_contents($cmfJsonFile), true) ?? [];
}

// Keep the original cmf_*.jpg files from root CMF folder
$rootCMFFiles = [];
if (is_dir($cmfRootDir)) {
    $files = scandir($cmfRootDir);
    foreach ($files as $file) {
        if ($file[0] === '.' || is_dir($cmfRootDir . '/' . $file)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

        // Keep existing categorizations if they exist
        if (isset($existingCMF[$file])) {
            $rootCMFFiles[$file] = $existingCMF[$file];
        }
    }
}

// Scan Colour-Library and add with Colour-Library/ prefix in path
$paintSwatches = [];
if (is_dir($colourLibraryDir)) {
    $files = scandir($colourLibraryDir);
    foreach ($files as $file) {
        if ($file[0] === '.') continue;
        if (!is_file($colourLibraryDir . '/' . $file)) continue;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) continue;

        // Extract prefix
        preg_match('/^([A-Z]+)/', $file, $matches);
        $prefix = $matches[1] ?? 'UNKNOWN';

        // Store with Colour-Library/ prefix in path
        $relativePathKey = 'Colour-Library/' . $file;
        $paintSwatches[$relativePathKey] = [$prefix];
    }
}

// Merge everything
$merged = array_merge($rootCMFFiles, $paintSwatches);
ksort($merged);

// Write updated CMF.json
$written = file_put_contents(
    $cmfJsonFile,
    json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
);

if ($written === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Could not write CMF.json']);
    exit;
}

echo json_encode([
    'ok' => true,
    'message' => 'CMF.json regenerated with correct relative paths',
    'totalEntries' => count($merged),
    'rootCMFImages' => count($rootCMFFiles),
    'paintSwatchesWithPaths' => count($paintSwatches),
    'samplePaths' => array_slice(array_keys($paintSwatches), 0, 5)
]);
?>
