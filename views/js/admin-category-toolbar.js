/**
 * ITROOM API - Admin Category Footer Buttons
 * Injects "Generate FAQs" button in the PS8 category page footer
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        if (!isCategoryEditPage()) {
            return;
        }

        waitForFooter(function(footer) {
            injectFaqButton(footer);
        });
    });

    /**
     * Check if we are on the category edit page
     */
    function isCategoryEditPage() {
        var url = window.location.href;
        var isCategoriesPage = url.includes('/sell/catalog/categories/');
        var isEditPage = url.includes('/edit') || url.match(/\/categories\/\d+$/);
        return isCategoriesPage && isEditPage;
    }

    /**
     * Wait for the footer to be available in the DOM
     */
    function waitForFooter(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            // Try multiple selectors for different PS8 layouts
            var footer = document.querySelector('.form-footer');

            if (!footer) {
                footer = document.querySelector('.card-footer');
            }

            if (!footer) {
                footer = document.querySelector('.btn-group-action');
            }

            if (!footer) {
                // Look for the save button's parent
                var saveBtn = document.querySelector('button[type="submit"][name="save"], #category_footer_save');
                if (saveBtn) {
                    footer = saveBtn.parentElement;
                }
            }

            if (footer) {
                clearInterval(interval);
                callback(footer);
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 100);
    }

    /**
     * Inject the FAQ button in the footer
     */
    function injectFaqButton(footer) {
        if (document.getElementById('itrblueboost-category-faq-btn')) {
            return;
        }

        var faqUrl = window.itrblueboostCategoryFaqUrl;

        if (!faqUrl) {
            return;
        }

        var faqCount = window.itrblueboostCategoryFaqCount || 0;

        var button = document.createElement('a');
        button.id = 'itrblueboost-category-faq-btn';
        button.href = faqUrl;
        button.className = 'btn btn-outline-secondary mr-2';
        button.title = 'Manage category FAQs';
        button.innerHTML = '<i class="material-icons">help_outline</i> FAQ (' + faqCount + ')';

        // Insert at the beginning of the footer
        footer.insertBefore(button, footer.firstChild);
    }

})();
