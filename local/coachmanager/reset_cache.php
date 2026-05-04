<?php
// Force OPcache reset.
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "OPcache reset OK.<br>";
} else {
    echo "OPcache not available.<br>";
}

if (function_exists('opcache_invalidate')) {
    $file = __DIR__ . '/coach_dashboard_v2.php';
    opcache_invalidate($file, true);
    echo "Invalidated: $file<br>";
    echo "File size: " . filesize($file) . " bytes<br>";
    echo "File mtime: " . date('Y-m-d H:i:s', filemtime($file)) . "<br>";

    // Show line 4776 of the actual file on server.
    $lines = file($file);
    echo "<br>Line 4776: <code>" . htmlspecialchars(trim($lines[4775] ?? 'N/A')) . "</code><br>";
    echo "Line 4777: <code>" . htmlspecialchars(trim($lines[4776] ?? 'N/A')) . "</code><br>";
    echo "Total lines: " . count($lines) . "<br>";
}

// Check if record_exists appears anywhere in the file.
$content = file_get_contents(__DIR__ . '/coach_dashboard_v2.php');
if (strpos($content, 'record_exists') !== false) {
    echo "<br><strong style='color:red;'>TROVATO record_exists nel file!</strong><br>";
    // Find the line.
    $lines = file(__DIR__ . '/coach_dashboard_v2.php');
    foreach ($lines as $num => $line) {
        if (strpos($line, 'record_exists') !== false) {
            echo "Riga " . ($num + 1) . ": <code>" . htmlspecialchars(trim($line)) . "</code><br>";
        }
    }
} else {
    echo "<br><strong style='color:green;'>record_exists NON presente nel file - OK</strong><br>";
}
