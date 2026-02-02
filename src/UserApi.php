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

        // Self-service password change (any authenticated user)
        if (preg_match('#^/api/auth/password$#', $path)) {
            if ($method === 'POST') {
                Auth::requireAuth();
                Auth::requireCsrf();
                $this->changeOwnPassword();
                return;
            }
        }

        // Sendable users list (any authenticated user can see)
        if (preg_match('#^/api/users/sendable$#', $path)) {
            Auth::requireAuth();
            if ($method === 'GET') {
                $this->listSendableUsers();
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

    private function changeOwnPassword(): void
    {
        $input = getJsonInput();

        $error = validateRequired($input, ['current_password', 'new_password']);
        if ($error !== null) {
            jsonError($error);
        }

        // Validate new password length
        if (strlen($input['new_password']) < 8) {
            jsonError('New password must be at least 8 characters');
        }

        // Get current user - try by ID first, then by email as fallback
        $userId = Auth::getCurrentUserId();
        $user = $this->userManager->getById($userId);
        if ($user === null) {
            // Fallback: look up by email from session
            $currentUser = Auth::getCurrentUser();
            if ($currentUser && !empty($currentUser['email'])) {
                $user = $this->userManager->getByEmail($currentUser['email']);
            }
        }
        if ($user === null) {
            jsonError('User not found', 404);
        }
        $userId = $user['id']; // Use the actual user ID from the lookup

        // Verify current password
        $verifiedUser = $this->userManager->verifyPassword($user['email'], $input['current_password']);
        if ($verifiedUser === null) {
            jsonError('Current password is incorrect', 401);
        }

        // Update password
        if ($this->userManager->changePassword($userId, $input['new_password'])) {
            jsonSuccess(['message' => 'Password changed successfully']);
        } else {
            jsonError('Failed to change password', 500);
        }
    }

    private function listUsers(): void
    {
        $users = $this->userManager->getAll();
        jsonSuccess($users);
    }

    private function listSendableUsers(): void
    {
        $users = $this->userManager->getAll();
        $currentUserId = Auth::getCurrentUserId();

        // Filter out current user and return minimal info for send dialog
        $sendableUsers = array_values(array_filter(
            array_map(function ($user) {
                return [
                    'id' => $user['id'],
                    'name' => $user['name'] ?: $user['email'],
                    'email' => $user['email'],
                ];
            }, $users),
            fn($user) => $user['id'] !== $currentUserId
        ));

        jsonSuccess($sendableUsers);
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

        $error = validateRequired($input, ['name', 'email', 'password', 'role']);
        if ($error !== null) {
            jsonError($error);
        }

        // Validate name
        if (empty(trim($input['name']))) {
            jsonError('Name is required');
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
                $input['name'],
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
