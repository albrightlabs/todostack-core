<?php
/**
 * Migration Script: Convert single todos.json to per-user todo lists
 *
 * This script:
 * 1. Creates the data/todos/ directory
 * 2. Loads existing data/todos.json (if it exists)
 * 3. Finds the first admin user
 * 4. Saves existing todos to data/todos/{first-admin-id}.json
 * 5. Creates empty lists for other users
 * 6. Adds sentFrom: null to all existing items
 *
 * Run this script once after deploying the per-user todo lists feature.
 *
 * Usage: php migrate-to-user-lists.php
 */

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

echo "=== TodoStack Migration: Per-User Todo Lists ===\n\n";

// Define paths
$dataPath = __DIR__ . '/data';
$oldTodosPath = $dataPath . '/todos.json';
$todosDir = $dataPath . '/todos';
$usersPath = $dataPath . '/users.json';

// Step 1: Create todos directory
echo "Step 1: Creating data/todos/ directory...\n";
if (!is_dir($todosDir)) {
    if (mkdir($todosDir, 0755, true)) {
        echo "  Created: $todosDir\n";
    } else {
        echo "  ERROR: Failed to create directory\n";
        exit(1);
    }
} else {
    echo "  Already exists: $todosDir\n";
}

// Step 2: Load users
echo "\nStep 2: Loading users...\n";
if (!file_exists($usersPath)) {
    echo "  ERROR: users.json not found at $usersPath\n";
    echo "  Please run the setup wizard first to create an admin user.\n";
    exit(1);
}

$usersData = json_decode(file_get_contents($usersPath), true);
if (!$usersData || !isset($usersData['users']) || empty($usersData['users'])) {
    echo "  ERROR: No users found in users.json\n";
    exit(1);
}

$users = $usersData['users'];
echo "  Found " . count($users) . " user(s)\n";

// Step 3: Find first admin user
echo "\nStep 3: Finding first admin user...\n";
$firstAdmin = null;
foreach ($users as $user) {
    if ($user['role'] === 'admin') {
        $firstAdmin = $user;
        break;
    }
}

if (!$firstAdmin) {
    echo "  ERROR: No admin user found\n";
    exit(1);
}
echo "  First admin: {$firstAdmin['email']} (ID: {$firstAdmin['id']})\n";

// Step 4: Load existing todos
echo "\nStep 4: Loading existing todos.json...\n";
$existingTodos = null;
if (file_exists($oldTodosPath)) {
    $existingTodos = json_decode(file_get_contents($oldTodosPath), true);
    if ($existingTodos) {
        $sectionCount = count($existingTodos['sections'] ?? []);
        $itemCount = 0;
        foreach (($existingTodos['sections'] ?? []) as $section) {
            $itemCount += count($section['items'] ?? []);
        }
        echo "  Found existing todos: $sectionCount section(s), $itemCount item(s)\n";
    } else {
        echo "  WARNING: Could not parse todos.json\n";
        $existingTodos = null;
    }
} else {
    echo "  No existing todos.json found (fresh install)\n";
}

// Step 5: Add sentFrom: null to all existing items
echo "\nStep 5: Adding sentFrom field to existing items...\n";
if ($existingTodos && isset($existingTodos['sections'])) {
    $updatedCount = 0;
    foreach ($existingTodos['sections'] as &$section) {
        if (isset($section['items'])) {
            foreach ($section['items'] as &$item) {
                if (!isset($item['sentFrom'])) {
                    $item['sentFrom'] = null;
                    $updatedCount++;
                }
            }
        }
    }
    echo "  Added sentFrom to $updatedCount item(s)\n";
} else {
    echo "  No items to update\n";
}

// Step 6: Save todos to first admin's file
echo "\nStep 6: Saving todos to first admin's list...\n";
$adminListPath = $todosDir . '/' . $firstAdmin['id'] . '.json';
if (file_exists($adminListPath)) {
    echo "  WARNING: File already exists at $adminListPath\n";
    echo "  Skipping to avoid overwriting existing data.\n";
} else {
    if ($existingTodos) {
        file_put_contents($adminListPath, json_encode($existingTodos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  Saved to: $adminListPath\n";
    } else {
        // Create empty list
        $emptyList = [
            'settings' => ['hideCompleted' => false],
            'sections' => [
                [
                    'id' => generateUuid(),
                    'title' => '',
                    'collapsed' => false,
                    'position' => 0,
                    'items' => []
                ]
            ]
        ];
        file_put_contents($adminListPath, json_encode($emptyList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        echo "  Created empty list at: $adminListPath\n";
    }
}

// Step 7: Create empty lists for other users
echo "\nStep 7: Creating empty lists for other users...\n";
$createdCount = 0;
foreach ($users as $user) {
    if ($user['id'] === $firstAdmin['id']) {
        continue; // Skip first admin, already handled
    }

    $userListPath = $todosDir . '/' . $user['id'] . '.json';
    if (file_exists($userListPath)) {
        echo "  Skipping {$user['email']} (file exists)\n";
        continue;
    }

    $emptyList = [
        'settings' => ['hideCompleted' => false],
        'sections' => [
            [
                'id' => generateUuid(),
                'title' => '',
                'collapsed' => false,
                'position' => 0,
                'items' => []
            ]
        ]
    ];
    file_put_contents($userListPath, json_encode($emptyList, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "  Created empty list for: {$user['email']}\n";
    $createdCount++;
}

if ($createdCount === 0) {
    echo "  No additional users to create lists for\n";
}

// Step 8: Summary
echo "\n=== Migration Complete ===\n\n";
echo "Summary:\n";
echo "  - Todos directory: $todosDir\n";
echo "  - First admin ({$firstAdmin['email']}) received existing todos\n";
echo "  - " . (count($users) - 1) . " other user(s) received empty lists\n";
echo "\nNext steps:\n";
echo "  1. Test the application to ensure everything works\n";
echo "  2. Once verified, you can delete the old data/todos.json file\n";
echo "  3. Deploy to production\n\n";

// Helper function
function generateUuid(): string
{
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}
