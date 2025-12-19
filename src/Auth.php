<?php
declare(strict_types=1);

namespace App;

class Auth
{
    public function __construct()
    {
        $this->ensureSession();
    }

    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Generate or retrieve CSRF token
     */
    public function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Validate CSRF token from request
     */
    public function validateCsrfToken(?string $token): bool
    {
        if ($token === null || empty($_SESSION['csrf_token'])) {
            return false;
        }
        return hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Get CSRF token from request header or body
     */
    public function getRequestCsrfToken(): ?string
    {
        // Check header first
        $headerToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? null;
        if ($headerToken !== null) {
            return $headerToken;
        }

        // Check POST body
        $input = getJsonInput();
        return $input['_token'] ?? null;
    }

    /**
     * Validate CSRF for write operations
     */
    public function requireCsrf(): void
    {
        $token = $this->getRequestCsrfToken();
        if (!$this->validateCsrfToken($token)) {
            jsonError('Invalid CSRF token', 403);
        }
    }

    /**
     * Check if password protection is enabled
     */
    public function isPasswordProtected(): bool
    {
        $password = Config::get('admin_password', '');
        return $password !== '';
    }

    /**
     * Check if user is authenticated
     */
    public function isAuthenticated(): bool
    {
        if (!$this->isPasswordProtected()) {
            return true;
        }
        return !empty($_SESSION['authenticated']);
    }

    /**
     * Attempt login with password
     */
    public function login(string $password): bool
    {
        $correctPassword = Config::get('admin_password', '');
        if ($correctPassword === '' || !password_verify($password, $correctPassword)) {
            // Also allow plain text comparison for simple setups
            if ($correctPassword !== $password) {
                return false;
            }
        }
        $_SESSION['authenticated'] = true;
        return true;
    }

    /**
     * Logout user
     */
    public function logout(): void
    {
        $_SESSION['authenticated'] = false;
        session_regenerate_id(true);
    }

    /**
     * Require authentication for protected pages
     */
    public function requireAuth(): void
    {
        if (!$this->isAuthenticated()) {
            if ($this->isApiRequest()) {
                jsonError('Authentication required', 401);
            }
            header('Location: /login');
            exit;
        }
    }

    /**
     * Check if current request is an API request
     */
    private function isApiRequest(): bool
    {
        $path = getPath();
        return str_starts_with($path, '/api/');
    }
}
