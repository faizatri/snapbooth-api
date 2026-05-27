<?php
// HAPUS FILE INI SETELAH SELESAI
$token = $_GET['token'] ?? '';
if ($token !== 'snapbooth2026') {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain');

$root = dirname(__DIR__);

$files = [
    'vendor/symfony/deprecation-contracts/function.php' => '<?php

if (!function_exists(\'trigger_deprecation\')) {
    function trigger_deprecation(string $package, string $version, string $message, mixed ...$args): void
    {
        @trigger_error(($package || $version ? "Since $package $version: " : \'\').(($args) ? vsprintf($message, $args) : $message), \E_USER_DEPRECATED);
    }
}
',
];

foreach ($files as $relPath => $content) {
    $fullPath = $root . '/' . $relPath;
    $dir = dirname($fullPath);

    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
        echo "Created dir: $dir\n";
    }

    if (file_put_contents($fullPath, $content) !== false) {
        echo "OK: $relPath\n";
    } else {
        echo "FAILED: $relPath\n";
    }
}

echo "\nDone. Now delete fixvendor.php and run debug.php again.\n";
