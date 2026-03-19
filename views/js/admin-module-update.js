/**
 * Module auto-update from GitHub releases.
 * ES5 compatible.
 */
(function () {
    'use strict';

    var checkUrl = window.itrblueboostUpdateCheckUrl || '';
    var performUrl = window.itrblueboostUpdatePerformUrl || '';
    var csrfToken = window.itrblueboostUpdateCsrfToken || '';
    var currentVersion = window.itrblueboostCurrentVersion || '';

    function init() {
        if (!checkUrl) {
            return;
        }
        checkForUpdate();
    }

    function checkForUpdate() {
        var xhr = new XMLHttpRequest();
        xhr.open('GET', checkUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            if (xhr.status === 200) {
                handleCheckResponse(JSON.parse(xhr.responseText));
            }
        };
        xhr.send();
    }

    function handleCheckResponse(response) {
        if (!response.success || !response.data) {
            return;
        }

        var data = response.data;
        var container = document.getElementById('itrblueboost-update-status');
        if (!container) {
            return;
        }

        if (data.hasUpdate) {
            renderUpdateAvailable(container, data);
        } else {
            renderUpToDate(container, data);
        }

        container.style.display = '';
    }

    function renderUpToDate(container, data) {
        var checkedDate = formatDate(data.checkedAt);
        container.style.cssText = 'display:flex;align-items:center;padding:10px 16px;background:#e8f5e9;border-radius:8px;margin-bottom:1rem;font-size:13px;color:#2e7d32;';
        container.innerHTML = '<i class="material-icons" style="font-size:18px;margin-right:8px;">check_circle</i>'
            + '<span><strong>v' + escapeHtml(currentVersion) + '</strong> &mdash; D&eacute;j&agrave; &agrave; jour</span>'
            + '<span style="margin-left:auto;color:#6c757d;font-size:12px;">'
            + 'Derni&egrave;re v&eacute;rification : ' + escapeHtml(checkedDate)
            + '</span>';
    }

    function renderUpdateAvailable(container, data) {
        container.style.cssText = 'display:flex;align-items:center;padding:12px 16px;background:linear-gradient(135deg,#fff3e0,#ffe0b2);border:1px solid #f0ad4e;border-radius:8px;margin-bottom:1rem;';
        container.innerHTML = '<i class="material-icons" style="font-size:22px;margin-right:10px;color:#e65100;">system_update</i>'
            + '<div style="flex:1;">'
            + '<strong style="font-size:14px;">Mise &agrave; jour disponible : v' + escapeHtml(data.latestVersion) + '</strong>'
            + '<div style="font-size:12px;color:#6c757d;margin-top:2px;">Version actuelle : v' + escapeHtml(currentVersion) + '</div>'
            + '</div>'
            + '<button type="button" class="btn btn-warning" id="itrblueboost-update-btn" style="font-weight:600;">'
            + '<i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:4px;">cloud_download</i>'
            + 'Mettre &agrave; jour'
            + '</button>';

        document.getElementById('itrblueboost-update-btn').addEventListener('click', function () {
            openUpdateModal(data);
        });
    }

    function openUpdateModal(data) {
        var existingModal = document.getElementById('itrblueboostUpdateModal');
        if (existingModal) {
            existingModal.remove();
        }

        var releaseNotes = data.releaseNotes || 'Aucune note de version.';
        var modal = document.createElement('div');
        modal.id = 'itrblueboostUpdateModal';
        modal.className = 'modal fade';
        modal.setAttribute('tabindex', '-1');
        modal.innerHTML = '<div class="modal-dialog modal-lg">'
            + '<div class="modal-content">'
            + '<div class="modal-header" style="background:linear-gradient(135deg,#f0ad4e,#d9a441);color:#fff;">'
            + '<h5 class="modal-title"><i class="material-icons" style="vertical-align:middle;margin-right:8px;">system_update</i>'
            + 'Mise &agrave; jour disponible : v' + escapeHtml(data.latestVersion) + '</h5>'
            + '<button type="button" class="close" data-dismiss="modal" style="color:#fff;opacity:1;">&times;</button>'
            + '</div>'
            + '<div class="modal-body">'
            + '<p><strong>Version actuelle :</strong> v' + escapeHtml(currentVersion) + '</p>'
            + '<p><strong>Nouvelle version :</strong> v' + escapeHtml(data.latestVersion) + '</p>'
            + '<hr>'
            + '<h6>Notes de version</h6>'
            + '<div class="itrblueboost-release-notes" style="max-height:300px;overflow-y:auto;padding:15px;background:#f8f9fa;border-radius:8px;font-size:13px;white-space:pre-wrap;">'
            + escapeHtml(releaseNotes)
            + '</div>'
            + '</div>'
            + '<div class="modal-footer">'
            + '<button type="button" class="btn btn-secondary" data-dismiss="modal">Annuler</button>'
            + '<button type="button" class="btn btn-warning" id="itrblueboostPerformUpdate" style="font-weight:600;">'
            + '<i class="material-icons" style="font-size:16px;vertical-align:middle;margin-right:4px;">cloud_download</i>'
            + 'Mettre &agrave; jour'
            + '</button>'
            + '</div>'
            + '</div>'
            + '</div>';

        document.body.appendChild(modal);

        var $modal = $(modal);
        $modal.modal('show');

        document.getElementById('itrblueboostPerformUpdate').addEventListener('click', function () {
            performUpdate(data.zipUrl, $modal);
        });
    }

    function performUpdate(zipUrl, $modal) {
        var updateBtn = document.getElementById('itrblueboostPerformUpdate');
        updateBtn.disabled = true;
        updateBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Mise &agrave; jour en cours...';

        var closeButtons = $modal.find('[data-dismiss="modal"]');
        closeButtons.prop('disabled', true);

        var formData = new FormData();
        formData.append('_token', csrfToken);
        formData.append('zipUrl', zipUrl);

        var xhr = new XMLHttpRequest();
        xhr.open('POST', performUrl, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) {
                return;
            }
            handleUpdateResponse(xhr, $modal);
        };
        xhr.send(formData);
    }

    function handleUpdateResponse(xhr, $modal) {
        var response;
        try {
            response = JSON.parse(xhr.responseText);
        } catch (e) {
            response = { success: false, error: 'Erreur de communication avec le serveur.' };
        }

        var modalBody = $modal.find('.modal-body');

        if (xhr.status === 200 && response.success) {
            modalBody.html(
                '<div style="text-align:center;padding:30px;">'
                + '<i class="material-icons" style="font-size:64px;color:#70d99f;">check_circle</i>'
                + '<h4 style="margin-top:15px;">Mise &agrave; jour r&eacute;ussie !</h4>'
                + '<p>Le module a &eacute;t&eacute; mis &agrave; jour vers la version <strong>v'
                + escapeHtml(response.data.version || '') + '</strong>.</p>'
                + '<p style="color:#6c757d;">La page va se recharger automatiquement...</p>'
                + '</div>'
            );
            $modal.find('.modal-footer').html(
                '<button type="button" class="btn btn-primary" onclick="window.location.reload();">Recharger</button>'
            );
            setTimeout(function () {
                window.location.reload();
            }, 3000);
            return;
        }

        var errorMsg = response.error || 'Une erreur est survenue lors de la mise &agrave; jour.';
        modalBody.html(
            '<div style="text-align:center;padding:30px;">'
            + '<i class="material-icons" style="font-size:64px;color:#e74c3c;">error</i>'
            + '<h4 style="margin-top:15px;">Erreur</h4>'
            + '<p>' + escapeHtml(errorMsg) + '</p>'
            + '</div>'
        );
        $modal.find('.modal-footer').html(
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">Fermer</button>'
        );
    }

    function formatDate(timestamp) {
        if (!timestamp) {
            return '';
        }
        var d = new Date(timestamp * 1000);
        var pad = function (n) { return n < 10 ? '0' + n : n; };
        return pad(d.getDate()) + '/' + pad(d.getMonth() + 1) + '/' + d.getFullYear()
            + ' ' + pad(d.getHours()) + ':' + pad(d.getMinutes());
    }

    function escapeHtml(str) {
        if (!str) {
            return '';
        }
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
