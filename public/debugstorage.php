<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

define('LARAVEL_START', microtime(true));
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

header('Content-Type: text/plain');

// 1. Cek env values
echo "=== ENV STORAGE ===\n";
echo "STORAGE_DISK     : " . (env('STORAGE_DISK') ?? '(not set, default: r2)') . "\n";
echo "FILESYSTEM_DISK  : " . (env('FILESYSTEM_DISK') ?? '(not set)') . "\n";
echo "APP_URL          : " . env('APP_URL') . "\n\n";

// 2. Cek config
echo "=== CONFIG ===\n";
$diskName = config('filesystems.storage_disk', 'r2');
echo "Active disk      : {$diskName}\n";
echo "Disk root        : " . (config("filesystems.disks.{$diskName}.root") ?? '(none)') . "\n";
echo "Disk url         : " . (config("filesystems.disks.{$diskName}.url") ?? '(none)') . "\n\n";

// 3. Test write ke templates/previews
echo "=== WRITE TEST ===\n";
try {
    $disk = \Illuminate\Support\Facades\Storage::disk($diskName);
    $testPath = 'templates/previews/test_' . time() . '.txt';
    $result = $disk->put($testPath, 'test content');
    if ($result) {
        echo "Write test       : OK ({$testPath})\n";
        $exists = $disk->exists($testPath);
        echo "Exists check     : " . ($exists ? 'YES' : 'NO') . "\n";
        $disk->delete($testPath);
        echo "Cleanup          : OK\n";
    } else {
        echo "Write test       : FAILED (returned false)\n";
    }
} catch (\Throwable $e) {
    echo "Write test ERROR : " . $e->getMessage() . "\n";
    echo "Trace            : " . $e->getTraceAsString() . "\n";
}

// 4. Cek direktori di filesystem
echo "\n=== DIRECTORY CHECK ===\n";
$publicPath = public_path('storage/snapbooth');
echo "public/storage/snapbooth     : " . (is_dir($publicPath) ? 'EXISTS' : 'MISSING') . "\n";
$templatesPath = $publicPath . '/templates';
echo "public/storage/snapbooth/templates : " . (is_dir($templatesPath) ? 'EXISTS' : 'MISSING') . "\n";
$previewsPath = $templatesPath . '/previews';
echo "public/storage/snapbooth/templates/previews : " . (is_dir($previewsPath) ? 'EXISTS' : 'MISSING') . "\n";

// Try to create if missing
if (!is_dir($previewsPath)) {
    $created = @mkdir($previewsPath, 0755, true);
    echo "mkdir result     : " . ($created ? 'created!' : 'FAILED (' . error_get_last()['message'] . ')') . "\n";
}

echo "\n=== SELESAI ===\n";
