/**
 * ITRBlueBoost - Bulk Image Generation on Product List
 * Thin wrapper around ITRBulkCommon with polling logic.
 */
(function() {
    'use strict';

    var B = ITRBulkCommon;
    var bulkActionAdded = false;
    var modalRefs = null;
    var pollingTimer = null;
    var PREFIX = 'itrblueboost-bulk-img-';

    function init() {
        if (typeof itrblueboostBulkImageLabel === 'undefined') {
            return;
        }

        B.initBulk(addBulkAction, createModal);
    }

    function addBulkAction() {
        if (bulkActionAdded) {
            return;
        }

        bulkActionAdded = B.addBulkActionButton({
            dropdownSelectors: B.PRODUCT_DROPDOWN_SELECTORS,
            cssClass: 'itrblueboost-bulk-image',
            label: itrblueboostBulkImageLabel,
            icon: 'image',
            onClick: handleBulkImageClick
        });
    }

    function createModal() {
        if (modalRefs) {
            return;
        }

        modalRefs = B.createBulkModal({
            modalId: 'itrblueboostBulkImageModal',
            prefix: PREFIX,
            title: 'Generate Images (AI) - Bulk',
            icon: 'image',
            headerGradient: 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
            entityLabel: 'product(s)',
            promptLabel: 'Select an image prompt:',
            progressLabel: 'Generating images... Please wait.',
            btnClass: 'btn-primary',
            onGenerate: handleGenerate
        });
    }

    function handleBulkImageClick(e) {
        e.preventDefault();
        e.stopPropagation();

        var selectedIds = B.getSelectedIds(B.PRODUCT_CHECKBOX_SELECTORS, 'data-product-id');

        if (selectedIds.length === 0) {
            alert('Please select at least one product.');
            return;
        }

        if (!modalRefs) {
            createModal();
        }

        window.itrblueboostSelectedImageProductIds = selectedIds;

        var countEl = document.getElementById(PREFIX + 'count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        B.resetModal(PREFIX, modalRefs);

        if (pollingTimer) {
            clearInterval(pollingTimer);
            pollingTimer = null;
        }

        $('#itrblueboostBulkImageModal').modal('show');
        B.loadPrompts(itrblueboostBulkImagePromptsUrl, PREFIX, modalRefs);
    }

    function handleGenerate() {
        var promptId = modalRefs.promptSelect.value;
        var productIds = window.itrblueboostSelectedImageProductIds || [];

        if (!promptId || productIds.length === 0) {
            return;
        }

        document.getElementById(PREFIX + 'form').classList.add('d-none');
        modalRefs.generateBtn.classList.add('d-none');
        document.getElementById(PREFIX + 'progress').classList.remove('d-none');

        B.updateProgressBar(PREFIX, 5, 'Creating generation job...');

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
                B.showGenerateError(PREFIX, data.message || 'Failed to create generation job.');
                return;
            }

            B.updateProgressBar(PREFIX, 10, 'Starting image generation...');
            fireAndForgetProcess(data.job_id);
            pollJobStatus(data.job_id);
        })
        .catch(function() {
            B.showGenerateError(PREFIX, 'Connection error while creating job.');
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

                B.updateProgressBar(PREFIX, data.progress || 0, data.progress_label || 'Processing...');

                if (data.status === 'completed') {
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                    showResults(data.data || {});
                }

                if (data.status === 'failed') {
                    clearInterval(pollingTimer);
                    pollingTimer = null;
                    B.showGenerateError(PREFIX, data.error_message || 'Generation failed.');
                }
            })
            .catch(function() {
                // Polling error, will retry on next interval
            });
        }, 2000);
    }

    function showResults(data) {
        document.getElementById(PREFIX + 'progress').classList.add('d-none');

        var resultDiv = document.getElementById(PREFIX + 'result');
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
                html += '<a href="' + B.escapeHtml(item.image_url) + '" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">' +
                    '<span><i class="material-icons" style="vertical-align: middle; font-size: 18px;">image</i> ' +
                    B.escapeHtml(item.name) + '</span>' +
                    '<span class="badge badge-primary badge-pill">' + item.image_count + ' image(s)</span>' +
                    '</a>';
            });
            html += '</div>';
        }

        if (errors.length > 0) {
            html += '<div class="alert alert-warning"><strong>Errors:</strong><ul class="mb-0">';
            errors.forEach(function(err) {
                html += '<li>' + B.escapeHtml(err) + '</li>';
            });
            html += '</ul></div>';
        }

        resultDiv.innerHTML = html;
        resultDiv.classList.remove('d-none');
    }

    init();
    B.observeForBulkAction(addBulkAction, function() { return bulkActionAdded; });
})();
