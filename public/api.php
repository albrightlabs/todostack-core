<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Config;
use App\Auth;
use App\TodoList;
use App\Api;
use App\UserApi;
use App\Upload;

// Initialize config
$config = Config::getInstance();

// Get request info
$method = getMethod();
$path = getPath();

// Handle file uploads (multipart/form-data) - needs special handling
if ($method === 'POST' && preg_match('#^/api/items/([^/]+)/attachments$#', $path, $matches)) {
    Auth::requireAuth();
    Auth::requireCsrf();

    if (!Auth::canWrite()) {
        jsonError('Read-only access. Modifications not allowed.', 403);
    }

    $itemId = $matches[1];

    // Handle file upload
    if (!isset($_FILES['file'])) {
        jsonError('No file provided', 400);
    }

    $upload = new Upload();
    $result = $upload->handleUpload($_FILES['file']);

    if (!$result['success']) {
        jsonError($result['error'], 400);
    }

    // Add attachment to item
    $todoList = new TodoList(Config::get('data_path'));
    $attachment = $todoList->addAttachment($itemId, [
        'filename' => $result['filename'],
        'url' => $result['url'],
        'size' => $result['size'],
        'type' => $result['type'],
    ]);

    if ($attachment === null) {
        // Clean up uploaded file
        $upload->deleteFile($result['url']);
        jsonError('Item not found', 404);
    }

    jsonSuccess($attachment, 201);
}

// Route to appropriate API handler
if (str_starts_with($path, '/api/auth/') || str_starts_with($path, '/api/users')) {
    // User and auth endpoints
    $userApi = new UserApi();
    $userApi->handle($method, $path);
} else {
    // Todo list endpoints
    $todoList = new TodoList(Config::get('data_path'));
    $api = new Api($todoList);
    $api->handle($method, $path);
}
