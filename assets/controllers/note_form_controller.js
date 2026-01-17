import { Controller } from '@hotwired/stimulus';
import { showToast, announce } from 'ui_utils';

const defaultConfig = {
    mode: 'create',
    noteId: null,
    submitUrl: '/api/notes',
    redirectUrl: '/notes',
    initialTitle: '',
    initialDescription: '',
    initialLabels: [],
    initialVisibility: 'private',
};

export default class extends Controller {
    connect() {
        this.abort = new AbortController();
        this.config = this.readConfig();
        this.elements = this.findElements();
        this.state = {
            title: '',
            description: '',
            labels: [],
            visibility: this.config.initialVisibility || 'private',
            isSubmitting: false,
            errors: {
                title: [],
                description: [],
                labels: [],
                visibility: [],
                _request: [],
            },
        };

        this.applyInitialState();
        this.bindEvents();
        this.updateVisibilityDescription(this.state.visibility);
        this.updateVisibilityLabels();
        this.updateButtonStates();

        setTimeout(() => {
            this.updateVisibilityLabels();
        }, 0);

        this.element.setAttribute('data-note-form-ready', 'true');

        if (this.elements.titleInput) {
            requestAnimationFrame(() => this.elements.titleInput.focus());
        }
    }

    disconnect() {
        this.abort?.abort();
    }

    readConfig() {
        try {
            const raw = this.element.dataset.noteConfig;
            const parsed = raw ? JSON.parse(raw) : {};
            return { ...defaultConfig, ...(parsed || {}) };
        } catch (error) {
            console.warn('Nie udało się odczytać konfiguracji formularza', error);
            return defaultConfig;
        }
    }

    findElements() {
        const scope = this.element;
        return {
            form: scope,
            titleInput: scope.querySelector('[data-title-input]'),
            titleCounter: scope.querySelector('[data-title-counter]'),
            titleError: scope.querySelector('[data-title-error]'),
            descriptionTextarea: scope.querySelector('[data-description-textarea]'),
            descriptionCounter: scope.querySelector('[data-description-counter]'),
            descriptionError: scope.querySelector('[data-description-error]'),
            visibilityInputs: Array.from(scope.querySelectorAll('[data-visibility-input]')),
            visibilityDescription: scope.querySelector('[data-visibility-description]'),
            visibilityError: scope.querySelector('[data-visibility-error]'),
            visibilityLabels: Array.from(scope.querySelectorAll('[data-visibility-label]')),
            tagInput: scope.querySelector('[data-tag-input]'),
            addTagBtn: scope.querySelector('[data-add-tag-btn]'),
            tagsContainer: scope.querySelector('[data-tags-container]'),
            labelsInput: scope.querySelector('[data-labels-input]'),
            labelsError: scope.querySelector('[data-labels-error]'),
            formErrors: scope.querySelector('[data-form-errors]'),
            errorList: scope.querySelector('[data-error-list]'),
            statusIndicator: scope.querySelector('[data-status-indicator]') || document.querySelector('[data-status-indicator]'),
            savingSpinner: scope.querySelector('[data-saving-spinner]') || document.querySelector('[data-saving-spinner]'),
            markdownToolbar: scope.querySelector('[data-markdown-toolbar]'),
            csrfToken: scope.querySelector('[data-csrf-token]'),
        };
    }

