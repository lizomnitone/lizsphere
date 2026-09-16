<?php
/**
 * test-gallery-dl.php
 * ───────────────────
 * Debug endpoint to test gallery-dl
 * Visit: http://localhost:8000/test-gallery-dl.php?url=YOUR_PINTEREST_URL
 */

header('Content-Type: text/plain');

$url = $_GET['url'] ?? null;

if (!$url) {
    echo "Usage: test-gallery-dl.php?url=https://pinterest.com/...\n";
    exit;
}

echo "Testing gallery-dl with URL: $url\n";
echo str_repeat("─", 60) . "\n\n";

$tempDir = sys_get_temp_dir() . '/gallery-dl-test-' . time();
mkdir($tempDir, 0755, true);

$galleryDl = '/Library/Frameworks/Python.framework/Versions/3.13/bin/gallery-dl';
$cmd = $galleryDl . ' -v -d ' . escapeshellarg($tempDir) . ' ' . escapeshellarg($url) . ' 2>&1';

echo "Command:\n$cmd\n\n";
echo "Output:\n";
echo str_repeat("─", 60) . "\n";

passthru($cmd);

echo "\n" . str_repeat("─", 60) . "\n";
echo "\nFiles downloaded:\n";

$files = [];
if (is_dir($tempDir)) {
    $rdi = new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS);
    $rii = new RecursiveIteratorIterator($rdi);
    foreach ($rii as $file) {
        if ($file->isFile()) {
            $files[] = str_replace($tempDir . '/', '', $file->getPathname());
        }
    }
}

if (empty($files)) {
    echo "No files downloaded.\n";
} else {
    echo "Found " . count($files) . " file(s):\n";
    foreach ($files as $file) {
        echo "  - $file\n";
    }
}

// Cleanup
exec('rm -rf ' . escapeshellarg($tempDir));
?>
