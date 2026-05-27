<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$snapPath = dirname(__DIR__) . '/public/storage/snapbooth';

if (!is_dir($snapPath)) {
    echo "Directory not found: $snapPath\n";
    exit;
}

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($snapPath, RecursiveDirectoryIterator::SKIP_DOTS)
);

$files = [];
foreach ($iterator as $file) {
    if ($file->isFile()) {
        $files[] = [
            'path' => str_replace($snapPath, '', $file->getPathname()),
            'size' => $file->getSize(),
            'perms' => substr(sprintf('%o', fileperms($file->getPathname())), -4),
        ];
    }
}

if (empty($files)) {
    echo "No files found in storage/snapbooth/\n";
} else {
    echo count($files) . " file(s) found:\n\n";
    foreach ($files as $f) {
        echo "{$f['path']} ({$f['size']} bytes, perms: {$f['perms']})\n";
    }
}

// Also show latest photos from DB
echo "\n--- Latest photos from database ---\n";
define('LARAVEL_START', microtime(true));
require dirname(__DIR__) . '/vendor/autoload.php';
$app = require_once dirname(__DIR__) . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$photos = \App\Models\Photo::latest()->take(5)->get(['id', 'file_path', 'file_url', 'thumbnail_path', 'session_id']);
foreach ($photos as $p) {
    echo "Photo #{$p->id} (session {$p->session_id})\n";
    echo "  file_url: {$p->file_url}\n";
    echo "  file_path: {$p->file_path}\n";
    echo "  file exists: " . (file_exists(dirname(__DIR__) . '/public/storage/snapbooth/' . $p->file_path) ? 'YES' : 'NO') . "\n\n";
}
