# TodoStack Core

A simple, fast to-do list application with flat-file JSON storage. Prioritizes speed of adding items - users can add a new to-do in as few clicks/keystrokes as possible.

Built as an open-source framework that organizations can customize and brand as their own.

## Features

- **Quick Item Entry**: Single input field always visible, press Enter to add immediately
- **Sections**: Organize items into titled sections with collapse/expand
- **Nested Items**: Any item can have child items (sub-tasks)
- **Item Details**: Due dates, priority levels, descriptions via modal
- **Completion Tracking**: Click checkbox to mark complete with strikethrough styling
- **Hide Completed**: Option to hide completed items
- **Drag & Drop**: Reorder items within sections
- **Dark Mode**: Automatic based on system preference
- **Flat-file Storage**: Single JSON file, no database required
- **Responsive**: Works on mobile devices
- **Fully Customizable**: Branding, colors, logos, and custom CSS/JS

## Requirements

- PHP 8.1+
- Composer
- Apache with mod_rewrite (or PHP built-in server for development)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/albright-labs/todostack-core.git
   cd todostack-core
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Copy environment configuration:
   ```bash
   cp .env.example .env
   ```

4. Configure settings in `.env` (see [Customization](#customization) below)

5. Ensure the `data/` directory is writable:
   ```bash
   chmod 755 data/
   ```

## Development Server

Start the PHP built-in server:

```bash
php -S localhost:8000 -t public public/router.php
```

Then open http://localhost:8000 in your browser.

## Customization

TodoStack is designed to be easily branded and customized by organizations. There are three levels of customization:

### 1. Environment-Based Branding (Easy)

Edit your `.env` file to customize branding without touching any code:

```env
# Site Identity
SITE_NAME="My Company Tasks"
SITE_TAGLINE="Get things done"
SITE_EMOJI="✅"

# Logo (replaces site name in header)
LOGO_URL="/assets/my-logo.png"
LOGO_WIDTH="150"

# Favicon
FAVICON_URL="/assets/favicon.png"
FAVICON_EMOJI="✅"
FAVICON_LETTER="M"
FAVICON_SHOW_LETTER=true

# Header Link (optional external link)
EXTERNAL_LINK_NAME="Main Site"
EXTERNAL_LINK_URL="https://mycompany.com"
EXTERNAL_LINK_LOGO="/assets/company-icon.png"

# Footer
FOOTER_TEXT="© 2025 My Company. All rights reserved."

# Colors
COLOR_PRIMARY="#8b5cf6"
COLOR_PRIMARY_HOVER="#7c3aed"
```

### 2. Custom CSS (Moderate)

Create `public/assets/custom.css` for styling overrides:

```css
/* Override CSS custom properties */
:root {
    --accent-color: #8b5cf6;
    --accent-hover: #7c3aed;
}

/* Custom header styling */
.app-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

/* Custom fonts */
body {
    font-family: 'Inter', sans-serif;
}
```

This file is gitignored, so your customizations won't conflict with upstream updates.

### 3. Custom JavaScript (Advanced)

Create `public/assets/custom.js` for behavioral customizations:

```javascript
// Add custom functionality
document.addEventListener('DOMContentLoaded', function() {
    console.log('Custom JS loaded');
    // Your custom code here
});
```

This file is also gitignored.

### Branding Variables Reference

| Variable | Default | Description |
|----------|---------|-------------|
| `SITE_NAME` | "TodoStack" | Site title in header and browser tab |
| `SITE_TAGLINE` | "Simple, fast task management" | Short description (reserved) |
| `SITE_EMOJI` | "" | Emoji shown before site name |
| `LOGO_URL` | "" | Path to logo image (replaces text) |
| `LOGO_WIDTH` | "120" | Max width of logo in pixels |
| `FAVICON_URL` | "" | Path to custom favicon |
| `FAVICON_EMOJI` | "" | Emoji for dynamic favicon |
| `FAVICON_LETTER` | "" | Letter overlay on favicon |
| `FAVICON_SHOW_LETTER` | true | Show/hide letter overlay |
| `EXTERNAL_LINK_NAME` | "" | Text for header external link |
| `EXTERNAL_LINK_URL` | "" | URL for header external link |
| `EXTERNAL_LINK_LOGO` | "" | Logo for header external link |
| `FOOTER_TEXT` | "" | Footer content (supports HTML) |
| `COLOR_PRIMARY` | "#228be6" | Primary accent color |
| `COLOR_PRIMARY_HOVER` | "#1c7ed6" | Primary color hover state |

### CSS Custom Properties

Override these in `custom.css` for complete theme control:

```css
:root {
    /* Backgrounds */
    --bg-primary: #ffffff;
    --bg-secondary: #f8f9fa;
    --bg-tertiary: #e9ecef;
    --bg-hover: #f1f3f5;

    /* Text */
    --text-primary: #1a1a1a;
    --text-secondary: #495057;
    --text-muted: #868e96;

    /* Borders */
    --border-color: #dee2e6;
    --border-light: #e9ecef;

    /* Accent (primary color) */
    --accent-color: #228be6;
    --accent-hover: #1c7ed6;

    /* Priority colors */
    --priority-high: #fa5252;
    --priority-medium: #fab005;
    --priority-low: #40c057;

    /* Danger */
    --danger-color: #fa5252;
    --danger-hover: #e03131;

    /* Shadows */
    --shadow-sm: 0 1px 2px rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.07);
    --shadow-lg: 0 10px 15px rgba(0, 0, 0, 0.1);

    /* Borders */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
}
```

## Production Deployment

### Apache

Point your document root to the `public/` directory. The included `.htaccess` file handles URL rewriting.

```apache
<VirtualHost *:80>
    ServerName todolist.example.com
    DocumentRoot /var/www/todostack-core/public

    <Directory /var/www/todostack-core/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

### Nginx

```nginx
server {
    listen 80;
    server_name todolist.example.com;
    root /var/www/todostack-core/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location /api {
        try_files $uri $uri/ /api.php?$query_string;
    }
}
```

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | /api/list | Get full todo list |
| PUT | /api/settings | Update settings |
| POST | /api/sections | Create section |
| PUT | /api/sections/{id} | Update section |
| PUT | /api/sections/{id}/reorder | Reorder section |
| DELETE | /api/sections/{id} | Delete section |
| POST | /api/sections/{id}/items | Create item in section |
| GET | /api/items/{id} | Get item |
| PUT | /api/items/{id} | Update item |
| DELETE | /api/items/{id} | Delete item |
| PUT | /api/items/{id}/toggle | Toggle completion |
| PUT | /api/items/{id}/move | Move item |
| POST | /api/items/{id}/children | Add child item |
| PUT | /api/items/{id}/children/{childId} | Update child |
| PUT | /api/items/{id}/children/{childId}/toggle | Toggle child |
| DELETE | /api/items/{id}/children/{childId} | Delete child |

## Data Storage

All data is stored in `data/todos.json`. The file is created automatically on first run with a default empty section.

### Data Structure

```json
{
  "settings": {
    "hideCompleted": false,
    "theme": "auto"
  },
  "sections": [
    {
      "id": "uuid",
      "title": "Section Title",
      "position": 0,
      "collapsed": false,
      "items": [
        {
          "id": "uuid",
          "title": "Task title",
          "description": "",
          "completed": false,
          "priority": null,
          "dueDate": null,
          "position": 0,
          "createdAt": "2025-01-01T00:00:00Z",
          "updatedAt": "2025-01-01T00:00:00Z",
          "children": []
        }
      ]
    }
  ]
}
```

## Keyboard Shortcuts

| Key | Action |
|-----|--------|
| Enter | Add item (when in input field) |
| Escape | Close modal |
| Ctrl/Cmd + S | Save item (when modal is open) |

## Security

- CSRF protection on all write operations
- Input validation on all user input
- HTML escaping on all output
- File locking on writes to prevent corruption
- Optional password protection (set `ADMIN_PASSWORD` in `.env`)

## Upgrading

Since custom files (`custom.css`, `custom.js`, `.env`, `data/`) are gitignored, you can safely pull updates:

```bash
git pull origin main
composer install
```

Your branding and data will be preserved.

## License

MIT License
