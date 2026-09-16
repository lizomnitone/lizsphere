<?php
/**
 * load-categorization.php
 * ────────────────────────
 * Handles two kinds of POST payloads sent by design-gallery.html:
 *
 *  1. Image categorizations  (saveServerCategorizations)
 *     Body: { "FolderName": { "filename.jpg": "category", ... }, ... }
 *     Action: writes / updates  data/categorizations/<FolderName>.json
 *
 *  2. Board-category definitions  (saveBoardCategories)
 *     Body: { "Art": ["ink drawings", ...], "Industrial Design": [...], ... }
 *     Action: writes data/design-gallery-images/Design Inspo Images/board-categories.json
 *
 * Both responses are JSON: { "ok": true }  or  { "ok": false, "error": "..." }
 *
 * Place this file at:
 *   data/design-gallery-images/Design Inspo Images/load-categorization.php
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS, GET');
header('Access-Control-Allow-Headers: Content-Type');

// Log all requests
$logFile = __DIR__ . '/load-categorization.log';
error_log(date('Y-m-d H:i:s') . ' | Method: ' . $_SERVER['REQUEST_METHOD'] . ' | URL: ' . $_SERVER['REQUEST_URI'] . "\n", 3, $logFile);

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Method not allowed']);
    exit;
}

// ── Parse body ────────────────────────────────────────────────────────────────
$raw  = file_get_contents('php://input');
$data = json_decode($raw, true);

if ($data === null) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Invalid JSON']);
    exit;
}

// ── Detect payload type ───────────────────────────────────────────────────────
// Board-category definitions: values are arrays of strings (category names).
// Image categorizations:      values are objects (filename → category string).
$isBoardCategories = false;
foreach ($data as $key => $value) {
    if (is_array($value) && array_values($value) === $value) {
        // Sequential (non-associative) array → board-category list
        $isBoardCategories = true;
    }
    break; // only need to inspect the first key
}

// ── Base paths ────────────────────────────────────────────────────────────────
// __DIR__ is the directory this PHP file lives in, i.e.
//   data/design-gallery-images/Design Inspo Images/
$baseDir = __DIR__;

if ($isBoardCategories) {
    // ── Save board-categories.json ────────────────────────────────────────────
    $outFile = $baseDir . '/board-categories.json';
    $written = file_put_contents(
        $outFile,
        json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
    );

    if ($written === false) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Could not write board-categories.json']);
        exit;
    }

    echo json_encode(['ok' => true, 'saved' => 'board-categories.json']);

} else {
    // ── Save per-folder categorization files ──────────────────────────────────
    // $data shape: { "FolderName": { "art_001.jpg": "ink drawings", ... }, ... }
    $catDir = $baseDir . '/categorizations';
    if (!is_dir($catDir)) {
        mkdir($catDir, 0755, true);
    }

    $savedFolders = [];
    foreach ($data as $folder => $entries) {
        if (!is_array($entries)) continue;

        // Sanitise folder name for use as a filename
        $safeName = preg_replace('/[^\w\- ]/', '_', $folder);
        $outFile   = $catDir . '/' . $safeName . '.json';

        // Merge with existing data so we don't lose untracked images
        $existing = [];
        if (file_exists($outFile)) {
            $existing = json_decode(file_get_contents($outFile), true) ?? [];
        }
        $merged = array_merge($existing, $entries);

        $written = file_put_contents(
            $outFile,
            json_encode($merged, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        if ($written !== false) {
            $savedFolders[] = $safeName;
        }
    }

    echo json_encode(['ok' => true, 'saved' => $savedFolders]);
}
