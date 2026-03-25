/**
 * ITROOM API - Admin Product Footer Buttons
 * Injects "AI Images" button in the PS8/PS1.7 product page footer
 * Compatible with PrestaShop 8.x and 1.7.8+
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        if (!isProductEditPage()) {
            return;
        }

        waitForFooter(function(footer, isPS8) {
            injectButtons(footer, isPS8);
        });
    });

    function isProductEditPage() {
        var url = window.location.href;

        var isProductsPage = url.indexOf('/sell/catalog/products/') !== -1 || url.indexOf('/sell/catalog/products-v2/') !== -1;

        if (isProductsPage) {
            var hasProductId = /\/products(-v2)?\/\d+/.test(url);
            var isListPage = /\/products(-v2)?\/?(\?|$)/.test(url) || url.indexOf('/products/new') !== -1;

            if (hasProductId && !isListPage) {
                return true;
            }
        }

        return url.indexOf('controller=AdminProducts') !== -1
            && (url.indexOf('updateproduct') !== -1 || url.indexOf('addproduct') !== -1);
    }

    function waitForFooter(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            var footer = null;
            var isPS8 = false;

            footer = document.getElementById('product_footer_actions');
            if (footer) {
                isPS8 = true;
                clearInterval(interval);
                callback(footer, isPS8);
                return;
            }

            footer = document.querySelector('.product-footer.justify-content-md-center');
            if (!footer) {
                footer = document.querySelector('.product-footer');
            }
            if (!footer) {
                var saveBtn = document.getElementById('product_form_save_go_to_catalog');
                if (saveBtn) {
                    footer = saveBtn.parentElement;
                }
            }

            if (footer) {
                clearInterval(interval);
                callback(footer, false);
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 100);
    }

    function injectButtons(footer, isPS8) {
        var imageUrl = window.itrblueboostImageUrl;

        if (!imageUrl) {
            return;
        }

        if (document.getElementById('itrblueboost-image-btn')) {
            return;
        }

        var imageBtn = document.createElement('a');
        imageBtn.id = 'itrblueboost-image-btn';
        imageBtn.className = 'btn btn-outline-secondary itrblueboost-btn';
        imageBtn.href = imageUrl;
        imageBtn.title = 'AI Images';
        imageBtn.innerHTML = '<i class="material-icons">auto_awesome</i><span>AI Images</span>';

        if (isPS8) {
            var container = document.createElement('div');
            container.id = 'itrblueboost-buttons-container';
            container.className = 'itrblueboost-ps8-buttons';
            container.appendChild(imageBtn);

            var groupDefault = footer.querySelector('.group-default');
            if (groupDefault && groupDefault.nextSibling) {
                groupDefault.parentNode.insertBefore(container, groupDefault.nextSibling);
            } else if (groupDefault) {
                groupDefault.parentNode.appendChild(container);
            } else {
                footer.insertBefore(container, footer.firstChild);
            }
        } else {
            var targetColumn = footer.querySelector('.col-sm-5.col-lg-7.text-right') || footer;
            targetColumn.appendChild(imageBtn);
        }
    }

})();
