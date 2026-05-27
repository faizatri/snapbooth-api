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
$app->make(Illuminate\Contracts\Http\Kernel::class);

header('Content-Type: text/plain');

$name     = $_GET['name']     ?? 'Admin';
$email    = $_GET['email']    ?? 'admin@snapbooth.com';
$password = $_GET['password'] ?? 'Admin1234!';

try {
    $existing = \App\Models\User::where('email', $email)->first();
    if ($existing) {
        echo "User sudah ada: {$email}\n";
        exit;
    }

    \App\Models\User::create([
        'name'     => $name,
        'email'    => $email,
        'password' => \Illuminate\Support\Facades\Hash::make($password),
    ]);

    echo "OK: User dibuat\n";
    echo "Email   : {$email}\n";
    echo "Password: {$password}\n";
    echo "\nHAPUS file createadmin.php sekarang!\n";
} catch (\Throwable $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
