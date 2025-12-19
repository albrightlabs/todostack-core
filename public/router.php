<?php
declare(strict_types=1);

/**
 * PHP Built-in Server Router
 * Usage: php -S localhost:8000 -t public router.php
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');

// Serve static files directly
if ($uri !== '/' && file_exists(__DIR__ . $uri)) {
    // Check if it's a static file
    $extension = pathinfo($uri, PATHINFO_EXTENSION);
    $staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf'];

    if (in_array($extension, $staticExtensions)) {
        return false; // Let PHP serve the file
    }
}

// Route API requests
if (str_starts_with($uri, '/api/')) {
    require __DIR__ . '/api.php';
    exit;
}

// All other requests go to index.php
require __DIR__ . '/index.php';
