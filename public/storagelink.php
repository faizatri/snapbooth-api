<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$target = dirname(__DIR__) . '/storage/app/public';
$link   = __DIR__ . '/storage';

echo "target : $target\n";
echo "link   : $link\n";
echo "target is_dir: " . (is_dir($target) ? 'YES' : 'NO') . "\n";
echo "link exists  : " . (file_exists($link) ? 'YES' : 'NO') . "\n";
echo "link is_link : " . (is_link($link) ? 'YES' : 'NO') . "\n";
echo "symlink() exists: " . (function_exists('symlink') ? 'YES' : 'NO') . "\n\n";

if (is_link($link)) {
    echo "Symlink already exists -> " . readlink($link) . "\n";
    exit;
}

if (!is_dir($target)) {
    if (mkdir($target, 0775, true)) {
        echo "Created: storage/app/public\n";
    } else {
        echo "ERROR: Cannot create target dir\n";
        exit;
    }
}

if (!function_exists('symlink')) {
    echo "symlink() is disabled on this server.\n";
    echo "Fallback: creating public/storage as a real directory...\n";
    if (!file_exists($link)) {
        if (mkdir($link, 0775, true)) {
            echo "OK: created public/storage as real folder (no symlink).\n";
            echo "NOTE: Files will need to be stored directly in public/storage.\n";
        } else {
            echo "ERROR: Cannot create public/storage directory.\n";
        }
    }
    exit;
}

if (symlink($target, $link)) {
    echo "OK: public/storage -> $target\n";
} else {
    echo "FAILED: symlink() returned false.\n";
}
