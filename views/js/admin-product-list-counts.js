/**
 * ITRBlueBoost - Display FAQ/Image/Content counts on product listing
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

        // Method 1: data-product-id on rows (PS 1.7.x)
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

        // Method 2: extract IDs from product edit links (PS 8.x products-v2 + PS 1.7.x)
        var editLinks = document.querySelectorAll(
            'table a[href*="/products-v2/"][href*="/edit"],' +
            'table a[href*="/products/"][href*="/edit"],' +
            'table a[href*="products&id_product="]'
        );
        if (editLinks.length > 0) {
            editLinks.forEach(function(link) {
                var href = link.getAttribute('href');
                var match = href.match(/products(?:-v2)?\/(\d+)/);
                if (!match) {
                    match = href.match(/id_product=(\d+)/);
                }
                if (match) {
                    var id = parseInt(match[1], 10);
                    if (id > 0 && ids.indexOf(id) === -1) {
                        ids.push(id);
                    }
                }
            });
            if (ids.length > 0) {
                return ids;
            }
        }

        // Method 3: bulk action checkboxes (various PS versions)
        var selectors = [
            'input[name="bulk_action_selected_products[]"]',
            'input.js-bulk-action-checkbox[value]',
            'input.ps-bulk-action-checkbox[value]',
            'input[name*="products_bulk"][type="checkbox"]',
            'input[name*="product"][name*="bulk"][type="checkbox"]'
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
                if (ids.length > 0) {
                    return ids;
                }
            }
        }

        // Method 4: ID column in table rows
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
        // Method 1: row with data-product-id (PS 1.7.x)
        var row = document.querySelector('tr[data-product-id="' + productId + '"]');
        if (row) {
            var cell = findNameCellInRow(row);
            if (cell) {
                return cell;
            }
        }

        // Method 2: find row via product edit link (PS 8.x + universal)
        var linkSelectors = [
            'a[href*="/products-v2/' + productId + '/edit"]',
            'a[href*="/products-v2/' + productId + '"]',
            'a[href*="/products/' + productId + '"]',
            'a[href*="id_product=' + productId + '"]'
        ];

        for (var i = 0; i < linkSelectors.length; i++) {
            var links = document.querySelectorAll('table ' + linkSelectors[i]);
            for (var j = 0; j < links.length; j++) {
                var cell = links[j].closest('td');
                if (cell) {
                    return cell;
                }
            }
        }

        // Method 3: find checkbox then traverse row
        var cbSelectors = [
            'input[name="bulk_action_selected_products[]"][value="' + productId + '"]',
            'input.js-bulk-action-checkbox[value="' + productId + '"]',
            'input.ps-bulk-action-checkbox[value="' + productId + '"]',
            'input[type="checkbox"][value="' + productId + '"]'
        ];

        for (var i = 0; i < cbSelectors.length; i++) {
            var cb = document.querySelector(cbSelectors[i]);
            if (cb) {
                row = cb.closest('tr');
                if (row) {
                    var cell = findNameCellInRow(row);
                    if (cell) {
                        return cell;
                    }
                }
            }
        }

        return null;
    }

    function findNameCellInRow(row) {
        var cells = row.querySelectorAll('td');

        // Look for a cell containing a link with text (product name)
        for (var i = 2; i < cells.length && i < 8; i++) {
            var link = cells[i].querySelector('a');
            if (link && link.textContent.trim().length > 1) {
                return cells[i];
            }
        }

        return null;
    }

    function createBadge(className, text) {
        var badge = document.createElement('span');
        badge.className = 'badge ' + className + ' itrblueboost-count-badge';
        badge.textContent = text;
        return badge;
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
                var contentCount = productCounts.content || 0;

                if (faqCount === 0 && imageCount === 0 && contentCount === 0) {
                    return;
                }

                var cell = findNameCell(id);
                if (!cell) {
                    return;
                }

                var container = document.createElement('div');
                container.className = 'itrblueboost-list-counts';

                if (faqCount > 0) {
                    container.appendChild(
                        createBadge('badge-info', faqCount + ' FAQ')
                    );
                }

                if (imageCount > 0) {
                    container.appendChild(
                        createBadge('badge-secondary', imageCount + ' Image' + (imageCount > 1 ? 's' : ''))
                    );
                }

                if (contentCount > 0) {
                    container.appendChild(
                        createBadge('badge-success', contentCount + ' Contenu' + (contentCount > 1 ? 's' : ''))
                    );
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
