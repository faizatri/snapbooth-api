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

header('Content-Type: text/plain');

$commands = [
    ['config:clear', []],
    ['config:cache', []],
    ['route:cache',  []],
    ['event:cache',  []],
];

foreach ($commands as [$cmd, $args]) {
    echo "=== php artisan {$cmd} ===\n";
    try {
        $output = new \Symfony\Component\Console\Output\BufferedOutput();
        $status = $kernel->call($cmd, $args, $output);
        $result = trim($output->fetch());
        echo ($result ?: '(ok, no output)') . "\n";
        echo "Exit code: {$status}\n";
    } catch (\Throwable $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "=== SELESAI ===\n";
echo "HAPUS recache.php sekarang!\n";
