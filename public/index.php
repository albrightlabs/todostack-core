<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Config;
use App\Auth;
use App\TodoList;

// Initialize services
$config = Config::getInstance();
$auth = new Auth();
$todoList = new TodoList(Config::get('data_path'));

// Get CSRF token for JavaScript
$csrfToken = $auth->getCsrfToken();

// Get initial data
$list = $todoList->getList();

// Get branding configuration
$branding = Config::getBranding();

// Page data
$pageTitle = $branding['site_name'];

// Render the app
$content = render('app', [
    'csrfToken' => $csrfToken,
    'list' => $list,
    'branding' => $branding,
]);

echo render('layout', [
    'pageTitle' => $pageTitle,
    'content' => $content,
    'csrfToken' => $csrfToken,
    'branding' => $branding,
]);
