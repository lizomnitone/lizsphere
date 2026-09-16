<?php
// Log all requests immediately
$logDir = __DIR__;
$logFile = $logDir . '/sync-debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " | Method: {$_SERVER['REQUEST_METHOD']} | URL: {$_SERVER['REQUEST_URI']}\n", FILE_APPEND);

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed, got: ' . $_SERVER['REQUEST_METHOD']]);
    exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

$baseDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/categorizations';
if (!is_dir($baseDir)) {
    mkdir($baseDir, 0755, true);
}

$saved = 0;
foreach ($data as $boardName => $categorizations) {
    if (!is_array($categorizations)) continue;

    $filename = str_replace(' ', '_', $boardName);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
    $filepath = $baseDir . '/' . $filename . '.json';

    $existing = [];
    if (file_exists($filepath)) {
        $existing = json_decode(file_get_contents($filepath), true);
        if (!is_array($existing)) $existing = [];
    }

    foreach ($categorizations as $imageFile => $categories) {
        if (is_array($categories)) {
            $existing[$imageFile] = $categories;
        }
    }

    $json = json_encode($existing, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if (file_put_contents($filepath, $json) !== false) {
        $saved++;
    }
}

echo json_encode([
    'ok' => true,
    'message' => "Saved categorizations for $saved board(s)",
    'saved' => $saved
]);
?>