    bindEvents() {
        this.on(this.elements.form, 'submit', (event) => event.preventDefault());

        if (this.elements.titleInput) {
            this.on(this.elements.titleInput, 'input', (event) => {
                this.state.title = event.target.value;
                this.updateTitleCounter();
                this.clearFieldError('title');
            });
        }

        if (this.elements.descriptionTextarea) {
            this.on(this.elements.descriptionTextarea, 'input', (event) => {
                this.state.description = event.target.value;
                this.updateDescriptionCounter();
                this.clearFieldError('description');
            });
        }

        this.elements.visibilityInputs.forEach((input) => {
            this.on(input, 'change', (event) => {
                this.state.visibility = event.target.value;
                this.updateVisibilityDescription(event.target.value);
                this.updateVisibilityLabels();
                this.clearFieldError('visibility');
            });
        });

        if (this.elements.tagInput) {
            this.on(this.elements.tagInput, 'keydown', (event) => {
                if (event.key === 'Enter' || event.key === ',') {
                    event.preventDefault();
                    this.addLabel(event.target.value);
                }
            });
            this.on(this.elements.tagInput, 'blur', (event) => {
                if (event.target.value.trim() !== '') {
                    this.addLabel(event.target.value);
                }
            });
        }

        if (this.elements.addTagBtn) {
            this.on(this.elements.addTagBtn, 'click', (event) => {
                event.preventDefault();
                if (this.elements.tagInput?.value.trim()) {
                    this.addLabel(this.elements.tagInput.value);
                }
            });
        }

        if (this.elements.tagsContainer) {
            this.on(this.elements.tagsContainer, 'click', (event) => {
                const btn = event.target.closest('[data-remove-tag]');
                if (!btn) return;
                const index = parseInt(btn.getAttribute('data-remove-tag'), 10);
                if (Number.isInteger(index)) {
                    this.removeLabel(index);
                }
            });
        }

        if (this.elements.markdownToolbar) {
            this.on(this.elements.markdownToolbar, 'click', (event) => {
                const btn = event.target.closest('[data-md-action]');
                if (btn) {
                    this.insertMarkdown(btn.getAttribute('data-md-action'));
                }
            });
        }

        const submitBtn = document.querySelector('[data-submit-btn]');
        if (submitBtn) {
            this.on(submitBtn, 'click', () => this.submitNote());
        }

        this.on(document, 'keydown', (event) => {
            if (!this.element.isConnected) return;
            if ((event.ctrlKey || event.metaKey) && event.key === 'b') {
                event.preventDefault();
                this.insertMarkdown('bold');
            }
            if ((event.ctrlKey || event.metaKey) && event.key === 'i') {
                event.preventDefault();
                this.insertMarkdown('italic');
            }
            if ((event.ctrlKey || event.metaKey) && event.key === 'k') {
                event.preventDefault();
                this.insertMarkdown('link');
            }
            if ((event.ctrlKey || event.metaKey) && event.key === 's') {
                event.preventDefault();
                this.submitNote({ stay: this.config.mode === 'edit' });
            }
        });
    }

    applyInitialState() {
        if (this.elements.titleInput) {
            if (!this.elements.titleInput.value && this.config.initialTitle) {
                this.elements.titleInput.value = this.config.initialTitle;
            }
            this.state.title = this.elements.titleInput.value || '';
            this.updateTitleCounter();
        }

        if (this.elements.descriptionTextarea) {
            if (!this.elements.descriptionTextarea.value && this.config.initialDescription) {
                this.elements.descriptionTextarea.value = this.config.initialDescription;
            }
            this.state.description = this.elements.descriptionTextarea.value || '';
            this.updateDescriptionCounter();
        }

        const parsedLabels = this.parseLabelsInputValue();
        this.state.labels = this.deduplicateLabels(this.config.initialLabels && this.config.initialLabels.length ? this.config.initialLabels : parsedLabels);
        this.updateLabelsInput();
        this.renderTags();

        if (this.elements.visibilityInputs.length > 0) {
            const checked = this.elements.visibilityInputs.find((input) => input.checked);
            if (checked) {
                this.state.visibility = checked.value;
            }
        }
    }

    validateField(field, value) {
        const errors = [];
        if (field === 'title') {
            if (!value.trim()) errors.push('Tytuł jest wymagany');
            if (value.length > 255) errors.push('Tytuł nie może przekraczać 255 znaków');
        }
        if (field === 'description') {
            if (!value.trim()) errors.push('Opis jest wymagany');
            if (value.length > 100000) errors.push('Opis nie może przekraczać 100000 znaków');
        }
        if (field === 'visibility' && !['private', 'public', 'draft'].includes(value)) {
            errors.push('Nieprawidłowa widoczność');
        }
        return errors;
    }

