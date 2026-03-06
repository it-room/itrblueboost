/**
 * ITRBlueBoost - Display FAQ counts on category listing
 */
(function() {
    'use strict';

    function init() {
        if (typeof itrblueboostCategoryListCountsUrl === 'undefined') {
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

    function getAllCategoryIds() {
        var ids = [];

        // Method 1: tree view rows with id="tr_{parent}_{id}_{depth}"
        var treeRows = document.querySelectorAll('tr[id^="tr_"]');
        if (treeRows.length > 0) {
            treeRows.forEach(function(row) {
                var parts = row.id.split('_');
                if (parts.length >= 3) {
                    var id = parseInt(parts[2], 10);
                    if (id > 0 && ids.indexOf(id) === -1) {
                        ids.push(id);
                    }
                }
            });
            if (ids.length > 0) {
                return ids;
            }
        }

        // Method 2: extract IDs from category edit links
        var editLinks = document.querySelectorAll(
            'table a[href*="/categories/"][href*="/edit"],' +
            'table a[href*="id_category="]'
        );
        if (editLinks.length > 0) {
            editLinks.forEach(function(link) {
                var href = link.getAttribute('href');
                var match = href.match(/categories\/(\d+)/);
                if (!match) {
                    match = href.match(/id_category=(\d+)/);
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

        // Method 3: bulk checkboxes
        var selectors = [
            'input[name="category_id_category[]"]',
            'input.js-bulk-action-checkbox[value]',
            'input.ps-bulk-action-checkbox[value]',
            'input[name*="category"][name*="bulk"][type="checkbox"]'
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

        return ids;
    }

    function findNameCell(categoryId) {
        // Method 1: tree view row tr_{parent}_{id}_{depth} → 3rd td
        var treeRows = document.querySelectorAll('tr[id^="tr_"]');
        for (var t = 0; t < treeRows.length; t++) {
            var parts = treeRows[t].id.split('_');
            if (parts.length >= 3 && parseInt(parts[2], 10) === categoryId) {
                var cells = treeRows[t].querySelectorAll('td');
                if (cells.length >= 3) {
                    return cells[2];
                }
            }
        }

        // Method 2: find row via category edit link, return the <td>
        var linkSelectors = [
            'table a[href*="/categories/' + categoryId + '/edit"]',
            'table a[href*="/categories/' + categoryId + '"]',
            'table a[href*="id_category=' + categoryId + '"]'
        ];

        for (var i = 0; i < linkSelectors.length; i++) {
            var links = document.querySelectorAll(linkSelectors[i]);
            for (var j = 0; j < links.length; j++) {
                if (links[j].textContent.trim().length > 1) {
                    return links[j].closest('td');
                }
            }
        }

        return null;
    }

    function injectCounts() {
        document.querySelectorAll('.itrblueboost-category-counts').forEach(function(el) {
            el.remove();
        });

        var categoryIds = getAllCategoryIds();
        if (categoryIds.length === 0) {
            return;
        }

        var formData = new FormData();
        formData.append('category_ids', categoryIds.join(','));

        fetch(itrblueboostCategoryListCountsUrl, {
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

            categoryIds.forEach(function(id) {
                var categoryCounts = counts[id];
                if (!categoryCounts) {
                    return;
                }

                var faqCount = categoryCounts.faq || 0;
                if (faqCount === 0) {
                    return;
                }

                var cell = findNameCell(id);
                if (!cell) {
                    return;
                }

                var container = document.createElement('div');
                container.className = 'itrblueboost-category-counts';
                container.style.marginTop = '4px';

                var badge = document.createElement('span');
                badge.className = 'badge badge-info itrblueboost-count-badge';
                badge.textContent = faqCount + ' FAQ';
                container.appendChild(badge);

                cell.appendChild(container);
            });
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
                if (!document.querySelector('.itrblueboost-category-counts')) {
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
