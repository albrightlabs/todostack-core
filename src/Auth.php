<?php
declare(strict_types=1);

namespace App;

class Auth
{
    private const SESSION_LIFETIME = 7200; // 2 hours
    private const SESSION_KEYS = [
        'user_id',
        'user_email',
        'user_role',
        'is_super_admin',
        'auth_time',
        'csrf_token',
    ];

    private static ?UserManager $userManager = null;

    public static function init(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => self::SESSION_LIFETIME,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        session_start();

        // Check session timeout
        if (isset($_SESSION['auth_time'])) {
            $elapsed = time() - $_SESSION['auth_time'];
            if ($elapsed > self::SESSION_LIFETIME) {
                self::logout();
            }
        }
    }

    public static function getUserManager(): UserManager
    {
        if (self::$userManager === null) {
            self::$userManager = new UserManager();
        }
        return self::$userManager;
    }

    public static function login(string $email, string $password): ?array
    {
        self::init();

        $userManager = self::getUserManager();
        $user = $userManager->verifyPassword($email, $password);

        if ($user === null) {
            return null;
        }

        // Regenerate session ID on login for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['is_super_admin'] = $user['is_super_admin'];
        $_SESSION['auth_time'] = time();

        // Generate new CSRF token
        self::regenerateCsrfToken();

        return $user;
    }

    public static function logout(): void
    {
        self::init();

        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }

    public static function check(): bool
    {
        self::init();

        if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
            return false;
        }

        // Check session timeout
        if (isset($_SESSION['auth_time'])) {
            $elapsed = time() - $_SESSION['auth_time'];
            if ($elapsed > self::SESSION_LIFETIME) {
                self::logout();
                return false;
            }

            // Update last activity time
            $_SESSION['auth_time'] = time();
        }

        return true;
    }

    public static function requireAuth(): void
    {
        if (!self::check()) {
            if (self::isAjax()) {
                jsonError('Unauthorized', 401);
            }
            header('Location: /login');
            exit;
        }
    }

    public static function requireAdmin(): void
    {
        self::requireAuth();

        if (!self::isAdmin()) {
            if (self::isAjax()) {
                jsonError('Admin access required', 403);
            }
            header('Location: /');
            exit;
        }
    }

    public static function canWrite(): bool
    {
        return self::isAdmin();
    }

    public static function isAdmin(): bool
    {
        self::init();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
    }

    public static function isReadOnly(): bool
    {
        self::init();
        return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'readonly';
    }

    public static function isSuperAdmin(): bool
    {
        self::init();
        return isset($_SESSION['is_super_admin']) && $_SESSION['is_super_admin'] === true;
    }

    public static function getCurrentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['user_email'] ?? null,
            'role' => $_SESSION['user_role'] ?? null,
            'is_super_admin' => $_SESSION['is_super_admin'] ?? false,
        ];
    }

    public static function getCurrentUserId(): ?string
    {
        self::init();
        return $_SESSION['user_id'] ?? null;
    }

    public static function getCurrentUserRole(): ?string
    {
        self::init();
        return $_SESSION['user_role'] ?? null;
    }

    public static function getCsrfToken(): string
    {
        self::init();

        if (!isset($_SESSION['csrf_token'])) {
            self::regenerateCsrfToken();
        }

        return $_SESSION['csrf_token'];
    }

    public static function regenerateCsrfToken(): string
    {
        self::init();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        return $_SESSION['csrf_token'];
    }

    public static function validateCsrf(?string $token = null): bool
    {
        self::init();

        if ($token === null) {
            // Check header first, then POST data
            $token = $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? $_POST['csrf_token']
                ?? null;
        }

        if ($token === null || !isset($_SESSION['csrf_token'])) {
            return false;
        }

        return hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function requireCsrf(): void
    {
        if (!self::validateCsrf()) {
            jsonError('Invalid CSRF token', 403);
        }
    }

    public static function csrfField(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(self::getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    public static function csrfMeta(): string
    {
        return '<meta name="csrf-token" content="' . htmlspecialchars(self::getCsrfToken(), ENT_QUOTES, 'UTF-8') . '">';
    }

    private static function isAjax(): bool
    {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
            && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest'
            || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
            || str_starts_with(getPath(), '/api/');
    }
}
