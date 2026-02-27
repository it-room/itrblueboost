/**
 * ITRBlueBoost - Bulk FAQ Generation on Product List
 * Thin wrapper around ITRBulkCommon.
 */
(function() {
    'use strict';

    var B = ITRBulkCommon;
    var bulkActionAdded = false;
    var modalRefs = null;
    var PREFIX = 'itrblueboost-bulk-';

    function init() {
        if (typeof itrblueboostBulkFaqLabel === 'undefined') {
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
            cssClass: 'itrblueboost-bulk-faq',
            label: itrblueboostBulkFaqLabel,
            icon: 'auto_awesome',
            onClick: handleBulkFaqClick
        });
    }

    function createModal() {
        if (modalRefs) {
            return;
        }

        modalRefs = B.createBulkModal({
            modalId: 'itrblueboostBulkFaqModal',
            prefix: PREFIX,
            title: 'Generate FAQ (AI) - Bulk',
            icon: 'auto_awesome',
            headerGradient: 'linear-gradient(135deg, #25b9d7 0%, #1e9bb5 100%)',
            entityLabel: 'product(s)',
            promptLabel: 'Select a prompt:',
            progressLabel: 'Generating FAQs... Please wait.',
            btnClass: 'btn-success',
            onGenerate: handleGenerate
        });
    }

    function handleBulkFaqClick(e) {
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

        window.itrblueboostSelectedProductIds = selectedIds;

        var countEl = document.getElementById(PREFIX + 'count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        B.resetModal(PREFIX, modalRefs);
        $('#itrblueboostBulkFaqModal').modal('show');
        B.loadPrompts(itrblueboostBulkFaqPromptsUrl, PREFIX, modalRefs);
    }

    function handleGenerate() {
        var promptId = modalRefs.promptSelect.value;
        var productIds = window.itrblueboostSelectedProductIds || [];

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

        fetch(itrblueboostBulkFaqGenerateUrl, {
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
                resultDiv.innerHTML = B.buildResultHtml(data, 'quiz', 'faq_count', 'FAQ', 'faq_url');
            } else {
                resultDiv.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="material-icons" style="vertical-align: middle;">error</i> ' +
                    B.escapeHtml(data.message || 'Error generating FAQs.') +
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
