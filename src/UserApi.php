<?php
declare(strict_types=1);

namespace App;

class UserApi
{
    private UserManager $userManager;

    public function __construct()
    {
        $this->userManager = Auth::getUserManager();
    }

    public function handle(string $method, string $path): void
    {
        // Auth endpoints (no auth required for login)
        if (preg_match('#^/api/auth/login$#', $path)) {
            if ($method === 'POST') {
                $this->login();
                return;
            }
        }

        if (preg_match('#^/api/auth/logout$#', $path)) {
            if ($method === 'POST') {
                $this->logout();
                return;
            }
        }

        if (preg_match('#^/api/auth/status$#', $path)) {
            if ($method === 'GET') {
                $this->getStatus();
                return;
            }
        }

        if (preg_match('#^/api/auth/me$#', $path)) {
            if ($method === 'GET') {
                Auth::requireAuth();
                $this->getCurrentUser();
                return;
            }
        }

        // User management endpoints (admin only)
        if (preg_match('#^/api/users$#', $path)) {
            Auth::requireAdmin();
            if ($method === 'GET') {
                $this->listUsers();
                return;
            }
            if ($method === 'POST') {
                Auth::requireCsrf();
                $this->createUser();
                return;
            }
        }

        if (preg_match('#^/api/users/([a-f0-9-]+)$#', $path, $matches)) {
            Auth::requireAdmin();
            $userId = $matches[1];

            if ($method === 'GET') {
                $this->getUser($userId);
                return;
            }
            if ($method === 'PUT') {
                Auth::requireCsrf();
                $this->updateUser($userId);
                return;
            }
            if ($method === 'DELETE') {
                Auth::requireCsrf();
                $this->deleteUser($userId);
                return;
            }
        }

        if (preg_match('#^/api/users/([a-f0-9-]+)/password$#', $path, $matches)) {
            Auth::requireAdmin();
            Auth::requireCsrf();
            if ($method === 'POST') {
                $this->changePassword($matches[1]);
                return;
            }
        }

        jsonError('Not found', 404);
    }

    private function login(): void
    {
        $input = getJsonInput();

        $error = validateRequired($input, ['email', 'password']);
        if ($error !== null) {
            jsonError($error);
        }

        $user = Auth::login($input['email'], $input['password']);

        if ($user === null) {
            jsonError('Invalid email or password', 401);
        }

        jsonSuccess([
            'user' => $user,
            'csrf_token' => Auth::getCsrfToken(),
        ]);
    }

    private function logout(): void
    {
        Auth::logout();
        jsonSuccess(null);
    }

    private function getStatus(): void
    {
        $authenticated = Auth::check();
        $user = $authenticated ? Auth::getCurrentUser() : null;

        jsonSuccess([
            'authenticated' => $authenticated,
            'user' => $user,
            'csrf_token' => Auth::getCsrfToken(),
        ]);
    }

    private function getCurrentUser(): void
    {
        $user = Auth::getCurrentUser();
        if ($user === null) {
            jsonError('Not authenticated', 401);
        }
        jsonSuccess($user);
    }

    private function listUsers(): void
    {
        $users = $this->userManager->getAll();
        jsonSuccess($users);
    }

    private function getUser(string $id): void
    {
        $user = $this->userManager->getById($id);
        if ($user === null) {
            jsonError('User not found', 404);
        }
        jsonSuccess($user);
    }

    private function createUser(): void
    {
        $input = getJsonInput();

        $error = validateRequired($input, ['email', 'password', 'role']);
        if ($error !== null) {
            jsonError($error);
        }

        // Validate email format
        if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email format');
        }

        // Validate password length
        if (strlen($input['password']) < 8) {
            jsonError('Password must be at least 8 characters');
        }

        try {
            $user = $this->userManager->create(
                $input['email'],
                $input['password'],
                $input['role']
            );
            jsonSuccess($user, 201);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage());
        }
    }

    private function updateUser(string $id): void
    {
        $input = getJsonInput();

        if (empty($input)) {
            jsonError('No data provided');
        }

        // Validate email format if provided
        if (isset($input['email']) && !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            jsonError('Invalid email format');
        }

        try {
            $user = $this->userManager->update($id, $input);
            if ($user === null) {
                jsonError('User not found', 404);
            }
            jsonSuccess($user);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage());
        }
    }

    private function deleteUser(string $id): void
    {
        try {
            $deleted = $this->userManager->delete($id);
            if (!$deleted) {
                jsonError('User not found', 404);
            }
            jsonSuccess(null);
        } catch (\RuntimeException $e) {
            jsonError($e->getMessage());
        }
    }

    private function changePassword(string $id): void
    {
        $input = getJsonInput();

        $error = validateRequired($input, ['password']);
        if ($error !== null) {
            jsonError($error);
        }

        // Validate password length
        if (strlen($input['password']) < 8) {
            jsonError('Password must be at least 8 characters');
        }

        $changed = $this->userManager->changePassword($id, $input['password']);
        if (!$changed) {
            jsonError('User not found', 404);
        }

        jsonSuccess(null);
    }
}
