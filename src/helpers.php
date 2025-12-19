<?php
declare(strict_types=1);

use App\Config;

/**
 * Generate UUID v4
 */
function uuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0xffff)
    );
}

/**
 * Get current ISO 8601 timestamp
 */
function now(): string
{
    return (new DateTime('now', new DateTimeZone('UTC')))->format(DateTime::ATOM);
}

/**
 * Sanitize user input for display
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get JSON input from request body
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    if ($input === false || $input === '') {
        return [];
    }
    $decoded = json_decode($input, true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Send JSON response and exit
 */
function jsonResponse(array $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/**
 * Send JSON success response
 */
function jsonSuccess(mixed $data = null, int $code = 200): never
{
    jsonResponse(['success' => true, 'data' => $data], $code);
}

/**
 * Send JSON error response
 */
function jsonError(string $message, int $code = 400): never
{
    jsonResponse(['success' => false, 'error' => $message], $code);
}

/**
 * Validate required fields exist in data
 */
function validateRequired(array $data, array $fields): ?string
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || (is_string($data[$field]) && trim($data[$field]) === '')) {
            return "Missing required field: {$field}";
        }
    }
    return null;
}

/**
 * Get base URL for the application
 */
function baseUrl(): string
{
    $siteUrl = Config::get('site_url', '');
    if ($siteUrl !== '') {
        return rtrim($siteUrl, '/');
    }

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "{$protocol}://{$host}";
}

/**
 * Render a template with variables
 */
function render(string $template, array $vars = []): string
{
    $templatePath = dirname(__DIR__) . '/templates/' . $template . '.php';

    if (!file_exists($templatePath)) {
        throw new RuntimeException("Template not found: {$template}");
    }

    extract($vars, EXTR_SKIP);
    ob_start();
    include $templatePath;
    return ob_get_clean() ?: '';
}

/**
 * Get request method
 */
function getMethod(): string
{
    return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
}

/**
 * Get request path without query string
 */
function getPath(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    $path = parse_url($uri, PHP_URL_PATH);
    return $path !== false && $path !== null ? $path : '/';
}
