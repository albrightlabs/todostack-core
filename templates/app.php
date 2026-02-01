<header class="site-header">
    <div>
        <div class="header-left">
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

            <?php if (count($allUsers) > 1): ?>
            <div class="user-list-selector">
                <select id="user-list-select" onchange="if(this.value) window.location.href = this.value === '<?= htmlspecialchars($currentUser['id']) ?>' ? '/' : '/?user=' + this.value">
                    <?php foreach ($allUsers as $user): ?>
                    <option value="<?= htmlspecialchars($user['id']) ?>" <?= $user['id'] === $viewingUserId ? 'selected' : '' ?>>
                        <?= $user['id'] === $currentUser['id'] ? 'My List' : htmlspecialchars($user['name'] ?: $user['email']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <?php if ($branding['external_link_url']): ?>
            <a href="<?= htmlspecialchars($branding['external_link_url']) ?>" class="header-external-link" target="_blank" rel="noopener noreferrer">
                <?php if ($branding['external_link_logo']): ?>
                <img src="<?= htmlspecialchars($branding['external_link_logo']) ?>" alt="<?= htmlspecialchars($branding['external_link_name']) ?>" width="16" height="16">
                <?php endif; ?>
                <?= htmlspecialchars($branding['external_link_name']) ?> &rarr;
            </a>
            <?php endif; ?>
            <?php if ($canWrite): ?>
            <span class="admin-badge">
                <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" /></svg>
                Admin
            </span>
            <?php endif; ?>
            <button type="button" class="btn btn-icon" id="settings-btn" title="Settings">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </button>
            <div class="user-menu" id="user-menu">
                <button type="button" class="user-menu-toggle" id="user-menu-toggle">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </button>
                <div class="user-menu-dropdown" id="user-menu-dropdown">
                    <div class="user-menu-info">
                        <span class="user-menu-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></span>
                    </div>
                    <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/users" class="user-menu-item">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
                            <circle cx="9" cy="7" r="4"></circle>
                            <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                        </svg>
                        Manage Users
                    </a>
                    <a href="#" class="user-menu-item" onclick="event.preventDefault(); TodoApp.showCleanupModal();">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"></polyline>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                        </svg>
                        Clean Up Uploads
                    </a>
                    <?php endif; ?>
                    <a href="#" class="user-menu-item" onclick="event.preventDefault(); TodoApp.showChangePasswordModal();">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                        Change Password
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
    </div>
</header>

<?php if (!$isOwnList): ?>
<div class="viewing-indicator">
    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
        <circle cx="12" cy="12" r="3"></circle>
    </svg>
    Viewing <?= htmlspecialchars($viewingUser['name'] ?: $viewingUser['email']) ?>'s list
    <?php if (!$canWrite): ?>
    <span class="readonly-badge">(Read-only)</span>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="app">

    <main class="app-main">
        <div class="global-input-container">
            <input type="text" id="global-input" class="global-input" placeholder="Add a task..." autocomplete="off">
        </div>

        <div id="sections-container" class="sections-container">
            <!-- Sections rendered by JavaScript -->
        </div>

        <button type="button" class="btn btn-add-section" id="add-section-btn">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="12" y1="5" x2="12" y2="19"></line>
                <line x1="5" y1="12" x2="19" y2="12"></line>
            </svg>
            Add Section
        </button>
    </main>
</div>

<?php if ($branding['footer_text'] || $branding['footer_show_powered_by']): ?>
<footer class="site-footer">
    <?php if ($branding['footer_text']): ?>
    <div class="footer-text"><?= $branding['footer_text'] ?></div>
    <?php endif; ?>
    <?php if ($branding['footer_show_powered_by']): ?>
    <div class="powered-by">
        Powered by <a href="https://github.com/albrightlabs/todostack-core" target="_blank" rel="noopener">TodoStack</a>
    </div>
    <?php endif; ?>
</footer>
<?php endif; ?>

<!-- Item Detail Modal -->
<div class="modal-overlay" id="item-modal">
    <div class="modal">
        <div class="modal-header">
            <h2 class="modal-title">Item Details</h2>
            <button type="button" class="btn btn-icon modal-close" id="modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Title</label>
                <input type="text" class="form-input" id="modal-title" placeholder="Task title">
            </div>

            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea class="form-textarea" id="modal-description" placeholder="Add notes or details..."></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label">Due Date</label>
                    <input type="date" class="form-input" id="modal-due-date">
                </div>
                <div class="form-group">
                    <label class="form-label">Priority</label>
                    <select class="form-select" id="modal-priority">
                        <option value="">None</option>
                        <option value="low">Low</option>
                        <option value="medium">Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>
            </div>

            <div class="modal-children">
                <div class="modal-children-header">
                    <span class="form-label">Sub-tasks</span>
                </div>
                <div id="modal-children-list" class="children-list"></div>
                <div class="add-child-container">
                    <input type="text" class="form-input" id="modal-add-child" placeholder="Add sub-task...">
                </div>
            </div>

            <div class="modal-attachments">
                <div class="modal-attachments-header">
                    <span class="form-label">Attachments</span>
                </div>
                <div id="modal-attachments-list" class="attachments-list"></div>
                <?php if ($canWrite): ?>
                <input type="file" id="attachment-upload" hidden multiple>
                <button type="button" class="btn btn-secondary btn-sm" id="add-attachment-btn">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"></path>
                    </svg>
                    Add attachment
                </button>
                <?php endif; ?>
            </div>

            <div class="modal-meta">
                <span id="modal-created"></span>
                <span id="modal-updated"></span>
            </div>

            <button type="button" class="btn btn-danger btn-delete" id="modal-delete">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="3 6 5 6 21 6"></polyline>
                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                </svg>
                Delete Item
            </button>
        </div>
    </div>
</div>

<!-- Settings Modal -->
<div class="modal-overlay" id="settings-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title">Settings</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="settings-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="setting-item">
                <label class="setting-label">
                    <input type="checkbox" id="setting-hide-completed">
                    <span>Hide completed items</span>
                </label>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal-overlay" id="confirm-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title">Confirm Delete</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="confirm-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p id="confirm-message">Are you sure you want to delete this item?</p>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close="confirm-modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="confirm-delete">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Section Name Modal -->
<div class="modal-overlay" id="section-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title" id="section-modal-title">Add Section</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="section-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="text" class="form-input" id="section-name-input" placeholder="Section name">
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close="section-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="section-save">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Send Item Modal -->
<div class="modal-overlay" id="send-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title">Send Item</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="send-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <p class="send-modal-description">Send this item to another user's list:</p>
            <div class="form-group">
                <label class="form-label">Select User</label>
                <select class="form-select" id="send-target-user">
                    <option value="">Choose a user...</option>
                </select>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close="send-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="send-confirm" disabled>
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="22" y1="2" x2="11" y2="13"></line>
                        <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                    </svg>
                    Send
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Change Password Modal -->
<div class="modal-overlay" id="password-modal">
    <div class="modal modal-sm">
        <div class="modal-header">
            <h2 class="modal-title">Change Password</h2>
            <button type="button" class="btn btn-icon modal-close" data-close="password-modal">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-input" id="current-password" required>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" class="form-input" id="new-password" required>
                <span class="form-help">Minimum 8 characters</span>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" class="form-input" id="confirm-password" required>
            </div>
            <div id="password-error" class="form-error" style="display: none;"></div>
            <div class="modal-actions">
                <button type="button" class="btn btn-secondary" data-close="password-modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="save-password">Save</button>
            </div>
        </div>
    </div>
</div>

<!-- Initial data for JavaScript -->
<script>
    window.TODOAPP_INITIAL_DATA = <?= json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.TODOAPP_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
    window.TODOAPP_CAN_WRITE = <?= json_encode($canWrite) ?>;
    window.TODOAPP_VIEWING_USER_ID = <?= json_encode($viewingUserId) ?>;
    window.TODOAPP_CURRENT_USER_ID = <?= json_encode($currentUser['id']) ?>;
    window.TODOAPP_IS_OWN_LIST = <?= json_encode($isOwnList) ?>;
</script>
