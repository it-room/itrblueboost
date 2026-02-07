/**
 * ITROOM API - Admin Product Footer Buttons
 * Injects "FAQs" and "AI Images" buttons in the PS8/PS1.7 product page footer
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

        waitForFooter(function(footer, isPS8) {
            console.log('[ITRBLUEBOOST] Footer found:', footer, 'isPS8:', isPS8);
            injectButtons(footer, isPS8);
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
     * Detect PrestaShop version based on DOM structure
     */
    function detectPSVersion() {
        // PS8 specific: #product_footer_actions exists
        if (document.getElementById('product_footer_actions')) {
            return 8;
        }
        // PS1.7 specific: .product-footer.justify-content-md-center exists
        if (document.querySelector('.product-footer.justify-content-md-center')) {
            return 17;
        }
        // Fallback detection
        if (document.querySelector('.product-header .toolbar')) {
            return 8;
        }
        return 17;
    }

    /**
     * Wait for the footer to be available in the DOM
     */
    function waitForFooter(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            var footer = null;
            var isPS8 = false;

            // PS8: #product_footer_actions
            footer = document.getElementById('product_footer_actions');
            if (footer) {
                isPS8 = true;
                clearInterval(interval);
                callback(footer, isPS8);
                return;
            }

            // PS1.7: .product-footer.justify-content-md-center
            footer = document.querySelector('.product-footer.justify-content-md-center');
            if (footer) {
                isPS8 = false;
                clearInterval(interval);
                callback(footer, isPS8);
                return;
            }

            // Fallback PS1.7 selectors
            footer = document.querySelector('.product-footer');
            if (footer) {
                isPS8 = false;
                clearInterval(interval);
                callback(footer, isPS8);
                return;
            }

            // Legacy: form footer
            footer = document.querySelector('#product_form_save_go_to_catalog');
            if (footer) {
                footer = footer.parentElement;
                isPS8 = false;
                clearInterval(interval);
                callback(footer, isPS8);
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
                console.log('[ITRBLUEBOOST] Footer not found after ' + maxAttempts + ' attempts');
            }
        }, 100);
    }

    /**
     * Inject both buttons
     */
    function injectButtons(footer, isPS8) {
        var faqUrl = window.itrblueboostFaqUrl;
        var imageUrl = window.itrblueboostImageUrl;
        var faqCount = window.itrblueboostFaqCount || 0;

        if (!faqUrl && !imageUrl) {
            return;
        }

        if (isPS8) {
            injectPS8FooterButtons(footer, faqUrl, imageUrl, faqCount);
        } else {
            injectPS17FooterButtons(footer, faqUrl, imageUrl, faqCount);
        }
    }

    /**
     * Inject buttons in PS8 footer (#product_footer_actions)
     * Position: after .group-default
     */
    function injectPS8FooterButtons(footer, faqUrl, imageUrl, faqCount) {
        // Find .group-default to insert after it
        var groupDefault = footer.querySelector('.group-default');

        // Create container for our buttons
        var container = document.createElement('div');
        container.id = 'itrblueboost-buttons-container';
        container.className = 'itrblueboost-ps8-buttons';

        // Create FAQ button
        if (faqUrl && !document.getElementById('itrblueboost-faq-btn')) {
            var faqBtn = document.createElement('a');
            faqBtn.id = 'itrblueboost-faq-btn';
            faqBtn.className = 'btn btn-outline-secondary itrblueboost-btn';
            faqBtn.href = faqUrl;
            faqBtn.title = 'FAQs (' + faqCount + ')';
            faqBtn.innerHTML = '<i class="material-icons">help_outline</i><span>FAQ (' + faqCount + ')</span>';
            container.appendChild(faqBtn);
            console.log('[ITRBLUEBOOST] FAQ button created for PS8 footer');
        }

        // Create AI Images button
        if (imageUrl && !document.getElementById('itrblueboost-image-btn')) {
            var imageBtn = document.createElement('a');
            imageBtn.id = 'itrblueboost-image-btn';
            imageBtn.className = 'btn btn-outline-secondary itrblueboost-btn';
            imageBtn.href = imageUrl;
            imageBtn.title = 'AI Images';
            imageBtn.innerHTML = '<i class="material-icons">auto_awesome</i><span>AI Images</span>';
            container.appendChild(imageBtn);
            console.log('[ITRBLUEBOOST] AI Images button created for PS8 footer');
        }

        // Insert container after .group-default
        if (groupDefault && groupDefault.nextSibling) {
            groupDefault.parentNode.insertBefore(container, groupDefault.nextSibling);
        } else if (groupDefault) {
            groupDefault.parentNode.appendChild(container);
        } else {
            // Fallback: insert at the beginning of footer
            footer.insertBefore(container, footer.firstChild);
        }

        console.log('[ITRBLUEBOOST] Buttons injected in PS8 footer (after .group-default)');
    }

    /**
     * Inject buttons in PS1.7 footer (.product-footer.justify-content-md-center)
     * Buttons are inserted directly in the second column without a wrapper div
     */
    function injectPS17FooterButtons(footer, faqUrl, imageUrl, faqCount) {
        // Find the second column (.col-sm-5.col-lg-7.text-right) in footer
        var targetColumn = footer.querySelector('.col-sm-5.col-lg-7.text-right') || footer;

        // Create FAQ button
        if (faqUrl && !document.getElementById('itrblueboost-faq-btn')) {
            var faqBtn = document.createElement('a');
            faqBtn.id = 'itrblueboost-faq-btn';
            faqBtn.href = faqUrl;
            faqBtn.className = 'btn btn-outline-secondary itrblueboost-btn';
            faqBtn.title = 'Manage product FAQs';
            faqBtn.innerHTML = '<i class="material-icons">help_outline</i> FAQs (' + faqCount + ')';
            targetColumn.appendChild(faqBtn);
            console.log('[ITRBLUEBOOST] FAQ button created for PS1.7 footer');
        }

        // Create AI Images button
        if (imageUrl && !document.getElementById('itrblueboost-image-btn')) {
            var imageBtn = document.createElement('a');
            imageBtn.id = 'itrblueboost-image-btn';
            imageBtn.href = imageUrl;
            imageBtn.className = 'btn btn-outline-secondary itrblueboost-btn';
            imageBtn.title = 'Manage AI product images';
            imageBtn.innerHTML = '<i class="material-icons">auto_awesome</i> AI Images';
            targetColumn.appendChild(imageBtn);
            console.log('[ITRBLUEBOOST] AI Images button created for PS1.7 footer');
        }

        console.log('[ITRBLUEBOOST] Buttons injected in PS1.7 footer (second column)');
    }

})();
