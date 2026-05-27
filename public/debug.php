<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

echo "PHP Version: " . phpversion() . "\n";
echo "PHP SAPI: " . php_sapi_name() . "\n\n";

$root = dirname(__DIR__);
echo ".env exists: " . (file_exists($root . '/.env') ? 'YES' : 'NO') . "\n";
echo "vendor/autoload.php exists: " . (file_exists($root . '/vendor/autoload.php') ? 'YES' : 'NO') . "\n";
echo "bootstrap/app.php exists: " . (file_exists($root . '/bootstrap/app.php') ? 'YES' : 'NO') . "\n";
echo "storage/logs writable: " . (is_writable($root . '/storage/logs') ? 'YES' : 'NO') . "\n";
echo "bootstrap/cache writable: " . (is_writable($root . '/bootstrap/cache') ? 'YES' : 'NO') . "\n\n";

echo "--- Loaded PHP Extensions ---\n";
$needed = ['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath', 'fileinfo', 'curl'];
foreach ($needed as $ext) {
    echo $ext . ': ' . (extension_loaded($ext) ? 'OK' : 'MISSING') . "\n";
}

echo "\n--- .env APP_KEY preview ---\n";
$env = file_get_contents($root . '/.env');
if ($env !== false) {
    preg_match('/^APP_KEY=(.*)$/m', $env, $m);
    $key = $m[1] ?? '(not found)';
    echo "APP_KEY set: " . (strlen(trim($key)) > 0 ? 'YES (' . strlen(trim($key)) . ' chars)' : 'NO (empty)') . "\n";
} else {
    echo ".env could not be read\n";
}

echo "\n--- Try autoload ---\n";
try {
    require $root . '/vendor/autoload.php';
    echo "autoload: OK\n";
} catch (Throwable $e) {
    echo "autoload ERROR: " . $e->getMessage() . "\n";
    exit;
}

echo "\n--- Try bootstrap ---\n";
try {
    $app = require_once $root . '/bootstrap/app.php';
    echo "bootstrap: OK\n";
    echo "Laravel version: " . $app->version() . "\n";
} catch (Throwable $e) {
    echo "bootstrap ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " line " . $e->getLine() . "\n";
}
