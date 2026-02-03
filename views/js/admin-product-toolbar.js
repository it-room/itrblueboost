/**
 * ITROOM API - Admin Product Footer Buttons
 * Injects "Generate FAQs" and "AI Images" buttons in the PS8 product page footer
 * Compatible with PrestaShop 8.x and 1.7.8+
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        console.log('[ITRBLUEBOOST] DOMContentLoaded - checking page...');

        if (!isProductEditPage()) {
            console.log('[ITRBLUEBOOST] Not a product edit page, skipping.');
            return;
        }

        console.log('[ITRBLUEBOOST] Product edit page detected, waiting for footer...');
        console.log('[ITRBLUEBOOST] faqUrl:', window.itrblueboostFaqUrl);
        console.log('[ITRBLUEBOOST] imageUrl:', window.itrblueboostImageUrl);

        waitForFooter(function(footer) {
            console.log('[ITRBLUEBOOST] Footer found:', footer);
            injectFaqButton(footer);
            injectImageButton(footer);
        });
    });

    /**
     * Check if we are on the product edit page (PS8, PS1.7.8 Symfony or PS1.7 legacy)
     */
    function isProductEditPage() {
        var url = window.location.href;

        // PS8 / PS1.7.8+ Symfony product page: /sell/catalog/products/123 or /sell/catalog/products-v2/123
        var isProductsPage = url.indexOf('/sell/catalog/products/') !== -1 || url.indexOf('/sell/catalog/products-v2/') !== -1;

        if (isProductsPage) {
            // Check if URL contains a product ID (number after /products/ or /products-v2/)
            var hasProductId = /\/products(-v2)?\/\d+/.test(url);
            // Exclude list pages (no ID in URL)
            var isListPage = /\/products(-v2)?\/?(\?|$)/.test(url) || url.indexOf('/products/new') !== -1;

            if (hasProductId && !isListPage) {
                return true;
            }
        }

        // PS 1.7.x legacy product page
        var isLegacyPage = url.indexOf('controller=AdminProducts') !== -1
            && (url.indexOf('updateproduct') !== -1 || url.indexOf('addproduct') !== -1);

        return isLegacyPage;
    }

    /**
     * Wait for the footer to be available in the DOM (PS8, PS1.7.8 Symfony or PS1.7 legacy)
     */
    function waitForFooter(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            var footer = null;

            // PS8 selectors (exact structure from PrestaShop 8 templates)
            // Structure: .product-footer > .product-footer-container > .form-group > #product-footer-left.product-footer-left
            footer = document.querySelector('#product-footer-left');

            if (!footer) {
                footer = document.querySelector('.product-footer-left');
            }

            if (!footer) {
                footer = document.querySelector('.product-footer-container .form-group');
            }

            if (!footer) {
                footer = document.querySelector('.product-footer-container');
            }

            if (!footer) {
                footer = document.querySelector('.product-footer');
            }

            // PS 1.7.8 Symfony form selectors
            if (!footer) {
                footer = document.querySelector('#product-actions .product-footer');
            }

            if (!footer) {
                footer = document.querySelector('.product-page-footer');
            }

            if (!footer) {
                footer = document.querySelector('#form_step1_type_product');
                if (footer) {
                    footer = footer.closest('.form-group') || footer.parentElement;
                }
            }

            // PS 1.7.8 - button container at the bottom
            if (!footer) {
                footer = document.querySelector('.btn-floating-container');
            }

            if (!footer) {
                footer = document.querySelector('.product-header .header-toolbar');
            }

            // PS 1.7.x legacy selectors
            if (!footer) {
                footer = document.querySelector('#product_form_save_go_to_catalog');
                if (footer) {
                    footer = footer.parentElement;
                }
            }

            if (!footer) {
                footer = document.querySelector('.product-footer .btn-group');
            }

            if (!footer) {
                footer = document.querySelector('#form_switch_product_type');
                if (footer) {
                    footer = footer.closest('.form-group') || footer.parentElement;
                }
            }

            if (!footer) {
                footer = document.querySelector('.btn-group-floating');
            }

            if (!footer) {
                footer = document.querySelector('#submit[name="submitAddproduct"]');
                if (footer) {
                    footer = footer.parentElement;
                }
            }

            // Fallback: header toolbar
            if (!footer) {
                footer = document.querySelector('.page-head-tabs');
            }

            if (!footer) {
                footer = document.querySelector('.header-toolbar .title-row');
            }

            if (footer) {
                clearInterval(interval);
                callback(footer);
            } else if (attempts >= maxAttempts) {
                clearInterval(interval);
                console.log('[ITRBLUEBOOST] Footer not found after ' + maxAttempts + ' attempts');
            }
        }, 100);
    }

    /**
     * Check if we are on PS8 structure
     * PS8 structure: .product-footer > .product-footer-container > .form-group > #product-footer-left.product-footer-left
     */
    function isPS8Structure() {
        return document.querySelector('#product-footer-left') !== null
            || document.querySelector('.product-footer-container') !== null;
    }

    /**
     * Inject the FAQ button in the footer (PS8, PS1.7.8 Symfony or PS1.7 legacy)
     */
    function injectFaqButton(footer) {
        if (document.getElementById('itrblueboost-faq-btn')) {
            return;
        }

        var faqUrl = window.itrblueboostFaqUrl;

        if (!faqUrl) {
            return;
        }

        var faqCount = window.itrblueboostFaqCount || 0;

        var button = document.createElement('a');
        button.id = 'itrblueboost-faq-btn';
        button.href = faqUrl;
        button.title = 'Manage product FAQs';

        if (isPS8Structure()) {
            // PS8 structure: buttons are directly inside #product-footer-left
            var footerLeft = document.querySelector('#product-footer-left') || document.querySelector('.product-footer-left');

            if (!footerLeft) {
                console.log('[ITRBLUEBOOST] PS8 footer-left not found, using fallback');
                // Fallback: append to footer
                button.className = 'btn btn-outline-secondary';
                button.style.marginLeft = '10px';
                button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">help_outline</i> FAQs (' + faqCount + ')';
                footer.appendChild(button);
                return;
            }

            button.className = 'btn btn-outline-secondary';
            button.style.marginLeft = '10px';
            button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">help_outline</i> FAQs (' + faqCount + ')';

            footerLeft.appendChild(button);
            console.log('[ITRBLUEBOOST] FAQ button injected in PS8 footer');
        } else {
            // PS 1.7.8 Symfony or legacy structure - append to found footer
            button.className = 'btn btn-outline-secondary';
            button.style.marginLeft = '10px';
            button.style.marginRight = '5px';
            button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">help_outline</i> FAQs (' + faqCount + ')';

            footer.appendChild(button);
            console.log('[ITRBLUEBOOST] FAQ button injected in legacy footer');
        }
    }

    /**
     * Inject the AI Images button in the footer (PS8, PS1.7.8 Symfony or PS1.7 legacy)
     */
    function injectImageButton(footer) {
        if (document.getElementById('itrblueboost-image-btn')) {
            return;
        }

        var imageUrl = window.itrblueboostImageUrl || '';

        if (!imageUrl) {
            return;
        }

        var button = document.createElement('a');
        button.id = 'itrblueboost-image-btn';
        button.href = imageUrl;
        button.title = 'Manage AI product images';

        if (isPS8Structure()) {
            // PS8 structure: buttons are directly inside #product-footer-left
            var footerLeft = document.querySelector('#product-footer-left') || document.querySelector('.product-footer-left');

            if (!footerLeft) {
                console.log('[ITRBLUEBOOST] PS8 footer-left not found for image btn, using fallback');
                // Fallback: append to footer
                button.className = 'btn btn-outline-secondary';
                button.style.marginLeft = '5px';
                button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">image</i> AI Images';
                footer.appendChild(button);
                return;
            }

            button.className = 'btn btn-outline-secondary';
            button.style.marginLeft = '5px';
            button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">image</i> AI Images';

            footerLeft.appendChild(button);
            console.log('[ITRBLUEBOOST] AI Images button injected in PS8 footer');
        } else {
            // PS 1.7.8 Symfony or legacy structure - append to found footer
            button.className = 'btn btn-outline-secondary';
            button.style.marginLeft = '5px';
            button.innerHTML = '<i class="material-icons" style="vertical-align: middle; font-size: 18px;">image</i> AI Images';

            footer.appendChild(button);
            console.log('[ITRBLUEBOOST] AI Images button injected in legacy footer');
        }
    }

})();
