/**
 * ITRBlueBoost - Bulk FAQ Generation on Category List
 * Thin wrapper around ITRBulkCommon.
 */
(function() {
    'use strict';

    var B = ITRBulkCommon;
    var bulkActionAdded = false;
    var modalRefs = null;
    var PREFIX = 'itrblueboost-bulk-category-';

    function init() {
        if (typeof itrblueboostBulkCategoryFaqLabel === 'undefined') {
            return;
        }

        B.initBulk(addBulkAction, createModal);
    }

    function addBulkAction() {
        if (bulkActionAdded) {
            return;
        }

        bulkActionAdded = B.addBulkActionButton({
            dropdownSelectors: B.CATEGORY_DROPDOWN_SELECTORS,
            cssClass: 'itrblueboost-bulk-category-faq',
            label: itrblueboostBulkCategoryFaqLabel,
            icon: 'auto_awesome',
            onClick: handleBulkFaqClick
        });
    }

    function createModal() {
        if (modalRefs) {
            return;
        }

        modalRefs = B.createBulkModal({
            modalId: 'itrblueboostBulkCategoryFaqModal',
            prefix: PREFIX,
            title: 'Generate Category FAQ (AI) - Bulk',
            icon: 'auto_awesome',
            headerGradient: 'linear-gradient(135deg, #25b9d7 0%, #1e9bb5 100%)',
            entityLabel: 'category(ies)',
            promptLabel: 'Select a prompt:',
            progressLabel: 'Generating FAQs... Please wait.',
            btnClass: 'btn-success',
            onGenerate: handleGenerate
        });
    }

    function handleBulkFaqClick(e) {
        e.preventDefault();
        e.stopPropagation();

        var selectedIds = B.getSelectedIds(B.CATEGORY_CHECKBOX_SELECTORS, 'data-category-id');

        if (selectedIds.length === 0) {
            alert('Please select at least one category.');
            return;
        }

        if (!modalRefs) {
            createModal();
        }

        window.itrblueboostSelectedCategoryIds = selectedIds;

        var countEl = document.getElementById(PREFIX + 'count');
        if (countEl) {
            countEl.textContent = selectedIds.length;
        }

        B.resetModal(PREFIX, modalRefs);
        $('#itrblueboostBulkCategoryFaqModal').modal('show');
        B.loadPrompts(itrblueboostBulkCategoryFaqPromptsUrl, PREFIX, modalRefs);
    }

    function handleGenerate() {
        var promptId = modalRefs.promptSelect.value;
        var categoryIds = window.itrblueboostSelectedCategoryIds || [];

        if (!promptId || categoryIds.length === 0) {
            return;
        }

        document.getElementById(PREFIX + 'form').classList.add('d-none');
        modalRefs.generateBtn.classList.add('d-none');
        document.getElementById(PREFIX + 'progress').classList.remove('d-none');

        B.updateProgressBar(PREFIX, 50, 'Processing...');

        var formData = new FormData();
        formData.append('prompt_id', promptId);
        formData.append('category_ids', categoryIds.join(','));

        fetch(itrblueboostBulkCategoryFaqGenerateUrl, {
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
