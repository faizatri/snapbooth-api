<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$webRoot = '/home/hrgs6662/public_html/snapbooth-web';

if (!is_dir($webRoot)) {
    echo "ERROR: Directory not found: $webRoot\n";
    exit;
}

echo "Fixing permissions in: $webRoot\n\n";

// Fix all directories to 755
$dirs = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($webRoot, RecursiveDirectoryIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

$fixedDirs  = 0;
$fixedFiles = 0;

foreach ($dirs as $item) {
    if ($item->isDir()) {
        chmod($item->getPathname(), 0755);
        $fixedDirs++;
    } elseif ($item->isFile()) {
        chmod($item->getPathname(), 0644);
        $fixedFiles++;
    }
}

// Also fix the root directory itself
chmod($webRoot, 0755);

echo "Fixed $fixedDirs directories (755)\n";
echo "Fixed $fixedFiles files (644)\n\n";

// Verify .htaccess
$htaccess = $webRoot . '/dist/.htaccess';
if (file_exists($htaccess)) {
    echo ".htaccess found: $htaccess\n";
    echo ".htaccess permissions: " . substr(sprintf('%o', fileperms($htaccess)), -4) . "\n";
    echo ".htaccess content:\n" . file_get_contents($htaccess) . "\n";
} else {
    echo "WARNING: dist/.htaccess not found!\n";
}

echo "\nDone. Hapus fixperms.php sekarang!\n";
