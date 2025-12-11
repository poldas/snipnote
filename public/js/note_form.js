/**
 * Note Form Management
 * Handles form state, validation, user interactions, and API integration
 */

(function () {
    'use strict';

    const defaultConfig = {
        mode: 'create',
        noteId: null,
        submitUrl: '/api/notes',
        redirectUrl: '/notes',
        previewUrl: '/api/notes/preview',
        initialTitle: '',
        initialDescription: '',
        initialLabels: [],
        initialVisibility: 'private'
    };

    // Filled lazily in init() once the form element is available
    let config = defaultConfig;

    // ========== DOM Elements ==========
    const elements = {
        form: null,
        titleInput: null,
        titleCounter: null,
        titleError: null,
        descriptionTextarea: null,
        descriptionCounter: null,
        descriptionError: null,
        visibilityInputs: null,
        visibilityDescription: null,
        visibilityError: null,
        tagInput: null,
        addTagBtn: null,
        tagsContainer: null,
        labelsInput: null,
        labelsError: null,
        formErrors: null,
        errorList: null,
        previewBtn: null,
        submitBtn: null,
        statusIndicator: null,
        savingSpinner: null,
        previewSection: null,
        previewContent: null,
        closePreview: null,
        markdownToolbar: null,
        ariaLive: null,
        csrfToken: null
    };

    function refreshElements() {
        elements.form = document.querySelector('[data-note-form]');
        elements.titleInput = document.querySelector('[data-title-input]');
        elements.titleCounter = document.querySelector('[data-title-counter]');
        elements.titleError = document.querySelector('[data-title-error]');
        elements.descriptionTextarea = document.querySelector('[data-description-textarea]');
        elements.descriptionCounter = document.querySelector('[data-description-counter]');
        elements.descriptionError = document.querySelector('[data-description-error]');
        elements.visibilityInputs = document.querySelectorAll('[data-visibility-input]');
        elements.visibilityDescription = document.querySelector('[data-visibility-description]');
        elements.visibilityError = document.querySelector('[data-visibility-error]');
        elements.tagInput = document.querySelector('[data-tag-input]');
        elements.addTagBtn = document.querySelector('[data-add-tag-btn]');
        elements.tagsContainer = document.querySelector('[data-tags-container]');
        elements.labelsInput = document.querySelector('[data-labels-input]');
        elements.labelsError = document.querySelector('[data-labels-error]');
        elements.formErrors = document.querySelector('[data-form-errors]');
        elements.errorList = document.querySelector('[data-error-list]');
        elements.previewBtn = document.querySelector('[data-preview-btn]');
        elements.submitBtn = document.querySelector('[data-submit-btn]');
        elements.statusIndicator = document.querySelector('[data-status-indicator]');
        elements.savingSpinner = document.querySelector('[data-saving-spinner]');
        elements.previewSection = document.querySelector('[data-preview-section]');
        elements.previewContent = document.querySelector('[data-preview-content]');
        elements.closePreview = document.querySelector('[data-close-preview]');
        elements.markdownToolbar = document.querySelector('[data-markdown-toolbar]');
        elements.ariaLive = document.querySelector('[data-global-aria-live]');
        elements.csrfToken = document.querySelector('[data-csrf-token]');
    }

    // ========== State Management ==========
    const formState = {
        title: '',
        description: '',
        labels: [],
        visibility: config.initialVisibility || 'private',
        isSubmitting: false,
        isPreviewing: false,
        errors: {
            title: [],
            description: [],
            labels: [],
            visibility: [],
            _request: []
        }
    };

    // ========== Validation ==========
    function validateField(field, value) {
        const errors = [];

        switch (field) {
            case 'title':
                if (!value || value.trim() === '') {
                    errors.push('Tytuł jest wymagany');
                }
                if (value.length > 255) {
                    errors.push('Tytuł nie może przekraczać 255 znaków');
                }
                break;

            case 'description':
                if (!value || value.trim() === '') {
                    errors.push('Opis jest wymagany');
                }
                if (value.length > 10000) {
                    errors.push('Opis nie może przekraczać 10000 znaków');
                }
                break;

            case 'visibility':
                if (!['private', 'public', 'draft'].includes(value)) {
                    errors.push('Nieprawidłowa wartość widoczności');
                }
                break;

            case 'labels':
                // Labels are optional, no required validation
                break;
        }

        return errors;
    }

    function validateForm() {
        const errors = {
            title: validateField('title', formState.title),
            description: validateField('description', formState.description),
            visibility: validateField('visibility', formState.visibility),
            labels: [],
            _request: []
        };

        const hasErrors = Object.values(errors).some(fieldErrors => fieldErrors.length > 0);
        return { valid: !hasErrors, errors };
    }

    // ========== Error Display ==========
    function showFieldError(field, messages) {
        const errorElement = elements[`${field}Error`];
        const inputElement = elements[`${field}Input`] || elements[`${field}Textarea`] || elements[`${field}Inputs`]?.[0];

        if (errorElement && messages.length > 0) {
            errorElement.textContent = messages.join(', ');
            errorElement.classList.remove('hidden');

            if (inputElement) {
                inputElement.setAttribute('aria-invalid', 'true');
                inputElement.classList.add('border-red-500', 'focus:ring-red-500');
            }
        } else if (errorElement) {
            errorElement.textContent = '';
            errorElement.classList.add('hidden');

            if (inputElement) {
                inputElement.setAttribute('aria-invalid', 'false');
                inputElement.classList.remove('border-red-500', 'focus:ring-red-500');
            }
        }
    }

    function clearFieldError(field) {
        showFieldError(field, []);
    }

    function showGlobalErrors(errors) {
        if (!elements.formErrors || !elements.errorList) return;

        const allErrors = [];
        Object.entries(errors).forEach(([field, messages]) => {
            if (messages.length > 0) {
                messages.forEach(msg => allErrors.push(msg));
            }
        });

        if (allErrors.length > 0) {
            elements.errorList.innerHTML = allErrors
                .map(err => `<li>${escapeHtml(err)}</li>`)
                .join('');
            elements.formErrors.classList.remove('hidden');
            announce('Wykryto błędy walidacji. Sprawdź formularz.');
        } else {
            elements.formErrors.classList.add('hidden');
        }
    }

    function clearAllErrors() {
        formState.errors = {
            title: [],
            description: [],
            labels: [],
            visibility: [],
            _request: []
        };
        clearFieldError('title');
        clearFieldError('description');
        clearFieldError('visibility');
        clearFieldError('labels');
        if (elements.formErrors) {
            elements.formErrors.classList.add('hidden');
        }
    }

    // ========== Character Counters ==========
    function updateTitleCounter() {
        const length = formState.title.length;
        if (elements.titleCounter) {
            elements.titleCounter.textContent = `${length} / 255`;
            if (length > 255) {
                elements.titleCounter.classList.add('text-red-600', 'font-semibold');
            } else {
                elements.titleCounter.classList.remove('text-red-600', 'font-semibold');
            }
        }
    }

    function updateDescriptionCounter() {
        const length = formState.description.length;
        if (elements.descriptionCounter) {
            elements.descriptionCounter.textContent = `${length} / 10000`;
            if (length > 10000) {
                elements.descriptionCounter.classList.add('text-red-600', 'font-semibold');
            } else {
                elements.descriptionCounter.classList.remove('text-red-600', 'font-semibold');
            }
        }
    }

    // ========== Tag Management ==========
    function deduplicateLabels(labels) {
        const seen = new Set();
        return labels.filter(label => {
            const normalized = label.toLowerCase().trim();
            if (normalized === '' || seen.has(normalized)) {
                return false;
            }
            seen.add(normalized);
            return true;
        });
    }

    function addLabel(label) {
        const trimmed = label.trim();
        if (trimmed === '') return;

        const newLabels = [...formState.labels, trimmed];
        formState.labels = deduplicateLabels(newLabels);
        updateLabelsInput();
        renderTags();

        if (elements.tagInput) {
            elements.tagInput.value = '';
        }
    }

    function removeLabel(index) {
        formState.labels = formState.labels.filter((_, i) => i !== index);
        updateLabelsInput();
        renderTags();
    }

    function renderTags() {
        if (!elements.tagsContainer) return;

        if (formState.labels.length === 0) {
            elements.tagsContainer.innerHTML = '<span class="text-sm text-slate-400">Brak etykiet</span>';
            return;
        }

        elements.tagsContainer.innerHTML = formState.labels
            .map((label, index) => `
                <span 
                    class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-lg text-sm font-medium border border-indigo-200"
                    role="listitem"
                >
                    <span>${escapeHtml(label)}</span>
                    <button
                        type="button"
                        data-remove-tag="${index}"
                        aria-label="Usuń etykietę ${escapeHtml(label)}"
                        class="text-indigo-600 hover:text-indigo-900 font-bold"
                    >
                        ✕
                    </button>
                </span>
            `)
            .join('');

        // Attach event listeners to remove buttons
        elements.tagsContainer.querySelectorAll('[data-remove-tag]').forEach(btn => {
            btn.addEventListener('click', function () {
                const index = parseInt(this.getAttribute('data-remove-tag'), 10);
                removeLabel(index);
            });
        });
    }

    function updateLabelsInput() {
        if (elements.labelsInput) {
            elements.labelsInput.value = JSON.stringify(formState.labels);
        }
    }

    // ========== Markdown Toolbar ==========
    function insertMarkdown(action) {
        if (!elements.descriptionTextarea) return;

        const textarea = elements.descriptionTextarea;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selectedText = textarea.value.substring(start, end);
        let replacement = '';

        switch (action) {
            case 'bold':
                replacement = `**${selectedText || 'pogrubiony tekst'}**`;
                break;
            case 'italic':
                replacement = `*${selectedText || 'tekst kursywą'}*`;
                break;
            case 'code':
                replacement = `\`${selectedText || 'kod'}\``;
                break;
            case 'link':
                replacement = `[${selectedText || 'tekst linku'}](https://example.com)`;
                break;
            case 'heading':
                replacement = `\n## ${selectedText || 'Nagłówek'}\n`;
                break;
            case 'list':
                replacement = `\n- ${selectedText || 'Element listy'}\n`;
                break;
            case 'quote':
                replacement = `\n> ${selectedText || 'Cytat'}\n`;
                break;
        }

        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);

        // Update state and counter
        formState.description = textarea.value;
        updateDescriptionCounter();

        // Set cursor position
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
        textarea.focus();
    }

    // ========== Visibility Toggle ==========
    function updateVisibilityDescription(value) {
        if (!elements.visibilityDescription) return;

        const descriptions = {
            private: 'Notatka widoczna tylko dla Ciebie i współpracowników.',
            public: 'Notatka będzie widoczna publicznie pod unikalnym linkiem.',
            draft: 'Szkic - notatka niewidoczna dla nikogo poza Tobą.'
        };

        elements.visibilityDescription.textContent = descriptions[value] || descriptions.private;
    }

    function updateVisibilityLabels() {
        document.querySelectorAll('[data-visibility-label]').forEach(label => {
            const value = label.getAttribute('data-visibility-label');
            if (value === formState.visibility) {
                label.classList.add('bg-white', 'text-indigo-700', 'border', 'border-indigo-200', 'shadow-sm');
                label.classList.remove('text-slate-600');
            } else {
                label.classList.remove('bg-white', 'text-indigo-700', 'border', 'border-indigo-200', 'shadow-sm');
                label.classList.add('text-slate-600');
            }
        });
    }

    // ========== API Integration ==========

    function isTokenExpired(token) {
        if (!token) return true;

        try {
            // Decode JWT payload (second part)
            const parts = token.split('.');
            if (parts.length !== 3) return true;

            const payload = JSON.parse(atob(parts[1].replace(/-/g, '+').replace(/_/g, '/')));

            // Check if exp exists and if it's in the future
            if (!payload.exp) return false; // No expiration

            // Add 10 second buffer to account for clock drift
            return payload.exp <= (Date.now() / 1000) + 10;
        } catch (e) {
            console.error('Failed to parse JWT:', e);
            return true;
        }
    }

    function redirectToLogin(message = 'Sesja wygasła. Zaloguj się ponownie.') {
        showToast(message, 'error');
        announce(message);
        setTimeout(() => {
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        }, 1500);
    }

    async function submitNote() {
        // Validate form
        const validation = validateForm();
        if (!validation.valid) {
            formState.errors = validation.errors;
            Object.entries(validation.errors).forEach(([field, messages]) => {
                showFieldError(field, messages);
            });
            showGlobalErrors(validation.errors);
            announce('Formularz zawiera błędy. Popraw je przed zapisaniem.');
            return;
        }

        // Clear errors
        clearAllErrors();

        // Prepare payload
        const payload = {
            title: formState.title.trim(),
            description: formState.description.trim(),
            labels: formState.labels.map(l => l.trim()).filter(l => l !== ''),
            visibility: formState.visibility
        };

        // Set submitting state
        formState.isSubmitting = true;
        updateButtonStates();

        const headers = {
            'Content-Type': 'application/json'
        };

        if (elements.csrfToken) {
            headers['X-CSRF-Token'] = elements.csrfToken.value;
        }

        try {
            const targetUrl = config.submitUrl || (config.mode === 'edit' && config.noteId ? `/api/notes/${config.noteId}` : '/api/notes');
            const method = config.mode === 'edit' ? 'PATCH' : 'POST';
            const response = await fetch(targetUrl, {
                method,
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                announce(config.mode === 'edit' ? 'Notatka została zaktualizowana' : 'Notatka została utworzona pomyślnie');
                showToast(config.mode === 'edit' ? 'Zapisano zmiany!' : 'Notatka utworzona!', 'success');

                // Redirect to dashboard or edit page
                setTimeout(() => {
                    window.location.href = config.redirectUrl || '/notes';
                }, 500);
                return;
            }

            // Handle errors
            if (response.status === 401) {
                redirectToLogin('Sesja wygasła lub brak autoryzacji.');
                return;
            }

            if (response.status === 403) {
                showToast('Brak uprawnień do zapisania tej notatki.', 'error');
                announce('Brak uprawnień do zapisania tej notatki.');
                return;
            }

            if (response.status === 400) {
                const errorData = await response.json();
                if (errorData.errors) {
                    formState.errors = Object.assign({}, {
                        title: [],
                        description: [],
                        labels: [],
                        visibility: [],
                        _request: []
                    }, errorData.errors);
                    Object.entries(formState.errors).forEach(([field, messages]) => {
                        if (Array.isArray(messages)) {
                            showFieldError(field, messages);
                        }
                    });
                    showGlobalErrors(formState.errors);
                    announce('Formularz zawiera błędy walidacji');
                }
                return;
            }

            if (response.status === 409) {
                showToast('Kolizja URL. Spróbuj zapisać ponownie.', 'error');
                announce('Kolizja URL. Spróbuj zapisać ponownie.');
                return;
            }

            // Generic error
            showToast('Nie udało się zapisać notatki. Spróbuj ponownie.', 'error');
            announce('Błąd serwera. Spróbuj ponownie.');

        } catch (error) {
            console.error('Submit error:', error);
            showToast('Błąd sieci. Sprawdź połączenie i spróbuj ponownie.', 'error');
            announce('Błąd sieci. Sprawdź połączenie.');
        } finally {
            formState.isSubmitting = false;
            updateButtonStates();
        }
    }

    async function previewNote() {
        // Validate required fields
        const titleErrors = validateField('title', formState.title);
        const descriptionErrors = validateField('description', formState.description);

        if (titleErrors.length > 0 || descriptionErrors.length > 0) {
            showFieldError('title', titleErrors);
            showFieldError('description', descriptionErrors);
            announce('Uzupełnij wymagane pola przed podglądem');
            return;
        }

        clearFieldError('title');
        clearFieldError('description');

        // Prepare payload
        const payload = {
            title: formState.title.trim(),
            description: formState.description.trim(),
            labels: formState.labels,
            visibility: formState.visibility
        };

        formState.isPreviewing = true;
        updateButtonStates();

        const headers = {
            'Content-Type': 'application/json'
        };

        try {
            const targetUrl = config.previewUrl || '/api/notes/preview';
            const response = await fetch(targetUrl, {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin'
            });

            if (response.ok) {
                const data = await response.json();
                const html = data?.data?.html;
                if (html) {
                    renderPreviewHtml(html);
                    announce('Podgląd wygenerowany');
                    return;
                }
                // Brak HTML w odpowiedzi – pokazujemy lokalny fallback
                renderLocalPreview('Brak danych podglądu z serwera, użyto wersji lokalnej.');
                return;
            }

            if (response.status === 401 || response.status === 403) {
                announce('Brak dostępu do podglądu.');
                showToast('Brak dostępu do podglądu.', 'error');
                formState.isPreviewing = false;
                updateButtonStates();
                return;
            }

            // Nieudana odpowiedź – pokaż komunikat i fallback
            renderLocalPreview('Nie udało się pobrać podglądu, pokazano wersję lokalną.');

        } catch (error) {
            console.error('Preview error:', error);
            renderLocalPreview('Błąd podglądu, pokazano wersję lokalną.');
        } finally {
            formState.isPreviewing = false;
            updateButtonStates();
        }
    }

    function renderPreviewHtml(html) {
        if (elements.previewContent) {
            elements.previewContent.innerHTML = html;
        }
        if (elements.previewSection) {
            elements.previewSection.classList.remove('hidden');
            elements.previewSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
    }

    function renderLocalPreview(message) {
        if (elements.previewContent) {
            const basicHtml = escapeHtml(formState.description)
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/`(.+?)`/g, '<code>$1</code>')
                .replace(/\n/g, '<br>');
            elements.previewContent.innerHTML = `<h2>${escapeHtml(formState.title || 'Podgląd')}</h2><div>${basicHtml || '<p class="text-slate-500">(Brak treści)</p>'}</div>`;
        }
        if (elements.previewSection) {
            elements.previewSection.classList.remove('hidden');
            elements.previewSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        announce(message);
        showToast(message, 'info');
    }

    // ========== UI Updates ==========
    function updateButtonStates() {
        if (elements.previewBtn) {
            elements.previewBtn.disabled = formState.isSubmitting || formState.isPreviewing;
        }
        if (elements.submitBtn) {
            elements.submitBtn.disabled = formState.isSubmitting || formState.isPreviewing;
        }

        if (elements.statusIndicator) {
            if (formState.isSubmitting) {
                elements.statusIndicator.textContent = 'Zapisywanie...';
            } else if (formState.isPreviewing) {
                elements.statusIndicator.textContent = 'Generowanie podglądu...';
            } else {
                elements.statusIndicator.textContent = '';
            }
        }

        if (elements.savingSpinner) {
            if (formState.isSubmitting || formState.isPreviewing) {
                elements.savingSpinner.classList.remove('hidden');
            } else {
                elements.savingSpinner.classList.add('hidden');
            }
        }
    }

    // ========== Utilities ==========
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function announce(message) {
        if (elements.ariaLive) {
            elements.ariaLive.textContent = message;
        }
    }

    function showToast(message, variant = 'info') {
        const toastStack = document.getElementById('toast-stack');
        if (!toastStack) return;

        const toast = document.createElement('div');
        toast.className = 'min-w-[240px] max-w-sm rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg flex items-start gap-2 ' +
            (variant === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800');
        toast.textContent = message;
        toastStack.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    // ========== Event Listeners ==========
    function initializeEventListeners() {
        // Title input
        if (elements.titleInput) {
            elements.titleInput.addEventListener('input', function () {
                formState.title = this.value;
                updateTitleCounter();
                clearFieldError('title');
            });

            // Initialize counter
            formState.title = elements.titleInput.value;
            updateTitleCounter();
        }

        // Description textarea
        if (elements.descriptionTextarea) {
            elements.descriptionTextarea.addEventListener('input', function () {
                formState.description = this.value;
                updateDescriptionCounter();
                clearFieldError('description');
            });

            // Initialize counter
            formState.description = elements.descriptionTextarea.value;
            updateDescriptionCounter();
        }

        // Visibility inputs
        elements.visibilityInputs.forEach(input => {
            input.addEventListener('change', function () {
                if (this.checked) {
                    formState.visibility = this.value;
                    updateVisibilityDescription(this.value);
                    updateVisibilityLabels();
                    clearFieldError('visibility');
                }
            });

            // Initialize visibility
            if (input.checked) {
                formState.visibility = input.value;
            }
        });

        // Initialize visibility description
        updateVisibilityDescription(formState.visibility);

        // Tag input
        if (elements.tagInput) {
            elements.tagInput.addEventListener('keydown', function (e) {
                if (e.key === 'Enter' || e.key === ',') {
                    e.preventDefault();
                    addLabel(this.value);
                }
            });

            elements.tagInput.addEventListener('blur', function () {
                if (this.value.trim() !== '') {
                    addLabel(this.value);
                }
            });
        }

        // Add tag button
        if (elements.addTagBtn) {
            elements.addTagBtn.addEventListener('click', function () {
                if (elements.tagInput && elements.tagInput.value.trim() !== '') {
                    addLabel(elements.tagInput.value);
                }
            });
        }

        // Initialize tags display
        renderTags();

        // Markdown toolbar
        if (elements.markdownToolbar) {
            elements.markdownToolbar.addEventListener('click', function (e) {
                const button = e.target.closest('[data-md-action]');
                if (button) {
                    const action = button.getAttribute('data-md-action');
                    insertMarkdown(action);
                }
            });
        }

        // Preview button
        if (elements.previewBtn) {
            elements.previewBtn.addEventListener('click', previewNote);
        }

        // Submit button
        if (elements.submitBtn) {
            elements.submitBtn.addEventListener('click', submitNote);
        }

        // Close preview
        if (elements.closePreview) {
            elements.closePreview.addEventListener('click', function () {
                if (elements.previewSection) {
                    elements.previewSection.classList.add('hidden');
                }
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function (e) {
            // Ctrl/Cmd + B for bold
            if ((e.ctrlKey || e.metaKey) && e.key === 'b') {
                e.preventDefault();
                insertMarkdown('bold');
            }
            // Ctrl/Cmd + I for italic
            if ((e.ctrlKey || e.metaKey) && e.key === 'i') {
                e.preventDefault();
                insertMarkdown('italic');
            }
            // Ctrl/Cmd + K for link
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                insertMarkdown('link');
            }
            // Ctrl/Cmd + S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                submitNote();
            }
        });
    }

    function applyInitialStateFromConfig() {
        if (elements.titleInput) {
            if (elements.titleInput.value === '' && config.initialTitle) {
                elements.titleInput.value = config.initialTitle;
            }
            formState.title = elements.titleInput.value || config.initialTitle || '';
            updateTitleCounter();
        }

        if (elements.descriptionTextarea) {
            if (elements.descriptionTextarea.value === '' && config.initialDescription) {
                elements.descriptionTextarea.value = config.initialDescription;
            }
            formState.description = elements.descriptionTextarea.value || config.initialDescription || '';
            updateDescriptionCounter();
        }

        if (Array.isArray(config.initialLabels)) {
            formState.labels = deduplicateLabels(config.initialLabels);
            updateLabelsInput();
            renderTags();
        } else {
            const parsedLabels = parseLabelsInputValue();
            formState.labels = deduplicateLabels(parsedLabels);
            updateLabelsInput();
            renderTags();
        }

        if (config.initialVisibility) {
            formState.visibility = config.initialVisibility;
            updateVisibilityDescription(formState.visibility);
            updateVisibilityLabels();
        }
    }

    function getFormConfig() {
        if (!elements.form || !elements.form.dataset.noteConfig) {
            return defaultConfig;
        }

        try {
            const parsed = JSON.parse(elements.form.dataset.noteConfig);
            return Object.assign({}, defaultConfig, parsed || {});
        } catch (error) {
            console.warn('Nie udało się odczytać konfiguracji formularza', error);
            return defaultConfig;
        }
    }

    function parseLabelsInputValue() {
        if (!elements.labelsInput || !elements.labelsInput.value) {
            return [];
        }

        try {
            const parsed = JSON.parse(elements.labelsInput.value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('Nie udało się sparsować etykiet z ukrytego pola', error);
            return [];
        }
    }

    // ========== Initialization ==========
    function init() {
        refreshElements();
        // console.log('Note form initialization started');
        config = getFormConfig();
        // console.log('Form element:', elements.form);
        // console.log('Title input:', elements.titleInput);
        // console.log('Description textarea:', elements.descriptionTextarea);
        // console.log('Visibility inputs count:', elements.visibilityInputs.length);

        // Check if form exists
        if (!elements.form) {
            console.error('Note form not found - missing [data-note-form] attribute');
            return;
        }

        if (!elements.titleInput) {
            console.error('Title input not found - missing [data-title-input] attribute');
        }

        if (!elements.descriptionTextarea) {
            console.error('Description textarea not found - missing [data-description-textarea] attribute');
        }

        if (!elements.visibilityInputs || elements.visibilityInputs.length === 0) {
            console.error('Visibility inputs not found - missing [data-visibility-input] attribute');
        }

        applyInitialStateFromConfig();
        initializeEventListeners();
        updateButtonStates();
        announce('Formularz notatki gotowy do wypełnienia');
        // console.log('Note form initialization completed');
    }

    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();