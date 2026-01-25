<header class="site-header">
    <div class="header-content" style="flex-direction: row; align-items: center; justify-content: space-between; height: 56px; padding: 0 16px;">
        <?php if ($branding['logo_url']): ?>
        <a href="/" class="site-logo">
            <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="<?= htmlspecialchars($branding['site_name']) ?>" style="max-width: <?= htmlspecialchars($branding['logo_width']) ?>px;">
        </a>
        <?php else: ?>
        <a href="/" class="site-logo">
            <?php if ($branding['site_emoji']): ?>
            <span class="site-logo-emoji"><?= htmlspecialchars($branding['site_emoji']) ?></span>
            <?php endif; ?>
            <?= htmlspecialchars($branding['site_name']) ?>
        </a>
        <?php endif; ?>
        <div class="user-menu" id="user-menu">
            <button type="button" class="user-menu-toggle" id="user-menu-toggle">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                    <circle cx="12" cy="7" r="4"></circle>
                </svg>
            </button>
            <div class="user-menu-dropdown" id="user-menu-dropdown">
                <div class="user-menu-info">
                    <span class="user-menu-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?></span>
                    <span class="user-menu-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                    <span class="user-menu-role role-<?= htmlspecialchars($currentUser['role'] ?? 'readonly') ?>"><?= $currentUser['role'] === 'admin' ? 'Admin' : 'Read-Only' ?></span>
                </div>
                <a href="/users" class="user-menu-item">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                        <circle cx="9" cy="7" r="4"></circle>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                    </svg>
                    Manage Users
                </a>
                <a href="/logout" class="user-menu-item user-menu-item-danger">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                    Sign Out
                </a>
            </div>
        </div>
    </div>
</header>

<div class="users-page-container">
    <div class="users-page">
        <div class="users-header">
            <h1>User Management</h1>
            <button type="button" class="btn btn-primary" id="add-user-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                </svg>
                Add User
            </button>
        </div>

        <div class="users-list" id="users-list">
            <div class="empty-state">Loading users...</div>
        </div>
    </div>
</div>

<!-- User Modal -->
<div class="modal-overlay" id="user-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title" id="user-modal-title">Add User</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="user-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="user-form">
                <input type="hidden" id="user-id" name="id">

                <div class="form-group">
                    <label class="form-label" for="user-name">Name</label>
                    <input type="text" class="form-input" id="user-name" name="name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user-email">Email</label>
                    <input type="email" class="form-input" id="user-email" name="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user-password">Password</label>
                    <input type="password" class="form-input" id="user-password" name="password" minlength="8">
                    <small class="form-help" id="password-help">Minimum 8 characters</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user-password-confirm">Confirm Password</label>
                    <input type="password" class="form-input" id="user-password-confirm" name="password_confirm" minlength="8">
                    <small class="form-help text-danger" id="password-match-error" style="display: none;">Passwords do not match</small>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user-role">Role</label>
                    <select class="form-select" id="user-role" name="role" required>
                        <option value="admin">Admin (Full Access)</option>
                        <option value="readonly">Read-Only (View Only)</option>
                    </select>
                </div>

                <div class="modal-actions">
                    <button type="button" class="btn btn-secondary" data-close="user-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="user-save">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="delete-user-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title">Delete User</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="delete-user-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p>Are you sure you want to delete <strong id="delete-user-name"></strong>?</p>
            <p class="text-muted">This action cannot be undone.</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close="delete-user-modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete-user">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
window.USERS_PAGE = true;
window.CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
window.CURRENT_USER_ID = <?= json_encode($currentUser['id'] ?? '') ?>;
</script>
