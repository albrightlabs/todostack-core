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

// Initialize config
$config = Config::getInstance();

// Get request info
$method = getMethod();
$path = getPath();

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