    validateForm() {
        const errors = {
            title: this.validateField('title', this.state.title),
            description: this.validateField('description', this.state.description),
            visibility: this.validateField('visibility', this.state.visibility),
            labels: [],
            _request: [],
        };
        const hasErrors = Object.values(errors).some((list) => list.length > 0);
        return { valid: !hasErrors, errors };
    }

    showFieldError(field, messages) {
        const errorEl = this.elements[`${field}Error`];
        const inputEl = this.elements[`${field}Input`] || this.elements[`${field}Textarea`] || this.elements[`${field}Inputs`]?.[0];

        if (errorEl) {
            if (messages.length) {
                errorEl.textContent = messages.join(', ');
                errorEl.classList.remove('hidden');
                if (inputEl) {
                    inputEl.setAttribute('aria-invalid', 'true');
                    inputEl.classList.add('border-red-500', 'focus:ring-red-500');
                }
            } else {
                errorEl.textContent = '';
                errorEl.classList.add('hidden');
                inputEl?.setAttribute('aria-invalid', 'false');
                inputEl?.classList.remove('border-red-500', 'focus:ring-red-500');
            }
        }
    }

    clearFieldError(field) {
        this.showFieldError(field, []);
    }

    showGlobalErrors(errors) {
        if (!this.elements.formErrors || !this.elements.errorList) return;

        // Clear list safely
        this.elements.errorList.textContent = '';

        const allErrors = [];
        Object.values(errors).forEach((messages) => {
            if (Array.isArray(messages)) {
                allErrors.push(...messages);
            }
        });

        if (allErrors.length) {
            allErrors.forEach(msg => {
                const li = document.createElement('li');
                li.textContent = msg;
                this.elements.errorList.appendChild(li);
            });
            this.elements.formErrors.classList.remove('hidden');
            announce('Wykryto błędy walidacji. Sprawdź formularz.');
        } else {
            this.elements.formErrors.classList.add('hidden');
        }
    }

    clearAllErrors() {
        this.state.errors = { title: [], description: [], labels: [], visibility: [], _request: [] };
        ['title', 'description', 'labels', 'visibility'].forEach((f) => this.clearFieldError(f));
        this.elements.formErrors?.classList.add('hidden');
        if (this.elements.errorList) this.elements.errorList.textContent = '';
    }

    updateTitleCounter() {
        if (!this.elements.titleCounter) return;
        const length = this.state.title.length;
        this.elements.titleCounter.textContent = `${length} / 255`;
        this.elements.titleCounter.classList.toggle('text-red-600', length > 255);
        this.elements.titleCounter.classList.toggle('font-semibold', length > 255);
    }

    updateDescriptionCounter() {
        if (!this.elements.descriptionCounter) return;
        const length = this.state.description.length;
        this.elements.descriptionCounter.textContent = `${length} / 100000`;
        this.elements.descriptionCounter.classList.toggle('text-red-600', length > 100000);
        this.elements.descriptionCounter.classList.toggle('font-semibold', length > 100000);
    }

    deduplicateLabels(labels) {
        const seen = new Set();
        return labels.filter((label) => {
            const normalized = label.toLowerCase().trim();
            if (!normalized || seen.has(normalized)) return false;
            seen.add(normalized);
            return true;
        });
    }

    addLabel(label) {
        const trimmed = label.trim();
        if (!trimmed) return;
        this.state.labels = this.deduplicateLabels([...this.state.labels, trimmed]);
        this.updateLabelsInput();
        this.renderTags();
        if (this.elements.tagInput) {
            this.elements.tagInput.value = '';
        }
    }

    removeLabel(index) {
        this.state.labels = this.state.labels.filter((_, i) => i !== index);
        this.updateLabelsInput();
        this.renderTags();
    }

