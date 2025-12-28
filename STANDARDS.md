# Development Standards

This document defines the coding standards, patterns, and conventions used across these PHP/JavaScript web application projects. Use this document to ensure consistency when developing or refactoring.

**Applies to:** PHP/JavaScript web applications using this framework

---

## Table of Contents

1. [Project Structure](#1-project-structure)
2. [PHP Standards](#2-php-standards)
3. [JavaScript Standards](#3-javascript-standards)
4. [CSS and Styling](#4-css-and-styling)
5. [Configuration Patterns](#5-configuration-patterns)
6. [Security Standards](#6-security-standards)
7. [API Design](#7-api-design)
8. [File and Content Conventions](#8-file-and-content-conventions)
9. [Documentation Standards](#9-documentation-standards)
10. [Template Patterns](#10-template-patterns)

---

## 1. Project Structure

### Directory Organization

```
project-root/
├── src/                      # PHP business logic classes
├── templates/                # PHP view templates
├── public/                   # Web root (document root for server)
│   ├── index.php            # Main entry point
│   ├── api.php              # API entry point (if applicable)
│   ├── router.php           # Development server router
│   ├── .htaccess            # Apache rewrite rules
│   └── assets/              # Static assets
│       ├── css/             # Stylesheets (or *.css in assets/)
│       ├── js/              # JavaScript files (or *.js in assets/)
│       └── images/          # Image assets
├── content/                  # User content (if applicable, gitignored)
├── vendor/                   # Composer dependencies (gitignored)
├── composer.json             # PHP dependencies
├── .env.example              # Configuration template
├── .gitignore                # Git ignore rules
├── README.md                 # Project documentation
└── STANDARDS.md              # This file
```

### Principles

- **Separation of concerns:** Business logic in `src/`, presentation in `templates/`, static files in `public/assets/`
- **Web root isolation:** Only `public/` is web-accessible; sensitive files stay outside
- **One class per file:** Each PHP class gets its own file (exception: helper functions)
- **Flat structure preferred:** Avoid deep nesting; keep directory depth minimal

---

## 2. PHP Standards

### File Header

Every PHP file MUST start with strict types declaration:

```php
<?php
declare(strict_types=1);

namespace App;
```

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Classes | PascalCase | `FileOperations`, `AdminAuth` |
| Methods | camelCase | `getContent()`, `validatePath()` |
| Properties | camelCase | `$contentDir`, `$isAuthenticated` |
| Constants | UPPER_SNAKE_CASE | `MAX_FILE_SIZE`, `DEFAULT_TIMEOUT` |
| Files (classes) | PascalCase.php | `Config.php`, `Content.php` |
| Files (helpers) | lowercase.php | `helpers.php` |

### Type Hints

Always use full type hints on parameters and return types:

```php
public function getDocument(string $path): ?array
{
    // Implementation
}

public function saveContent(string $path, string $content, bool $createBackup = true): bool
{
    // Implementation
}
```

### Design Patterns

#### Singleton Pattern (for Configuration)

```php
class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->loadDefaults();
        $this->loadFromEnv();
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->config[$key] ?? $default;
    }
}
```

#### Dependency Injection (for Services)

```php
// In entry point (index.php or api.php)
$config = Config::getInstance();
$todoList = new TodoList($dataPath);
$api = new Api($todoList);
```

### Helper Functions

Common utilities go in `helpers.php` without a class wrapper:

```php
<?php
declare(strict_types=1);

// No namespace for global helper functions

/**
 * Generate UUID v4
 */
function uuid(): string
{
    return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

/**
 * Get current ISO 8601 timestamp
 */
function now(): string
{
    return (new DateTime())->format(DateTime::ATOM);
}

/**
 * Sanitize user input
 */
function sanitize(string $input): string
{
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get JSON input from request body
 */
function getJsonInput(): array
{
    $input = file_get_contents('php://input');
    return json_decode($input, true) ?? [];
}

/**
 * Send JSON response
 */
function jsonResponse(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Send JSON error response
 */
function jsonError(string $message, int $code = 400): void
{
    jsonResponse(['success' => false, 'error' => $message], $code);
}
```

---

## 3. JavaScript Standards

### Architecture Pattern

Organize functionality in a single object:

```javascript
const AppName = {
    // State
    state: {
        data: null,
        currentItem: null
    },

    // Initialization
    init() {
        this.bindEvents();
        this.loadData();
    },

    // API wrapper
    async api(endpoint, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken
            }
        };
        const response = await fetch(endpoint, { ...defaults, ...options });
        return response.json();
    },

    // Event handling
    bindEvents() {
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));
    },

    handleKeyboard(e) {
        if (e.key === 'Escape') this.closeModal();
        if (e.ctrlKey && e.key === 's') {
            e.preventDefault();
            this.save();
        }
    },

    // Modal management
    openModal(modalId) {
        document.getElementById(modalId)?.classList.add('show');
    },

    closeModal() {
        document.querySelectorAll('.modal.show').forEach(m => m.classList.remove('show'));
    }
};

document.addEventListener('DOMContentLoaded', () => AppName.init());
```

### Naming Conventions

| Element | Convention | Example |
|---------|------------|---------|
| Objects | PascalCase | `TodoApp`, `AppController` |
| Functions/Methods | camelCase | `fetchData()`, `handleClick()` |
| Variables | camelCase | `currentItem`, `isLoading` |
| Constants | UPPER_SNAKE_CASE | `API_ENDPOINT`, `MAX_RETRIES` |
| DOM IDs | kebab-case | `item-modal`, `add-button` |
| CSS Classes | kebab-case | `modal-overlay`, `is-active` |

---

## 4. CSS and Styling

### CSS Custom Properties

```css
:root {
    /* Primary brand colors */
    --primary-color: #0066cc;
    --primary-hover: #0052a3;

    /* Background colors */
    --bg-primary: #ffffff;
    --bg-secondary: #f5f5f5;
    --bg-tertiary: #eeeeee;

    /* Text colors */
    --text-primary: #1a1a1a;
    --text-secondary: #666666;
    --text-muted: #999999;

    /* Border colors */
    --border-color: #e0e0e0;

    /* Semantic colors */
    --success-color: #28a745;
    --warning-color: #ffc107;
    --danger-color: #dc3545;
}
```

### Dark Mode Support

```css
@media (prefers-color-scheme: dark) {
    :root {
        --bg-primary: #1a1a1a;
        --bg-secondary: #2d2d2d;
        --bg-tertiary: #404040;

        --text-primary: #e5e5e5;
        --text-secondary: #b0b0b0;
        --text-muted: #808080;

        --border-color: #404040;
    }
}
```

---

## 5. Configuration Patterns

### Environment Variables (.env.example)

```env
# Site Settings
SITE_NAME="App Name"
SITE_URL=""

# Security
ADMIN_PASSWORD=""

# Features
FEATURE_DARK_MODE=true
```

### Config Class

```php
class Config
{
    private static ?Config $instance = null;
    private array $config = [];

    private function __construct()
    {
        $this->loadDefaults();
        $this->loadFromEnv();
    }

    private function loadDefaults(): void
    {
        $this->config = [
            'site_name' => 'App Name',
            'feature_dark_mode' => true,
        ];
    }

    private function loadFromEnv(): void
    {
        $envMap = [
            'SITE_NAME' => 'site_name',
            'FEATURE_DARK_MODE' => 'feature_dark_mode',
        ];

        foreach ($envMap as $envKey => $configKey) {
            $value = $_ENV[$envKey] ?? null;
            if ($value !== null) {
                if ($value === 'true') $value = true;
                if ($value === 'false') $value = false;
                $this->config[$configKey] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::getInstance()->config[$key] ?? $default;
    }

    public static function getInstance(): Config
    {
        if (self::$instance === null) {
            self::$instance = new Config();
        }
        return self::$instance;
    }
}
```

---

## 6. Security Standards

### CSRF Protection

```php
class Auth
{
    public function generateCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    public function validateCsrfToken(string $token): bool
    {
        if (empty($_SESSION['csrf_token'])) return false;
        return hash_equals($_SESSION['csrf_token'], $token);
    }
}
```

### Input Validation

```php
function validateRequired(array $data, array $fields): ?string
{
    foreach ($fields as $field) {
        if (empty($data[$field])) {
            return "Missing required field: {$field}";
        }
    }
    return null;
}
```

### Output Escaping

```php
// In templates
<?= htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8') ?>
```

---

## 7. API Design

### RESTful Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/api/items` | List all items |
| GET | `/api/items/{id}` | Get single item |
| POST | `/api/items` | Create item |
| PUT | `/api/items/{id}` | Update item |
| DELETE | `/api/items/{id}` | Delete item |

### Response Format

```json
{
  "success": true,
  "data": { ... }
}

{
  "success": false,
  "error": "Error message"
}
```

### HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Successful GET, PUT, DELETE |
| 201 | Successful POST (created) |
| 400 | Bad request (validation error) |
| 401 | Unauthorized |
| 404 | Resource not found |
| 500 | Internal server error |

---

## 8. File and Content Conventions

### File Organization

- Keep files small and focused
- One class per file for PHP classes
- Group related functionality together
- Use clear, descriptive file names

### Code Comments

- Use comments sparingly - code should be self-documenting
- Add comments for complex logic or non-obvious decisions
- Use PHPDoc blocks for public methods

---

## 9. Documentation Standards

### README.md Structure

```markdown
# Project Name

Brief description.

## Features

- Feature list

## Requirements

- PHP 8.1+
- Composer

## Installation

1. Clone repository
2. Run `composer install`
3. Copy `.env.example` to `.env`
4. Configure settings

## Usage

Basic usage instructions.

## License

MIT License
```

---

## 10. Template Patterns

### Layout Template

```php
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <?= $content ?>
    <script src="/assets/app.js"></script>
</body>
</html>
```

---

## Checklist

- [ ] Directory structure follows standard layout
- [ ] All PHP files have `declare(strict_types=1)`
- [ ] All classes use `namespace App;`
- [ ] Type hints on all method parameters and returns
- [ ] CSS uses custom properties for theming
- [ ] Dark mode support via media query
- [ ] Configuration via `.env` with `.env.example` provided
- [ ] CSRF protection on state-changing operations
- [ ] Output escaping in all templates
- [ ] RESTful API with consistent response format
- [ ] Comprehensive README.md

---
