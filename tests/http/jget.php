<?php
/**
 * Minimal JSON path extractor.
 * Usage: php jget.php '{"data":{"id":1}}' data.id
 */
$json = $argv[1] ?? '';
$path = $argv[2] ?? '';

$data = json_decode($json, true);
if ($data === null) {
    exit(0);
}

foreach (explode('.', $path) as $key) {
    if (!isset($data[$key])) {
        exit(0);
    }
    $data = $data[$key];
}

if (is_bool($data)) {
    echo $data ? 'true' : 'false';
} elseif ($data !== null) {
    echo $data;
}