    updateLabelsInput() {
        if (this.elements.labelsInput) {
            this.elements.labelsInput.value = JSON.stringify(this.state.labels);
        }
    }

    renderTags() {
        if (!this.elements.tagsContainer) return;

        // Clear container safely
        this.elements.tagsContainer.textContent = '';

        if (!this.state.labels.length) {
            const emptyHint = document.createElement('span');
            emptyHint.className = 'text-sm text-slate-400';
            emptyHint.textContent = 'Brak etykiet';
            this.elements.tagsContainer.appendChild(emptyHint);
            return;
        }

        this.state.labels.forEach((label, index) => {
            const span = document.createElement('span');
            span.className = 'inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-lg text-sm font-medium border border-indigo-200';
            span.setAttribute('role', 'listitem');
            span.setAttribute('data-testid', 'tag-chip');

            const labelText = document.createElement('span');
            labelText.textContent = label;

            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-remove-tag', index.toString());
            btn.setAttribute('data-testid', 'tag-remove-btn');
            btn.setAttribute('aria-label', `Usuń etykietę ${label}`);
            btn.className = 'text-indigo-600 hover:text-indigo-900 font-bold';
            btn.textContent = '✕';

            span.appendChild(labelText);
            span.appendChild(btn);
            this.elements.tagsContainer.appendChild(span);
        });
    }

    insertMarkdown(action) {
        const textarea = this.elements.descriptionTextarea;
        if (!textarea) return;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.substring(start, end);
        const withDefault = (fallback) => selected || fallback;

        const replacements = {
            bold: '**' + withDefault('pogrubiony tekst') + '**',
            italic: '*' + withDefault('tekst kursywą') + '*',
            code: '`' + withDefault('kod') + '`',
            link: '[' + withDefault('tekst linku') + '](https://example.com)',
            heading: '\n## ' + withDefault('Nagłówek') + '\n',
            list: '\n- ' + withDefault('Element listy') + '\n',
            quote: '\n> ' + withDefault('Cytat') + '\n',
        };

        const replacement = replacements[action];
        if (!replacement) return;

        textarea.value = textarea.value.substring(0, start) + replacement + textarea.value.substring(end);
        this.state.description = textarea.value;
        this.updateDescriptionCounter();
        const newPos = start + replacement.length;
        textarea.setSelectionRange(newPos, newPos);
        textarea.focus();
    }

    updateVisibilityDescription(value) {
        if (!this.elements.visibilityDescription) return;
        
        // Default structure (keys only), content must be provided via data attribute
        let descriptions = {
            private: '',
            public: '',
            draft: '',
        };

        try {
            const raw = this.element.getAttribute('data-note-form-visibility-descriptions-value');
            if (raw) {
                descriptions = JSON.parse(raw);
            } else {
                console.warn('Missing data-note-form-visibility-descriptions-value attribute. Visibility descriptions will be empty.');
            }
        } catch (e) {
            console.warn('Cannot parse visibility descriptions', e);
        }

        this.elements.visibilityDescription.textContent = descriptions[value] || descriptions.private || '';
    }

    updateVisibilityLabels() {
        this.elements.visibilityLabels.forEach((label) => {
            const value = label.getAttribute('data-visibility-label');
            const isActive = value === this.state.visibility;
            if (isActive) {
                label.classList.add('active');
            } else {
                label.classList.remove('active');
            }
        });
    }

