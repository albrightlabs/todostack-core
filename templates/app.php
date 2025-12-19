<div class="app">
    <header class="app-header">
        <div class="app-brand">
            <?php if ($branding['logo_url']): ?>
            <a href="/" class="app-logo-link">
                <img src="<?= htmlspecialchars($branding['logo_url']) ?>" alt="<?= htmlspecialchars($branding['site_name']) ?>" class="app-logo" style="max-width: <?= htmlspecialchars($branding['logo_width']) ?>px;">
            </a>
            <?php else: ?>
            <h1 class="app-title">
                <?php if ($branding['site_emoji']): ?>
                <span class="app-emoji"><?= htmlspecialchars($branding['site_emoji']) ?></span>
                <?php endif; ?>
                <?= htmlspecialchars($branding['site_name']) ?>
            </h1>
            <?php endif; ?>
        </div>
        <div class="header-actions">
            <?php if ($branding['external_link_url']): ?>
            <a href="<?= htmlspecialchars($branding['external_link_url']) ?>" class="external-link" target="_blank" rel="noopener">
                <?php if ($branding['external_link_logo']): ?>
                <img src="<?= htmlspecialchars($branding['external_link_logo']) ?>" alt="" class="external-link-logo">
                <?php endif; ?>
                <?php if ($branding['external_link_name']): ?>
                <span class="external-link-text"><?= htmlspecialchars($branding['external_link_name']) ?></span>
                <?php endif; ?>
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
                    <polyline points="15 3 21 3 21 9"></polyline>
                    <line x1="10" y1="14" x2="21" y2="3"></line>
                </svg>
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-icon" id="settings-btn" title="Settings">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"></circle>
                    <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"></path>
                </svg>
            </button>
        </div>
    </header>

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

    <?php if ($branding['footer_text']): ?>
    <footer class="app-footer">
        <?= $branding['footer_text'] ?>
    </footer>
    <?php endif; ?>
</div>

<!-- Item Detail Modal -->
<div class="modal-overlay" id="item-modal">
    <div class="modal">
        <div class="modal-header">
            <input type="text" class="modal-title-input" id="modal-title" placeholder="Task title">
            <button type="button" class="btn btn-icon modal-close" id="modal-close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <label class="form-label">Description</label>
            <textarea class="form-textarea" id="modal-description" placeholder="Add notes or details..."></textarea>

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

<!-- Initial data for JavaScript -->
<script>
    window.TODOAPP_INITIAL_DATA = <?= json_encode($list, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    window.TODOAPP_CSRF_TOKEN = <?= json_encode($csrfToken) ?>;
</script>
