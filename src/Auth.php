<?php
declare(strict_types=1);

namespace App;

class Auth
{
    private const SESSION_LIFETIME = 7200; // 2 hours
    private const MAX_LOGIN_ATTEMPTS = 5;
    private const LOCKOUT_DURATION = 900; // 15 minutes
    private const SESSION_KEYS = [
        'user_id',
        'user_name',
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

        // Detect HTTPS for secure cookie flag
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
            || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        session_set_cookie_params([
            'lifetime' => self::SESSION_LIFETIME,
            'path' => '/',
            'secure' => $isHttps,
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

    public static function hasAnyUsers(): bool
    {
        return self::getUserManager()->hasUsers();
    }

    public static function login(string $email, string $password): ?array
    {
        self::init();

        // Check rate limiting
        $ip = self::getClientIp();
        if (self::isRateLimited($ip)) {
            return null;
        }

        $userManager = self::getUserManager();
        $user = $userManager->verifyPassword($email, $password);

        if ($user === null) {
            self::recordFailedAttempt($ip);
            return null;
        }

        // Clear failed attempts on successful login
        self::clearFailedAttempts($ip);

        // Regenerate session ID on login for security
        session_regenerate_id(true);

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_name'] = $user['name'] ?? '';
        $_SESSION['user_email'] = $user['email'];
        $_SESSION['user_role'] = $user['role'];
        $_SESSION['is_super_admin'] = $user['is_super_admin'];
        $_SESSION['auth_time'] = time();

        // Generate new CSRF token
        self::regenerateCsrfToken();

        return $user;
    }

    public static function isRateLimited(?string $ip = null): bool
    {
        $ip = $ip ?? self::getClientIp();
        $attempts = self::getFailedAttempts($ip);

        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $elapsed = time() - $attempts['last_attempt'];
            if ($elapsed < self::LOCKOUT_DURATION) {
                return true;
            }
            // Lockout expired, clear attempts
            self::clearFailedAttempts($ip);
        }

        return false;
    }

    public static function getRateLimitRemainingTime(?string $ip = null): int
    {
        $ip = $ip ?? self::getClientIp();
        $attempts = self::getFailedAttempts($ip);

        if ($attempts['count'] >= self::MAX_LOGIN_ATTEMPTS) {
            $elapsed = time() - $attempts['last_attempt'];
            $remaining = self::LOCKOUT_DURATION - $elapsed;
            return max(0, $remaining);
        }

        return 0;
    }

    private static function getClientIp(): string
    {
        // Check for forwarded IP (behind proxy/load balancer)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            return trim($ips[0]);
        }

        return $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    private static function getRateLimitFile(): string
    {
        return Config::get('data_path', dirname(__DIR__) . '/data') . '/rate_limits.json';
    }

    private static function getFailedAttempts(string $ip): array
    {
        $file = self::getRateLimitFile();

        if (!file_exists($file)) {
            return ['count' => 0, 'last_attempt' => 0];
        }

        $data = json_decode(file_get_contents($file), true) ?? [];

        // Clean up old entries while we're here
        $data = self::cleanupRateLimits($data);

        return $data[$ip] ?? ['count' => 0, 'last_attempt' => 0];
    }

    private static function recordFailedAttempt(string $ip): void
    {
        $file = self::getRateLimitFile();
        $data = [];

        if (file_exists($file)) {
            $data = json_decode(file_get_contents($file), true) ?? [];
        }

        $data = self::cleanupRateLimits($data);

        if (!isset($data[$ip])) {
            $data[$ip] = ['count' => 0, 'last_attempt' => 0];
        }

        $data[$ip]['count']++;
        $data[$ip]['last_attempt'] = time();

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function clearFailedAttempts(string $ip): void
    {
        $file = self::getRateLimitFile();

        if (!file_exists($file)) {
            return;
        }

        $data = json_decode(file_get_contents($file), true) ?? [];
        unset($data[$ip]);

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
    }

    private static function cleanupRateLimits(array $data): array
    {
        $now = time();
        foreach ($data as $ip => $attempts) {
            // Remove entries older than lockout duration
            if ($now - $attempts['last_attempt'] > self::LOCKOUT_DURATION) {
                unset($data[$ip]);
            }
        }
        return $data;
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
            'name' => $_SESSION['user_name'] ?? '',
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
