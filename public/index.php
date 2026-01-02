<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

use App\Config;
use App\Auth;
use App\TodoList;

// Initialize config
$config = Config::getInstance();
$branding = Config::getBranding();

// Get request path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$path = trim($requestUri, '/');

// Handle logout
if ($path === 'logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// Handle login
if ($path === 'login') {
    $authError = null;

    // If already authenticated, redirect to home
    if (Auth::check()) {
        header('Location: /');
        exit;
    }

    // Process login form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (Auth::login($email, $password)) {
            header('Location: /');
            exit;
        }
        $authError = 'Invalid email or password. Please try again.';
    }

    // Show login page
    include __DIR__ . '/../templates/login.php';
    exit;
}

// All other routes require authentication
Auth::requireAuth();

// Get current user for templates
$currentUser = Auth::getCurrentUser();
$csrfToken = Auth::getCsrfToken();

// Handle users management page (admin only)
if ($path === 'users') {
    Auth::requireAdmin();

    $pageTitle = 'User Management - ' . $branding['site_name'];

    $content = render('users', [
        'csrfToken' => $csrfToken,
        'branding' => $branding,
        'currentUser' => $currentUser,
    ]);

    echo render('layout', [
        'pageTitle' => $pageTitle,
        'content' => $content,
        'csrfToken' => $csrfToken,
        'branding' => $branding,
        'currentUser' => $currentUser,
    ]);
    exit;
}

// Initialize todo list
$todoList = new TodoList(Config::get('data_path'));

// Get initial data
$list = $todoList->getList();

// Page data
$pageTitle = 'To-Do List - ' . $branding['site_name'];

// Render the app
$content = render('app', [
    'csrfToken' => $csrfToken,
    'list' => $list,
    'branding' => $branding,
    'currentUser' => $currentUser,
]);

echo render('layout', [
    'pageTitle' => $pageTitle,
    'content' => $content,
    'csrfToken' => $csrfToken,
    'branding' => $branding,
    'currentUser' => $currentUser,
]);
