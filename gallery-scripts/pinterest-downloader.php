<?php
/**
 * pinterest-downloader.php
 * ────────────────────────
 * Download images from Pinterest boards using gallery-dl
 * Visit: http://localhost:8000/pinterest-downloader.php
 */

header('Content-Type: application/json');

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['url']) || !isset($data['category'])) {
    http_response_code(400);
    echo json_encode([
        'ok' => false,
        'error' => 'Missing required fields: url, category'
    ]);
    exit;
}

$pinterestUrl = trim($data['url']);
$category = trim($data['category']);

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

// Create temporary directory for downloads
$tempDir = sys_get_temp_dir() . '/gallery-dl-' . time();
$targetDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/' . $category;

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0755, true);
}

// Download using gallery-dl
$galleryDl = '/Library/Frameworks/Python.framework/Versions/3.13/bin/gallery-dl';
$cmd = $galleryDl . ' -d ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($pinterestUrl) . ' 2>&1';

// Execute and capture output
$output = shell_exec($cmd);
$lines = explode("\n", trim($output));

// Log the attempt
error_log("Pinterest Download - URL: $pinterestUrl, Category: $category");
error_log("Command: $cmd");
error_log("Output: $output");

if (empty($output) || strpos($output, 'error') !== false || strpos($output, 'Error') !== false) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'gallery-dl failed or returned no images. Output: ' . $output,
        'debugInfo' => [
            'cmd' => $cmd,
            'url' => $pinterestUrl,
            'tempDir' => $tempDir
        ]
    ]);
    exit;
}

// Move downloaded files to gallery
$count = 0;
if (is_dir($tempDir)) {
    // Find all image files (gallery-dl downloads them in nested folders like pinterest/username/board/)
    $rdi = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $rii = new RecursiveIteratorIterator($rdi);

    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($rii as $file) {
        if ($file->isFile() && in_array(strtolower($file->getExtension()), $imageExtensions)) {
            $filename = $file->getFilename();
            $targetFile = $targetDir . '/' . $filename;

            // Avoid duplicates by adding number if file exists
            $counter = 1;
            $baseName = pathinfo($filename, PATHINFO_FILENAME);
            $ext = $file->getExtension();
            $originalTarget = $targetFile;

            while (file_exists($targetFile)) {
                $filename = $baseName . '_' . $counter . '.' . $ext;
                $targetFile = $targetDir . '/' . $filename;
                $counter++;
            }

            if (copy($file->getPathname(), $targetFile)) {
                $count++;
            }
        }
    }

    // Clean up temp directory
    exec('rm -rf ' . escapeshellarg($tempDir));
}

echo json_encode([
    'ok' => true,
    'message' => "Downloaded and imported $count images to $category",
    'count' => $count,
    'category' => $category,
    'nextStep' => 'Visit http://localhost:8000/generate-manifest.php to update the gallery'
]);
?>
