# Deployment Guide

This guide covers deploying TodoStack to a production server with Laravel Forge, ensuring user accounts and todo data are preserved across deployments with version history.

## The Problem

TodoStack stores user accounts in `data/users.json` and todos in `data/todos/` (one JSON file per user). When deploying new code, these could be overwritten. This guide solves that with a pre-deploy sync strategy that also provides version history.

## Strategy Overview

1. **User accounts** (`data/users.json`) are gitignored and copied between releases
2. **Todo data** (`data/todos/`) stays in git, with server changes synced back before each deployment
3. **Uploads** are copied between releases
4. Automated commits use `[skip ci]` to prevent deployment loops

## Prerequisites

- Laravel Forge (or similar deployment platform)
- GitHub repository for your TodoStack instance
- SSH access to your server

## User Accounts

User accounts are environment-specific. Each server has its own users.

### How It Works

- `data/users.json` is gitignored
- `data/_example/users.json` provides an empty template
- Deploy script copies users from previous release
- First deploy creates empty users file (run setup wizard to create admin)

### No Action Required

This is handled automatically by the deploy script below.

## Todo Data Persistence

Todo edits made on the server are synced to git before each deployment, providing version history.

### One-Time Server Setup

SSH into your server and create a persistent repo directory:

```bash
# Replace with your domain
DOMAIN="tasks.yourdomain.com"

# Create repo directory
mkdir -p /home/forge/$DOMAIN/repo

# Clone your TodoStack repo
git clone https://github.com/your-org/your-todostack.git /home/forge/$DOMAIN/repo

# Set up git credentials for pushing
git config --global credential.helper store

# Create credentials file (use a GitHub Personal Access Token)
# Format: https://USERNAME:TOKEN@github.com
nano /home/forge/.git-credentials
chmod 600 /home/forge/.git-credentials

# Test that push works
cd /home/forge/$DOMAIN/repo
git fetch origin
```

### Deploy Script

Use this as your Forge deploy script (replace `tasks.yourdomain.com` with your domain):

```bash
# Exit early if this is an auto-sync commit (prevents loops)
DOMAIN="tasks.yourdomain.com"
REPO_DIR="/home/forge/$DOMAIN/repo"

if [ -d "$REPO_DIR" ]; then
    cd "$REPO_DIR"
    git fetch origin
    LAST_COMMIT_MSG=$(git log origin/main -1 --pretty=%B)
    if echo "$LAST_COMMIT_MSG" | grep -q "\[skip ci\]"; then
        echo "Auto-sync commit detected. Skipping deployment."
        exit 0
    fi
fi

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# ═══════════════════════════════════════════════════════════════════════════════
# PRE-DEPLOY: Sync server todo data to git
# ═══════════════════════════════════════════════════════════════════════════════
CURRENT_TODOS="/home/forge/$DOMAIN/current/data/todos"

if [ -d "$CURRENT_TODOS" ] && [ -d "$REPO_DIR" ]; then
    echo "Syncing server todo data..."

    cd "$REPO_DIR"
    git fetch origin
    git checkout main
    git pull origin main

    # Sync todos directory from current deployment to repo
    rsync -a --delete "$CURRENT_TODOS/" "$REPO_DIR/data/todos/"

    # Check for changes and commit if any
    if [ -n "$(git status --porcelain data/todos/)" ]; then
        git config user.name "TodoStack Deploy"
        git config user.email "deploy@yourdomain.com"
        git add data/todos/
        git commit -m "Auto-sync server todo data [skip ci]"
        git push origin main
        echo "Server todo data synced to git."
    else
        echo "No todo data changes to sync."
    fi

    cd $FORGE_RELEASE_DIRECTORY
fi

# ═══════════════════════════════════════════════════════════════════════════════
# INSTALL: Dependencies
# ═══════════════════════════════════════════════════════════════════════════════
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

# ═══════════════════════════════════════════════════════════════════════════════
# SETUP: Directories and permissions
# ═══════════════════════════════════════════════════════════════════════════════
mkdir -p data/todos
mkdir -p public/uploads
chmod -R 775 data
chmod -R 775 public/uploads

# ═══════════════════════════════════════════════════════════════════════════════
# SETUP: User data (environment-specific)
# ═══════════════════════════════════════════════════════════════════════════════
# Copy users from previous release (preserves accounts across deploys)
PREVIOUS_USERS="/home/forge/$DOMAIN/current/data/users.json"
PREVIOUS_UPLOADS="/home/forge/$DOMAIN/current/public/uploads"

if [ -f "$PREVIOUS_USERS" ]; then
    cp "$PREVIOUS_USERS" data/users.json
    echo "Preserved user accounts from previous release."
elif [ -f "data/_example/users.json" ]; then
    cp data/_example/users.json data/users.json
    echo "Created fresh users.json from template."
fi

if [ -d "$PREVIOUS_UPLOADS" ]; then
    cp -r "$PREVIOUS_UPLOADS"/* public/uploads/ 2>/dev/null || true
    echo "Preserved uploads from previous release."
fi

$ACTIVATE_RELEASE()
```

### Forge Configuration

Configure Forge to ignore automated commits:

1. Go to your site in Forge
2. Navigate to Deployments
3. Set deployment trigger to ignore commits containing `[skip ci]`

This prevents the auto-sync commit from triggering another deployment.

## How It Works

### Normal Developer Workflow

1. Developer commits locally: `git commit -m "Update feature"`
2. Push to GitHub
3. Forge triggers deployment
4. Deploy script:
   - Syncs any server todo changes to git first
   - Pulls new code (including synced data)
   - Preserves user accounts
   - Activates new release

### Server Data Changes

1. User adds/edits/completes todos via TodoStack
2. Next deployment (from any push):
   - Deploy script detects server changes
   - Commits them with `[skip ci]`
   - Pushes to GitHub (no loop triggered)
   - Deploys the original changes
3. Todo data is now in git with version history

### First Deployment

1. Deploy runs, no previous data to sync
2. Fresh `users.json` created from template
3. Visit site to run setup wizard and create admin account

## Troubleshooting

### Todo data not syncing

Check repo directory exists and has proper permissions:

```bash
ls -la /home/forge/tasks.yourdomain.com/repo
```

### Git push failing

Verify credentials:

```bash
cat /home/forge/.git-credentials
git -C /home/forge/tasks.yourdomain.com/repo fetch origin
```

### Deploy loops

Ensure Forge ignores `[skip ci]` commits. Check GitHub commits to confirm the marker is present in automated commits.

### Lost user accounts after deploy

Check that the copy command is finding the previous users file:

```bash
ls -la /home/forge/tasks.yourdomain.com/current/data/
```

## Security Notes

- Keep `.git-credentials` permissions at 600
- Use a GitHub PAT with minimal permissions (repo access only)
- Consider using deploy keys instead of PATs for tighter security