    async submitNote(options = { stay: false }) {
        const validation = this.validateForm();
        if (!validation.valid) {
            this.state.errors = validation.errors;
            Object.entries(validation.errors).forEach(([field, messages]) => this.showFieldError(field, messages));
            this.showGlobalErrors(validation.errors);
            announce('Formularz zawiera błędy. Popraw je przed zapisaniem.');

            // Smooth scroll to the top of the form where errors are displayed
            this.elements.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
            return;
        }

        this.clearAllErrors();
        const payload = {
            title: this.state.title.trim(),
            description: this.state.description.trim(),
            labels: this.state.labels.map((l) => l.trim()).filter(Boolean),
            visibility: this.state.visibility,
        };

        this.state.isSubmitting = true;
        this.updateButtonStates();

        const headers = { 'Content-Type': 'application/json' };
        if (this.elements.csrfToken?.value) {
            headers['X-CSRF-Token'] = this.elements.csrfToken.value;
        }

        try {
            const targetUrl = this.config.submitUrl || (this.config.mode === 'edit' && this.config.noteId ? `/api/notes/${this.config.noteId}` : '/api/notes');
            const method = this.config.mode === 'edit' ? 'PATCH' : 'POST';
            const response = await fetch(targetUrl, {
                method,
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (response.ok) {
                announce(this.config.mode === 'edit' ? 'Notatka została zaktualizowana' : 'Notatka została utworzona pomyślnie');
                showToast(this.config.mode === 'edit' ? 'Zapisano zmiany' : 'Notatka utworzona', 'success');
                
                if (options.stay) {
                    setTimeout(() => {
                        window.location.reload();
                    }, 500);
                } else {
                    setTimeout(() => {
                        window.location.href = this.config.redirectUrl || '/notes';
                    }, 500);
                }
                return;
            }

            if (response.status === 401) {
                this.redirectToLogin('Sesja wygasła. Zaloguj się ponownie.');
                return;
            }
            if (response.status === 403) {
                showToast('Brak uprawnień do zapisania tej notatki.', 'error');
                announce('Brak uprawnień do zapisania tej notatki.');
                return;
            }
            if (response.status === 400) {
                const errorData = await response.json().catch(() => ({}));
                if (errorData.errors) {
                    this.state.errors = { ...this.state.errors, ...errorData.errors };
                    Object.entries(this.state.errors).forEach(([field, messages]) => {
                        if (Array.isArray(messages)) {
                            this.showFieldError(field, messages);
                        }
                    });
                    this.showGlobalErrors(this.state.errors);
                    announce('Formularz zawiera błędy walidacji');
                }
                return;
            }
            if (response.status === 409) {
                showToast('Kolizja URL. Spróbuj zapisać ponownie.', 'error');
                announce('Kolizja URL. Spróbuj zapisać ponownie.');
                return;
            }

            showToast('Nie udało się zapisać notatki. Spróbuj ponownie.', 'error');
            announce('Błąd serwera. Spróbuj ponownie.');
        } catch (error) {
            console.error('Submit error:', error);
            showToast('Błąd sieci. Sprawdź połączenie i spróbuj ponownie.', 'error');
            announce('Błąd sieci. Sprawdź połączenie.');
        } finally {
            this.state.isSubmitting = false;
            this.updateButtonStates();
        }
    }

    updateButtonStates() {
        const busy = this.state.isSubmitting;

        const submitBtn = document.querySelector('[data-submit-btn]');
        if (submitBtn) {
            submitBtn.disabled = busy;
        }

        if (this.elements.statusIndicator) {
            if (this.state.isSubmitting) this.elements.statusIndicator.textContent = 'Zapisywanie...';
            else this.elements.statusIndicator.textContent = '';
        }
        if (this.elements.savingSpinner) {
            this.elements.savingSpinner.classList.toggle('opacity-0', !busy);
            this.elements.savingSpinner.classList.toggle('opacity-100', busy);
        }
    }

    on(target, event, handler) {
        if (!target) return;
        target.addEventListener(event, handler, { signal: this.abort.signal });
    }

    parseLabelsInputValue() {
        if (!this.elements.labelsInput?.value) return [];
        try {
            const parsed = JSON.parse(this.elements.labelsInput.value);
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            console.warn('Nie udało się sparsować etykiet z ukrytego pola', error);
            return [];
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text ?? '';
        return div.innerHTML;
    }

    redirectToLogin(message = 'Sesja wygasła. Zaloguj się ponownie.') {
        showToast(message, 'error');
        announce(message);
        setTimeout(() => {
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        }, 1200);
    }
}
