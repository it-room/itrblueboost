/**
 * ITROOM - FAQ Produit - Script d'administration
 *
 * Gere le drag & drop pour le tri des FAQ
 */

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        initFaqSortable();
    });

    /**
     * Initialise le tri des FAQ par drag & drop
     */
    function initFaqSortable() {
        var table = document.querySelector('.js-grid-table tbody');

        if (!table || typeof Sortable === 'undefined') {
            return;
        }

        new Sortable(table, {
            handle: '.position-column, .js-drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            dragClass: 'sortable-drag',
            onEnd: function(evt) {
                updatePositions(table);
            }
        });

        // Ajouter curseur grab sur les lignes
        table.querySelectorAll('tr').forEach(function(row) {
            var positionCell = row.querySelector('.position-column, .js-drag-handle');
            if (positionCell) {
                positionCell.style.cursor = 'grab';
            }
        });
    }

    /**
     * Met a jour les positions via AJAX
     *
     * @param {HTMLElement} table Element tbody de la table
     */
    function updatePositions(table) {
        var rows = table.querySelectorAll('tr');
        var positions = {};

        rows.forEach(function(row, index) {
            var checkbox = row.querySelector('input[type="checkbox"]');
            if (checkbox && checkbox.value) {
                positions[index] = checkbox.value;
            }
        });

        // Recuperer l'URL de mise a jour des positions depuis le data attribute
        var updateUrl = table.closest('table').dataset.positionUpdateUrl;

        if (!updateUrl) {
            // Essayer de construire l'URL a partir du contexte
            var currentUrl = window.location.href;
            updateUrl = currentUrl.replace(/\/faq.*$/, '/faq/position');
        }

        fetch(updateUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: new URLSearchParams({ positions: JSON.stringify(positions) })
        })
        .then(function(response) {
            return response.json();
        })
        .then(function(data) {
            if (data.status) {
                showSuccessMessage(data.message || 'Positions mises a jour');
            } else {
                showErrorMessage(data.message || 'Erreur lors de la mise a jour');
            }
        })
        .catch(function(error) {
            console.error('Erreur:', error);
            showErrorMessage('Erreur lors de la mise a jour des positions');
        });
    }

    /**
     * Affiche un message de succes
     *
     * @param {string} message Message a afficher
     */
    function showSuccessMessage(message) {
        if (typeof $.growl !== 'undefined') {
            $.growl.notice({ message: message });
        } else if (typeof window.showSuccessMessage === 'function') {
            window.showSuccessMessage(message);
        } else {
            console.log('Success:', message);
        }
    }

    /**
     * Affiche un message d'erreur
     *
     * @param {string} message Message a afficher
     */
    function showErrorMessage(message) {
        if (typeof $.growl !== 'undefined') {
            $.growl.error({ message: message });
        } else if (typeof window.showErrorMessage === 'function') {
            window.showErrorMessage(message);
        } else {
            console.error('Error:', message);
        }
    }

})();
