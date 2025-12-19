<?php
declare(strict_types=1);

namespace App;

class TodoList
{
    private string $dataPath;
    private array $data;

    public function __construct(string $dataPath)
    {
        $this->dataPath = $dataPath;
        $this->ensureDataDirectory();
        $this->load();
    }

    private function ensureDataDirectory(): void
    {
        $dir = dirname($this->dataPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
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
                    'title' => '',
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
        // Don't delete if it's the only section
        if (count($this->data['sections']) <= 1) {
            return false;
        }

        foreach ($this->data['sections'] as $index => $section) {
            if ($section['id'] === $id) {
                array_splice($this->data['sections'], $index, 1);
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
}
