/**
 * ITRBlueBoost - Bulk Image Generation on Product List
 */
(function() {
    'use strict';

    var bulkActionAdded = false;
    var modalCreated = false;
    var pollingTimer = null;

    function init() {
        if (typeof itrblueboostBulkImageLabel === 'undefined') {
            return;
        }

        waitForElement('.bulk-catalog, .js-bulk-actions-btn, .bulk-actions-btn, [data-bulk-actions]', function() {
            addBulkAction();
        });

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(addBulkAction, 500);
            });
        } else {
            setTimeout(addBulkAction, 500);
        }

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

        var selectors = [
            '.bulk-catalog > .dropdown-menu',
            '#product_bulk_menu + .dropdown-menu',
            '#product_catalog_list .js-bulk-actions-btn + .dropdown-menu',
            '.js-bulk-actions-btn + .dropdown-menu',
            '.bulk-actions-btn + .dropdown-menu',
            '.dropdown-menu[aria-labelledby*="bulk"]',
            '.card-header .btn-group .dropdown-menu',
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

        if (dropdownMenu.querySelector('.itrblueboost-bulk-image')) {
            bulkActionAdded = true;
            return;
        }

        var divider = document.createElement('div');
        divider.className = 'dropdown-divider';
        dropdownMenu.appendChild(divider);

        var bulkItem = document.createElement('button');
        bulkItem.type = 'button';
        bulkItem.className = 'dropdown-item itrblueboost-bulk-image';
        bulkItem.innerHTML = '<i class="material-icons">image</i> ' + itrblueboostBulkImageLabel;
        bulkItem.addEventListener('click', handleBulkImageClick);

        dropdownMenu.appendChild(bulkItem);
        bulkActionAdded = true;
    }

    function createModal() {
        if (modalCreated) {
            return;
        }

        var modalHtml = '' +
        '<div class="modal fade" id="itrblueboostBulkImageModal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog modal-lg" role="document">' +
                '<div class="modal-content">' +
                    '<div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff;">' +
                        '<h5 class="modal-title">' +
                            '<i class="material-icons" style="vertical-align: middle;">image</i> ' +
                            'Generate Images (AI) - Bulk' +
                        '</h5>' +
                        '<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="color: #fff;">' +
                            '<span aria-hidden="true">&times;</span>' +
                        '</button>' +
                    '</div>' +
                    '<div class="modal-body">' +
                        '<div id="itrblueboost-bulk-img-loading" class="text-center py-4">' +
                            '<div class="spinner-border text-primary" role="status">' +
                                '<span class="sr-only">Loading...</span>' +
                            '</div>' +
                            '<p class="mt-2">Loading prompts...</p>' +
                        '</div>' +
                        '<div id="itrblueboost-bulk-img-error" class="alert alert-danger d-none"></div>' +
                        '<div id="itrblueboost-bulk-img-credits-warning" class="alert alert-warning d-none">' +
                            '<i class="material-icons" style="vertical-align: middle;">warning</i> ' +
                            'Insufficient credits. Please recharge your credits to use AI generation.' +
                        '</div>' +
                        '<div id="itrblueboost-bulk-img-form" class="d-none">' +
                            '<div class="alert alert-info">' +
                                '<i class="material-icons" style="vertical-align: middle;">info</i> ' +
                                '<strong id="itrblueboost-bulk-img-count">0</strong> product(s) selected' +
                            '</div>' +
                            '<div class="form-group">' +
                                '<label for="itrblueboost-bulk-img-prompt">Select an image prompt:</label>' +
                                '<select class="form-control" id="itrblueboost-bulk-img-prompt">' +
                                    '<option value="">-- Choose a prompt --</option>' +
                                '</select>' +
                                '<small class="form-text text-muted" id="itrblueboost-bulk-img-prompt-desc"></small>' +
                            '</div>' +
                        '</div>' +
                        '<div id="itrblueboost-bulk-img-progress" class="d-none">' +
                            '<div class="text-center py-3">' +
                                '<div class="spinner-border text-success" role="status">' +
                                    '<span class="sr-only">Generating...</span>' +
                                '</div>' +
                                '<p class="mt-2" id="itrblueboost-bulk-img-progress-text">Generating images... Please wait.</p>' +
                            '</div>' +
                            '<div class="progress" style="height: 25px;">' +
                                '<div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" ' +
                                     'style="width: 0%;" id="itrblueboost-bulk-img-progress-bar">0%</div>' +
                            '</div>' +
                        '</div>' +
                        '<div id="itrblueboost-bulk-img-result" class="d-none"></div>' +
                    '</div>' +
                    '<div class="modal-footer">' +
                        '<button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>' +
                        '<button type="button" class="btn btn-primary" id="itrblueboost-bulk-img-generate-btn" disabled>' +
                            '<i class="material-icons" style="vertical-align: middle;">image</i> ' +
                            'Generate' +
                        '</button>' +
                    '</div>' +
                '</div>' +
            '</div>' +
        '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modalCreated = true;

        var promptSelect = document.getElementById('itrblueboost-bulk-img-prompt');
        var generateBtn = document.getElementById('itrblueboost-bulk-img-generate-btn');
        var promptDesc = document.getElementById('itrblueboost-bulk-img-prompt-desc');
        var bulkImgHasInsufficientCredits = false;

        promptSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            promptDesc.textContent = selectedOption.dataset.description || '';
            generateBtn.disabled = !this.value || bulkImgHasInsufficientCredits;
        });

        generateBtn.addEventListener('click', handleGenerate);
    }

    function handleBulkImageClick(e) {
        e.preventDefault();
        e.stopPropagation();

        var selectedIds = getSelectedProductIds();

        if (selectedIds.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        if (!modalCreated) {
            createModal();
        }

        window.itrblueboostSelectedImageProductIds = selectedIds;

        var countEl = document.getElementById('itrblueboost-bulk-img-count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        resetModalState();

        $('#itrblueboostBulkImageModal').modal('show');

        loadPrompts();
    }

    function resetModalState() {
        var loadingEl = document.getElementById('itrblueboost-bulk-img-loading');
        var errorEl = document.getElementById('itrblueboost-bulk-img-error');
        var formEl = document.getElementById('itrblueboost-bulk-img-form');
        var progressEl = document.getElementById('itrblueboost-bulk-img-progress');
        var resultEl = document.getElementById('itrblueboost-bulk-img-result');
        var generateBtn = document.getElementById('itrblueboost-bulk-img-generate-btn');
        var promptSelect = document.getElementById('itrblueboost-bulk-img-prompt');

        var creditsWarningEl = document.getElementById('itrblueboost-bulk-img-credits-warning');

        if (loadingEl) loadingEl.classList.remove('d-none');
        if (errorEl) errorEl.classList.add('d-none');
        if (formEl) formEl.classList.add('d-none');
        if (progressEl) progressEl.classList.add('d-none');
        if (resultEl) {
            resultEl.classList.add('d-none');
            resultEl.innerHTML = '';
        }
        if (creditsWarningEl) creditsWarningEl.classList.add('d-none');
        bulkImgHasInsufficientCredits = false;
        if (generateBtn) {
            generateBtn.disabled = true;
            generateBtn.classList.remove('d-none');
        }
        if (promptSelect) promptSelect.value = '';

        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }
    }

    function getSelectedProductIds() {
        var ids = [];

        var checkboxSelectors = [
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

    function loadPrompts() {
        fetch(itrblueboostBulkImagePromptsUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            document.getElementById('itrblueboost-bulk-img-loading').classList.add('d-none');

            if (!data.success) {
                showError(data.message || 'Error loading prompts.');
                return;
            }

            var prompts = data.prompts || [];
            if (prompts.length === 0) {
                showError('No image prompts available.');
                return;
            }

            var select = document.getElementById('itrblueboost-bulk-img-prompt');
            select.innerHTML = '<option value="">-- Choose a prompt --</option>';

            prompts.forEach(function(prompt) {
                var option = document.createElement('option');
                option.value = prompt.id;
                option.textContent = prompt.title;
                option.dataset.description = prompt.description || '';
                select.appendChild(option);
            });

            document.getElementById('itrblueboost-bulk-img-form').classList.remove('d-none');

            if (typeof data.credits_remaining === 'number' && data.credits_remaining <= 0) {
                var creditsWarn = document.getElementById('itrblueboost-bulk-img-credits-warning');
                if (creditsWarn) creditsWarn.classList.remove('d-none');
                bulkImgHasInsufficientCredits = true;
                var genBtn = document.getElementById('itrblueboost-bulk-img-generate-btn');
                if (genBtn) genBtn.disabled = true;
            }
        })
        .catch(function() {
            document.getElementById('itrblueboost-bulk-img-loading').classList.add('d-none');
            showError('Connection error.');
        });
    }

    function showError(message) {
        var errorEl = document.getElementById('itrblueboost-bulk-img-error');
        errorEl.textContent = message;
        errorEl.classList.remove('d-none');
    }

    function handleGenerate() {
        var promptId = document.getElementById('itrblueboost-bulk-img-prompt').value;
        var productIds = window.itrblueboostSelectedImageProductIds || [];

        if (!promptId || productIds.length === 0) {
            return;
        }

        document.getElementById('itrblueboost-bulk-img-form').classList.add('d-none');
        document.getElementById('itrblueboost-bulk-img-generate-btn').classList.add('d-none');
        document.getElementById('itrblueboost-bulk-img-progress').classList.remove('d-none');

        updateProgressBar(5, 'Creating generation job...');

        var formData = new FormData();
        formData.append('prompt_id', promptId);
        formData.append('product_ids', productIds.join(','));

        fetch(itrblueboostBulkImageGenerateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.success || !data.job_id) {
                showGenerateError(data.message || 'Failed to create generation job.');
                return;
            }

            var jobId = data.job_id;

            updateProgressBar(10, 'Starting image generation...');

            fireAndForgetProcess(jobId);

            pollJobStatus(jobId);
        })
        .catch(function() {
            showGenerateError('Connection error while creating job.');
        });
    }

    function fireAndForgetProcess(jobId) {
        var processUrl = itrblueboostBulkImageProcessUrl.replace('/0/', '/' + jobId + '/');

        fetch(processUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).catch(function() {
            // Fire-and-forget: ignore network errors
        });
    }

    function pollJobStatus(jobId) {
        var statusUrl = itrblueboostBulkImageJobStatusUrl.replace('/0/', '/' + jobId + '/');

        pollingTimer = setInterval(function() {
            fetch(statusUrl, {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (!data.success) {
                    return;
                }

                var progress = data.progress || 0;
                var label = data.progress_label || 'Processing...';

                updateProgressBar(progress, label);

                if (data.status === 'completed') {
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                    showResults(data.data || {});
                }

                if (data.status === 'failed') {
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                    showGenerateError(data.error_message || 'Generation failed.');
                }
            })
            .catch(function() {
                // Polling error, will retry on next interval
            });
        }, 2000);
    }

    function updateProgressBar(progress, label) {
        var bar = document.getElementById('itrblueboost-bulk-img-progress-bar');
        var text = document.getElementById('itrblueboost-bulk-img-progress-text');

        if (bar) {
            bar.style.width = progress + '%';
            bar.textContent = progress + '%';
        }
        if (text) {
            text.textContent = label;
        }
    }

    function showGenerateError(message) {
        document.getElementById('itrblueboost-bulk-img-progress').classList.add('d-none');

        var resultDiv = document.getElementById('itrblueboost-bulk-img-result');
        resultDiv.innerHTML = '<div class="alert alert-danger">' +
            '<i class="material-icons" style="vertical-align: middle;">error</i> ' +
            escapeHtml(message) +
            '</div>';
        resultDiv.classList.remove('d-none');
    }

    function showResults(data) {
        document.getElementById('itrblueboost-bulk-img-progress').classList.add('d-none');

        var resultDiv = document.getElementById('itrblueboost-bulk-img-result');
        var processedItems = data.processed_items || [];
        var errors = data.errors || [];

        var html = '<div class="alert alert-success">' +
            '<i class="material-icons" style="vertical-align: middle;">check_circle</i> ' +
            'Bulk image generation completed! ' +
            '<strong>' + (data.total_processed || 0) + '/' + (data.total_products || 0) + '</strong> products processed.' +
            '</div>';

        if (processedItems.length > 0) {
            html += '<div class="list-group mb-3">';
            processedItems.forEach(function(item) {
                html += '<a href="' + escapeHtml(item.image_url) + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">' +
                    '<span><i class="material-icons" style="vertical-align: middle; font-size: 18px;">image</i> ' +
                    escapeHtml(item.name) + '</span>' +
                    '<span class="badge badge-primary badge-pill">' + item.image_count + ' image(s)</span>' +
                    '</a>';
            });
            html += '</div>';
        }

        if (errors.length > 0) {
            html += '<div class="alert alert-warning"><strong>Errors:</strong><ul class="mb-0">';
            errors.forEach(function(err) {
                html += '<li>' + escapeHtml(err) + '</li>';
            });
            html += '</ul></div>';
        }

        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
    }

    function escapeHtml(text) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(text));
        return div.innerHTML;
    }

    // Initialize
    init();

    // Re-init on page changes (for SPA-like navigation)
    if (typeof MutationObserver !== 'undefined') {
        var observer = new MutationObserver(function() {
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
