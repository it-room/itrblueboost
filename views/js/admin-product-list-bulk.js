/**
 * ITRBlueBoost - Bulk FAQ Generation on Product List
 */
(function() {
    'use strict';

    var bulkActionAdded = false;
    var modalCreated = false;
    var bulkHasInsufficientCredits = false;

    function init() {
        if (typeof itrblueboostBulkFaqLabel === 'undefined') {
            return;
        }

        // Wait for the bulk actions dropdown to be available
        waitForElement('.bulk-catalog, .js-bulk-actions-btn, .bulk-actions-btn, [data-bulk-actions]', function() {
            addBulkAction();
        });

        // Also try on DOMContentLoaded
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(addBulkAction, 500);
            });
        } else {
            setTimeout(addBulkAction, 500);
        }

        // Create the modal
        createModal();
    }

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

    function addBulkAction() {
        if (bulkActionAdded) {
            return;
        }

        // Multiple selectors for different PrestaShop versions
        var selectors = [
            // PS 1.7.x product catalog bulk actions
            '.bulk-catalog > .dropdown-menu',
            '#product_bulk_menu + .dropdown-menu',
            // PS 8.1+ product list v2
            '#product_catalog_list .js-bulk-actions-btn + .dropdown-menu',
            // PS 8.0 product list
            '.js-bulk-actions-btn + .dropdown-menu',
            '.bulk-actions-btn + .dropdown-menu',
            // Generic bulk actions
            '.dropdown-menu[aria-labelledby*="bulk"]',
            // Card header dropdowns
            '.card-header .btn-group .dropdown-menu',
            // Vue.js based (PS 8.1+)
            '.products-list-table-actions .dropdown-menu'
        ];

        var dropdownMenu = null;

        for (var i = 0; i < selectors.length; i++) {
            dropdownMenu = document.querySelector(selectors[i]);
            if (dropdownMenu) {
                break;
            }
        }

        if (!dropdownMenu) {
            return;
        }

        // Check if already added
        if (dropdownMenu.querySelector('.itrblueboost-bulk-faq')) {
            bulkActionAdded = true;
            return;
        }

        // Create divider
        var divider = document.createElement('div');
        divider.className = 'dropdown-divider';
        dropdownMenu.appendChild(divider);

        // Create the bulk action item
        var bulkItem = document.createElement('button');
        bulkItem.type = 'button';
        bulkItem.className = 'dropdown-item itrblueboost-bulk-faq';
        bulkItem.innerHTML = '<i class="material-icons">auto_awesome</i> ' + itrblueboostBulkFaqLabel;
        bulkItem.addEventListener('click', handleBulkFaqClick);

        dropdownMenu.appendChild(bulkItem);
        bulkActionAdded = true;

        console.log('ITRBlueBoost: Bulk FAQ action added to product list');
    }

    function createModal() {
        if (modalCreated) {
            return;
        }

        var modalHtml = `
        <div class="modal fade" id="itrblueboostBulkFaqModal" tabindex="-1" role="dialog" aria-hidden="true">
            <div class="modal-dialog modal-lg" role="document">
                <div class="modal-content">
                    <div class="modal-header" style="background: linear-gradient(135deg, #25b9d7 0%, #1e9bb5 100%); color: #fff;">
                        <h5 class="modal-title">
                            <i class="material-icons" style="vertical-align: middle;">auto_awesome</i>
                            Generate FAQ (AI) - Bulk
                        </h5>
                        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff;">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        <div id="itrblueboost-bulk-loading" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="sr-only">Loading...</span>
                            </div>
                            <p class="mt-2">Loading prompts...</p>
                        </div>
                        <div id="itrblueboost-bulk-error" class="alert alert-danger d-none"></div>
                        <div id="itrblueboost-bulk-credits-warning" class="alert alert-warning d-none">
                            <i class="material-icons" style="vertical-align: middle;">warning</i>
                            Insufficient credits. Please recharge your credits to use AI generation.
                        </div>
                        <div id="itrblueboost-bulk-form" class="d-none">
                            <div class="alert alert-info">
                                <i class="material-icons" style="vertical-align: middle;">info</i>
                                <strong id="itrblueboost-bulk-count">0</strong> product(s) selected
                            </div>
                            <div class="form-group">
                                <label for="itrblueboost-bulk-prompt">Select a prompt:</label>
                                <select class="form-control" id="itrblueboost-bulk-prompt">
                                    <option value="">-- Choose a prompt --</option>
                                </select>
                                <small class="form-text text-muted" id="itrblueboost-bulk-prompt-desc"></small>
                            </div>
                        </div>
                        <div id="itrblueboost-bulk-progress" class="d-none">
                            <div class="text-center py-3">
                                <div class="spinner-border text-success" role="status">
                                    <span class="sr-only">Generating...</span>
                                </div>
                                <p class="mt-2" id="itrblueboost-bulk-progress-text">Generating FAQs... Please wait.</p>
                            </div>
                            <div class="progress" style="height: 25px;">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar"
                                     style="width: 0%;" id="itrblueboost-bulk-progress-bar">0%</div>
                            </div>
                        </div>
                        <div id="itrblueboost-bulk-result" class="d-none"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                        <button type="button" class="btn btn-success" id="itrblueboost-bulk-generate-btn" disabled>
                            <i class="material-icons" style="vertical-align: middle;">auto_awesome</i>
                            Generate
                        </button>
                    </div>
                </div>
            </div>
        </div>`;

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modalCreated = true;

        // Bind events
        var promptSelect = document.getElementById('itrblueboost-bulk-prompt');
        var generateBtn = document.getElementById('itrblueboost-bulk-generate-btn');
        var promptDesc = document.getElementById('itrblueboost-bulk-prompt-desc');

        promptSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            promptDesc.textContent = selectedOption.dataset.description || '';
            generateBtn.disabled = !this.value || bulkHasInsufficientCredits;
        });

        generateBtn.addEventListener('click', handleGenerate);
    }

    function handleBulkFaqClick(e) {
        e.preventDefault();
        e.stopPropagation();

        var selectedIds = getSelectedProductIds();

        if (selectedIds.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        // Ensure modal is created
        if (!modalCreated) {
            createModal();
        }

        // Store selected IDs
        window.itrblueboostSelectedProductIds = selectedIds;

        // Update count in modal
        var countEl = document.getElementById('itrblueboost-bulk-count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        // Reset modal state
        var loadingEl = document.getElementById('itrblueboost-bulk-loading');
        var errorEl = document.getElementById('itrblueboost-bulk-error');
        var formEl = document.getElementById('itrblueboost-bulk-form');
        var progressEl = document.getElementById('itrblueboost-bulk-progress');
        var resultEl = document.getElementById('itrblueboost-bulk-result');
        var generateBtn = document.getElementById('itrblueboost-bulk-generate-btn');
        var promptSelect = document.getElementById('itrblueboost-bulk-prompt');

        var creditsWarningEl = document.getElementById('itrblueboost-bulk-credits-warning');

        if (loadingEl) loadingEl.classList.remove('d-none');
        if (errorEl) errorEl.classList.add('d-none');
        if (formEl) formEl.classList.add('d-none');
        if (progressEl) progressEl.classList.add('d-none');
        if (resultEl) resultEl.classList.add('d-none');
        if (creditsWarningEl) creditsWarningEl.classList.add('d-none');
        bulkHasInsufficientCredits = false;
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.classList.remove('d-none');
        }
        if (promptSelect) promptSelect.value = '';

        // Show modal
        $('#itrblueboostBulkFaqModal').modal('show');

        // Load prompts
        loadPrompts();
    }

    function getSelectedProductIds() {
        var ids = [];

        // PrestaShop uses different checkbox selectors depending on version
        var checkboxSelectors = [
            // PS 1.7.x product catalog
            'input[name="bulk_action_selected_products[]"]:checked',
            '#product_catalog_list input[type="checkbox"]:checked:not([id*="select_all"])',
            'input[name="product_catalog_id[]"]:checked',
            // Bulk checkboxes
            'input[name="products_bulk[]"]:checked',
            'input[name="product_bulk[]"]:checked',
            'input.js-bulk-action-checkbox:checked',
            // Generic product checkboxes
            'table input[type="checkbox"][value]:checked:not([name*="select_all"]):not([id*="select_all"])',
            // Vue-based grid (PS 8.1+)
            '.product-row input[type="checkbox"]:checked',
            'tr[class*="product"] input[type="checkbox"]:checked'
        ];

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

        // If no checkboxes found, try to get from data attributes on rows
        if (ids.length === 0) {
            var rows = document.querySelectorAll('tr.selected, tr[data-selected="true"], tr.active');
            rows.forEach(function(row) {
                var id = row.dataset.productId || row.dataset.id || row.getAttribute('data-product-id');
                if (id) {
                    var idInt = parseInt(id, 10);
                    if (idInt > 0 && ids.indexOf(idInt) === -1) {
                        ids.push(idInt);
                    }
                }
            });
        }

        // Last resort: try to find IDs in the first cell of selected rows
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

        console.log('ITRBlueBoost: Selected product IDs:', ids);
        return ids;
    }

    function loadPrompts() {
        fetch(itrblueboostBulkFaqPromptsUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            document.getElementById('itrblueboost-bulk-loading').classList.add('d-none');

            if (!data.success) {
                document.getElementById('itrblueboost-bulk-error').textContent = data.message || 'Error loading prompts.';
                document.getElementById('itrblueboost-bulk-error').classList.remove('d-none');
                return;
            }

            var prompts = data.prompts || [];
            if (prompts.length === 0) {
                document.getElementById('itrblueboost-bulk-error').textContent = 'No prompts available.';
                document.getElementById('itrblueboost-bulk-error').classList.remove('d-none');
                return;
            }

            var select = document.getElementById('itrblueboost-bulk-prompt');
            select.innerHTML = '<option value="">-- Choose a prompt --</option>';

            prompts.forEach(function(prompt) {
                var option = document.createElement('option');
                option.value = prompt.id;
                option.textContent = prompt.title;
                option.dataset.description = prompt.description || '';
                select.appendChild(option);
            });

            document.getElementById('itrblueboost-bulk-form').classList.remove('d-none');

            if (typeof data.credits_remaining === 'number' && data.credits_remaining <= 0) {
                var creditsWarn = document.getElementById('itrblueboost-bulk-credits-warning');
                if (creditsWarn) creditsWarn.classList.remove('d-none');
                bulkHasInsufficientCredits = true;
                var genBtn = document.getElementById('itrblueboost-bulk-generate-btn');
                if (genBtn) genBtn.disabled = true;
            }
        })
        .catch(function() {
            document.getElementById('itrblueboost-bulk-loading').classList.add('d-none');
            document.getElementById('itrblueboost-bulk-error').textContent = 'Connection error.';
            document.getElementById('itrblueboost-bulk-error').classList.remove('d-none');
        });
    }

    function handleGenerate() {
        var promptId = document.getElementById('itrblueboost-bulk-prompt').value;
        var productIds = window.itrblueboostSelectedProductIds || [];

        if (!promptId || productIds.length === 0) {
            return;
        }

        // Show progress
        document.getElementById('itrblueboost-bulk-form').classList.add('d-none');
        document.getElementById('itrblueboost-bulk-generate-btn').classList.add('d-none');
        document.getElementById('itrblueboost-bulk-progress').classList.remove('d-none');

        var progressBar = document.getElementById('itrblueboost-bulk-progress-bar');
        progressBar.style.width = '50%';
        progressBar.textContent = 'Processing...';

        var formData = new FormData();
        formData.append('prompt_id', promptId);
        formData.append('product_ids', productIds.join(','));

        fetch(itrblueboostBulkFaqGenerateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';

            document.getElementById('itrblueboost-bulk-progress').classList.add('d-none');

            var resultDiv = document.getElementById('itrblueboost-bulk-result');

            if (data.success) {
                var html = '<div class="alert alert-success">' +
                    '<i class="material-icons" style="vertical-align: middle;">check_circle</i> ' +
                    data.message + '<br>' +
                    '<small>Credits used: ' + data.credits_used + ' | Credits remaining: ' + data.credits_remaining + '</small>' +
                    '</div>';

                if (data.processed_items && data.processed_items.length > 0) {
                    html += '<div class="list-group mb-3">';
                    data.processed_items.forEach(function(item) {
                        html += '<a href="' + item.faq_url + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">' +
                            '<span><i class="material-icons" style="vertical-align: middle; font-size: 18px;">quiz</i> ' +
                            item.name + '</span>' +
                            '<span class="badge badge-primary badge-pill">' + item.faq_count + ' FAQ</span>' +
                            '</a>';
                    });
                    html += '</div>';
                }

                if (data.errors && data.errors.length > 0) {
                    html += '<div class="alert alert-warning"><strong>Errors:</strong><ul class="mb-0">';
                    data.errors.forEach(function(err) {
                        html += '<li>' + err + '</li>';
                    });
                    html += '</ul></div>';
                }

                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="material-icons" style="vertical-align: middle;">error</i> ' +
                    (data.message || 'Error generating FAQs.') +
                    '</div>';
            }

            resultDiv.classList.remove('d-none');
        })
        .catch(function() {
            document.getElementById('itrblueboost-bulk-progress').classList.add('d-none');
            document.getElementById('itrblueboost-bulk-result').innerHTML =
                '<div class="alert alert-danger">Connection error.</div>';
            document.getElementById('itrblueboost-bulk-result').classList.remove('d-none');
        });
    }

    // Initialize
    init();

    // Re-init on page changes (for SPA-like navigation)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function(mutations) {
            if (!bulkActionAdded) {
                addBulkAction();
            }
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
})();
