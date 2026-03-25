/**
 * ITROOM API - Admin Category Footer Buttons
 * Injects "FAQs" button in the PS8/PS1.7 category page footer
 * Compatible with PrestaShop 8.x, 8.1+, 9.x and 1.7.x
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
     * Check if we are on the category edit page (PS8+, PS1.7.8 Symfony or PS1.7 legacy)
     */
    function isCategoryEditPage() {
        var url = window.location.href;

        // PS8+ / PS1.7.8+ Symfony category page: /sell/catalog/categories/{id}/edit
        var isCategoriesPage = url.indexOf('/sell/catalog/categories/') !== -1;
        if (isCategoriesPage) {
            var isEditPage = url.indexOf('/edit') !== -1 || /\/categories\/\d+(?:\?|#|$)/.test(url);
            if (isEditPage) {
                return true;
            }
        }

        // PS 1.7.x legacy category page
        var isLegacyPage = url.indexOf('controller=AdminCategories') !== -1
            && (url.indexOf('updatecategory') !== -1 || url.indexOf('addcategory') !== -1);

        return isLegacyPage;
    }

    /**
     * Wait for the footer to be available in the DOM
     */
    function waitForFooter(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            var footer = findFooter();

            if (footer) {
                clearInterval(interval);
                callback(footer);
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 100);
    }

    /**
     * Find the form footer element across PS versions
     */
    function findFooter() {
        // PS8.1+/9: Symfony form footer with ID pattern category_footer
        var footer = document.getElementById('category_footer');
        if (footer) {
            return footer;
        }

        // PS8+: form-action-bar (fixed bottom action bar)
        footer = document.querySelector('.form-action-bar');
        if (footer) {
            return footer;
        }

        // PS8: card-footer inside main form card
        var mainForm = document.querySelector('form[name="category"]');
        if (mainForm) {
            footer = mainForm.querySelector('.card-footer');
            if (footer) {
                return footer;
            }
        }

        // PS8/PS1.7: generic form footer
        footer = document.querySelector('.form-footer');
        if (footer) {
            return footer;
        }

        // PS1.7 legacy: card-footer (first match in main content)
        var mainContent = document.getElementById('content');
        if (mainContent) {
            footer = mainContent.querySelector('.card-footer');
            if (footer) {
                return footer;
            }
        }

        // Fallback: any card-footer
        footer = document.querySelector('.card-footer');
        if (footer) {
            return footer;
        }

        // PS1.7 legacy: panel-footer
        footer = document.querySelector('.panel-footer');
        if (footer) {
            return footer;
        }

        // Fallback: find save button and use its parent
        var saveBtn = document.querySelector(
            '#category_footer_save, ' +
            'button[type="submit"][name="submitAddcategory"], ' +
            'button[type="submit"][name="submitAddcategoryAndStay"], ' +
            'button[type="submit"][name="category[save]"]'
        );
        if (saveBtn) {
            return saveBtn.parentElement;
        }

        return null;
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
        button.className = 'btn btn-outline-secondary itrblueboost-btn mr-2';
        button.title = 'Manage category FAQs';
        button.innerHTML = '<i class="material-icons">help_outline</i> FAQ (' + faqCount + ')';

        // Insert at the beginning of the footer
        footer.insertBefore(button, footer.firstChild);
    }

})();
