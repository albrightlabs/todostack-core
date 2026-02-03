<?php
declare(strict_types=1);

namespace App;

class TodoList
{
    private string $basePath;
    private string $userId;
    private string $dataPath;
    private array $data;

    public function __construct(string $basePath, string $userId)
    {
        $this->basePath = $basePath;
        $this->userId = $userId;
        $this->dataPath = $basePath . '/todos/' . $userId . '.json';
        $this->ensureDataDirectory();
        $this->load();
    }

    private function ensureDataDirectory(): void
    {
        $dir = dirname($this->dataPath); // data/todos/
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    /**
     * Get the user ID this list belongs to
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * Get the base data path
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Load data from JSON file
     */
    private function load(): void
    {
        if (!file_exists($this->dataPath)) {
            $this->data = $this->getDefaultData();
            $this->save();
            return;
        }

        $content = file_get_contents($this->dataPath);
        if ($content === false) {
            $this->data = $this->getDefaultData();
            return;
        }

        $decoded = json_decode($content, true);
        $this->data = is_array($decoded) ? $decoded : $this->getDefaultData();
    }

    /**
     * Get default data structure
     */
    private function getDefaultData(): array
    {
        return [
            'settings' => [
                'hideCompleted' => false,
                'theme' => 'auto',
            ],
            'sections' => [
                [
                    'id' => uuid(),
                    'title' => 'To-Do',
                    'position' => 0,
                    'collapsed' => false,
                    'items' => [],
                ],
            ],
        ];
    }

    /**
     * Save data to JSON file with locking
     */
    private function save(): bool
    {
        $json = json_encode($this->data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            return false;
        }

        $fp = fopen($this->dataPath, 'c');
        if ($fp === false) {
            return false;
        }

        if (!flock($fp, LOCK_EX)) {
            fclose($fp);
            return false;
        }

        ftruncate($fp, 0);
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return true;
    }

    /**
     * Get full list data
     */
    public function getList(): array
    {
        return $this->data;
    }

    /**
     * Update settings
     */
    public function updateSettings(array $settings): array
    {
        $this->data['settings'] = array_merge($this->data['settings'], $settings);
        $this->save();
        return $this->data['settings'];
    }

    // ========================================
    // Section Operations
    // ========================================

    /**
     * Create a new section
     */
    public function createSection(string $title): array
    {
        $maxPosition = 0;
        foreach ($this->data['sections'] as $section) {
            if ($section['position'] > $maxPosition) {
                $maxPosition = $section['position'];
            }
        }

        $section = [
            'id' => uuid(),
            'title' => trim($title),
            'position' => $maxPosition + 1,
            'collapsed' => false,
            'items' => [],
        ];

        $this->data['sections'][] = $section;
        $this->save();

        return $section;
    }

    /**
     * Update a section
     */
    public function updateSection(string $id, array $updates): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            if ($section['id'] === $id) {
                if (isset($updates['title'])) {
                    $section['title'] = trim($updates['title']);
                }
                if (isset($updates['collapsed'])) {
                    $section['collapsed'] = (bool) $updates['collapsed'];
                }
                // Position changes are handled by reorderSection()
                $this->save();
                return $section;
            }
        }
        return null;
    }

    /**
     * Reorder a section to a new position
     */
    public function reorderSection(string $id, int $targetPosition): ?array
    {
        $sourceIndex = null;
        $sourcePosition = null;

        // Find the source section
        foreach ($this->data['sections'] as $index => $section) {
            if ($section['id'] === $id) {
                $sourceIndex = $index;
                $sourcePosition = $section['position'];
                break;
            }
        }

        if ($sourceIndex === null) {
            return null;
        }

        // Remove section from array
        $movedSection = array_splice($this->data['sections'], $sourceIndex, 1)[0];

        // Insert at new position (by index based on target position)
        $insertIndex = 0;
        foreach ($this->data['sections'] as $index => $section) {
            if ($section['position'] >= $targetPosition) {
                $insertIndex = $index;
                break;
            }
            $insertIndex = $index + 1;
        }

        array_splice($this->data['sections'], $insertIndex, 0, [$movedSection]);

        // Reindex all positions
        foreach ($this->data['sections'] as $index => &$sec) {
            $sec['position'] = $index;
        }
        unset($sec); // Prevent reference bugs

        $this->save();

        // Return the moved section with updated position
        return $this->data['sections'][$insertIndex];
    }

    /**
     * Delete a section
     */
    public function deleteSection(string $id): bool
    {
        foreach ($this->data['sections'] as $index => $section) {
            if ($section['id'] === $id) {
                array_splice($this->data['sections'], $index, 1);

                // If we deleted the last section, create a new default section
                if (count($this->data['sections']) === 0) {
                    $this->data['sections'][] = [
                        'id' => uuid(),
                        'title' => 'To-Do',
                        'position' => 0,
                        'collapsed' => false,
                        'items' => [],
                    ];
                }

                $this->save();
                return true;
            }
        }
        return false;
    }

    /**
     * Get section by ID
     */
    public function getSection(string $id): ?array
    {
        foreach ($this->data['sections'] as $section) {
            if ($section['id'] === $id) {
                return $section;
            }
        }
        return null;
    }

    // ========================================
    // Item Operations
    // ========================================

    /**
     * Create a new item in a section
     */
    public function createItem(string $sectionId, string $title): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            if ($section['id'] === $sectionId) {
                $maxPosition = 0;
                foreach ($section['items'] as $item) {
                    if ($item['position'] > $maxPosition) {
                        $maxPosition = $item['position'];
                    }
                }

                $timestamp = now();
                $item = [
                    'id' => uuid(),
                    'title' => trim($title),
                    'description' => '',
                    'completed' => false,
                    'priority' => null,
                    'dueDate' => null,
                    'position' => $maxPosition + 1,
                    'createdAt' => $timestamp,
                    'updatedAt' => $timestamp,
                    'children' => [],
                    'attachments' => [],
                    'sentFrom' => null,
                ];

                $section['items'][] = $item;
                $this->save();
                return $item;
            }
        }
        return null;
    }

    /**
     * Find item by ID (returns reference info)
     */
    private function findItem(string $itemId): ?array
    {
        foreach ($this->data['sections'] as $sectionIndex => &$section) {
            foreach ($section['items'] as $itemIndex => &$item) {
                if ($item['id'] === $itemId) {
                    return [
                        'sectionIndex' => $sectionIndex,
                        'itemIndex' => $itemIndex,
                        'item' => &$item,
                    ];
                }
            }
        }
        return null;
    }

    /**
     * Get item by ID
     */
    public function getItem(string $id): ?array
    {
        $result = $this->findItem($id);
        return $result ? $result['item'] : null;
    }

    /**
     * Update an item
     */
    public function updateItem(string $id, array $updates): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $id) {
                    $allowedFields = ['title', 'description', 'completed', 'priority', 'dueDate', 'position'];

                    foreach ($allowedFields as $field) {
                        if (array_key_exists($field, $updates)) {
                            if ($field === 'title' || $field === 'description') {
                                $item[$field] = trim((string) $updates[$field]);
                            } elseif ($field === 'completed') {
                                $item[$field] = (bool) $updates[$field];
                            } elseif ($field === 'position') {
                                $item[$field] = (int) $updates[$field];
                            } else {
                                $item[$field] = $updates[$field];
                            }
                        }
                    }

                    $item['updatedAt'] = now();
                    $this->save();
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Toggle item completion
     */
    public function toggleItem(string $id): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $id) {
                    $item['completed'] = !$item['completed'];
                    $item['updatedAt'] = now();
                    $this->save();
                    return $item;
                }
            }
        }
        return null;
    }

    /**
     * Delete an item
     */
    public function deleteItem(string $id): bool
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as $index => $item) {
                if ($item['id'] === $id) {
                    array_splice($section['items'], $index, 1);
                    $this->save();
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Move item to different position or section
     */
    public function moveItem(string $id, ?string $targetSectionId = null, ?int $targetPosition = null): ?array
    {
        $movedItem = null;
        $sourceSectionIndex = null;
        $sourceItemIndex = null;

        // Find item location (no references needed - just reading)
        foreach ($this->data['sections'] as $sIndex => $section) {
            foreach ($section['items'] as $iIndex => $item) {
                if ($item['id'] === $id) {
                    $movedItem = $item;
                    $sourceSectionIndex = $sIndex;
                    $sourceItemIndex = $iIndex;
                    break 2;
                }
            }
        }

        if ($movedItem === null) {
            return null;
        }

        // Remove from source
        array_splice($this->data['sections'][$sourceSectionIndex]['items'], $sourceItemIndex, 1);

        // Find target section
        $targetSectionIndex = $sourceSectionIndex;
        if ($targetSectionId !== null) {
            foreach ($this->data['sections'] as $sIndex => $section) {
                if ($section['id'] === $targetSectionId) {
                    $targetSectionIndex = $sIndex;
                    break;
                }
            }
        }

        // Update position
        if ($targetPosition !== null) {
            $movedItem['position'] = $targetPosition;
        }

        $movedItem['updatedAt'] = now();

        // Insert at target
        if ($targetPosition !== null) {
            // Insert at specific position
            $inserted = false;
            $newItems = [];
            foreach ($this->data['sections'][$targetSectionIndex]['items'] as $item) {
                if (!$inserted && $item['position'] >= $targetPosition) {
                    $newItems[] = $movedItem;
                    $inserted = true;
                }
                $newItems[] = $item;
            }
            if (!$inserted) {
                $newItems[] = $movedItem;
            }
            $this->data['sections'][$targetSectionIndex]['items'] = $newItems;
        } else {
            // Append to end
            $this->data['sections'][$targetSectionIndex]['items'][] = $movedItem;
        }

        // Reindex positions
        $this->reindexPositions($targetSectionIndex);
        if ($sourceSectionIndex !== $targetSectionIndex) {
            $this->reindexPositions($sourceSectionIndex);
        }

        $this->save();
        return $movedItem;
    }

    /**
     * Reindex positions in a section
     */
    private function reindexPositions(int $sectionIndex): void
    {
        usort($this->data['sections'][$sectionIndex]['items'], function ($a, $b) {
            return $a['position'] <=> $b['position'];
        });

        foreach ($this->data['sections'][$sectionIndex]['items'] as $index => &$item) {
            $item['position'] = $index;
        }
    }

    // ========================================
    // Child Item Operations
    // ========================================

    /**
     * Add child item to a parent item
     */
    public function addChild(string $parentId, string $title): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $parentId) {
                    $maxPosition = 0;
                    foreach ($item['children'] as $child) {
                        if ($child['position'] > $maxPosition) {
                            $maxPosition = $child['position'];
                        }
                    }

                    $child = [
                        'id' => uuid(),
                        'title' => trim($title),
                        'completed' => false,
                        'position' => $maxPosition + 1,
                    ];

                    $item['children'][] = $child;
                    $item['updatedAt'] = now();
                    $this->save();
                    return $child;
                }
            }
        }
        return null;
    }

    /**
     * Update a child item
     */
    public function updateChild(string $parentId, string $childId, array $updates): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $parentId) {
                    foreach ($item['children'] as &$child) {
                        if ($child['id'] === $childId) {
                            if (isset($updates['title'])) {
                                $child['title'] = trim($updates['title']);
                            }
                            if (isset($updates['completed'])) {
                                $child['completed'] = (bool) $updates['completed'];
                            }
                            if (isset($updates['position'])) {
                                $child['position'] = (int) $updates['position'];
                            }
                            $item['updatedAt'] = now();
                            $this->save();
                            return $child;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Toggle child item completion
     */
    public function toggleChild(string $parentId, string $childId): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $parentId) {
                    foreach ($item['children'] as &$child) {
                        if ($child['id'] === $childId) {
                            $child['completed'] = !$child['completed'];
                            $item['updatedAt'] = now();
                            $this->save();
                            return $child;
                        }
                    }
                }
            }
        }
        return null;
    }

    /**
     * Delete a child item
     */
    public function deleteChild(string $parentId, string $childId): bool
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $parentId) {
                    foreach ($item['children'] as $index => $child) {
                        if ($child['id'] === $childId) {
                            array_splice($item['children'], $index, 1);
                            $item['updatedAt'] = now();
                            $this->save();
                            return true;
                        }
                    }
                }
            }
        }
        return false;
    }

    // ========================================
    // Attachment Operations
    // ========================================

    /**
     * Add an attachment to an item
     */
    public function addAttachment(string $itemId, array $attachment): ?array
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $itemId) {
                    // Initialize attachments array if not exists
                    if (!isset($item['attachments'])) {
                        $item['attachments'] = [];
                    }

                    // Create attachment record
                    $attachmentRecord = [
                        'id' => uuid(),
                        'filename' => $attachment['filename'] ?? 'unnamed',
                        'url' => $attachment['url'],
                        'size' => $attachment['size'] ?? 0,
                        'type' => $attachment['type'] ?? 'document',
                        'uploadedAt' => now(),
                    ];

                    $item['attachments'][] = $attachmentRecord;
                    $item['updatedAt'] = now();
                    $this->save();

                    return $attachmentRecord;
                }
            }
        }
        return null;
    }

    /**
     * Remove an attachment from an item
     */
    public function removeAttachment(string $itemId, string $attachmentId): bool
    {
        foreach ($this->data['sections'] as &$section) {
            foreach ($section['items'] as &$item) {
                if ($item['id'] === $itemId) {
                    if (!isset($item['attachments'])) {
                        return false;
                    }

                    $originalCount = count($item['attachments']);
                    $item['attachments'] = array_values(array_filter(
                        $item['attachments'],
                        fn($a) => $a['id'] !== $attachmentId
                    ));

                    // Check if anything was removed
                    if (count($item['attachments']) === $originalCount) {
                        return false;
                    }

                    $item['updatedAt'] = now();
                    $this->save();
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get an attachment by ID from an item
     */
    public function getAttachment(string $itemId, string $attachmentId): ?array
    {
        foreach ($this->data['sections'] as $section) {
            foreach ($section['items'] as $item) {
                if ($item['id'] === $itemId) {
                    foreach ($item['attachments'] ?? [] as $attachment) {
                        if ($attachment['id'] === $attachmentId) {
                            return $attachment;
                        }
                    }
                }
            }
        }
        return null;
    }

    // ========================================
    // Search Operations
    // ========================================

    /**
     * Search items by title and description
     */
    public function search(string $query, int $limit = 20): array
    {
        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $queryLower = mb_strtolower($query);
        $results = [];

        foreach ($this->data['sections'] as $section) {
            $sectionTitle = $section['title'] ?: 'Tasks';

            foreach ($section['items'] ?? [] as $item) {
                $relevance = 0;
                $preview = null;

                $titleLower = mb_strtolower($item['title']);
                $descLower = mb_strtolower($item['description'] ?? '');

                // Check title match
                if (mb_strpos($titleLower, $queryLower) !== false) {
                    $relevance += 100;
                    if ($titleLower === $queryLower) {
                        $relevance += 50; // Exact match bonus
                    }
                }

                // Check description match
                if (!empty($item['description']) && mb_strpos($descLower, $queryLower) !== false) {
                    $relevance += 10;
                    // Generate preview around match
                    $preview = $this->generatePreview($item['description'], $query);
                }

                // Check child items
                foreach ($item['children'] ?? [] as $child) {
                    $childTitleLower = mb_strtolower($child['title']);
                    if (mb_strpos($childTitleLower, $queryLower) !== false) {
                        $relevance += 5;
                    }
                }

                if ($relevance > 0) {
                    $results[] = [
                        'id' => $item['id'],
                        'title' => $item['title'],
                        'sectionTitle' => $sectionTitle,
                        'preview' => $preview,
                        'completed' => $item['completed'] ?? false,
                        'relevance' => $relevance,
                    ];
                }
            }
        }

        // Sort by relevance (highest first)
        usort($results, fn($a, $b) => $b['relevance'] <=> $a['relevance']);

        // Limit results
        return array_slice($results, 0, $limit);
    }

    /**
     * Generate a preview snippet around the search match
     */
    private function generatePreview(string $text, string $query, int $length = 150): string
    {
        $pos = mb_stripos($text, $query);
        if ($pos === false) {
            return mb_substr($text, 0, $length) . (mb_strlen($text) > $length ? '...' : '');
        }

        // Center the preview around the match
        $start = max(0, $pos - ($length / 3));
        $preview = mb_substr($text, $start, $length);

        // Clean up whitespace
        $preview = preg_replace('/\s+/', ' ', trim($preview));

        // Add ellipsis
        if ($start > 0) {
            $preview = '...' . $preview;
        }
        if ($start + $length < mb_strlen($text)) {
            $preview .= '...';
        }

        return $preview;
    }

    // ========================================
    // Send/Transfer Operations
    // ========================================

    /**
     * Send (transfer) an item to another user's list
     * Removes from current list, adds to target user's first section
     */
    public function sendItemToUser(string $itemId, string $targetUserId, string $senderName): ?array
    {
        // Find and remove item from current list
        $item = null;
        $sourceSectionIndex = null;
        $sourceItemIndex = null;

        foreach ($this->data['sections'] as $sIndex => $section) {
            foreach ($section['items'] as $iIndex => $existingItem) {
                if ($existingItem['id'] === $itemId) {
                    $item = $existingItem;
                    $sourceSectionIndex = $sIndex;
                    $sourceItemIndex = $iIndex;
                    break 2;
                }
            }
        }

        if ($item === null) {
            return null;
        }

        // Remove from source list
        array_splice($this->data['sections'][$sourceSectionIndex]['items'], $sourceItemIndex, 1);
        $this->save();

        // Add sentFrom metadata
        $item['sentFrom'] = [
            'userId' => $this->userId,
            'userName' => $senderName,
            'sentAt' => now(),
        ];
        $item['updatedAt'] = now();

        // Reset position for target list (will be at top)
        $item['position'] = 0;

        // Load target user's list and add item
        $targetList = new TodoList($this->basePath, $targetUserId);

        // Get target's data and add to first section
        if (!empty($targetList->data['sections'])) {
            // Shift existing items down
            foreach ($targetList->data['sections'][0]['items'] as &$existingItem) {
                $existingItem['position']++;
            }
            unset($existingItem);

            // Insert at beginning of first section
            array_unshift($targetList->data['sections'][0]['items'], $item);
            $targetList->save();
        }

        return $item;
    }

    /**
     * Set full data (used for direct data manipulation)
     */
    public function setData(array $data): void
    {
        $this->data = $data;
        $this->save();
    }
}
