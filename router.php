<?php
// router.php — used when running PHP built-in server on port 8080
// Usage: php -S 0.0.0.0:8080 router.php  (run from htdocs/streetlights/)
//
// Without this, the built-in server serves index.php for ALL requests,
// which is why the ESP32 was getting HTML back instead of insert_data.php output.

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Remove leading slash
$file = ltrim($uri, '/');

// If file exists, serve it directly (PHP handles .php, static serves others)
if ($file !== '' && file_exists(__DIR__ . '/' . $file)) {
    return false; // Let the built-in server handle it normally
}

// Otherwise fall through to index.php
require_once __DIR__ . '/index.php';
