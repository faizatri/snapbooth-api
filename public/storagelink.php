<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$target = dirname(__DIR__) . '/storage/app/public';
$link   = __DIR__ . '/storage';

if (is_link($link)) {
    echo "Symlink already exists: public/storage -> " . readlink($link) . "\n";
    exit;
}

if (file_exists($link)) {
    echo "ERROR: public/storage already exists as a real directory, not a symlink.\n";
    exit;
}

if (!is_dir($target)) {
    mkdir($target, 0775, true);
    echo "Created target dir: storage/app/public\n";
}

if (symlink($target, $link)) {
    echo "OK: public/storage -> $target\n";
} else {
    echo "FAILED: symlink() gagal. Coba hubungi hosting untuk aktifkan symlink.\n";
    echo "Alternatif: buat folder public/storage secara manual di File Manager.\n";
}
