/**
 * TodoApp - Main Application Object
 */
const TodoApp = {
    // State
    state: {
        list: null,
        currentItem: null,
        currentSection: null,
        draggedItem: null,
        draggedSection: null,
        expandedItems: new Set(),
    },

    // CSRF Token
    csrfToken: '',

    // ========================================
    // Initialization
    // ========================================

    init() {
        this.csrfToken = window.TODOAPP_CSRF_TOKEN || '';
        this.state.list = window.TODOAPP_INITIAL_DATA || { settings: {}, sections: [] };

        this.bindEvents();
        this.renderList();

        // Focus global input
        document.getElementById('global-input')?.focus();
    },

    bindEvents() {
        // Global input
        const globalInput = document.getElementById('global-input');
        globalInput?.addEventListener('keydown', (e) => this.handleGlobalInput(e));

        // Add section button
        document.getElementById('add-section-btn')?.addEventListener('click', () => this.showAddSectionModal());

        // Settings button
        document.getElementById('settings-btn')?.addEventListener('click', () => this.openModal('settings-modal'));

        // Settings checkbox
        document.getElementById('setting-hide-completed')?.addEventListener('change', (e) => {
            this.updateSettings({ hideCompleted: e.target.checked });
        });

        // Modal close buttons
        document.querySelectorAll('.modal-close, [data-close]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modalId = e.currentTarget.dataset.close || 'item-modal';
                this.closeModal(modalId);
            });
        });

        // Item modal events
        document.getElementById('modal-close')?.addEventListener('click', () => this.closeModal('item-modal'));
        document.getElementById('modal-title')?.addEventListener('blur', () => this.saveCurrentItem());
        document.getElementById('modal-description')?.addEventListener('blur', () => this.saveCurrentItem());
        document.getElementById('modal-due-date')?.addEventListener('change', () => this.saveCurrentItem());
        document.getElementById('modal-priority')?.addEventListener('change', () => this.saveCurrentItem());
        document.getElementById('modal-delete')?.addEventListener('click', () => this.confirmDeleteItem());
        document.getElementById('modal-add-child')?.addEventListener('keydown', (e) => this.handleAddChild(e));

        // Section modal events
        document.getElementById('section-save')?.addEventListener('click', () => this.saveSectionModal());
        document.getElementById('section-name-input')?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') this.saveSectionModal();
        });

        // Confirm modal
        document.getElementById('confirm-delete')?.addEventListener('click', () => this.executeDelete());

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => this.handleKeyboard(e));

        // Click outside modal to close
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    this.closeModal(overlay.id);
                }
            });
        });
    },

    handleKeyboard(e) {
        // Escape to close modals
        if (e.key === 'Escape') {
            const openModal = document.querySelector('.modal-overlay.show');
            if (openModal) {
                this.closeModal(openModal.id);
            }
        }

        // Ctrl/Cmd + S to save
        if ((e.ctrlKey || e.metaKey) && e.key === 's') {
            e.preventDefault();
            this.saveCurrentItem();
        }
    },

    // ========================================
    // API Methods
    // ========================================

    async api(endpoint, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.csrfToken,
            },
        };

        const config = {
            ...defaults,
            ...options,
            headers: { ...defaults.headers, ...options.headers },
        };

        try {
            const response = await fetch(`/api${endpoint}`, config);
            const data = await response.json();

            if (!data.success) {
                console.error('API Error:', data.error);
                return null;
            }

            return data.data;
        } catch (error) {
            console.error('API Request Failed:', error);
            return null;
        }
    },

    // ========================================
    // List Operations
    // ========================================

    async refreshList() {
        const list = await this.api('/list');
        if (list) {
            this.state.list = list;
            this.renderList();
        }
    },

    async updateSettings(settings) {
        const updated = await this.api('/settings', {
            method: 'PUT',
            body: JSON.stringify(settings),
        });

        if (updated) {
            this.state.list.settings = updated;
            this.renderList();
        }
    },

    // ========================================
    // Section Operations
    // ========================================

    async createSection(title) {
        const section = await this.api('/sections', {
            method: 'POST',
            body: JSON.stringify({ title }),
        });

        // Always refresh from backend to get authoritative state
        await this.refreshList();
    },

    async updateSection(id, updates) {
        const section = await this.api(`/sections/${id}`, {
            method: 'PUT',
            body: JSON.stringify(updates),
        });

        // Refresh from backend to get authoritative state
        await this.refreshList();
    },

    async deleteSection(id) {
        const result = await this.api(`/sections/${id}`, {
            method: 'DELETE',
        });

        // Always refresh from backend to get authoritative state
        await this.refreshList();
    },

    async reorderSection(id, targetPosition) {
        // Use dedicated reorder endpoint that properly reindexes all sections
        const result = await this.api(`/sections/${id}/reorder`, {
            method: 'PUT',
            body: JSON.stringify({ position: targetPosition }),
        });

        // Always refresh to get authoritative state from backend
        await this.refreshList();
    },

    toggleSectionCollapse(id) {
        const section = this.state.list.sections.find(s => s.id === id);
        if (section) {
            section.collapsed = !section.collapsed;
            this.updateSection(id, { collapsed: section.collapsed });
        }
    },

    showAddSectionModal() {
        this.state.currentSection = null;
        document.getElementById('section-modal-title').textContent = 'Add Section';
        document.getElementById('section-name-input').value = '';
        this.openModal('section-modal');
        // Delay focus to allow modal transition to start
        setTimeout(() => document.getElementById('section-name-input')?.focus(), 50);
    },

    showEditSectionModal(id) {
        const section = this.state.list.sections.find(s => s.id === id);
        if (section) {
            this.state.currentSection = section;
            document.getElementById('section-modal-title').textContent = 'Edit Section';
            document.getElementById('section-name-input').value = section.title;
            this.openModal('section-modal');
            // Delay focus to allow modal transition to start
            setTimeout(() => document.getElementById('section-name-input')?.focus(), 50);
        }
    },

    saveSectionModal() {
        const title = document.getElementById('section-name-input').value.trim();

        if (this.state.currentSection) {
            this.updateSection(this.state.currentSection.id, { title });
        } else {
            this.createSection(title);
        }

        this.closeModal('section-modal');
    },

    // ========================================
    // Item Operations
    // ========================================

    async createItem(sectionId, title) {
        const item = await this.api(`/sections/${sectionId}/items`, {
            method: 'POST',
            body: JSON.stringify({ title }),
        });

        // Refresh from backend to get authoritative state
        await this.refreshList();
    },

    async updateItem(id, updates) {
        const item = await this.api(`/items/${id}`, {
            method: 'PUT',
            body: JSON.stringify(updates),
        });

        // Refresh from backend to get authoritative state
        await this.refreshList();

        return item;
    },

    async toggleItem(id) {
        const item = await this.api(`/items/${id}/toggle`, {
            method: 'PUT',
        });

        // Trigger confetti if item was completed
        if (item && item.completed) {
            this.fireConfetti();
        }

        // Refresh from backend to get authoritative state
        await this.refreshList();
    },

    async deleteItem(id) {
        const result = await this.api(`/items/${id}`, {
            method: 'DELETE',
        });

        this.closeModal('item-modal');
        this.closeModal('confirm-modal');

        // Refresh from backend to get authoritative state
        await this.refreshList();
    },

    async moveItem(id, sectionId, position) {
        const item = await this.api(`/items/${id}/move`, {
            method: 'PUT',
            body: JSON.stringify({ sectionId, position }),
        });

        if (item) {
            await this.refreshList();
        }
    },

    updateItemInState(updatedItem) {
        for (const section of this.state.list.sections) {
            const index = section.items.findIndex(i => i.id === updatedItem.id);
            if (index !== -1) {
                section.items[index] = updatedItem;
                return;
            }
        }
    },

    removeItemFromState(id) {
        for (const section of this.state.list.sections) {
            const index = section.items.findIndex(i => i.id === id);
            if (index !== -1) {
                section.items.splice(index, 1);
                return;
            }
        }
    },

    findItemById(id) {
        for (const section of this.state.list.sections) {
            const item = section.items.find(i => i.id === id);
            if (item) return item;
        }
        return null;
    },

    toggleItemExpand(id) {
        if (this.state.expandedItems.has(id)) {
            this.state.expandedItems.delete(id);
        } else {
            this.state.expandedItems.add(id);
        }
        this.renderList();
    },

    startInlineEdit(itemId, titleElement) {
        // Don't start if already editing
        if (titleElement.querySelector('input')) return;

        const item = this.findItemById(itemId);
        if (!item) return;

        const originalTitle = item.title;
        const input = document.createElement('input');
        input.type = 'text';
        input.className = 'inline-title-input';
        input.value = originalTitle;

        // Replace span content with input
        titleElement.innerHTML = '';
        titleElement.appendChild(input);
        input.focus();
        input.select();

        const saveEdit = async () => {
            const newTitle = input.value.trim();
            if (newTitle && newTitle !== originalTitle) {
                await this.updateItem(itemId, { title: newTitle });
            } else {
                // Revert to original if empty or unchanged
                titleElement.textContent = originalTitle;
            }
        };

        const cancelEdit = () => {
            titleElement.textContent = originalTitle;
        };

        input.addEventListener('blur', saveEdit);

        input.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            } else if (e.key === 'Escape') {
                e.preventDefault();
                input.removeEventListener('blur', saveEdit);
                cancelEdit();
            }
        });

        // Prevent click from bubbling
        input.addEventListener('click', (e) => e.stopPropagation());
    },

    // ========================================
    // Child Item Operations
    // ========================================

    async addChild(parentId, title) {
        const child = await this.api(`/items/${parentId}/children`, {
            method: 'POST',
            body: JSON.stringify({ title }),
        });

        if (child) {
            this.state.expandedItems.add(parentId);
        }

        // Refresh from backend to get authoritative state
        await this.refreshList();
        this.renderModalChildren();
    },

    async toggleChild(parentId, childId) {
        const child = await this.api(`/items/${parentId}/children/${childId}/toggle`, {
            method: 'PUT',
        });

        // Trigger confetti if child was completed
        if (child && child.completed) {
            this.fireConfetti();
        }

        // Refresh from backend to get authoritative state
        await this.refreshList();
        this.renderModalChildren();
    },

    async deleteChild(parentId, childId) {
        await this.api(`/items/${parentId}/children/${childId}`, {
            method: 'DELETE',
        });

        // Refresh from backend to get authoritative state
        await this.refreshList();
        this.renderModalChildren();
    },

    handleAddChild(e) {
        if (e.key === 'Enter' && this.state.currentItem) {
            const input = e.target;
            const title = input.value.trim();
            if (title) {
                this.addChild(this.state.currentItem.id, title);
                input.value = '';
            }
        }
    },

    // ========================================
    // Input Handlers
    // ========================================

    async handleGlobalInput(e) {
        if (e.key === 'Enter') {
            const input = e.target;
            const title = input.value.trim();
            if (title) {
                // Add to first section
                const firstSection = this.state.list.sections[0];
                if (firstSection) {
                    input.value = '';
                    await this.createItem(firstSection.id, title);
                    // Re-focus the global input after list refresh
                    document.getElementById('global-input')?.focus();
                }
            }
        }
    },

    async handleSectionInput(e, sectionId) {
        if (e.key === 'Enter') {
            const input = e.target;
            const title = input.value.trim();
            if (title) {
                input.value = '';
                await this.createItem(sectionId, title);
                // Re-focus the input after list refresh (DOM was replaced)
                const newInput = document.querySelector(`.section-input[data-section-id="${sectionId}"]`);
                newInput?.focus();
            }
        }
    },

    // ========================================
    // Modal Operations
    // ========================================

    openModal(modalId) {
        document.getElementById(modalId)?.classList.add('show');
    },

    closeModal(modalId) {
        document.getElementById(modalId)?.classList.remove('show');

        if (modalId === 'item-modal') {
            this.state.currentItem = null;
        }
    },

    openItemModal(id) {
        const item = this.findItemById(id);
        if (!item) return;

        this.state.currentItem = item;

        // Populate modal
        document.getElementById('modal-title').value = item.title;
        document.getElementById('modal-description').value = item.description || '';
        document.getElementById('modal-due-date').value = item.dueDate || '';
        document.getElementById('modal-priority').value = item.priority || '';

        // Format dates
        const createdDate = new Date(item.createdAt).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
        const updatedDate = new Date(item.updatedAt).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });

        document.getElementById('modal-created').textContent = `Created: ${createdDate}`;
        document.getElementById('modal-updated').textContent = `Updated: ${updatedDate}`;

        this.renderModalChildren();
        this.openModal('item-modal');
    },

    renderModalChildren() {
        const container = document.getElementById('modal-children-list');
        if (!container || !this.state.currentItem) return;

        // Get fresh item data from state (in case of refresh)
        const freshItem = this.findItemById(this.state.currentItem.id);
        if (freshItem) {
            this.state.currentItem = freshItem;
        }

        const children = this.state.currentItem.children || [];
        container.innerHTML = children.map(child => `
            <div class="child-row ${child.completed ? 'completed' : ''}" data-child-id="${child.id}">
                <input type="checkbox" class="child-checkbox" ${child.completed ? 'checked' : ''}>
                <span class="child-title">${this.escapeHtml(child.title)}</span>
                <button type="button" class="child-delete" title="Delete">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
        `).join('');

        // Bind events
        container.querySelectorAll('.child-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const row = e.target.closest('.child-row');
                const childId = row.dataset.childId;
                this.toggleChild(this.state.currentItem.id, childId);
            });
        });

        container.querySelectorAll('.child-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const row = e.target.closest('.child-row');
                const childId = row.dataset.childId;
                this.deleteChild(this.state.currentItem.id, childId);
            });
        });
    },

    async saveCurrentItem() {
        if (!this.state.currentItem) return;

        const updates = {
            title: document.getElementById('modal-title').value.trim(),
            description: document.getElementById('modal-description').value.trim(),
            dueDate: document.getElementById('modal-due-date').value || null,
            priority: document.getElementById('modal-priority').value || null,
        };

        const item = await this.updateItem(this.state.currentItem.id, updates);
        if (item) {
            this.state.currentItem = item;
        }
    },

    confirmDeleteItem() {
        if (!this.state.currentItem) return;
        document.getElementById('confirm-message').textContent = 'Are you sure you want to delete this item?';
        this.openModal('confirm-modal');
    },

    executeDelete() {
        if (this.state.currentItem) {
            this.deleteItem(this.state.currentItem.id);
        }
    },

    // ========================================
    // Rendering
    // ========================================

    renderList() {
        const container = document.getElementById('sections-container');
        if (!container) return;

        const settings = this.state.list.settings || {};
        // Sort sections by position
        const sections = [...(this.state.list.sections || [])].sort((a, b) => a.position - b.position);

        // Update settings UI
        const hideCompletedCheckbox = document.getElementById('setting-hide-completed');
        if (hideCompletedCheckbox) {
            hideCompletedCheckbox.checked = settings.hideCompleted || false;
        }

        // Check if single untitled section
        const isSingleUntitled = sections.length === 1 && !sections[0].title;

        // Hide global input when there's only one section (section input suffices)
        const globalInputContainer = document.querySelector('.global-input-container');
        if (globalInputContainer) {
            globalInputContainer.style.display = sections.length <= 1 ? 'none' : 'block';
        }

        container.innerHTML = sections.map(section =>
            this.renderSection(section, isSingleUntitled, settings.hideCompleted)
        ).join('');

        this.bindSectionEvents();
    },

    renderSection(section, isSingleUntitled, hideCompleted) {
        const items = section.items || [];
        const sortedItems = this.sortItems(items, hideCompleted);
        const visibleItems = hideCompleted ? sortedItems.filter(i => !i.completed) : sortedItems;
        const canReorder = this.state.list.sections.length > 1;

        const sectionClass = `section ${section.collapsed ? 'collapsed' : ''} ${isSingleUntitled ? 'single-untitled' : ''}`;

        return `
            <div class="${sectionClass}" data-section-id="${section.id}">
                <div class="section-header">
                    ${canReorder ? `
                        <span class="section-drag-handle" draggable="true" title="Drag to reorder">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor">
                                <circle cx="5" cy="5" r="2"></circle>
                                <circle cx="5" cy="12" r="2"></circle>
                                <circle cx="5" cy="19" r="2"></circle>
                                <circle cx="12" cy="5" r="2"></circle>
                                <circle cx="12" cy="12" r="2"></circle>
                                <circle cx="12" cy="19" r="2"></circle>
                            </svg>
                        </span>
                    ` : ''}
                    <span class="section-toggle">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="6 9 12 15 18 9"></polyline>
                        </svg>
                    </span>
                    <span class="section-title">${this.escapeHtml(section.title) || 'Tasks'}</span>
                    <div class="section-actions">
                        <button type="button" class="section-action-btn section-edit" title="Edit section">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"></path>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"></path>
                            </svg>
                        </button>
                        <button type="button" class="section-action-btn section-delete" title="Delete section">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"></polyline>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path>
                            </svg>
                        </button>
                    </div>
                </div>
                <div class="section-content">
                    <ul class="items-list">
                        ${visibleItems.map(item => this.renderItem(item, 0, hideCompleted)).join('')}
                    </ul>
                    <div class="section-input-container">
                        <input type="text" class="section-input" placeholder="Add a task..." data-section-id="${section.id}">
                    </div>
                </div>
            </div>
        `;
    },

    renderItem(item, depth = 0, hideCompleted = false) {
        const hasChildren = item.children && item.children.length > 0;
        const isExpanded = this.state.expandedItems.has(item.id);
        const itemClass = `item ${item.completed ? 'completed' : ''} depth-${depth}`;

        let meta = [];
        if (item.dueDate) {
            const dueDate = new Date(item.dueDate);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const isOverdue = !item.completed && dueDate < today;

            meta.push(`
                <span class="item-due-date ${isOverdue ? 'overdue' : ''}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                        <line x1="16" y1="2" x2="16" y2="6"></line>
                        <line x1="8" y1="2" x2="8" y2="6"></line>
                        <line x1="3" y1="10" x2="21" y2="10"></line>
                    </svg>
                    ${dueDate.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })}
                </span>
            `);
        }
        if (item.priority) {
            meta.push(`<span class="item-priority ${item.priority}" title="${item.priority} priority"></span>`);
        }
        if (item.description) {
            meta.push(`
                <span class="item-has-description" title="Has description">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <line x1="17" y1="10" x2="3" y2="10"></line>
                        <line x1="21" y1="6" x2="3" y2="6"></line>
                        <line x1="21" y1="14" x2="3" y2="14"></line>
                        <line x1="17" y1="18" x2="3" y2="18"></line>
                    </svg>
                </span>
            `);
        }
        if (hasChildren) {
            const childCount = item.children.length;
            meta.push(`
                <span class="item-subtask-count" title="${childCount} subtask${childCount !== 1 ? 's' : ''}">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M9 18l6-6-6-6"></path>
                    </svg>
                    ${childCount}
                </span>
            `);
        }

        // Show children container when expanded (with inline input for adding subtasks)
        let childrenHtml = '';
        if (isExpanded || hasChildren) {
            const visibleChildren = hasChildren
                ? (hideCompleted ? item.children.filter(c => !c.completed) : item.children)
                : [];

            childrenHtml = `
                <div class="item-children ${isExpanded ? '' : 'hidden'}" data-parent-id="${item.id}">
                    ${visibleChildren.map(child => this.renderChildItem(child, item.id)).join('')}
                    <div class="inline-subtask-container">
                        <input type="text"
                               class="inline-subtask-input"
                               placeholder="Add subtask..."
                               data-parent-id="${item.id}">
                    </div>
                </div>
            `;
        }

        return `
            <li class="${itemClass}" data-item-id="${item.id}" draggable="true">
                <label class="item-checkbox">
                    <input type="checkbox" ${item.completed ? 'checked' : ''}>
                    <span class="checkmark"></span>
                </label>
                <div class="item-content">
                    <span class="item-title" data-item-id="${item.id}">${this.escapeHtml(item.title)}</span>
                    ${meta.length ? `<div class="item-meta">${meta.join('')}</div>` : ''}
                </div>
                <button type="button" class="item-details-btn" title="Edit details">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="1"></circle>
                        <circle cx="19" cy="12" r="1"></circle>
                        <circle cx="5" cy="12" r="1"></circle>
                    </svg>
                </button>
                <button type="button" class="item-expand ${isExpanded ? '' : 'collapsed'}" title="${hasChildren ? 'Toggle subtasks' : 'Add subtasks'}">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="6 9 12 15 18 9"></polyline>
                    </svg>
                </button>
            </li>
            ${childrenHtml}
        `;
    },

    renderChildItem(child, parentId) {
        const childClass = `item child-item ${child.completed ? 'completed' : ''} depth-1`;

        return `
            <li class="${childClass}" data-child-id="${child.id}" data-parent-id="${parentId}">
                <label class="item-checkbox">
                    <input type="checkbox" ${child.completed ? 'checked' : ''}>
                    <span class="checkmark"></span>
                </label>
                <div class="item-content">
                    <span class="item-title">${this.escapeHtml(child.title)}</span>
                </div>
            </li>
        `;
    },

    sortItems(items, moveCompletedToBottom = false) {
        return [...items].sort((a, b) => {
            if (moveCompletedToBottom) {
                if (a.completed !== b.completed) {
                    return a.completed ? 1 : -1;
                }
            }
            return a.position - b.position;
        });
    },

    bindSectionEvents() {
        // Section header clicks
        document.querySelectorAll('.section-header').forEach(header => {
            header.addEventListener('click', (e) => {
                if (e.target.closest('.section-actions')) return;
                const sectionId = header.closest('.section').dataset.sectionId;
                this.toggleSectionCollapse(sectionId);
            });
        });

        // Section edit buttons
        document.querySelectorAll('.section-edit').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const sectionId = btn.closest('.section').dataset.sectionId;
                this.showEditSectionModal(sectionId);
            });
        });

        // Section delete buttons
        document.querySelectorAll('.section-delete').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const sectionId = btn.closest('.section').dataset.sectionId;
                if (confirm('Delete this section and all its items?')) {
                    this.deleteSection(sectionId);
                }
            });
        });

        // Section inputs
        document.querySelectorAll('.section-input').forEach(input => {
            input.addEventListener('keydown', (e) => {
                const sectionId = input.dataset.sectionId;
                this.handleSectionInput(e, sectionId);
            });
        });

        // Item checkboxes
        document.querySelectorAll('.item[data-item-id] .item-checkbox input').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const itemId = e.target.closest('.item').dataset.itemId;
                this.toggleItem(itemId);
            });
        });

        // Child checkboxes
        document.querySelectorAll('.child-item[data-child-id] .item-checkbox input').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation();
                const childEl = e.target.closest('.child-item');
                const childId = childEl.dataset.childId;
                const parentId = childEl.dataset.parentId;
                this.toggleChild(parentId, childId);
            });
        });

        // Item title clicks - inline editing
        document.querySelectorAll('.item[data-item-id] .item-title').forEach(title => {
            title.addEventListener('click', (e) => {
                e.stopPropagation();
                const itemId = title.dataset.itemId;
                this.startInlineEdit(itemId, title);
            });
        });

        // Item details button clicks - open modal
        document.querySelectorAll('.item[data-item-id] .item-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const itemId = btn.closest('.item').dataset.itemId;
                this.openItemModal(itemId);
            });
        });

        // Item meta clicks - open modal
        document.querySelectorAll('.item[data-item-id] .item-meta').forEach(meta => {
            meta.addEventListener('click', (e) => {
                e.stopPropagation();
                const itemId = e.target.closest('.item').dataset.itemId;
                this.openItemModal(itemId);
            });
        });

        // Item expand buttons
        document.querySelectorAll('.item-expand').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.stopPropagation();
                const itemId = btn.closest('.item').dataset.itemId;
                this.toggleItemExpand(itemId);
            });
        });

        // Inline subtask inputs
        document.querySelectorAll('.inline-subtask-input').forEach(input => {
            input.addEventListener('keydown', async (e) => {
                if (e.key === 'Enter') {
                    const parentId = input.dataset.parentId;
                    const title = input.value.trim();
                    if (title && parentId) {
                        input.value = '';
                        await this.addChild(parentId, title);
                        // Re-focus the inline input after list refresh
                        const newInput = document.querySelector(`.inline-subtask-input[data-parent-id="${parentId}"]`);
                        newInput?.focus();
                    }
                }
            });
        });

        // Section drag handles for reordering
        document.querySelectorAll('.section-drag-handle').forEach(handle => {
            const section = handle.closest('.section');

            handle.addEventListener('dragstart', (e) => {
                e.stopPropagation();
                this.state.draggedSection = section.dataset.sectionId;
                section.classList.add('section-dragging');
                e.dataTransfer.effectAllowed = 'move';
            });

            handle.addEventListener('dragend', () => {
                section.classList.remove('section-dragging');
                this.state.draggedSection = null;
                document.querySelectorAll('.section-drop-target, .section-drag-over').forEach(el => {
                    el.classList.remove('section-drop-target', 'section-drag-over');
                });
            });
        });

        // Section drop targets for section reordering
        document.querySelectorAll('.section').forEach(section => {
            section.addEventListener('dragover', (e) => {
                if (!this.state.draggedSection) return;
                if (this.state.draggedSection === section.dataset.sectionId) return;

                e.preventDefault();
                section.classList.add('section-drop-target');
            });

            section.addEventListener('dragleave', (e) => {
                if (!section.contains(e.relatedTarget)) {
                    section.classList.remove('section-drop-target');
                }
            });

            section.addEventListener('drop', (e) => {
                if (!this.state.draggedSection) return;
                if (this.state.draggedSection === section.dataset.sectionId) return;

                e.preventDefault();
                section.classList.remove('section-drop-target');

                const targetSection = this.state.list.sections.find(s => s.id === section.dataset.sectionId);
                if (targetSection) {
                    this.reorderSection(this.state.draggedSection, targetSection.position);
                }
            });
        });

        // Item drag and drop (within same section only)
        this.bindDragEvents();
    },

    bindDragEvents() {
        // Item drag events for reordering within a section
        document.querySelectorAll('.item[data-item-id]').forEach(item => {
            item.addEventListener('dragstart', (e) => {
                this.state.draggedItem = item.dataset.itemId;
                item.classList.add('dragging');
            });

            item.addEventListener('dragend', () => {
                item.classList.remove('dragging');
                this.state.draggedItem = null;
                document.querySelectorAll('.drag-over').forEach(el => {
                    el.classList.remove('drag-over');
                });
            });

            item.addEventListener('dragover', (e) => {
                e.preventDefault();
                if (this.state.draggedItem && this.state.draggedItem !== item.dataset.itemId) {
                    // Only allow drop if in same section
                    const draggedSection = this.findSectionByItemId(this.state.draggedItem);
                    const targetSection = this.findSectionByItemId(item.dataset.itemId);
                    if (draggedSection && targetSection && draggedSection.id === targetSection.id) {
                        item.classList.add('drag-over');
                    }
                }
            });

            item.addEventListener('dragleave', () => {
                item.classList.remove('drag-over');
            });

            item.addEventListener('drop', (e) => {
                e.preventDefault();
                item.classList.remove('drag-over');

                if (this.state.draggedItem && this.state.draggedItem !== item.dataset.itemId) {
                    const draggedSection = this.findSectionByItemId(this.state.draggedItem);
                    const targetItem = this.findItemById(item.dataset.itemId);
                    const targetSection = this.findSectionByItemId(item.dataset.itemId);

                    // Only move if in same section
                    if (draggedSection && targetSection && draggedSection.id === targetSection.id && targetItem) {
                        this.moveItem(this.state.draggedItem, targetSection.id, targetItem.position);
                    }
                }
            });
        });
    },

    findSectionByItemId(itemId) {
        for (const section of this.state.list.sections) {
            if (section.items.some(i => i.id === itemId)) {
                return section;
            }
        }
        return null;
    },

    // ========================================
    // Utilities
    // ========================================

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    },

    fireConfetti() {
        // Rich color palette with some shimmery options
        const colors = [
            '#ff6b6b', '#ee5a5a', // reds
            '#4ecdc4', '#45b7aa', // teals
            '#ffe66d', '#ffd93d', // yellows
            '#95e1d3', '#a8e6cf', // mints
            '#f38181', '#ff9a8b', // corals
            '#aa96da', '#c9b1ff', // purples
            '#fcbad3', '#ffc4d6', // pinks
            '#74b9ff', '#a29bfe', // blues
            '#ffeaa7', '#fdcb6e', // golds
        ];
        const particleCount = 60;

        // Fire from bottom-left
        this.createConfettiBurst(0, window.innerHeight, 55, colors, particleCount);
        // Fire from bottom-right
        this.createConfettiBurst(window.innerWidth, window.innerHeight, 125, colors, particleCount);
    },

    createConfettiBurst(startX, startY, angle, colors, count) {
        const container = document.createElement('div');
        container.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;pointer-events:none;z-index:9999;overflow:hidden;';
        document.body.appendChild(container);

        for (let i = 0; i < count; i++) {
            // Stagger particle creation for more natural burst
            setTimeout(() => {
                this.createConfettiParticle(container, startX, startY, angle, colors);
            }, i * 8);
        }

        // Cleanup container after animation
        setTimeout(() => {
            if (container.parentNode) container.remove();
        }, 5000);
    },

    createConfettiParticle(container, startX, startY, angle, colors) {
        const particle = document.createElement('div');
        const color = colors[Math.floor(Math.random() * colors.length)];

        // Different particle types: ribbon (60%), square (25%), circle (15%)
        const typeRand = Math.random();
        let width, height, borderRadius, isRibbon;

        if (typeRand < 0.6) {
            // Ribbon/streamer - tall and thin
            width = Math.random() * 4 + 3;
            height = Math.random() * 12 + 10;
            borderRadius = '2px';
            isRibbon = true;
        } else if (typeRand < 0.85) {
            // Square
            const size = Math.random() * 8 + 5;
            width = size;
            height = size;
            borderRadius = '2px';
            isRibbon = false;
        } else {
            // Circle
            const size = Math.random() * 8 + 4;
            width = size;
            height = size;
            borderRadius = '50%';
            isRibbon = false;
        }

        const spread = 55;
        const particleAngle = (angle - spread / 2 + Math.random() * spread) * (Math.PI / 180);
        const velocity = Math.random() * 900 + 800;
        let velX = Math.cos(particleAngle) * velocity;
        let velY = -Math.sin(particleAngle) * velocity;

        // Initial state
        let x = startX + (Math.random() - 0.5) * 20;
        let y = startY;
        let rotZ = Math.random() * 360;
        let rotY = Math.random() * 360; // For 3D tumble effect
        const rotZSpeed = (Math.random() - 0.5) * 600;
        const rotYSpeed = (Math.random() - 0.5) * 800;

        // Physics properties vary by particle type
        const gravity = 350 + Math.random() * 150;
        const friction = 0.985 + Math.random() * 0.01;
        const drift = (Math.random() - 0.5) * 60; // Horizontal wind drift

        // Wobble for ribbons (flutter effect)
        const wobbleSpeed = Math.random() * 8 + 4;
        const wobbleAmount = isRibbon ? (Math.random() * 40 + 20) : 0;
        let wobblePhase = Math.random() * Math.PI * 2;

        particle.style.cssText = `
            position: absolute;
            left: ${x}px;
            top: ${y}px;
            width: ${width}px;
            height: ${height}px;
            background: ${color};
            border-radius: ${borderRadius};
            transform-style: preserve-3d;
            backface-visibility: visible;
        `;

        container.appendChild(particle);

        const startTime = performance.now();
        let lastTime = startTime;

        const animate = (time) => {
            const elapsed = (time - startTime) / 1000;
            const dt = Math.min((time - lastTime) / 1000, 0.05); // Cap delta time
            lastTime = time;

            if (elapsed > 4 || y > window.innerHeight + 100) {
                particle.remove();
                return;
            }

            // Apply physics
            velY += gravity * dt;
            velX += drift * dt;
            velX *= Math.pow(friction, dt * 60);
            velY *= Math.pow(friction, dt * 60);

            x += velX * dt;
            y += velY * dt;
            rotZ += rotZSpeed * dt;
            rotY += rotYSpeed * dt;
            wobblePhase += wobbleSpeed * dt;

            // Calculate wobble for ribbons (simulates air resistance flutter)
            const wobble = Math.sin(wobblePhase) * wobbleAmount;

            // Opacity fade
            const opacity = elapsed < 2.5 ? 1 : Math.max(0, 1 - (elapsed - 2.5) / 1.5);

            // 3D transform with wobble
            const scaleX = isRibbon ? Math.cos(rotY * Math.PI / 180) : 1;

            particle.style.left = x + 'px';
            particle.style.top = y + 'px';
            particle.style.transform = `rotateZ(${rotZ + wobble}deg) scaleX(${Math.abs(scaleX) * 0.3 + 0.7})`;
            particle.style.opacity = opacity;

            requestAnimationFrame(animate);
        };

        requestAnimationFrame(animate);
    },
};

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => TodoApp.init());
