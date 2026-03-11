/**
 * ITRBlueBoost - Category Content Inline Button
 * Injects "Generate Content" button in the category edit page
 */
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        if (!isCategoryEditPage()) {
            return;
        }

        waitForDescriptionFields(function() {
            injectGenerateButton();
        });
    });

    function isCategoryEditPage() {
        var url = window.location.href;
        var isCategoriesPage = url.indexOf('/sell/catalog/categories/') !== -1;
        var isEditPage = url.indexOf('/edit') !== -1 || /\/categories\/\d+$/.test(url);
        return isCategoriesPage && isEditPage;
    }

    function waitForDescriptionFields(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            var descContainer = document.getElementById('category_description');
            var rteField = document.querySelector('textarea[name*="category[description]"]');
            var tinyMCEField = document.querySelector('.mce-tinymce');
            var autoloadRte = document.querySelector('.autoload_rte');

            if (descContainer || rteField || tinyMCEField || autoloadRte) {
                clearInterval(interval);
                setTimeout(callback, 800);
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
            }
        }, 100);
    }

    function injectGenerateButton() {
        var promptsUrl = window.itrblueboostCategoryContentPromptsUrl;
        var generateUrl = window.itrblueboostCategoryContentGenerateUrl;

        if (!promptsUrl || !generateUrl) {
            return;
        }

        var targetContainer = findDescriptionContainer();
        if (!targetContainer) {
            return;
        }

        if (targetContainer.querySelector('.itrblueboost-generate-cat-content-btn')) {
            return;
        }

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary itrblueboost-generate-cat-content-btn';
        btn.style.cssText = 'margin: 5px 0; display: inline-flex; align-items: center; gap: 5px;';
        btn.innerHTML = '<i class="material-icons" style="font-size: 18px;">auto_awesome</i> <span>G\u00e9n\u00e9rer le contenu (IA)</span>';

        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openGenerateModal();
        });

        var btnWrapper = document.createElement('div');
        btnWrapper.className = 'itrblueboost-btn-wrapper';
        btnWrapper.style.cssText = 'margin-bottom: 10px;';
        btnWrapper.appendChild(btn);

        var heading = targetContainer.querySelector('h3, h2, label');
        if (heading) {
            if (heading.nextSibling) {
                heading.parentNode.insertBefore(btnWrapper, heading.nextSibling);
            } else {
                heading.parentNode.appendChild(btnWrapper);
            }
        } else {
            targetContainer.insertBefore(btnWrapper, targetContainer.firstChild);
        }

        if (!document.getElementById('itrblueboost-cat-content-modal')) {
            injectModal();
        }
    }

    function findDescriptionContainer() {
        // Try category_description container
        var container = document.getElementById('category_description');
        if (container) {
            return resolveContainer(container);
        }

        // Try textarea by name
        var textarea = document.querySelector('textarea[name*="category[description]"]:not([name*="additional_description"]):not([name*="meta_description"])');
        if (textarea) {
            return findFieldContainer(textarea);
        }

        // Try autoload_rte
        var rteFields = document.querySelectorAll('textarea.autoload_rte');
        for (var i = 0; i < rteFields.length; i++) {
            var el = rteFields[i];
            if ((el.id && el.id.indexOf('meta_') !== -1) || (el.name && el.name.indexOf('meta_') !== -1)) {
                continue;
            }
            if (el.id && el.id.indexOf('description') !== -1) {
                return findFieldContainer(el);
            }
        }

        return null;
    }

    function resolveContainer(el) {
        var inlineTags = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LABEL', 'SPAN', 'A'];

        if (inlineTags.indexOf(el.tagName) === -1) {
            return el;
        }

        var parent = el.parentElement;
        while (parent) {
            if (parent.querySelector('textarea, .mce-tinymce, .autoload_rte')) {
                return parent;
            }
            if (parent.classList && (parent.classList.contains('card-body') || parent.classList.contains('form-group') || parent.classList.contains('card'))) {
                return parent;
            }
            parent = parent.parentElement;
        }

        return el.parentElement;
    }

    function findFieldContainer(el) {
        var container = el.closest('.translations.tabbable');
        if (container && container.parentElement) {
            return container.parentElement;
        }

        container = el.closest('.card-body');
        if (container) {
            return container;
        }

        container = el.closest('.form-group');
        if (container) {
            return container;
        }

        return el.parentElement;
    }

    function injectModal() {
        var t = window.itrblueboostCategoryContentTranslations || {};
        var modalHtml = '<div class="modal fade itrblueboost-modal" id="itrblueboost-cat-content-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="material-icons">auto_awesome</i> ' + (t.modalTitle || 'Generate content with AI') + '</h5>' +
            '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div id="itrblueboost-cat-content-loading" class="text-center py-4">' +
            '<div class="spinner-border text-primary" role="status"></div>' +
            '<p class="mt-2">' + (t.loadingPrompts || 'Loading available prompts...') + '</p>' +
            '</div>' +
            '<div id="itrblueboost-cat-content-error" class="alert alert-danger d-none"></div>' +
            '<div id="itrblueboost-cat-content-prompts" class="d-none">' +
            '<p class="mb-3">' + (t.selectPrompt || 'Select a prompt to generate content:') + '</p>' +
            '<select class="form-control" id="itrblueboost-cat-content-prompt-select">' +
            '<option value="">-- ' + (t.choosePrompt || 'Choose a prompt') + ' --</option>' +
            '</select>' +
            '<small class="form-text text-muted mt-2" id="itrblueboost-cat-content-prompt-description"></small>' +
            '</div>' +
            '<div id="itrblueboost-cat-content-progress" class="d-none text-center py-4">' +
            '<div class="spinner-border text-success" role="status"></div>' +
            '<p class="mt-2">' + (t.generating || 'Generating... This may take a few seconds.') + '</p>' +
            '</div>' +
            '<div id="itrblueboost-cat-content-result" class="d-none"></div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">' + (t.close || 'Close') + '</button>' +
            '<button type="button" class="btn btn-success" id="itrblueboost-cat-content-confirm" disabled>' +
            '<i class="material-icons">auto_awesome</i> ' + (t.generate || 'Generate') +
            '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        var promptSelect = document.getElementById('itrblueboost-cat-content-prompt-select');
        var confirmBtn = document.getElementById('itrblueboost-cat-content-confirm');
        var promptDescription = document.getElementById('itrblueboost-cat-content-prompt-description');

        promptSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            promptDescription.textContent = selectedOption.dataset.description || '';
            confirmBtn.disabled = !this.value;
        });

        confirmBtn.addEventListener('click', function() {
            generateContent();
        });
    }

    function openGenerateModal() {
        var promptsUrl = window.itrblueboostCategoryContentPromptsUrl;

        var modal = document.getElementById('itrblueboost-cat-content-modal');
        var loading = document.getElementById('itrblueboost-cat-content-loading');
        var error = document.getElementById('itrblueboost-cat-content-error');
        var prompts = document.getElementById('itrblueboost-cat-content-prompts');
        var progress = document.getElementById('itrblueboost-cat-content-progress');
        var result = document.getElementById('itrblueboost-cat-content-result');
        var promptSelect = document.getElementById('itrblueboost-cat-content-prompt-select');
        var confirmBtn = document.getElementById('itrblueboost-cat-content-confirm');

        loading.classList.remove('d-none');
        error.classList.add('d-none');
        prompts.classList.add('d-none');
        progress.classList.add('d-none');
        result.classList.add('d-none');
        confirmBtn.disabled = true;
        confirmBtn.classList.remove('d-none');
        promptSelect.innerHTML = '<option value="">-- Choisir un prompt --</option>';

        $('#itrblueboost-cat-content-modal').modal('show');

        fetch(promptsUrl, {
            method: 'GET',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            loading.classList.add('d-none');

            if (!data.success) {
                error.textContent = data.message || 'Erreur lors du chargement des prompts.';
                error.classList.remove('d-none');
                return;
            }

            var promptList = data.prompts || [];
            if (promptList.length === 0) {
                error.textContent = 'Aucun prompt disponible.';
                error.classList.remove('d-none');
                return;
            }

            promptList.forEach(function(prompt) {
                var option = document.createElement('option');
                option.value = prompt.id;
                option.textContent = prompt.title;
                option.dataset.description = prompt.short_description || '';
                promptSelect.appendChild(option);
            });

            prompts.classList.remove('d-none');
        })
        .catch(function() {
            loading.classList.add('d-none');
            error.textContent = 'Erreur de connexion.';
            error.classList.remove('d-none');
        });
    }

    function generateContent() {
        var prompts = document.getElementById('itrblueboost-cat-content-prompts');
        var progress = document.getElementById('itrblueboost-cat-content-progress');
        var result = document.getElementById('itrblueboost-cat-content-result');
        var confirmBtn = document.getElementById('itrblueboost-cat-content-confirm');
        var promptSelect = document.getElementById('itrblueboost-cat-content-prompt-select');
        var generateUrl = window.itrblueboostCategoryContentGenerateUrl;

        var promptId = promptSelect.value;
        if (!promptId) {
            return;
        }

        prompts.classList.add('d-none');
        confirmBtn.classList.add('d-none');
        progress.classList.remove('d-none');

        var formData = new FormData();
        formData.append('prompt_id', promptId);

        fetch(generateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            progress.classList.add('d-none');

            if (data.success) {
                var previewHtml = '<div class="alert alert-success">' +
                    '<i class="material-icons">check_circle</i> ' + data.message +
                    '<br><small>Cr\u00e9dits utilis\u00e9s: ' + data.credits_used + ' | Cr\u00e9dits restants: ' + data.credits_remaining + '</small>' +
                    '</div>';

                if (data.description) {
                    previewHtml += '<div class="form-group mt-3">' +
                        '<label><strong>Description :</strong></label>' +
                        '<div class="border p-3 bg-light" style="max-height: 150px; overflow-y: auto;">' + data.description + '</div>' +
                        '</div>';
                }

                if (data.description_short) {
                    previewHtml += '<div class="form-group mt-3">' +
                        '<label><strong>Description additionnelle :</strong></label>' +
                        '<div class="border p-3 bg-light" style="max-height: 150px; overflow-y: auto;">' + data.description_short + '</div>' +
                        '</div>';
                }

                previewHtml += '<button type="button" class="btn btn-primary mt-2" id="itrblueboost-insert-cat-content">' +
                    '<i class="material-icons">add</i> Ins\u00e9rer dans les champs' +
                    '</button>';

                result.innerHTML = previewHtml;
                result.classList.remove('d-none');

                document.getElementById('itrblueboost-insert-cat-content').addEventListener('click', function() {
                    var insertBtn = this;
                    insertBtn.disabled = true;
                    insertBtn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Validation...';

                    acceptContentOnApi(data.content_id, function() {
                        if (data.description) {
                            insertContentIntoField(data.description, 'description');
                        }
                        if (data.description_short) {
                            insertContentIntoField(data.description_short, 'additional_description');
                        }
                        $('#itrblueboost-cat-content-modal').modal('hide');
                    });
                });
            } else {
                result.innerHTML = '<div class="alert alert-danger">' +
                    '<i class="material-icons">error</i> ' + (data.message || 'Erreur') +
                    '</div>';
                result.classList.remove('d-none');
                prompts.classList.remove('d-none');
                confirmBtn.classList.remove('d-none');
            }
        })
        .catch(function() {
            progress.classList.add('d-none');
            result.innerHTML = '<div class="alert alert-danger">Erreur de connexion.</div>';
            result.classList.remove('d-none');
            prompts.classList.remove('d-none');
            confirmBtn.classList.remove('d-none');
        });
    }

    function insertContentIntoField(content, fieldType) {
        var selectors = getFieldSelectors(fieldType);
        var inserted = false;

        for (var i = 0; i < selectors.length; i++) {
            var fields = document.querySelectorAll(selectors[i]);

            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];

                if ((field.id && field.id.indexOf('meta_') !== -1) || (field.name && field.name.indexOf('meta_') !== -1)) {
                    continue;
                }

                if (typeof tinymce !== 'undefined' && field.id) {
                    var editor = tinymce.get(field.id);
                    if (editor) {
                        editor.setContent(content);
                        editor.save();
                        editor.fire('change');
                        field.value = content;
                        field.dispatchEvent(new Event('change', { bubbles: true }));
                        inserted = true;
                        continue;
                    }
                }

                if (field.tagName === 'TEXTAREA' || field.tagName === 'INPUT') {
                    field.value = content;
                    field.dispatchEvent(new Event('change', { bubbles: true }));
                    inserted = true;
                }
            }

            if (inserted) {
                break;
            }
        }
    }

    function getFieldSelectors(fieldType) {
        if (fieldType === 'additional_description') {
            return [
                'textarea[name*="category[additional_description]"]',
                'textarea[id*="additional_description"]:not([id*="meta_"])',
                'textarea[name*="[additional_description]"]:not([name*="meta_"])'
            ];
        }

        return [
            'textarea[name*="category[description]"]:not([name*="additional_description"]):not([name*="meta_description"])',
            'textarea[id*="category_description_"]:not([id*="additional"]):not([id*="meta_"])',
            'textarea[id*="_description_"]:not([id*="additional"]):not([id*="meta_"]):not([id*="short"])'
        ];
    }

    function acceptContentOnApi(contentId, callback) {
        var acceptUrl = window.itrblueboostCategoryContentAcceptUrl;

        if (!acceptUrl || !contentId) {
            callback(false);
            return;
        }

        var url = acceptUrl.replace('/0/accept', '/' + contentId + '/accept');

        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            callback(data.success || false);
        })
        .catch(function() {
            callback(false);
        });
    }

})();
