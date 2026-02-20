/**
 * ITRBlueBoost - Display FAQ/Image counts on product listing
 */
(function() {
    'use strict';

    var injected = false;

    function init() {
        if (typeof itrblueboostListCountsUrl === 'undefined') {
            return;
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                setTimeout(injectCounts, 600);
            });
        } else {
            setTimeout(injectCounts, 600);
        }

        observeDomChanges();
    }

    function getAllProductIds() {
        var ids = [];

        // Method 1: data-product-id on rows
        var rows = document.querySelectorAll('tr[data-product-id]');
        if (rows.length > 0) {
            rows.forEach(function(row) {
                var id = parseInt(row.getAttribute('data-product-id'), 10);
                if (id > 0 && ids.indexOf(id) === -1) {
                    ids.push(id);
                }
            });
            return ids;
        }

        // Method 2: all checkboxes (not just checked)
        var selectors = [
            'input[name="bulk_action_selected_products[]"]',
            'input.js-bulk-action-checkbox[value]'
        ];

        for (var i = 0; i < selectors.length; i++) {
            var checkboxes = document.querySelectorAll(selectors[i]);
            if (checkboxes.length > 0) {
                checkboxes.forEach(function(cb) {
                    var val = parseInt(cb.value, 10);
                    if (val > 0 && ids.indexOf(val) === -1) {
                        ids.push(val);
                    }
                });
                return ids;
            }
        }

        // Method 3: ID column in table rows
        var tableRows = document.querySelectorAll('table tbody tr');
        tableRows.forEach(function(row) {
            var cells = row.querySelectorAll('td');
            if (cells.length >= 4) {
                var idText = (cells[1] || cells[0]).textContent.trim();
                var id = parseInt(idText, 10);
                if (id > 0 && ids.indexOf(id) === -1) {
                    ids.push(id);
                }
            }
        });

        return ids;
    }

    function findNameCell(productId) {
        // Method 1: row with data-product-id
        var row = document.querySelector('tr[data-product-id="' + productId + '"]');
        if (row) {
            // Name is typically in 4th td (after checkbox, id, image)
            var cells = row.querySelectorAll('td');
            for (var i = 2; i < cells.length && i < 6; i++) {
                var link = cells[i].querySelector('a');
                if (link && link.textContent.trim().length > 1) {
                    return cells[i];
                }
            }
        }

        // Method 2: find checkbox then traverse row
        var cb = document.querySelector(
            'input[name="bulk_action_selected_products[]"][value="' + productId + '"]'
        );
        if (cb) {
            row = cb.closest('tr');
            if (row) {
                var cells = row.querySelectorAll('td');
                for (var i = 2; i < cells.length && i < 6; i++) {
                    var link = cells[i].querySelector('a');
                    if (link && link.textContent.trim().length > 1) {
                        return cells[i];
                    }
                }
            }
        }

        return null;
    }

    function injectCounts() {
        // Remove previous injections (in case of AJAX reload)
        document.querySelectorAll('.itrblueboost-list-counts').forEach(function(el) {
            el.remove();
        });
        injected = false;

        var productIds = getAllProductIds();
        if (productIds.length === 0) {
            return;
        }

        var formData = new FormData();
        formData.append('product_ids', productIds.join(','));

        fetch(itrblueboostListCountsUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (!data.success || !data.counts) {
                return;
            }

            var counts = data.counts;

            productIds.forEach(function(id) {
                var productCounts = counts[id];
                if (!productCounts) {
                    return;
                }

                var faqCount = productCounts.faq || 0;
                var imageCount = productCounts.images || 0;

                if (faqCount === 0 && imageCount === 0) {
                    return;
                }

                var cell = findNameCell(id);
                if (!cell) {
                    return;
                }

                var container = document.createElement('div');
                container.className = 'itrblueboost-list-counts';

                if (faqCount > 0) {
                    var faqBadge = document.createElement('span');
                    faqBadge.className = 'badge badge-info itrblueboost-count-badge';
                    faqBadge.textContent = faqCount + ' FAQ';
                    container.appendChild(faqBadge);
                }

                if (imageCount > 0) {
                    var imgBadge = document.createElement('span');
                    imgBadge.className = 'badge badge-secondary itrblueboost-count-badge';
                    imgBadge.textContent = imageCount + ' Image' + (imageCount > 1 ? 's' : '');
                    container.appendChild(imgBadge);
                }

                cell.appendChild(container);
            });

            injected = true;
        })
        .catch(function() {
            // Non-critical, fail silently
        });
    }

    function observeDomChanges() {
        if (typeof MutationObserver === 'undefined') {
            return;
        }

        var debounceTimer = null;

        var observer = new MutationObserver(function() {
            // Debounce: wait for DOM to stabilize after AJAX reload
            if (debounceTimer) {
                clearTimeout(debounceTimer);
            }

            debounceTimer = setTimeout(function() {
                if (!document.querySelector('.itrblueboost-list-counts')) {
                    injectCounts();
                }
            }, 500);
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }

    init();
})();
