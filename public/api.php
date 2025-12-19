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

// Initialize services
$config = Config::getInstance();
$auth = new Auth();
$todoList = new TodoList(Config::get('data_path'));
$api = new Api($todoList, $auth);

// Handle request
$method = getMethod();
$path = getPath();

// Handle API routes
$api->handle($method, $path);
