/**
 * ITROOM API - Admin Product Content Inline Buttons
 * Injects "Generate Content" buttons next to description fields in PS8/PS1.7 product form
 * Compatible with PrestaShop 8.x and 1.7.8+
 */
(function() {
    'use strict';

    // Wait for DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        console.log('[ITRBLUEBOOST-CONTENT] DOMContentLoaded - checking page...');

        if (!isProductEditPage()) {
            console.log('[ITRBLUEBOOST-CONTENT] Not a product edit page, skipping.');
            return;
        }

        console.log('[ITRBLUEBOOST-CONTENT] Product edit page detected, waiting for description fields...');

        waitForDescriptionFields(function() {
            console.log('[ITRBLUEBOOST-CONTENT] Description fields found, injecting buttons...');
            injectGenerateButtons();
        });
    });

    /**
     * Check if we are on the product edit page
     */
    function isProductEditPage() {
        var url = window.location.href;

        // PS8 / PS1.7.8+ Symfony product page
        var isProductsPage = url.indexOf('/sell/catalog/products/') !== -1
            || url.indexOf('/sell/catalog/products-v2/') !== -1;

        if (isProductsPage) {
            var hasProductId = /\/products(-v2)?\/\d+/.test(url);
            var isListPage = /\/products(-v2)?\/?(\?|$)/.test(url)
                || url.indexOf('/products/new') !== -1;

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
     * Wait for description fields to be available
     */
    function waitForDescriptionFields(callback) {
        var maxAttempts = 100;
        var attempts = 0;

        var interval = setInterval(function() {
            attempts++;

            // Strategy 1: Container divs (most reliable)
            var descContainer = document.getElementById('description');
            var descShortContainer = document.getElementById('description_short');

            // Strategy 2: PS 1.7.8+ field IDs (product_step1_description_X)
            var descStep1 = document.querySelector('[id^="product_step1_description_"]:not([id*="description_short"])');
            var descShortStep1 = document.querySelector('[id^="product_step1_description_short_"]');

            // Strategy 3: PS8 field selectors
            var descPS8 = document.querySelector('[name*="product[description]"]:not([name*="description_short"])');
            var descShortPS8 = document.querySelector('[name*="product[description_short]"]');

            // Strategy 4: TinyMCE wrapper
            var tinyMCEDesc = document.querySelector('.mce-tinymce');

            // Strategy 5: autoload_rte class (PS 1.7.8 TinyMCE textareas)
            var rteField = document.querySelector('.autoload_rte');

            if (descContainer || descShortContainer || descStep1 || descShortStep1
                || descPS8 || descShortPS8 || tinyMCEDesc || rteField) {
                clearInterval(interval);
                // Wait for TinyMCE to fully initialize
                setTimeout(callback, 800);
                return;
            }

            if (attempts >= maxAttempts) {
                clearInterval(interval);
                console.log('[ITRBLUEBOOST-CONTENT] Description fields not found after ' + maxAttempts + ' attempts');
            }
        }, 100);
    }

    /**
     * Inject generate buttons next to description fields
     */
    function injectGenerateButtons() {
        var productId = window.itrblueboostProductId;
        var promptsUrl = window.itrblueboostContentPromptsUrl;
        var generateUrl = window.itrblueboostContentGenerateUrl;

        if (!productId || !promptsUrl || !generateUrl) {
            console.log('[ITRBLUEBOOST-CONTENT] Missing configuration:',
                'productId=' + productId,
                'promptsUrl=' + promptsUrl,
                'generateUrl=' + generateUrl
            );
            return;
        }

        // Find description fields
        var descriptionFields = findDescriptionFields();

        descriptionFields.forEach(function(fieldInfo) {
            injectButtonForField(fieldInfo, promptsUrl, generateUrl, productId);
        });

        // Inject modal if not exists
        if (!document.getElementById('itrblueboost-content-modal')) {
            injectModal();
        }
    }

    /**
     * Find all description fields in the form
     */
    function findDescriptionFields() {
        var fields = [];

        // Strategy 1: Container IDs (most reliable for PS 1.7.8+)
        var descContainerById = document.getElementById('description');
        var descShortContainerById = document.getElementById('description_short');

        if (descContainerById) {
            // PS8: #description can be an h3, use parent as container
            var descContainer = resolveContainer(descContainerById);
            fields.push({
                container: descContainer,
                type: 'description',
                label: 'Description'
            });
            console.log('[ITRBLUEBOOST-CONTENT] Found description container by #description (tag: ' + descContainerById.tagName + ')');
        }

        if (descShortContainerById) {
            var descShortContainer = resolveContainer(descShortContainerById);
            fields.push({
                container: descShortContainer,
                type: 'description_short',
                label: 'Description courte'
            });
            console.log('[ITRBLUEBOOST-CONTENT] Found description_short container by #description_short (tag: ' + descShortContainerById.tagName + ')');
        }

        if (fields.length > 0) {
            console.log('[ITRBLUEBOOST-CONTENT] Found ' + fields.length + ' fields via container IDs');
            return fields;
        }

        // Strategy 2: PS 1.7.8+ field IDs (product_step1_description_X)
        var descStep1 = document.querySelector('[id^="product_step1_description_"]:not([id*="description_short"])');
        if (descStep1) {
            var container = findFieldContainer(descStep1);
            if (container) {
                fields.push({
                    container: container,
                    type: 'description',
                    label: 'Description'
                });
                console.log('[ITRBLUEBOOST-CONTENT] Found description via product_step1 selector');
            }
        }

        var descShortStep1 = document.querySelector('[id^="product_step1_description_short_"]');
        if (descShortStep1) {
            var container = findFieldContainer(descShortStep1);
            if (container) {
                fields.push({
                    container: container,
                    type: 'description_short',
                    label: 'Description courte'
                });
                console.log('[ITRBLUEBOOST-CONTENT] Found description_short via product_step1 selector');
            }
        }

        if (fields.length > 0) {
            console.log('[ITRBLUEBOOST-CONTENT] Found ' + fields.length + ' fields via step1 selectors');
            return fields;
        }

        // Strategy 3: Broad textarea search by ID/name containing "description"
        // Covers PS8 v2 (product_basic_description_X), PS8 v1 (product_description_X), etc.
        var allDescTextareas = document.querySelectorAll('textarea[id*="_description_"], textarea[name*="[description]"]');
        var foundDesc = false;
        var foundDescShort = false;

        for (var i = 0; i < allDescTextareas.length; i++) {
            var el = allDescTextareas[i];
            var elId = el.id || '';
            var elName = el.name || '';
            var isShort = elId.indexOf('description_short') !== -1
                || elName.indexOf('description_short') !== -1;

            if (isShort && !foundDescShort) {
                var container = findFieldContainer(el);
                if (container) {
                    foundDescShort = true;
                    fields.push({
                        container: container,
                        type: 'description_short',
                        label: 'Description courte'
                    });
                    console.log('[ITRBLUEBOOST-CONTENT] Found description_short via broad textarea selector (id=' + elId + ')');
                }
            } else if (!isShort && !foundDesc) {
                var container = findFieldContainer(el);
                if (container) {
                    foundDesc = true;
                    fields.push({
                        container: container,
                        type: 'description',
                        label: 'Description'
                    });
                    console.log('[ITRBLUEBOOST-CONTENT] Found description via broad textarea selector (id=' + elId + ')');
                }
            }
        }

        if (fields.length > 0) {
            console.log('[ITRBLUEBOOST-CONTENT] Found ' + fields.length + ' fields via broad textarea selectors');
            return fields;
        }

        // Strategy 4: PS 1.7 legacy selectors (form_step1_description_X)
        var descPS17 = document.querySelector('[id^="form_step1_description_"]:not([id*="description_short"])');
        if (descPS17) {
            var container = findFieldContainer(descPS17);
            if (container) {
                fields.push({
                    container: container,
                    type: 'description',
                    label: 'Description'
                });
                console.log('[ITRBLUEBOOST-CONTENT] Found description via PS1.7 legacy selector');
            }
        }

        var descShortPS17 = document.querySelector('[id^="form_step1_description_short_"]');
        if (descShortPS17) {
            var container = findFieldContainer(descShortPS17);
            if (container) {
                fields.push({
                    container: container,
                    type: 'description_short',
                    label: 'Description courte'
                });
                console.log('[ITRBLUEBOOST-CONTENT] Found description_short via PS1.7 legacy selector');
            }
        }

        // Strategy 5: Broad search via .autoload_rte textareas
        if (fields.length === 0) {
            var rteFields = document.querySelectorAll('textarea.autoload_rte');
            console.log('[ITRBLUEBOOST-CONTENT] Broad search: found ' + rteFields.length + ' autoload_rte textareas');

            for (var i = 0; i < rteFields.length; i++) {
                var el = rteFields[i];
                var isShortDesc = (el.id && el.id.indexOf('description_short') !== -1)
                    || (el.name && el.name.indexOf('description_short') !== -1);
                var isDesc = !isShortDesc
                    && ((el.id && el.id.indexOf('description') !== -1)
                    || (el.name && el.name.indexOf('description') !== -1));

                if (isDesc) {
                    var container = findFieldContainer(el);
                    if (container) {
                        fields.push({
                            container: container,
                            type: 'description',
                            label: 'Description'
                        });
                    }
                } else if (isShortDesc) {
                    var container = findFieldContainer(el);
                    if (container) {
                        fields.push({
                            container: container,
                            type: 'description_short',
                            label: 'Description courte'
                        });
                    }
                }
            }
        }

        console.log('[ITRBLUEBOOST-CONTENT] Total fields found: ' + fields.length);
        return fields;
    }

    /**
     * Resolve a container element: if element is an inline tag (h3, h2, label, span),
     * walk up to find a meaningful block-level parent.
     */
    function resolveContainer(el) {
        var inlineTags = ['H1', 'H2', 'H3', 'H4', 'H5', 'H6', 'LABEL', 'SPAN', 'A'];

        if (inlineTags.indexOf(el.tagName) === -1) {
            return el;
        }

        // Walk up to find a block container that holds more than just the heading
        var parent = el.parentElement;

        while (parent) {
            // Check if this parent holds form fields (textarea, editor, etc.)
            if (parent.querySelector('textarea, .mce-tinymce, .autoload_rte, [class*="editor"]')) {
                return parent;
            }

            // PS8: card-body, form-group, or similar wrappers
            if (parent.classList && (
                parent.classList.contains('card-body')
                || parent.classList.contains('form-group')
                || parent.classList.contains('card')
                || parent.classList.contains('product-description')
            )) {
                return parent;
            }

            parent = parent.parentElement;
        }

        // Fallback: use the heading's parent
        return el.parentElement;
    }

    /**
     * Find the best container element for a field
     */
    function findFieldContainer(el) {
        // Try to find a meaningful wrapper
        var container = el.closest('.translations.tabbable');
        if (container && container.parentElement) {
            return container.parentElement;
        }

        // PS8: look for card-body wrapper
        container = el.closest('.card-body');
        if (container) {
            return container;
        }

        container = el.closest('.form-group');
        if (container) {
            return container;
        }

        container = el.closest('.translation-field');
        if (container && container.parentElement && container.parentElement.parentElement) {
            return container.parentElement.parentElement;
        }

        return el.parentElement;
    }

    /**
     * Inject button for a specific field
     */
    function injectButtonForField(fieldInfo, promptsUrl, generateUrl, productId) {
        // Check if button already exists
        if (fieldInfo.container.querySelector('.itrblueboost-generate-content-btn')) {
            return;
        }

        // Create button
        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'btn btn-outline-secondary itrblueboost-generate-content-btn';
        btn.style.cssText = 'margin: 5px 0; display: inline-flex; align-items: center; gap: 5px;';
        btn.innerHTML = '<i class="material-icons" style="font-size: 18px;">auto_awesome</i> <span>G\u00e9n\u00e9rer (' + fieldInfo.label + ')</span>';
        btn.dataset.contentType = fieldInfo.type;
        btn.dataset.promptsUrl = promptsUrl;
        btn.dataset.generateUrl = generateUrl;
        btn.dataset.productId = productId;

        // Add click handler
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            openGenerateModal(this);
        });

        // Find the best place to insert the button
        var btnWrapper = document.createElement('div');
        btnWrapper.className = 'itrblueboost-btn-wrapper';
        btnWrapper.style.cssText = 'margin-bottom: 10px;';
        btnWrapper.appendChild(btn);

        // Try to insert before the translations wrapper or at the top of container
        var translationsWrapper = fieldInfo.container.querySelector('.translations.tabbable');
        if (translationsWrapper) {
            fieldInfo.container.insertBefore(btnWrapper, translationsWrapper);
            console.log('[ITRBLUEBOOST-CONTENT] Button injected before translations wrapper for ' + fieldInfo.type);
            return;
        }

        // PS8: look for h3 heading inside the container, insert after it
        var heading = fieldInfo.container.querySelector('h3, h2');
        if (heading) {
            if (heading.nextSibling) {
                heading.parentNode.insertBefore(btnWrapper, heading.nextSibling);
            } else {
                heading.parentNode.appendChild(btnWrapper);
            }
            console.log('[ITRBLUEBOOST-CONTENT] Button injected after heading for ' + fieldInfo.type);
            return;
        }

        var label = fieldInfo.container.querySelector('label');
        if (label) {
            if (label.nextSibling) {
                label.parentNode.insertBefore(btnWrapper, label.nextSibling);
            } else {
                label.parentNode.appendChild(btnWrapper);
            }
            console.log('[ITRBLUEBOOST-CONTENT] Button injected after label for ' + fieldInfo.type);
            return;
        }

        // Fallback: insert at the beginning of container
        fieldInfo.container.insertBefore(btnWrapper, fieldInfo.container.firstChild);
        console.log('[ITRBLUEBOOST-CONTENT] Button injected at top of container for ' + fieldInfo.type);
    }

    /**
     * Inject modal for content generation
     */
    function injectModal() {
        var t = window.itrblueboostContentTranslations || {};
        var modalHtml = '<div class="modal fade itrblueboost-modal" id="itrblueboost-content-modal" tabindex="-1" role="dialog" aria-hidden="true">' +
            '<div class="modal-dialog" role="document">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="material-icons">auto_awesome</i> ' + (t.modalTitle || 'Generate content with AI') + '</h5>' +
            '<button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<div id="itrblueboost-content-loading" class="text-center py-4">' +
            '<div class="spinner-border text-primary" role="status"></div>' +
            '<p class="mt-2">' + (t.loadingPrompts || 'Loading available prompts...') + '</p>' +
            '</div>' +
            '<div id="itrblueboost-content-error" class="alert alert-danger d-none"></div>' +
            '<div id="itrblueboost-content-prompts" class="d-none">' +
            '<p class="mb-3">' + (t.selectPrompt || 'Select a prompt to generate content:') + '</p>' +
            '<select class="form-control" id="itrblueboost-content-prompt-select">' +
            '<option value="">-- ' + (t.choosePrompt || 'Choose a prompt') + ' --</option>' +
            '</select>' +
            '<small class="form-text text-muted mt-2" id="itrblueboost-content-prompt-description"></small>' +
            '</div>' +
            '<div id="itrblueboost-content-progress" class="d-none text-center py-4">' +
            '<div class="spinner-border text-success" role="status"></div>' +
            '<p class="mt-2">' + (t.generating || 'Generating... This may take a few seconds.') + '</p>' +
            '</div>' +
            '<div id="itrblueboost-content-result" class="d-none"></div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-dismiss="modal">' + (t.close || 'Close') + '</button>' +
            '<button type="button" class="btn btn-success" id="itrblueboost-content-confirm" disabled>' +
            '<i class="material-icons">auto_awesome</i> ' + (t.generate || 'Generate') +
            '</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';

        document.body.insertAdjacentHTML('beforeend', modalHtml);

        // Add event handlers
        var promptSelect = document.getElementById('itrblueboost-content-prompt-select');
        var confirmBtn = document.getElementById('itrblueboost-content-confirm');
        var promptDescription = document.getElementById('itrblueboost-content-prompt-description');

        promptSelect.addEventListener('change', function() {
            var selectedOption = this.options[this.selectedIndex];
            promptDescription.textContent = selectedOption.dataset.description || '';
            confirmBtn.disabled = !this.value;
        });

        confirmBtn.addEventListener('click', function() {
            generateContent();
        });
    }

    var currentContentType = '';
    var currentGenerateUrl = '';

    /**
     * Open generate modal
     */
    function openGenerateModal(btn) {
        var promptsUrl = btn.dataset.promptsUrl;
        currentGenerateUrl = btn.dataset.generateUrl;
        currentContentType = btn.dataset.contentType;

        var modal = document.getElementById('itrblueboost-content-modal');
        var loading = document.getElementById('itrblueboost-content-loading');
        var error = document.getElementById('itrblueboost-content-error');
        var prompts = document.getElementById('itrblueboost-content-prompts');
        var progress = document.getElementById('itrblueboost-content-progress');
        var result = document.getElementById('itrblueboost-content-result');
        var promptSelect = document.getElementById('itrblueboost-content-prompt-select');
        var confirmBtn = document.getElementById('itrblueboost-content-confirm');

        // Reset state
        loading.classList.remove('d-none');
        error.classList.add('d-none');
        prompts.classList.add('d-none');
        progress.classList.add('d-none');
        result.classList.add('d-none');
        confirmBtn.disabled = true;
        confirmBtn.classList.remove('d-none');
        promptSelect.innerHTML = '<option value="">-- Choisir un prompt --</option>';

        showModal();

        // Load prompts
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

    /**
     * Generate content
     */
    function generateContent() {
        var prompts = document.getElementById('itrblueboost-content-prompts');
        var progress = document.getElementById('itrblueboost-content-progress');
        var result = document.getElementById('itrblueboost-content-result');
        var confirmBtn = document.getElementById('itrblueboost-content-confirm');
        var promptSelect = document.getElementById('itrblueboost-content-prompt-select');

        var promptId = promptSelect.value;
        if (!promptId) {
            return;
        }

        prompts.classList.add('d-none');
        confirmBtn.classList.add('d-none');
        progress.classList.remove('d-none');

        var formData = new FormData();
        formData.append('prompt_id', promptId);
        formData.append('content_type', currentContentType);

        fetch(currentGenerateUrl, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            progress.classList.add('d-none');

            if (data.success) {
                result.innerHTML = '<div class="alert alert-success">' +
                    '<i class="material-icons">check_circle</i> ' + data.message +
                    '<br><small>Cr\u00e9dits utilis\u00e9s: ' + data.credits_used + ' | Cr\u00e9dits restants: ' + data.credits_remaining + '</small>' +
                    '</div>' +
                    '<div class="form-group mt-3">' +
                    '<label>Contenu g\u00e9n\u00e9r\u00e9:</label>' +
                    '<div class="border p-3 bg-light" style="max-height: 200px; overflow-y: auto;">' + (data.content || '') + '</div>' +
                    '</div>' +
                    '<button type="button" class="btn btn-primary mt-2" id="itrblueboost-insert-content">' +
                    '<i class="material-icons">add</i> Ins\u00e9rer dans le champ' +
                    '</button>';

                result.classList.remove('d-none');

                // Add insert handler
                document.getElementById('itrblueboost-insert-content').addEventListener('click', function() {
                    var insertBtn = this;
                    insertBtn.disabled = true;
                    insertBtn.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"></div> Validation...';

                    acceptContentOnApi(data.content_id, function(success) {
                        insertContentIntoField(data.content, currentContentType);
                        hideModal();

                        if (!success) {
                            console.warn('[ITRBLUEBOOST-CONTENT] API accept failed, content inserted locally only');
                        }
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

    /**
     * Insert content into field
     */
    function insertContentIntoField(content, contentType) {
        var inserted = false;

        // Build selectors for description or description_short
        var selectors = getFieldSelectors(contentType);

        for (var i = 0; i < selectors.length; i++) {
            var fields = document.querySelectorAll(selectors[i]);

            for (var j = 0; j < fields.length; j++) {
                var field = fields[j];

                // Skip if this is description_short and we want description (or vice versa)
                if (contentType !== 'description_short') {
                    if ((field.id && field.id.indexOf('description_short') !== -1)
                        || (field.name && field.name.indexOf('description_short') !== -1)) {
                        continue;
                    }
                }

                // Try TinyMCE first
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

                // Regular textarea/input
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

        console.log('[ITRBLUEBOOST-CONTENT] Content inserted into ' + contentType + ': ' + (inserted ? 'YES' : 'NO'));
    }

    /**
     * Get field selectors for a given content type
     */
    function getFieldSelectors(contentType) {
        if (contentType === 'description_short') {
            return [
                // PS 1.7.8+
                '[id^="product_step1_description_short_"]',
                // PS8 v1
                '[name*="product[description_short]"]',
                '[id*="product_description_short"]',
                // PS8 v2 (product_basic_description_short_X)
                'textarea[id*="_description_short_"]',
                'textarea[name*="[description_short]"]',
                // PS 1.7 legacy
                '[id^="form_step1_description_short_"]'
            ];
        }

        return [
            // PS 1.7.8+
            '[id^="product_step1_description_"]:not([id*="description_short"])',
            // PS8 v1
            '[name*="product[description]"]:not([name*="description_short"])',
            '[id*="product_description_"]:not([id*="description_short"])',
            // PS8 v2 (product_basic_description_X)
            'textarea[id*="_description_"]:not([id*="description_short"])',
            'textarea[name*="[description]"]:not([name*="description_short"])',
            // PS 1.7 legacy
            '[id^="form_step1_description_"]:not([id*="description_short"])'
        ];
    }

    /**
     * Accept content on API via the accept endpoint
     */
    function acceptContentOnApi(contentId, callback) {
        var acceptUrl = window.itrblueboostContentAcceptUrl;

        if (!acceptUrl || !contentId) {
            console.warn('[ITRBLUEBOOST-CONTENT] Missing acceptUrl or contentId, skipping API accept');
            callback(false);
            return;
        }

        // Replace the placeholder contentId (0) with the real one
        var url = acceptUrl.replace('/0/accept', '/' + contentId + '/accept');

        fetch(url, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(response) { return response.json(); })
        .then(function(data) {
            if (data.success) {
                console.log('[ITRBLUEBOOST-CONTENT] Content ' + contentId + ' accepted via API');
            } else {
                console.warn('[ITRBLUEBOOST-CONTENT] API accept error: ' + (data.message || 'Unknown'));
            }
            callback(data.success || false);
        })
        .catch(function(error) {
            console.error('[ITRBLUEBOOST-CONTENT] Accept request failed:', error);
            callback(false);
        });
    }

    /**
     * Show modal
     */
    function showModal() {
        $('#itrblueboost-content-modal').modal('show');
    }

    /**
     * Hide modal
     */
    function hideModal() {
        $('#itrblueboost-content-modal').modal('hide');
    }

})();
