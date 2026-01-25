<?php
declare(strict_types=1);

namespace App;

class UserManager
{
    private string $usersFile;
    private array $data = [];
    private bool $loaded = false;

    public function __construct(?string $dataPath = null)
    {
        $basePath = $dataPath ?? dirname(__DIR__) . '/data';
        $this->usersFile = $basePath . '/users.json';
    }

    private function load(): void
    {
        if ($this->loaded) {
            return;
        }

        if (file_exists($this->usersFile)) {
            $content = file_get_contents($this->usersFile);
            if ($content !== false) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $this->data = $decoded;
                }
            }
        }

        if (empty($this->data)) {
            $this->data = [
                'users' => [],
                'meta' => [
                    'version' => 1,
                    'last_modified' => now(),
                ],
            ];
        }

        $this->loaded = true;
    }

    public function hasUsers(): bool
    {
        $this->load();
        return count($this->data['users']) > 0;
    }

    private function save(): void
    {
        $this->data['meta']['last_modified'] = now();

        $dir = dirname($this->usersFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        file_put_contents($this->usersFile, $json, LOCK_EX);
        chmod($this->usersFile, 0600);
    }

    public function getAll(): array
    {
        $this->load();

        return array_map(function ($user) {
            return $this->sanitizeUser($user);
        }, $this->data['users']);
    }

    public function getById(string $id): ?array
    {
        $this->load();

        foreach ($this->data['users'] as $user) {
            if ($user['id'] === $id) {
                return $this->sanitizeUser($user);
            }
        }
        return null;
    }

    public function getByEmail(string $email): ?array
    {
        $this->load();

        $email = strtolower(trim($email));
        foreach ($this->data['users'] as $user) {
            if (strtolower($user['email']) === $email) {
                return $user; // Include password_hash for verification
            }
        }
        return null;
    }

    public function create(string $name, string $email, string $password, string $role = 'readonly', bool $isSuperAdmin = false): array
    {
        $this->load();

        $name = trim($name);
        $email = strtolower(trim($email));

        // Validate name
        if (empty($name)) {
            throw new \RuntimeException('Name is required');
        }

        // Check for duplicate email
        if ($this->getByEmail($email) !== null) {
            throw new \RuntimeException('A user with this email already exists');
        }

        // Validate role
        if (!in_array($role, ['admin', 'readonly'], true)) {
            throw new \RuntimeException('Invalid role');
        }

        $user = [
            'id' => uuid(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'is_super_admin' => $isSuperAdmin,
            'created_at' => now(),
            'updated_at' => now(),
            'last_login_at' => null,
        ];

        $this->data['users'][] = $user;
        $this->save();

        return $this->sanitizeUser($user);
    }

    public function update(string $id, array $data): ?array
    {
        $this->load();

        foreach ($this->data['users'] as &$user) {
            if ($user['id'] === $id) {
                // Update allowed fields
                if (isset($data['name'])) {
                    $name = trim($data['name']);
                    if (empty($name)) {
                        throw new \RuntimeException('Name is required');
                    }
                    $user['name'] = $name;
                }

                if (isset($data['email'])) {
                    $newEmail = strtolower(trim($data['email']));
                    // Check for duplicate (excluding current user)
                    $existing = $this->getByEmail($newEmail);
                    if ($existing !== null && $existing['id'] !== $id) {
                        throw new \RuntimeException('A user with this email already exists');
                    }
                    $user['email'] = $newEmail;
                }

                if (isset($data['role']) && !$user['is_super_admin']) {
                    if (!in_array($data['role'], ['admin', 'readonly'], true)) {
                        throw new \RuntimeException('Invalid role');
                    }
                    $user['role'] = $data['role'];
                }

                $user['updated_at'] = now();
                $this->save();

                return $this->sanitizeUser($user);
            }
        }
        return null;
    }

    public function delete(string $id): bool
    {
        $this->load();

        foreach ($this->data['users'] as $index => $user) {
            if ($user['id'] === $id) {
                if ($user['is_super_admin']) {
                    throw new \RuntimeException('Cannot delete super admin');
                }
                array_splice($this->data['users'], $index, 1);
                $this->save();
                return true;
            }
        }
        return false;
    }

    public function verifyPassword(string $email, string $password): ?array
    {
        $user = $this->getByEmail($email);
        if ($user === null) {
            return null;
        }

        if (!password_verify($password, $user['password_hash'])) {
            return null;
        }

        // Update last login
        $this->updateLastLogin($user['id']);

        return $this->sanitizeUser($user);
    }

    public function changePassword(string $id, string $newPassword): bool
    {
        $this->load();

        foreach ($this->data['users'] as &$user) {
            if ($user['id'] === $id) {
                $user['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
                $user['updated_at'] = now();
                $this->save();
                return true;
            }
        }
        return false;
    }

    private function updateLastLogin(string $id): void
    {
        foreach ($this->data['users'] as &$user) {
            if ($user['id'] === $id) {
                $user['last_login_at'] = now();
                $this->save();
                return;
            }
        }
    }

    public function isSuperAdmin(string $id): bool
    {
        $this->load();

        foreach ($this->data['users'] as $user) {
            if ($user['id'] === $id) {
                return $user['is_super_admin'] === true;
            }
        }
        return false;
    }

    private function sanitizeUser(array $user): array
    {
        unset($user['password_hash']);
        return $user;
    }
}
