<?php
declare(strict_types=1);

namespace App;

class Api
{
    private TodoList $todoList;
    private ?string $viewingUserId;
    private string $currentUserId;

    public function __construct(TodoList $todoList, ?string $viewingUserId = null)
    {
        $this->todoList = $todoList;
        $this->viewingUserId = $viewingUserId;
        $this->currentUserId = Auth::getCurrentUserId() ?? '';
    }

    /**
     * Handle API request
     */
    public function handle(string $method, string $path): void
    {
        // Remove /api prefix
        $path = preg_replace('#^/api#', '', $path) ?: '/';

        // Require authentication for all API requests
        Auth::requireAuth();

        // Require CSRF and write permission for mutations
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            Auth::requireCsrf();

            // Check write permission
            // For own list: use normal canWrite() check
            // For other's list: require admin role
            $isOwnList = $this->viewingUserId === null || $this->viewingUserId === $this->currentUserId;
            $canEdit = $isOwnList ? Auth::canWrite() : Auth::isAdmin();

            if (!$canEdit) {
                jsonError('You do not have permission to modify this list.', 403);
            }
        }

        // Route the request
        match (true) {
            // List
            $method === 'GET' && $path === '/list' => $this->getList(),

            // Search
            $method === 'GET' && $path === '/search' => $this->handleSearch(),

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
            $method === 'POST' && preg_match('#^/items/([^/]+)/send$#', $path, $m) => $this->sendItem($m[1]),

            // Child items
            $method === 'POST' && preg_match('#^/items/([^/]+)/children$#', $path, $m) => $this->addChild($m[1]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/children/([^/]+)$#', $path, $m) => $this->updateChild($m[1], $m[2]),
            $method === 'PUT' && preg_match('#^/items/([^/]+)/children/([^/]+)/toggle$#', $path, $m) => $this->toggleChild($m[1], $m[2]),
            $method === 'DELETE' && preg_match('#^/items/([^/]+)/children/([^/]+)$#', $path, $m) => $this->deleteChild($m[1], $m[2]),

            // Attachments
            $method === 'DELETE' && preg_match('#^/items/([^/]+)/attachments/([^/]+)$#', $path, $m) => $this->deleteAttachment($m[1], $m[2]),

            // Cleanup uploads (admin only)
            $method === 'GET' && $path === '/cleanup-uploads' => $this->analyzeUploads(),
            $method === 'POST' && $path === '/cleanup-uploads' => $this->cleanupUploads(),

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

    private function handleSearch(): void
    {
        $query = $_GET['q'] ?? '';
        if (empty(trim($query))) {
            jsonError('Query required', 400);
        }

        $results = $this->todoList->search($query);
        jsonSuccess($results);
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

    private function sendItem(string $id): void
    {
        $input = getJsonInput();
        $error = validateRequired($input, ['targetUserId']);
        if ($error !== null) {
            jsonError($error);
        }

        $targetUserId = $input['targetUserId'];

        // Validate target user exists
        $userManager = Auth::getUserManager();
        $targetUser = $userManager->getById($targetUserId);
        if ($targetUser === null) {
            jsonError('Target user not found', 404);
        }

        // Cannot send to self
        if ($targetUserId === $this->currentUserId) {
            jsonError('Cannot send item to yourself', 400);
        }

        // Get current user's name for the sentFrom metadata
        $currentUser = Auth::getCurrentUser();
        $senderName = $currentUser['name'] ?: $currentUser['email'];

        // Perform the transfer
        $item = $this->todoList->sendItemToUser($id, $targetUserId, $senderName);

        if ($item === null) {
            jsonError('Item not found', 404);
        }

        jsonSuccess([
            'sent' => true,
            'item' => $item,
            'targetUser' => [
                'id' => $targetUser['id'],
                'name' => $targetUser['name'] ?: $targetUser['email'],
            ],
        ]);
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

    // ========================================
    // Attachment Handlers
    // ========================================

    private function deleteAttachment(string $itemId, string $attachmentId): void
    {
        // Get the attachment to find the file URL
        $attachment = $this->todoList->getAttachment($itemId, $attachmentId);

        if ($attachment === null) {
            jsonError('Attachment not found', 404);
        }

        // Remove from item
        if (!$this->todoList->removeAttachment($itemId, $attachmentId)) {
            jsonError('Failed to remove attachment', 500);
        }

        // Note: File is left on disk for orphan cleanup

        jsonSuccess(['deleted' => true]);
    }

    // ========================================
    // Upload Cleanup Handlers
    // ========================================

    private function analyzeUploads(): void
    {
        // Only admins can analyze uploads
        if (!Auth::isAdmin()) {
            jsonError('Admin access required', 403);
        }

        $upload = new Upload();
        $analysis = $upload->analyzeUploads();
        jsonSuccess($analysis);
    }

    private function cleanupUploads(): void
    {
        // Only admins can cleanup uploads
        if (!Auth::isAdmin()) {
            jsonError('Admin access required', 403);
        }

        $upload = new Upload();
        $result = $upload->cleanupOrphanedUploads();
        jsonSuccess($result);
    }
}
