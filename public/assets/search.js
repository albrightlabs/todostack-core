/**
 * TodoStack Search
 * Provides search functionality with keyboard navigation
 */
(function() {
    'use strict';

    var SearchUI = {
        searchModal: null,
        searchInput: null,
        searchResults: null,
        searchTrigger: null,
        selectedIndex: -1,
        results: [],
        debounceTimer: null,

        init: function() {
            this.createSearchUI();
            this.bindEvents();
        },

        createSearchUI: function() {
            // Create search modal
            var modal = document.createElement('div');
            modal.className = 'search-modal';
            modal.innerHTML =
                '<div class="search-modal-backdrop"></div>' +
                '<div class="search-modal-content">' +
                    '<div class="search-input-wrapper">' +
                        '<svg class="search-icon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                            '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />' +
                        '</svg>' +
                        '<input type="text" class="search-input" placeholder="Search tasks..." autocomplete="off" />' +
                        '<kbd class="search-shortcut">ESC</kbd>' +
                    '</div>' +
                    '<div class="search-results"></div>' +
                    '<div class="search-footer">' +
                        '<span class="search-hint"><kbd>&uarr;</kbd><kbd>&darr;</kbd> Navigate</span>' +
                        '<span class="search-hint"><kbd>Enter</kbd> Select</span>' +
                        '<span class="search-hint"><kbd>Esc</kbd> Close</span>' +
                    '</div>' +
                '</div>';

            document.body.appendChild(modal);

            this.searchModal = modal;
            this.searchInput = modal.querySelector('.search-input');
            this.searchResults = modal.querySelector('.search-results');

            // Create search trigger button in header
            var headerRight = document.querySelector('.header-right');
            if (headerRight) {
                var searchBtn = document.createElement('button');
                searchBtn.type = 'button';
                searchBtn.className = 'btn btn-icon';
                searchBtn.title = 'Search (/)';
                searchBtn.innerHTML =
                    '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor">' +
                        '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />' +
                    '</svg>';
                headerRight.insertBefore(searchBtn, headerRight.firstChild);
                this.searchTrigger = searchBtn;
            }
        },

        bindEvents: function() {
            var self = this;

            // Search trigger button
            if (this.searchTrigger) {
                this.searchTrigger.addEventListener('click', function() {
                    self.open();
                });
            }

            // Backdrop click
            var backdrop = this.searchModal.querySelector('.search-modal-backdrop');
            backdrop.addEventListener('click', function() {
                self.close();
            });

            // Input events
            this.searchInput.addEventListener('input', function() {
                self.handleInput();
            });

            this.searchInput.addEventListener('keydown', function(e) {
                self.handleKeydown(e);
            });

            // Global keyboard shortcut
            document.addEventListener('keydown', function(e) {
                // Open with "/" key (but not in input fields)
                if (e.key === '/' && !self.isInputFocused()) {
                    e.preventDefault();
                    self.open();
                }

                // Open with Cmd/Ctrl + K
                if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
                    e.preventDefault();
                    self.open();
                }

                // Close with Escape
                if (e.key === 'Escape' && self.isOpen()) {
                    self.close();
                }
            });

            // Result clicks
            this.searchResults.addEventListener('click', function(e) {
                var result = e.target.closest('.search-result');
                if (result) {
                    var itemId = result.dataset.itemId;
                    if (itemId) {
                        self.close();
                        if (typeof TodoApp !== 'undefined' && TodoApp.openItemModal) {
                            TodoApp.openItemModal(itemId);
                        }
                    }
                }
            });

            // Mouse hover on results
            this.searchResults.addEventListener('mousemove', function(e) {
                var result = e.target.closest('.search-result');
                if (result) {
                    var index = parseInt(result.dataset.index, 10);
                    if (!isNaN(index)) {
                        self.setSelected(index);
                    }
                }
            });
        },

        open: function() {
            var self = this;
            this.searchModal.classList.add('show');
            document.body.style.overflow = 'hidden';
            // Use setTimeout to ensure focus happens after CSS transition
            setTimeout(function() {
                self.searchInput.focus();
                self.searchInput.select();
            }, 50);
        },

        close: function() {
            this.searchModal.classList.remove('show');
            this.searchInput.value = '';
            this.searchResults.innerHTML = '';
            this.results = [];
            this.selectedIndex = -1;
            document.body.style.overflow = '';
        },

        isOpen: function() {
            return this.searchModal.classList.contains('show');
        },

        isInputFocused: function() {
            var activeEl = document.activeElement;
            return activeEl && (
                activeEl.tagName === 'INPUT' ||
                activeEl.tagName === 'TEXTAREA' ||
                activeEl.isContentEditable
            );
        },

        handleInput: function() {
            var self = this;
            var query = this.searchInput.value.trim();

            clearTimeout(this.debounceTimer);

            if (query.length < 2) {
                this.searchResults.innerHTML = '<div class="search-empty">Type at least 2 characters to search</div>';
                this.results = [];
                this.selectedIndex = -1;
                return;
            }

            this.searchResults.innerHTML = '<div class="search-loading">Searching...</div>';

            this.debounceTimer = setTimeout(function() {
                self.performSearch(query);
            }, 150);
        },

        performSearch: function(query) {
            var self = this;

            fetch('/api/search?q=' + encodeURIComponent(query), {
                headers: {
                    'X-CSRF-Token': window.TODOAPP_CSRF_TOKEN || ''
                }
            })
                .then(function(response) {
                    return response.json();
                })
                .then(function(data) {
                    if (data.success) {
                        self.results = data.data;
                        self.renderResults(query);
                    } else {
                        self.searchResults.innerHTML = '<div class="search-empty">Search failed</div>';
                    }
                })
                .catch(function() {
                    self.searchResults.innerHTML = '<div class="search-empty">Search failed</div>';
                });
        },

        renderResults: function(query) {
            var self = this;

            if (this.results.length === 0) {
                this.searchResults.innerHTML = '<div class="search-empty">No results found for "' + this.escapeHtml(query) + '"</div>';
                return;
            }

            var html = this.results.map(function(result, index) {
                var highlighted = self.highlightMatch(result.title, query);
                var preview = result.preview ? self.highlightMatch(result.preview, query) : '';
                var completedClass = result.completed ? ' search-result-completed' : '';

                return '<div class="search-result' + (index === 0 ? ' selected' : '') + completedClass + '" data-item-id="' + self.escapeHtml(result.id) + '" data-index="' + index + '">' +
                    '<div class="search-result-title">' + highlighted + '</div>' +
                    (preview ? '<div class="search-result-preview">' + preview + '</div>' : '') +
                    '<div class="search-result-path">' + self.escapeHtml(result.sectionTitle) + '</div>' +
                '</div>';
            }).join('');

            this.searchResults.innerHTML = html;
            this.selectedIndex = 0;
        },

        highlightMatch: function(text, query) {
            if (!text) return '';
            var escaped = this.escapeHtml(text);
            var regex = new RegExp('(' + this.escapeRegex(query) + ')', 'gi');
            return escaped.replace(regex, '<mark>$1</mark>');
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },

        escapeRegex: function(string) {
            return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
        },

        handleKeydown: function(e) {
            switch (e.key) {
                case 'ArrowDown':
                    e.preventDefault();
                    this.moveSelection(1);
                    break;
                case 'ArrowUp':
                    e.preventDefault();
                    this.moveSelection(-1);
                    break;
                case 'Enter':
                    e.preventDefault();
                    this.selectCurrent();
                    break;
            }
        },

        moveSelection: function(delta) {
            if (this.results.length === 0) return;

            var newIndex = this.selectedIndex + delta;
            if (newIndex < 0) newIndex = this.results.length - 1;
            if (newIndex >= this.results.length) newIndex = 0;

            this.setSelected(newIndex);
        },

        setSelected: function(index) {
            if (index === this.selectedIndex) return;

            var results = this.searchResults.querySelectorAll('.search-result');
            results.forEach(function(r, i) {
                r.classList.toggle('selected', i === index);
            });

            this.selectedIndex = index;

            // Scroll into view
            var selected = results[index];
            if (selected) {
                selected.scrollIntoView({ block: 'nearest' });
            }
        },

        selectCurrent: function() {
            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                var itemId = this.results[this.selectedIndex].id;
                this.close();
                if (typeof TodoApp !== 'undefined' && TodoApp.openItemModal) {
                    TodoApp.openItemModal(itemId);
                }
            }
        }
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        SearchUI.init();
    });
})();
