<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$publicPath  = dirname(__DIR__) . '/public';
$storagePath = $publicPath . '/storage';
$snapPath    = $storagePath . '/snapbooth';

echo "public/storage       : " . (is_dir($storagePath) ? 'EXISTS' : 'MISSING') . "\n";
echo "public/storage perms : " . (is_dir($storagePath) ? substr(sprintf('%o', fileperms($storagePath)), -4) : '-') . "\n";
echo "public/storage/snapbooth : " . (is_dir($snapPath) ? 'EXISTS' : 'MISSING') . "\n\n";

// Create snapbooth dir if missing
if (!is_dir($snapPath)) {
    if (mkdir($snapPath, 0775, true)) {
        echo "Created: public/storage/snapbooth\n";
    } else {
        echo "ERROR: Cannot create public/storage/snapbooth\n";
    }
} else {
    echo "snapbooth dir already exists\n";
}

// Fix permissions
chmod($storagePath, 0775);
chmod($snapPath, 0775);
echo "Permissions set to 775\n\n";

// Test write
$testFile = $snapPath . '/test_write.txt';
if (file_put_contents($testFile, 'ok') !== false) {
    echo "Write test: OK\n";
    unlink($testFile);
} else {
    echo "Write test: FAILED - directory not writable!\n";
}
