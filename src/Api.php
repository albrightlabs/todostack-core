<?php
declare(strict_types=1);

namespace App;

class Api
{
    private TodoList $todoList;
    private Auth $auth;

    public function __construct(TodoList $todoList, Auth $auth)
    {
        $this->todoList = $todoList;
        $this->auth = $auth;
    }

    /**
     * Handle API request
     */
    public function handle(string $method, string $path): void
    {
        // Remove /api prefix
        $path = preg_replace('#^/api#', '', $path) ?: '/';

        // Require CSRF for write operations
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $this->auth->requireCsrf();
        }

        // Route the request
        match (true) {
            // List
            $method === 'GET' && $path === '/list' => $this->getList(),

            // Settings
            $method === 'PUT' && $path === '/settings' => $this->updateSettings(),

            // Sections
            $method === 'POST' && $path === '/sections' => $this->createSection(),
            $method === 'PUT' && preg_match('#^/sections/([^/]+)$#', $path, $m) => $this->updateSection($m[1]),
            $method === 'PUT' && preg_match('#^/sections/([^/]+)/reorder$#', $path, $m) => $this->reorderSection($m[1]),
            $method === 'DELETE' && preg_match('#^/sections/([^/]+)$#', $path, $m) => $this->deleteSection($m[1]),

            // Items in sections
            $method === 'POST' && preg_match('#^/sections/([^/]+)/items$#', $path, $m) => $this->createItem($m[1]),

            // Items
            $method === 'GET' && preg_match('#^/items/([^/]+)$#', $path, $m) => $this->getItem($m[1]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)$#', $path, $m) => $this->updateItem($m[1]),
            $method === 'DELETE' && preg_match('#^/items/([^/]+)$#', $path, $m) => $this->deleteItem($m[1]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/toggle$#', $path, $m) => $this->toggleItem($m[1]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/move$#', $path, $m) => $this->moveItem($m[1]),

            // Child items
            $method === 'POST' && preg_match('#^/items/([^/]+)/children$#', $path, $m) => $this->addChild($m[1]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/children/([^/]+)$#', $path, $m) => $this->updateChild($m[1], $m[2]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/children/([^/]+)/toggle$#', $path, $m) => $this->toggleChild($m[1], $m[2]),
            $method === 'DELETE' && preg_match('#^/items/([^/]+)/children/([^/]+)$#', $path, $m) => $this->deleteChild($m[1], $m[2]),

            default => jsonError('Not found', 404),
        };
    }

    // ========================================
    // List & Settings
    // ========================================

    private function getList(): void
    {
        jsonSuccess($this->todoList->getList());
    }

    private function updateSettings(): void
    {
        $input = getJsonInput();
        $settings = $this->todoList->updateSettings($input);
        jsonSuccess($settings);
    }

    // ========================================
    // Section Handlers
    // ========================================

    private function createSection(): void
    {
        $input = getJsonInput();
        $title = $input['title'] ?? '';

        $section = $this->todoList->createSection($title);
        jsonSuccess($section, 201);
    }

    private function updateSection(string $id): void
    {
        $input = getJsonInput();
        $section = $this->todoList->updateSection($id, $input);

        if ($section === null) {
            jsonError('Section not found', 404);
        }

        jsonSuccess($section);
    }

    private function deleteSection(string $id): void
    {
        if (!$this->todoList->deleteSection($id)) {
            jsonError('Cannot delete section (not found or only section)', 400);
        }

        jsonSuccess(['deleted' => true]);
    }

    private function reorderSection(string $id): void
    {
        $input = getJsonInput();
        $targetPosition = isset($input['position']) ? (int) $input['position'] : 0;

        $section = $this->todoList->reorderSection($id, $targetPosition);

        if ($section === null) {
            jsonError('Section not found', 404);
        }

        jsonSuccess($section);
    }

    // ========================================
    // Item Handlers
    // ========================================

    private function createItem(string $sectionId): void
    {
        $input = getJsonInput();
        $error = validateRequired($input, ['title']);
        if ($error !== null) {
            jsonError($error);
        }

        $item = $this->todoList->createItem($sectionId, $input['title']);

        if ($item === null) {
            jsonError('Section not found', 404);
        }

        jsonSuccess($item, 201);
    }

    private function getItem(string $id): void
    {
        $item = $this->todoList->getItem($id);

        if ($item === null) {
            jsonError('Item not found', 404);
        }

        jsonSuccess($item);
    }

    private function updateItem(string $id): void
    {
        $input = getJsonInput();
        $item = $this->todoList->updateItem($id, $input);

        if ($item === null) {
            jsonError('Item not found', 404);
        }

        jsonSuccess($item);
    }

    private function deleteItem(string $id): void
    {
        if (!$this->todoList->deleteItem($id)) {
            jsonError('Item not found', 404);
        }

        jsonSuccess(['deleted' => true]);
    }

    private function toggleItem(string $id): void
    {
        $item = $this->todoList->toggleItem($id);

        if ($item === null) {
            jsonError('Item not found', 404);
        }

        jsonSuccess($item);
    }

    private function moveItem(string $id): void
    {
        $input = getJsonInput();
        $targetSectionId = $input['sectionId'] ?? null;
        $targetPosition = isset($input['position']) ? (int) $input['position'] : null;

        $item = $this->todoList->moveItem($id, $targetSectionId, $targetPosition);

        if ($item === null) {
            jsonError('Item not found', 404);
        }

        jsonSuccess($item);
    }

    // ========================================
    // Child Item Handlers
    // ========================================

    private function addChild(string $parentId): void
    {
        $input = getJsonInput();
        $error = validateRequired($input, ['title']);
        if ($error !== null) {
            jsonError($error);
        }

        $child = $this->todoList->addChild($parentId, $input['title']);

        if ($child === null) {
            jsonError('Parent item not found', 404);
        }

        jsonSuccess($child, 201);
    }

    private function updateChild(string $parentId, string $childId): void
    {
        $input = getJsonInput();
        $child = $this->todoList->updateChild($parentId, $childId, $input);

        if ($child === null) {
            jsonError('Child item not found', 404);
        }

        jsonSuccess($child);
    }

    private function toggleChild(string $parentId, string $childId): void
    {
        $child = $this->todoList->toggleChild($parentId, $childId);

        if ($child === null) {
            jsonError('Child item not found', 404);
        }

        jsonSuccess($child);
    }

    private function deleteChild(string $parentId, string $childId): void
    {
        if (!$this->todoList->deleteChild($parentId, $childId)) {
            jsonError('Child item not found', 404);
        }

        jsonSuccess(['deleted' => true]);
    }
}
