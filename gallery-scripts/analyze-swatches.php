<?php
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', 0);

/**
 * Analyze paint swatches by actual RGB color
 * Reads each image, categorizes, and sorts by saturation (high→low) then hue (warm→cool)
 */

header('Content-Type: application/json; charset=utf-8');

$colourLibraryDir = __DIR__ . '/data/design-gallery-images/Design Inspo Images/CMF/Colour-Library';
$cmfFile = __DIR__ . '/data/design-gallery-images/Design Inspo Images/categorizations/CMF.json';

$data = [];
$sortData = [];  // For sorting: [saturation, hue, filename]
$colorCounts = [];
$analyzed = 0;
$failed = 0;

// Get all image files
$files = array_filter(
    scandir($colourLibraryDir),
    fn($f) => $f[0] !== '.' && is_file("$colourLibraryDir/$f") && preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $f)
);

foreach ($files as $file) {
    $filePath = "$colourLibraryDir/$file";
    $relPath = "Colour-Library/$file";

    // Extract prefix from filename (e.g., "DE" from "DE6031.jpg")
    if (preg_match('/^([A-Z]+)/', $file, $m)) {
        $prefix = $m[1];
    } else {
        $prefix = 'DE';
    }

    try {
        // Get dominant color from actual image
        $result = getDominantColorWithMetrics($filePath);

        if ($result) {
            $data[$relPath] = [$prefix, $result['color']];
            $colorCounts[$result['color']] = ($colorCounts[$result['color']] ?? 0) + 1;
            $sortData[] = [
                'path' => $relPath,
                'saturation' => $result['saturation'],
                'hue' => $result['hue'],
                'prefix' => $prefix,
                'color' => $result['color']
            ];
            $analyzed++;
        } else {
            $data[$relPath] = [$prefix];
            $failed++;
        }
    } catch (Exception $e) {
        $data[$relPath] = [$prefix];
        $failed++;
    }
}

// Sort by saturation (high→low), then hue (0°→360° = red→red, warm→cool)
usort($sortData, function($a, $b) {
    // Primary sort: saturation descending (most saturated first)
    if (abs($a['saturation'] - $b['saturation']) > 0.01) {
        return $b['saturation'] <=> $a['saturation'];
    }
    // Secondary sort: hue ascending (warm to cool)
    return $a['hue'] <=> $b['hue'];
});

// Rebuild data in sorted order
$sortedData = [];
foreach ($sortData as $item) {
    $sortedData[$item['path']] = [$item['prefix'], $item['color']];
}

// Save
file_put_contents($cmfFile, json_encode($sortedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

arsort($colorCounts);

echo json_encode([
    'ok' => true,
    'message' => "Analyzed & sorted by saturation (high→low) then hue (warm→cool)",
    'analyzed' => $analyzed,
    'failed' => $failed,
    'total_images' => count($sortedData),
    'colorDistribution' => $colorCounts
], JSON_PRETTY_PRINT);

// Get dominant color and metrics from image
function getDominantColorWithMetrics($filePath) {
    if (!extension_loaded('gd')) return null;

    $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
    $image = null;

    try {
        if ($ext === 'jpg' || $ext === 'jpeg') {
            $image = @imagecreatefromjpeg($filePath);
        } elseif ($ext === 'png') {
            $image = @imagecreatefrompng($filePath);
        } elseif ($ext === 'gif') {
            $image = @imagecreatefromgif($filePath);
        } elseif ($ext === 'webp') {
            $image = @imagecreatefromwebp($filePath);
        }

        if (!$image) return null;

        // Get average color
        $small = imagecreatetruecolor(1, 1);
        imagecopyresampled($small, $image, 0, 0, 0, 0, 1, 1, imagesx($image), imagesy($image));
        $rgb = imagecolorat($small, 0, 0);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;

        return categorizeByRGBWithMetrics($r, $g, $b);
    } catch (Exception $e) {
        return null;
    }
}

// Categorize color based on actual RGB values - returns color + metrics
function categorizeByRGBWithMetrics($r, $g, $b) {
    $rNorm = $r / 255;
    $gNorm = $g / 255;
    $bNorm = $b / 255;

    $max = max($rNorm, $gNorm, $bNorm);
    $min = min($rNorm, $gNorm, $bNorm);
    $l = ($max + $min) / 2;
    $d = $max - $min;

    // Calculate saturation and hue early
    $s = $l > 0.5 ? ($d === 0 ? 0 : $d / (2 - $max - $min)) : ($d === 0 ? 0 : $d / ($max + $min));

    $h = 0;
    if ($d > 0) {
        if ($max === $rNorm) {
            $h = (($gNorm - $bNorm) / $d + ($gNorm < $bNorm ? 6 : 0)) / 6;
        } elseif ($max === $gNorm) {
            $h = (($bNorm - $rNorm) / $d + 2) / 6;
        } else {
            $h = (($rNorm - $gNorm) / $d + 4) / 6;
        }
    }
    $hDeg = $h * 360;

    // Check neutrals first (low saturation)
    if ($l > 0.88) {
        return ['color' => 'white', 'saturation' => $s, 'hue' => $hDeg];
    }
    if ($l < 0.08) {
        return ['color' => 'black', 'saturation' => $s, 'hue' => $hDeg];
    }
    if ($s < 0.12) {  // Very desaturated = grey, white, or black
        if ($l > 0.62) return ['color' => 'white', 'saturation' => $s, 'hue' => $hDeg];
        if ($l < 0.35) return ['color' => 'black', 'saturation' => $s, 'hue' => $hDeg];
        return ['color' => 'grey', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Brown: dark orange/red only
    if (($hDeg >= 15 && $hDeg < 38) && $l < 0.42 && $s > 0.2) {
        return ['color' => 'brown', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Beige: light, desaturated orange/yellow
    if (($hDeg >= 22 && $hDeg < 48) && $l > 0.55 && $l < 0.78 && $s < 0.33) {
        return ['color' => 'beige', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Pink: light, desaturated reds + purplish pinks
    if (($hDeg >= 330 && $hDeg < 360) && $s < 0.5 && $l > 0.5) {
        return ['color' => 'pink', 'saturation' => $s, 'hue' => $hDeg];
    }
    if (($hDeg >= 0 && $hDeg < 10) && $s < 0.5 && $l > 0.5) {
        return ['color' => 'pink', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Purple: deeper, more saturated
    if ($hDeg >= 265 && $hDeg < 320) {
        return ['color' => 'purple', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Red: pure reds only
    if (($hDeg < 12 || $hDeg >= 330) && $s > 0.3) {
        return ['color' => 'red', 'saturation' => $s, 'hue' => $hDeg];
    }

    // Categorize by hue
    if ($hDeg < 12 || $hDeg >= 330) $color = 'red';
    else if ($hDeg < 38) $color = 'orange';
    else if ($hDeg < 58) $color = 'yellow';
    else if ($hDeg < 140) $color = 'green';
    else if ($hDeg < 265) $color = 'blue';
    else if ($hDeg < 320) $color = 'purple';
    else $color = 'pink';

    return ['color' => $color, 'saturation' => $s, 'hue' => $hDeg];
}
?>

