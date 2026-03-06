/**
 * ITRBlueBoost - Bulk Content Generation on Product List
 * Thin wrapper around ITRBulkCommon.
 */
(function() {
    'use strict';

    var B = ITRBulkCommon;
    var bulkActionAdded = false;
    var modalRefs = null;
    var PREFIX = 'itrblueboost-bulk-content-';

    function init() {
        if (typeof itrblueboostBulkContentLabel === 'undefined') {
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
            cssClass: 'itrblueboost-bulk-content',
            label: itrblueboostBulkContentLabel,
            icon: 'description',
            onClick: handleBulkContentClick
        });
    }

    function createModal() {
        if (modalRefs) {
            return;
        }

        modalRefs = B.createBulkModal({
            modalId: 'itrblueboostBulkContentModal',
            prefix: PREFIX,
            title: 'Generate Content (AI) - Bulk',
            icon: 'description',
            entityLabel: 'product(s)',
            promptLabel: 'Select a prompt:',
            progressLabel: 'Generating content... Please wait.',
            btnClass: 'btn-success',
            onGenerate: handleGenerate
        });
    }

    function handleBulkContentClick(e) {
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

        window.itrblueboostSelectedContentProductIds = selectedIds;

        var countEl = document.getElementById(PREFIX + 'count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        B.resetModal(PREFIX, modalRefs);
        $('#itrblueboostBulkContentModal').modal('show');
        B.loadPrompts(itrblueboostBulkContentPromptsUrl, PREFIX, modalRefs);

        if (typeof itrblueboostListCountsUrl !== 'undefined') {
            var t = window.itrblueboostModalTranslations || {};
            B.loadExistingCounts({
                url: itrblueboostListCountsUrl,
                ids: selectedIds,
                prefix: PREFIX,
                countKey: 'content',
                label: t.includingWithContents || 'including %count% with generated contents',
                idParam: 'product_ids'
            });
        }
    }

    function handleGenerate() {
        var promptId = modalRefs.promptSelect.value;
        var productIds = window.itrblueboostSelectedContentProductIds || [];

        if (!promptId || productIds.length === 0) {
            return;
        }

        document.getElementById(PREFIX + 'form').classList.add('d-none');
        modalRefs.generateBtn.classList.add('d-none');
        document.getElementById(PREFIX + 'progress').classList.remove('d-none');

        B.updateProgressBar(PREFIX, 50, 'Processing...');

        var formData = new FormData();
        formData.append('prompt_id', promptId);
        formData.append('product_ids', productIds.join(','));

        fetch(itrblueboostBulkContentGenerateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            B.updateProgressBar(PREFIX, 100, '100%');
            document.getElementById(PREFIX + 'progress').classList.add('d-none');

            var resultDiv = document.getElementById(PREFIX + 'result');

            if (data.success) {
                var html = '<div class="alert alert-success">' +
                    '<i class="material-icons" style="vertical-align: middle;">check_circle</i> ' +
                    B.escapeHtml(data.message || 'Content generation completed!');

                if (typeof data.credits_used !== 'undefined') {
                    html += '<br><small>Credits used: ' + data.credits_used +
                        ' | Credits remaining: ' + data.credits_remaining + '</small>';
                }

                html += '</div>';

                if (data.errors && data.errors.length > 0) {
                    html += '<div class="alert alert-warning"><strong>Errors:</strong><ul class="mb-0">';
                    data.errors.forEach(function(err) {
                        html += '<li>' + B.escapeHtml(err) + '</li>';
                    });
                    html += '</ul></div>';
                }

                resultDiv.innerHTML = html;
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="material-icons" style="vertical-align: middle;">error</i> ' +
                    B.escapeHtml(data.message || 'Error generating content.') +
                    '</div>';
            }

            resultDiv.classList.remove('d-none');
        })
        .catch(function() {
            B.showGenerateError(PREFIX, 'Connection error.');
        });
    }

    init();
    B.observeForBulkAction(addBulkAction, function() { return bulkActionAdded; });
})();
