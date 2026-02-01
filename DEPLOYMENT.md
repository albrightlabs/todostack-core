# Deployment Guide

This guide covers deploying TodoStack to a production server with Laravel Forge, ensuring user accounts and todo data are preserved across deployments.

## The Problem

TodoStack stores user accounts in `data/users.json` and todos in `data/todos.json`. When deploying new code, these could be overwritten. This guide solves that with a data persistence strategy.

## Strategy Overview

1. **User accounts** and **todo data** are gitignored (environment-specific)
2. Data files are copied from the previous release during deployment
3. First deploy creates empty data files (run setup wizard to create admin)

## Prerequisites

- Laravel Forge (or similar deployment platform)
- GitHub repository for your TodoStack instance
- SSH access to your server

## Data Persistence

User accounts and todos are environment-specific. Each server has its own data.

### How It Works

- `data/users.json` and `data/todos.json` are gitignored
- `data/_example/` provides templates for fresh installs
- Deploy script copies data from previous release
- First deploy creates empty files (run setup wizard to create admin)

### No Manual Action Required

This is handled automatically by the deploy script below.

## Server Setup

### One-Time Server Configuration

SSH into your server and ensure directories are ready:

```bash
# Replace with your domain
DOMAIN="tasks.yourdomain.com"

# The site directory is created by Forge during site setup
# Just verify it exists
ls -la /home/forge/$DOMAIN
```

### Deploy Script

Use this as your Forge deploy script (replace `tasks.yourdomain.com` with your domain):

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# ═══════════════════════════════════════════════════════════════════════════════
# INSTALL: Dependencies
# ═══════════════════════════════════════════════════════════════════════════════
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ═══════════════════════════════════════════════════════════════════════════════
# SETUP: Directories and permissions
# ═══════════════════════════════════════════════════════════════════════════════
DOMAIN="tasks.yourdomain.com"

mkdir -p data
chmod -R 775 data

# ═══════════════════════════════════════════════════════════════════════════════
# SETUP: Data persistence (environment-specific)
# ═══════════════════════════════════════════════════════════════════════════════
# Copy data from previous release (preserves users and todos across deploys)

PREVIOUS_USERS="/home/forge/$DOMAIN/current/data/users.json"
PREVIOUS_TODOS="/home/forge/$DOMAIN/current/data/todos.json"

if [ -f "$PREVIOUS_USERS" ]; then
    cp "$PREVIOUS_USERS" data/users.json
    echo "Preserved user accounts from previous release."
fi

if [ -f "$PREVIOUS_TODOS" ]; then
    cp "$PREVIOUS_TODOS" data/todos.json
    echo "Preserved todos from previous release."
fi

# If no previous users file, the app will show setup wizard on first visit

$ACTIVATE_RELEASE()
```

## How It Works

### Normal Developer Workflow

1. Developer commits locally: `git commit -m "Add new feature"`
2. Push to GitHub
3. Forge triggers deployment
4. Deploy script:
   - Installs dependencies
   - Creates data directory
   - Copies users and todos from previous release
   - Activates new release

### First Deployment

1. Deploy runs, no previous data to copy
2. App detects no users, shows setup wizard
3. Create first admin account via wizard
4. Start using TodoStack

### Subsequent Deployments

1. Deploy copies `users.json` and `todos.json` from previous release
2. All user accounts and todos preserved
3. Zero downtime with Forge's symlink strategy

## Troubleshooting

### Lost user accounts after deploy

Check that the copy command is finding the previous users file:

```bash
ls -la /home/forge/tasks.yourdomain.com/current/data/
```

If the file doesn't exist, the app will show the setup wizard.

### Lost todos after deploy

Same check as above:

```bash
cat /home/forge/tasks.yourdomain.com/current/data/todos.json
```

### Permission errors

Ensure the data directory has correct permissions:

```bash
chmod -R 775 /home/forge/tasks.yourdomain.com/current/data
```

## Alternative: Symlink Approach

If you prefer to keep data completely outside deployments:

```bash
# One-time setup on server
mkdir -p /home/forge/tasks.yourdomain.com/persistent/data

# In deploy script, replace data copy with:
rm -rf data
ln -s /home/forge/$DOMAIN/persistent/data data
```

This keeps data entirely separate from deployments. Useful if you want to manage backups independently.

## Backup Strategy

Consider adding automated backups:

```bash
# Add to deploy script before $ACTIVATE_RELEASE()
BACKUP_DIR="/home/forge/$DOMAIN/backups"
mkdir -p "$BACKUP_DIR"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

if [ -f "$PREVIOUS_USERS" ]; then
    cp "$PREVIOUS_USERS" "$BACKUP_DIR/users_$TIMESTAMP.json"
fi

if [ -f "$PREVIOUS_TODOS" ]; then
    cp "$PREVIOUS_TODOS" "$BACKUP_DIR/todos_$TIMESTAMP.json"
fi

# Keep only last 30 backups
ls -t "$BACKUP_DIR"/users_*.json 2>/dev/null | tail -n +31 | xargs -r rm
ls -t "$BACKUP_DIR"/todos_*.json 2>/dev/null | tail -n +31 | xargs -r rm
```

## Security Notes

- Ensure `data/` directory has restrictive permissions (775)
- The `.htaccess` in `data/` prevents direct web access
- Consider encrypting backups if they contain sensitive data
- Regularly audit user accounts and remove inactive users
