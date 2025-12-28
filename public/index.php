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
$branding = Config::getBranding();

// Get request path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = trim($requestUri, '/');

// Handle logout
if ($path === 'logout') {
    $auth->logout();
    header('Location: /');
    exit;
}

// Handle login
if ($path === 'login') {
    $authError = null;

    // Process login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
        if ($auth->login($_POST['password'])) {
            header('Location: /');
            exit;
        }
        $authError = 'Incorrect password. Please try again.';
    }

    // Show login page if password protected and not authenticated
    if ($auth->isPasswordProtected() && !$auth->isAuthenticated()) {
        include __DIR__ . '/../templates/password.php';
        exit;
    }

    // If not password protected or already authenticated, redirect to home
    header('Location: /');
    exit;
}

// Check authentication for main app
if ($auth->isPasswordProtected() && !$auth->isAuthenticated()) {
    header('Location: /login');
    exit;
}

// Initialize todo list
$todoList = new TodoList(Config::get('data_path'));

// Get CSRF token for JavaScript
$csrfToken = $auth->getCsrfToken();

// Get initial data
$list = $todoList->getList();

// Page data
$pageTitle = 'To-Do List - ' . $branding['site_name'];

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
