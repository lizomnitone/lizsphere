<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$results = [];

try {
    $basePath = __DIR__ . '/data/design-gallery-images/Design Inspo Images';
    $categorizationsDir = $basePath . '/categorizations';
    $cmfRootDir = $basePath . '/CMF';
    $colourLibraryDir = $cmfRootDir . '/Colour-Library';

    // Ensure dirs exist
    if (!is_dir($basePath)) throw new Exception("Base path not found: $basePath");
    if (!is_dir($categorizationsDir)) mkdir($categorizationsDir, 0755, true);

    // ─── 1. Generate manifest.json ──────────────────────────────────────────────
    try {
        $manifest = [];
        $dirs = array_filter(scandir($basePath), fn($f) => $f[0] !== '.' && is_dir("$basePath/$f"));

        foreach ($dirs as $folder) {
            $folderPath = "$basePath/$folder";
            $images = [];

            $dirIterator = new RecursiveDirectoryIterator($folderPath, RecursiveDirectoryIterator::SKIP_DOTS);
            $iterator = new RecursiveIteratorIterator($dirIterator);

            foreach ($iterator as $file) {
                $ext = strtolower($file->getExtension());
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
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
        file_put_contents("$basePath/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
        $results['manifest'] = [
            'ok' => true,
            'boards' => count($manifest),
            'totalImages' => array_sum(array_map('count', $manifest))
        ];
    } catch (Exception $e) {
        $results['manifest'] = ['ok' => false, 'error' => $e->getMessage()];
    }

    // ─── 2. Generate Paint Swatches categorizations ─────────────────────────────
    try {
        $paintSwatches = [];
        if (is_dir($colourLibraryDir)) {
            $files = array_filter(scandir($colourLibraryDir), fn($f) => $f[0] !== '.' && is_file("$colourLibraryDir/$f"));

            foreach ($files as $file) {
                $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                    preg_match('/^([A-Z]+)/', $file, $matches);
                    $prefix = $matches[1] ?? 'UNKNOWN';
                    $paintSwatches[$file] = [$prefix];
                }
            }
        }

        ksort($paintSwatches);
        $results['paintSwatches'] = [
            'ok' => true,
            'totalImages' => count($paintSwatches),
            'prefixes' => count($paintSwatches) > 0 ? array_unique(array_merge(...array_values($paintSwatches))) : []
        ];
    } catch (Exception $e) {
        $results['paintSwatches'] = ['ok' => false, 'error' => $e->getMessage()];
    }

    // ─── 3. Update CMF.json with paint swatches ────────────────────────────────
    try {
        $cmfData = [];
        $cmfFile = "$categorizationsDir/CMF.json";

        // Keep existing root CMF images
        if (file_exists($cmfFile)) {
            $existingCMF = json_decode(file_get_contents($cmfFile), true) ?? [];
            $cmfData = array_filter($existingCMF, fn($k) => !str_contains($k, 'Colour-Library/'), ARRAY_FILTER_USE_KEY);
        }

        // Add paint swatches with path prefix
        foreach ($paintSwatches as $filename => $categories) {
            $cmfData["Colour-Library/$filename"] = $categories;
        }

        ksort($cmfData);
        file_put_contents($cmfFile, json_encode($cmfData, JSON_PRETTY_PRINT));
        $results['cmfJson'] = [
            'ok' => true,
            'totalEntries' => count($cmfData)
        ];
    } catch (Exception $e) {
        $results['cmfJson'] = ['ok' => false, 'error' => $e->getMessage()];
    }

    // ─── 4. Verify board-categories.json ────────────────────────────────────
    try {
        $boardCategoriesFile = "$basePath/board-categories.json";
        if (file_exists($boardCategoriesFile)) {
            $data = json_decode(file_get_contents($boardCategoriesFile), true);
            $results['boardCategories'] = [
                'ok' => true,
                'groups' => count($data ?? [])
            ];
        } else {
            $results['boardCategories'] = ['ok' => false, 'error' => 'board-categories.json not found'];
        }
    } catch (Exception $e) {
        $results['boardCategories'] = ['ok' => false, 'error' => $e->getMessage()];
    }

    // ─── 5. Analyze paint swatches by color ─────────────────────────────────
    try {
        if (!is_dir($colourLibraryDir)) throw new Exception('Colour-Library not found');

        $analyzed = 0;
        $colorCounts = [];
        $files = array_filter(
            scandir($colourLibraryDir),
            fn($f) => $f[0] !== '.' && is_file("$colourLibraryDir/$f") && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)
        );

        foreach ($files as $file) {
            $filePath = "$colourLibraryDir/$file";
            $relPath = "Colour-Library/$file";

            if (extension_loaded('gd')) {
                $dominantColor = getDominantColor($filePath);
                if ($dominantColor) {
                    $existing = $cmfData[$relPath] ?? [];
                    $prefix = $existing[0] ?? null;
                    $categories = [];
                    if ($prefix) $categories[] = $prefix;
                    $categories[] = $dominantColor;
                    $cmfData[$relPath] = $categories;
                    $analyzed++;
                    $colorCounts[$dominantColor] = ($colorCounts[$dominantColor] ?? 0) + 1;
                }
            }
        }

        ksort($cmfData);
        file_put_contents($cmfFile, json_encode($cmfData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $results['swatchAnalysis'] = [
            'ok' => true,
            'analyzed' => $analyzed,
            'colorDistribution' => $colorCounts
        ];
    } catch (Exception $e) {
        $results['swatchAnalysis'] = ['ok' => false, 'error' => $e->getMessage()];
    }

    $allOk = array_reduce($results, fn($c, $r) => $c && ($r['ok'] ?? false), true);

    echo json_encode([
        'success' => $allOk,
        'message' => $allOk ? '✓ Gallery initialization complete!' : '⚠ Some steps had errors',
        'steps' => $results
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'steps' => $results
    ]);
}

// ─── Helper functions for color analysis ──────────────────────────────────
function getDominantColor($filePath) {
    if (!extension_loaded('gd')) {
        return null;
    }

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

    try {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = @imagecreatefromjpeg($filePath);
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($filePath);
        } elseif ($ext === 'gif') {
            $image = @imagecreatefromgif($filePath);
        } elseif ($ext === 'webp') {
            $image = @imagecreatefromwebp($filePath);
        } else {
            return null;
        }

        if (!$image) return null;

        $small = imagecreatetruecolor(1, 1);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));

        $rgb = imagecolorat($small, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return categorizeColor($r, $g, $b);
    } catch (Exception $e) {
        return null;
    }
}

function categorizeColor($r, $g, $b) {
    $rNorm = $r / 255;
    $gNorm = $g / 255;
    $bNorm = $b / 255;

    $max = max($rNorm, $gNorm, $bNorm);
    $min = min($rNorm, $gNorm, $bNorm);
    $l = ($max + $min) / 2;

    if ($l > 0.92) {
        return 'white';
    } elseif ($l < 0.08) {
        return 'black';
    }

    if ($max === $min) {
        if ($l > 0.7) return 'white';
        if ($l < 0.3) return 'black';
        return 'grey';
    }

    $d = $max - $min;
    if ($max === $rNorm) {
        $h = (($gNorm - $bNorm) / $d + ($gNorm < $bNorm ? 6 : 0)) / 6;
    } elseif ($max === $gNorm) {
        $h = (($bNorm - $rNorm) / $d + 2) / 6;
    } else {
        $h = (($rNorm - $gNorm) / $d + 4) / 6;
    }

    $s = $max === $min ? 0 : ($l > 0.5 ? $d / (2 - $max - $min) : $d / ($max + $min));

    if ($s < 0.1) {
        if ($l > 0.7) return 'white';
        if ($l < 0.3) return 'black';
        return 'grey';
    }

    $hDeg = $h * 360;

    if (($hDeg < 45 || $hDeg >= 15) && $l < 0.5 && $s > 0.2) {
        if ($hDeg < 45 && $hDeg >= 15 && $l < 0.45) {
            return 'brown';
        }
    }

    if ($hDeg < 15 || $hDeg >= 345) {
        return 'red';
    } elseif ($hDeg < 45) {
        return 'orange';
    } elseif ($hDeg < 65) {
        return 'yellow';
    } elseif ($hDeg < 150) {
        return 'green';
    } elseif ($hDeg < 260) {
        return 'blue';
    } elseif ($hDeg < 290) {
        return 'purple';
    } else {
        return 'pink';
    }
}
?>
