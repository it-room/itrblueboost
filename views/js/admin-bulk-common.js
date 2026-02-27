/**
 * ITRBlueBoost - Common utilities for bulk generation actions.
 *
 * Provides shared helpers: waitForElement, addBulkActionButton, getSelectedIds,
 * createBulkModal, loadPrompts, showError, escapeHtml.
 */
var ITRBulkCommon = (function() {
    'use strict';

    /**
     * Wait for a DOM element matching the selector.
     *
     * @param {string} selector CSS selector
     * @param {Function} callback Called with the found element
     * @param {number} [maxAttempts=50] Maximum polling attempts
     */
    function waitForElement(selector, callback, maxAttempts) {
        maxAttempts = maxAttempts || 50;
        var attempts = 0;

        var interval = setInterval(function() {
            var element = document.querySelector(selector);
            attempts++;

            if (element || attempts >= maxAttempts) {
                clearInterval(interval);
                if (element) {
                    callback(element);
                }
            }
        }, 100);
    }

    /**
     * Add a bulk action button to a dropdown menu.
     *
     * @param {Object} config
     * @param {string[]} config.dropdownSelectors CSS selectors for the dropdown menu
     * @param {string} config.cssClass Unique CSS class for the button (used to avoid duplicates)
     * @param {string} config.label Button label HTML
     * @param {string} config.icon Material icon name
     * @param {Function} config.onClick Click handler
     * @returns {boolean} true if added, false otherwise
     */
    function addBulkActionButton(config) {
        var dropdownMenu = null;

        for (var i = 0; i < config.dropdownSelectors.length; i++) {
            dropdownMenu = document.querySelector(config.dropdownSelectors[i]);
            if (dropdownMenu) {
                break;
            }
        }

        if (!dropdownMenu) {
            return false;
        }

        if (dropdownMenu.querySelector('.' + config.cssClass)) {
            return true;
        }

        var divider = document.createElement('div');
        divider.className = 'dropdown-divider';
        dropdownMenu.appendChild(divider);

        var bulkItem = document.createElement('button');
        bulkItem.type = 'button';
        bulkItem.className = 'dropdown-item ' + config.cssClass;
        bulkItem.innerHTML = '<i class="material-icons">' + config.icon + '</i> ' + config.label;
        bulkItem.addEventListener('click', config.onClick);

        dropdownMenu.appendChild(bulkItem);
        return true;
    }

    /**
     * Get selected entity IDs from checkboxes in a grid.
     *
     * @param {string[]} checkboxSelectors CSS selectors to try, in priority order
     * @param {string} [dataIdAttr] Data attribute name for fallback row-based detection
     * @returns {number[]} Array of selected IDs
     */
    function getSelectedIds(checkboxSelectors, dataIdAttr) {
        var ids = [];
        var checkboxes = [];

        for (var i = 0; i < checkboxSelectors.length; i++) {
            var found = document.querySelectorAll(checkboxSelectors[i]);
            if (found.length > 0) {
                checkboxes = found;
                break;
            }
        }

        checkboxes.forEach(function(cb) {
            var value = parseInt(cb.value, 10);
            if (value > 0 && ids.indexOf(value) === -1) {
                ids.push(value);
            }
        });

        // Fallback: try data attributes on selected rows
        if (ids.length === 0) {
            var rows = document.querySelectorAll('tr.selected, tr[data-selected="true"], tr.active');
            rows.forEach(function(row) {
                var id = (dataIdAttr ? row.getAttribute(dataIdAttr) : null)
                    || row.dataset.id
                    || row.getAttribute('data-product-id')
                    || row.getAttribute('data-category-id');
                if (id) {
                    var idInt = parseInt(id, 10);
                    if (idInt > 0 && ids.indexOf(idInt) === -1) {
                        ids.push(idInt);
                    }
                }
            });
        }

        // Last resort: parse first cell of checked rows
        if (ids.length === 0) {
            var selectedRows = document.querySelectorAll('tr:has(input[type="checkbox"]:checked)');
            selectedRows.forEach(function(row) {
                var firstCell = row.querySelector('td:first-child, td:nth-child(2)');
                if (firstCell) {
                    var text = firstCell.textContent.trim();
                    var id = parseInt(text, 10);
                    if (id > 0 && ids.indexOf(id) === -1) {
                        ids.push(id);
                    }
                }
            });
        }

        return ids;
    }

    /**
     * Create a bulk generation modal and append it to the body.
     *
     * @param {Object} config
     * @param {string} config.modalId Modal element ID
     * @param {string} config.prefix ID prefix for inner elements (e.g. 'itrblueboost-bulk-')
     * @param {string} config.title Modal title
     * @param {string} config.icon Material icon name
     * @param {string} config.headerGradient CSS gradient for header background
     * @param {string} config.entityLabel e.g. 'product(s)' or 'category(ies)'
     * @param {string} config.promptLabel Label for prompt select
     * @param {string} config.progressLabel Default progress text
     * @param {string} config.btnClass CSS class for generate button (e.g. 'btn-success', 'btn-primary')
     * @param {Function} config.onGenerate Generate button click handler
     * @returns {Object} References to key DOM elements {promptSelect, generateBtn, promptDesc}
     */
    function createBulkModal(config) {
        var p = config.prefix;
        var modalHtml =
        '<div class="modal fade" id="' + config.modalId + '" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog modal-lg" role="document">' +
                '<div class="modal-content">' +
                    '<div class="modal-header" style="background: ' + config.headerGradient + '; color: #fff;">' +
                        '<h5 class="modal-title">' +
                            '<i class="material-icons" style="vertical-align: middle;">' + config.icon + '</i> ' +
                            escapeHtml(config.title) +
                        '</h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff;">' +
                            '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div id="' + p + 'loading" class="text-center py-4">' +
                            '<div class="spinner-border text-primary" role="status">' +
                                '<span class="sr-only">Loading...</span>' +
                            '</div>' +
                            '<p class="mt-2">Loading prompts...</p>' +
                        '</div>' +
                        '<div id="' + p + 'error" class="alert alert-danger d-none"></div>' +
                        '<div id="' + p + 'credits-warning" class="alert alert-warning d-none">' +
                            '<i class="material-icons" style="vertical-align: middle;">warning</i> ' +
                            'Insufficient credits. Please recharge your credits to use AI generation.' +
                        '</div>' +
                        '<div id="' + p + 'form" class="d-none">' +
                            '<div class="alert alert-info">' +
                                '<i class="material-icons" style="vertical-align: middle;">info</i> ' +
                                '<strong id="' + p + 'count">0</strong> ' + escapeHtml(config.entityLabel) + ' selected' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label for="' + p + 'prompt">' + escapeHtml(config.promptLabel) + '</label>' +
                                '<select class="form-control" id="' + p + 'prompt">' +
                                    '<option value="">-- Choose a prompt --</option>' +
                                '</select>' +
                                '<small class="form-text text-muted" id="' + p + 'prompt-desc"></small>' +
                            '</div>' +
                        '</div>' +
                        '<div id="' + p + 'progress" class="d-none">' +
                            '<div class="text-center py-3">' +
                                '<div class="spinner-border text-success" role="status">' +
                                    '<span class="sr-only">Generating...</span>' +
                                '</div>' +
                                '<p class="mt-2" id="' + p + 'progress-text">' + escapeHtml(config.progressLabel) + '</p>' +
                            '</div>' +
                            '<div class="progress" style="height: 25px;">' +
                                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" ' +
                                     'style="width: 0%;" id="' + p + 'progress-bar">0%</div>' +
                            '</div>' +
                        '</div>' +
                        '<div id="' + p + 'result" class="d-none"></div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>' +
                        '<button type="button" class="btn ' + config.btnClass + '" id="' + p + 'generate-btn" disabled>' +
                            '<i class="material-icons" style="vertical-align: middle;">' + config.icon + '</i> ' +
                            'Generate' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var promptSelect = document.getElementById(p + 'prompt');
        var generateBtn = document.getElementById(p + 'generate-btn');
        var promptDesc = document.getElementById(p + 'prompt-desc');

        var hasInsufficientCredits = false;

        promptSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            promptDesc.textContent = selectedOption.dataset.description || '';
            generateBtn.disabled = !this.value || hasInsufficientCredits;
        });

        generateBtn.addEventListener('click', config.onGenerate);

        return {
            promptSelect: promptSelect,
            generateBtn: generateBtn,
            promptDesc: promptDesc,
            setInsufficientCredits: function(val) { hasInsufficientCredits = val; },
            getInsufficientCredits: function() { return hasInsufficientCredits; }
        };
    }

    /**
     * Reset a modal to its initial state.
     *
     * @param {string} prefix ID prefix
     * @param {Object} refs Modal refs from createBulkModal
     */
    function resetModal(prefix, refs) {
        var p = prefix;

        var loadingEl = document.getElementById(p + 'loading');
        var errorEl = document.getElementById(p + 'error');
        var formEl = document.getElementById(p + 'form');
        var progressEl = document.getElementById(p + 'progress');
        var resultEl = document.getElementById(p + 'result');
        var creditsWarningEl = document.getElementById(p + 'credits-warning');

        if (loadingEl) loadingEl.classList.remove('d-none');
        if (errorEl) errorEl.classList.add('d-none');
        if (formEl) formEl.classList.add('d-none');
        if (progressEl) progressEl.classList.add('d-none');
        if (resultEl) {
            resultEl.classList.add('d-none');
            resultEl.innerHTML = '';
        }
        if (creditsWarningEl) creditsWarningEl.classList.add('d-none');

        refs.setInsufficientCredits(false);

        if (refs.generateBtn) {
            refs.generateBtn.disabled = true;
            refs.generateBtn.classList.remove('d-none');
        }
        if (refs.promptSelect) refs.promptSelect.value = '';
    }

    /**
     * Load prompts via AJAX and populate the modal select.
     *
     * @param {string} url Prompts URL
     * @param {string} prefix ID prefix
     * @param {Object} refs Modal refs from createBulkModal
     */
    function loadPrompts(url, prefix, refs) {
        var p = prefix;

        fetch(url, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            document.getElementById(p + 'loading').classList.add('d-none');

            if (!data.success) {
                showError(prefix, data.message || 'Error loading prompts.');
                return;
            }

            var prompts = data.prompts || [];
            if (prompts.length === 0) {
                showError(prefix, 'No prompts available.');
                return;
            }

            var select = refs.promptSelect;
            select.innerHTML = '<option value="">-- Choose a prompt --</option>';

            prompts.forEach(function(prompt) {
                var option = document.createElement('option');
                option.value = prompt.id;
                option.textContent = prompt.title;
                option.dataset.description = prompt.description || '';
                select.appendChild(option);
            });

            document.getElementById(p + 'form').classList.remove('d-none');

            if (typeof data.credits_remaining === 'number' && data.credits_remaining <= 0) {
                var creditsWarn = document.getElementById(p + 'credits-warning');
                if (creditsWarn) creditsWarn.classList.remove('d-none');
                refs.setInsufficientCredits(true);
                if (refs.generateBtn) refs.generateBtn.disabled = true;
            }
        })
        .catch(function() {
            document.getElementById(p + 'loading').classList.add('d-none');
            showError(prefix, 'Connection error.');
        });
    }

    /**
     * Show an error in the modal.
     *
     * @param {string} prefix ID prefix
     * @param {string} message Error message
     */
    function showError(prefix, message) {
        var errorEl = document.getElementById(prefix + 'error');
        errorEl.textContent = message;
        errorEl.classList.remove('d-none');
    }

    /**
     * Update the progress bar in the modal.
     *
     * @param {string} prefix ID prefix
     * @param {number} progress Percentage (0-100)
     * @param {string} label Text label
     */
    function updateProgressBar(prefix, progress, label) {
        var bar = document.getElementById(prefix + 'progress-bar');
        var text = document.getElementById(prefix + 'progress-text');

        if (bar) {
            bar.style.width = progress + '%';
            bar.textContent = progress + '%';
        }
        if (text) {
            text.textContent = label;
        }
    }

    /**
     * Show the result section with an error message.
     *
     * @param {string} prefix ID prefix
     * @param {string} message Error message
     */
    function showGenerateError(prefix, message) {
        document.getElementById(prefix + 'progress').classList.add('d-none');

        var resultDiv = document.getElementById(prefix + 'result');
        resultDiv.innerHTML = '<div class="alert alert-danger">' +
            '<i class="material-icons" style="vertical-align: middle;">error</i> ' +
            escapeHtml(message) +
            '</div>';
        resultDiv.classList.remove('d-none');
    }

    /**
     * Build result HTML for processed items.
     *
     * @param {Object} data Response data
     * @param {string} data.message Success message
     * @param {Array} data.processed_items Array of processed items
     * @param {Array} data.errors Array of error strings
     * @param {string} itemIcon Material icon for each item
     * @param {string} itemCountKey Key in item object for the badge count
     * @param {string} itemCountLabel Label after count in badge
     * @param {string} itemUrlKey Key in item object for the link URL
     * @returns {string} HTML string
     */
    function buildResultHtml(data, itemIcon, itemCountKey, itemCountLabel, itemUrlKey) {
        var html = '<div class="alert alert-success">' +
            '<i class="material-icons" style="vertical-align: middle;">check_circle</i> ' +
            escapeHtml(data.message || 'Generation completed!');

        if (typeof data.credits_used !== 'undefined') {
            html += '<br><small>Credits used: ' + data.credits_used +
                ' | Credits remaining: ' + data.credits_remaining + '</small>';
        }

        html += '</div>';

        var processedItems = data.processed_items || [];
        if (processedItems.length > 0) {
            html += '<div class="list-group mb-3">';
            processedItems.forEach(function(item) {
                var url = item[itemUrlKey] || '#';
                html += '<a href="' + escapeHtml(url) + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">' +
                    '<span><i class="material-icons" style="vertical-align: middle; font-size: 18px;">' + itemIcon + '</i> ' +
                    escapeHtml(item.name) + '</span>' +
                    '<span class="badge badge-primary badge-pill">' + item[itemCountKey] + ' ' + itemCountLabel + '</span>' +
                    '</a>';
            });
            html += '</div>';
        }

        var errors = data.errors || [];
        if (errors.length > 0) {
            html += '<div class="alert alert-warning"><strong>Errors:</strong><ul class="mb-0">';
            errors.forEach(function(err) {
                html += '<li>' + escapeHtml(err) + '</li>';
            });
            html += '</ul></div>';
        }

        return html;
    }

    /**
     * Escape HTML entities.
     *
     * @param {string} text
     * @returns {string}
     */
    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    /**
     * Set up a MutationObserver to re-try adding the bulk action button.
     *
     * @param {Function} addFn The addBulkAction function to call
     * @param {Function} isAddedFn Returns true if already added
     */
    function observeForBulkAction(addFn, isAddedFn) {
        if (typeof MutationObserver !== 'undefined') {
            var observer = new MutationObserver(function() {
                if (!isAddedFn()) {
                    addFn();
                }
            });

            observer.observe(document.body, {
                childList: true,
                subtree: true
            });
        }
    }

    /**
     * Standard init pattern: wait for element + DOMContentLoaded fallback.
     *
     * @param {Function} addFn The addBulkAction function
     * @param {Function} createFn The createModal function
     */
    function initBulk(addFn, createFn) {
        waitForElement('.bulk-catalog, .js-bulk-actions-btn, .bulk-actions-btn, [data-bulk-actions]', function() {
            addFn();
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(addFn, 500);
            });
        } else {
            setTimeout(addFn, 500);
        }

        createFn();
    }

    // Dropdown selectors for product list pages
    var PRODUCT_DROPDOWN_SELECTORS = [
        '.bulk-catalog > .dropdown-menu',
        '#product_bulk_menu + .dropdown-menu',
        '#product_catalog_list .js-bulk-actions-btn + .dropdown-menu',
        '.js-bulk-actions-btn + .dropdown-menu',
        '.bulk-actions-btn + .dropdown-menu',
        '.dropdown-menu[aria-labelledby*="bulk"]',
        '.card-header .btn-group .dropdown-menu',
        '.products-list-table-actions .dropdown-menu'
    ];

    // Dropdown selectors for category list pages
    var CATEGORY_DROPDOWN_SELECTORS = [
        '.bulk-catalog > .dropdown-menu',
        '#category_bulk_menu + .dropdown-menu',
        '#category_grid .js-bulk-actions-btn + .dropdown-menu',
        '.js-bulk-actions-btn + .dropdown-menu',
        '.bulk-actions-btn + .dropdown-menu',
        '.dropdown-menu[aria-labelledby*="bulk"]',
        '.card-header .btn-group .dropdown-menu',
        '.categories-list-table-actions .dropdown-menu'
    ];

    // Checkbox selectors for product selection
    var PRODUCT_CHECKBOX_SELECTORS = [
        'input[name="bulk_action_selected_products[]"]:checked',
        '#product_catalog_list input[type="checkbox"]:checked:not([id*="select_all"])',
        'input[name="product_catalog_id[]"]:checked',
        'input[name="products_bulk[]"]:checked',
        'input[name="product_bulk[]"]:checked',
        'input.js-bulk-action-checkbox:checked',
        'table input[type="checkbox"][value]:checked:not([name*="select_all"]):not([id*="select_all"])',
        '.product-row input[type="checkbox"]:checked',
        'tr[class*="product"] input[type="checkbox"]:checked'
    ];

    // Checkbox selectors for category selection
    var CATEGORY_CHECKBOX_SELECTORS = [
        'input[name="bulk_action_selected_categories[]"]:checked',
        '#category_grid input[type="checkbox"]:checked:not([id*="select_all"])',
        'input[name="category_categories_bulk[]"]:checked',
        'input[name="categories_bulk[]"]:checked',
        'input[name="category_bulk[]"]:checked',
        'input.js-bulk-action-checkbox:checked',
        'table input[type="checkbox"][value]:checked:not([name*="select_all"]):not([id*="select_all"])',
        '.category-row input[type="checkbox"]:checked',
        'tr[class*="category"] input[type="checkbox"]:checked'
    ];

    return {
        waitForElement: waitForElement,
        addBulkActionButton: addBulkActionButton,
        getSelectedIds: getSelectedIds,
        createBulkModal: createBulkModal,
        resetModal: resetModal,
        loadPrompts: loadPrompts,
        showError: showError,
        updateProgressBar: updateProgressBar,
        showGenerateError: showGenerateError,
        buildResultHtml: buildResultHtml,
        escapeHtml: escapeHtml,
        observeForBulkAction: observeForBulkAction,
        initBulk: initBulk,
        PRODUCT_DROPDOWN_SELECTORS: PRODUCT_DROPDOWN_SELECTORS,
        CATEGORY_DROPDOWN_SELECTORS: CATEGORY_DROPDOWN_SELECTORS,
        PRODUCT_CHECKBOX_SELECTORS: PRODUCT_CHECKBOX_SELECTORS,
        CATEGORY_CHECKBOX_SELECTORS: CATEGORY_CHECKBOX_SELECTORS
    };
})();
