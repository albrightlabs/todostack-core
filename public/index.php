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

// Handle API requests
if (str_starts_with($path, 'api/') || $path === 'api') {
    require __DIR__ . '/api.php';
    exit;
}

// Check if setup is needed (no users exist)
if (!Auth::hasAnyUsers() && $path !== 'setup') {
    header('Location: /setup');
    exit;
}

// Handle setup (first-time installation)
if ($path === 'setup') {
    // If users already exist, redirect to login
    if (Auth::hasAnyUsers()) {
        header('Location: /login');
        exit;
    }

    $setupError = null;

    // Process setup form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!Auth::validateCsrf($_POST['csrf_token'] ?? null)) {
            $setupError = 'Invalid security token. Please try again.';
        } else {
            $name = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $passwordConfirm = $_POST['password_confirm'] ?? '';

            // Validate inputs
            if (empty($name)) {
                $setupError = 'Name is required.';
            } elseif (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $setupError = 'A valid email address is required.';
            } elseif (strlen($password) < 8) {
                $setupError = 'Password must be at least 8 characters.';
            } elseif ($password !== $passwordConfirm) {
                $setupError = 'Passwords do not match.';
            } else {
                // Create the first user as super admin
                try {
                    $userManager = Auth::getUserManager();
                    $userManager->create($name, $email, $password, 'admin', true);

                    // Auto-login the new user
                    Auth::login($email, $password);

                    header('Location: /');
                    exit;
                } catch (\Exception $e) {
                    $setupError = $e->getMessage();
                }
            }
        }
    }

    // Show setup page
    include __DIR__ . '/../templates/setup.php';
    exit;
}

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
        // Check rate limiting first
        if (Auth::isRateLimited()) {
            $remaining = Auth::getRateLimitRemainingTime();
            $minutes = ceil($remaining / 60);
            $authError = "Too many failed attempts. Please try again in {$minutes} minute(s).";
        } elseif (!Auth::validateCsrf($_POST['csrf_token'] ?? null)) {
            $authError = 'Invalid security token. Please try again.';
        } else {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            if (Auth::login($email, $password)) {
                header('Location: /');
                exit;
            }

            // Check if now rate limited after failed attempt
            if (Auth::isRateLimited()) {
                $remaining = Auth::getRateLimitRemainingTime();
                $minutes = ceil($remaining / 60);
                $authError = "Too many failed attempts. Please try again in {$minutes} minute(s).";
            } else {
                $authError = 'Invalid email or password. Please try again.';
            }
        }
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
