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
        <!-- Users loaded by JavaScript -->
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
                    <label class="form-label" for="user-email">Email</label>
                    <input type="email" class="form-input" id="user-email" name="email" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="user-password">Password</label>
                    <input type="password" class="form-input" id="user-password" name="password" minlength="8">
                    <small class="form-help" id="password-help">Minimum 8 characters</small>
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
            <p>Are you sure you want to delete <strong id="delete-user-email"></strong>?</p>
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
