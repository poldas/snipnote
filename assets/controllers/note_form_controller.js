import { Controller } from '@hotwired/stimulus';

const defaultConfig = {
    mode: 'create',
    noteId: null,
    submitUrl: '/api/notes',
    redirectUrl: '/notes',
    previewUrl: '/api/notes/preview',
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
            isPreviewing: false,
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

        // Ensure visibility buttons are properly initialized after DOM is ready
        setTimeout(() => {
            this.updateVisibilityLabels();
        }, 0);

        this.announce('Formularz notatki gotowy do wypełnienia');
    }

    disconnect() {
        this.abort?.abort();
    }

    // ---------- Setup ----------
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
            previewBtn: scope.querySelector('[data-preview-btn]') || document.querySelector('[data-preview-btn]'),
            submitBtn: null, // Will be found dynamically when needed
            statusIndicator: scope.querySelector('[data-status-indicator]') || document.querySelector('[data-status-indicator]'),
            savingSpinner: scope.querySelector('[data-saving-spinner]') || document.querySelector('[data-saving-spinner]'),
            previewSection: scope.querySelector('[data-preview-section]'),
            previewContent: scope.querySelector('[data-preview-content]'),
            closePreview: scope.querySelector('[data-close-preview]'),
            markdownToolbar: scope.querySelector('[data-markdown-toolbar]'),
            ariaLive: document.querySelector('[data-global-aria-live]'),
            csrfToken: scope.querySelector('[data-csrf-token]'),
        };
    }

    bindEvents() {
        // Prevent native submit
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
            this.on(this.elements.addTagBtn, 'click', () => {
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

        if (this.elements.previewBtn) {
            this.on(this.elements.previewBtn, 'click', () => this.previewNote());
        }

        // Submit button is found dynamically to handle cases where it's outside the form scope
        const submitBtn = document.querySelector('[data-submit-btn]');
        if (submitBtn) {
            this.on(submitBtn, 'click', () => this.submitNote());
        }

        if (this.elements.closePreview) {
            this.on(this.elements.closePreview, 'click', () => {
                this.elements.previewSection?.classList.add('hidden');
            });
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
                this.submitNote();
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
        this.state.labels = this.deduplicateLabels(this.config.initialLabels.length ? this.config.initialLabels : parsedLabels);
        this.updateLabelsInput();
        this.renderTags();

        if (this.elements.visibilityInputs.length > 0) {
            const checked = this.elements.visibilityInputs.find((input) => input.checked);
            if (checked) {
                this.state.visibility = checked.value;
            }
        }
    }

    // ---------- Validation ----------
    validateField(field, value) {
        const errors = [];
        if (field === 'title') {
            if (!value.trim()) errors.push('Tytuł jest wymagany');
            if (value.length > 255) errors.push('Tytuł nie może przekraczać 255 znaków');
        }
        if (field === 'description') {
            if (!value.trim()) errors.push('Opis jest wymagany');
            if (value.length > 10000) errors.push('Opis nie może przekraczać 10000 znaków');
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

    // ---------- Errors ----------
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
        const allErrors = [];
        Object.values(errors).forEach((messages) => {
            if (Array.isArray(messages)) {
                allErrors.push(...messages);
            }
        });
        if (allErrors.length) {
            this.elements.errorList.innerHTML = allErrors.map((msg) => `<li>${this.escapeHtml(msg)}</li>`).join('');
            this.elements.formErrors.classList.remove('hidden');
            this.announce('Wykryto błędy walidacji. Sprawdź formularz.');
        } else {
            this.elements.formErrors.classList.add('hidden');
            this.elements.errorList.innerHTML = '';
        }
    }

    clearAllErrors() {
        this.state.errors = { title: [], description: [], labels: [], visibility: [], _request: [] };
        ['title', 'description', 'labels', 'visibility'].forEach((f) => this.clearFieldError(f));
        this.elements.formErrors?.classList.add('hidden');
    }

    // ---------- Counters ----------
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
        this.elements.descriptionCounter.textContent = `${length} / 10000`;
        this.elements.descriptionCounter.classList.toggle('text-red-600', length > 10000);
        this.elements.descriptionCounter.classList.toggle('font-semibold', length > 10000);
    }

    // ---------- Labels ----------
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
        if (!this.state.labels.length) {
            this.elements.tagsContainer.innerHTML = '<span class="text-sm text-slate-400">Brak etykiet</span>';
            return;
        }
        this.elements.tagsContainer.innerHTML = this.state.labels
            .map((label, index) => `
                <span class="inline-flex items-center gap-2 px-3 py-1.5 bg-indigo-100 text-indigo-800 rounded-lg text-sm font-medium border border-indigo-200" role="listitem">
                    <span>${this.escapeHtml(label)}</span>
                    <button type="button" data-remove-tag="${index}" aria-label="Usuń etykietę ${this.escapeHtml(label)}" class="text-indigo-600 hover:text-indigo-900 font-bold">✕</button>
                </span>
            `)
            .join('');
    }

    // ---------- Markdown ----------
    insertMarkdown(action) {
        const textarea = this.elements.descriptionTextarea;
        if (!textarea) return;
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.substring(start, end);
        const withDefault = (fallback) => selected || fallback;

        const replacements = {
            bold: `**${withDefault('pogrubiony tekst')}**`,
            italic: `*${withDefault('tekst kursywą')}*`,
            code: `\`${withDefault('kod')}\``,
            link: `[${withDefault('tekst linku')}](https://example.com)`,
            heading: `\n## ${withDefault('Nagłówek')}\n`,
            list: `\n- ${withDefault('Element listy')}\n`,
            quote: `\n> ${withDefault('Cytat')}\n`,
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

    // ---------- Visibility ----------
    updateVisibilityDescription(value) {
        if (!this.elements.visibilityDescription) return;
        const descriptions = {
            private: 'Notatka widoczna tylko dla Ciebie i współpracowników.',
            public: 'Notatka będzie widoczna publicznie pod unikalnym linkiem.',
            draft: 'Szkic - notatka niewidoczna dla nikogo poza Tobą.',
        };
        this.elements.visibilityDescription.textContent = descriptions[value] || descriptions.private;
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

    // ---------- Network ----------
    async submitNote() {
        const validation = this.validateForm();
        if (!validation.valid) {
            this.state.errors = validation.errors;
            Object.entries(validation.errors).forEach(([field, messages]) => this.showFieldError(field, messages));
            this.showGlobalErrors(validation.errors);
            this.announce('Formularz zawiera błędy. Popraw je przed zapisaniem.');
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
                this.announce(this.config.mode === 'edit' ? 'Notatka została zaktualizowana' : 'Notatka została utworzona pomyślnie');
                this.showToast(this.config.mode === 'edit' ? 'Zapisano zmiany!' : 'Notatka utworzona!', 'success');
                setTimeout(() => {
                    window.location.href = this.config.redirectUrl || '/notes';
                }, 500);
                return;
            }

            if (response.status === 401) {
                this.redirectToLogin('Sesja wygasła lub brak autoryzacji.');
                return;
            }
            if (response.status === 403) {
                this.showToast('Brak uprawnień do zapisania tej notatki.', 'error');
                this.announce('Brak uprawnień do zapisania tej notatki.');
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
                    this.announce('Formularz zawiera błędy walidacji');
                }
                return;
            }
            if (response.status === 409) {
                this.showToast('Kolizja URL. Spróbuj zapisać ponownie.', 'error');
                this.announce('Kolizja URL. Spróbuj zapisać ponownie.');
                return;
            }

            this.showToast('Nie udało się zapisać notatki. Spróbuj ponownie.', 'error');
            this.announce('Błąd serwera. Spróbuj ponownie.');
        } catch (error) {
            console.error('Submit error:', error);
            this.showToast('Błąd sieci. Sprawdź połączenie i spróbuj ponownie.', 'error');
            this.announce('Błąd sieci. Sprawdź połączenie.');
        } finally {
            this.state.isSubmitting = false;
            this.updateButtonStates();
        }
    }

    async previewNote() {
        const titleErrors = this.validateField('title', this.state.title);
        const descriptionErrors = this.validateField('description', this.state.description);
        if (titleErrors.length || descriptionErrors.length) {
            this.showFieldError('title', titleErrors);
            this.showFieldError('description', descriptionErrors);
            this.announce('Uzupełnij wymagane pola przed podglądem');
            return;
        }

        this.state.isPreviewing = true;
        this.updateButtonStates();

        const headers = { 'Content-Type': 'application/json' };
        if (this.elements.csrfToken?.value) headers['X-CSRF-Token'] = this.elements.csrfToken.value;

        const payload = {
            title: this.state.title.trim(),
            description: this.state.description.trim(),
            labels: this.state.labels,
            visibility: this.state.visibility,
        };

        try {
            const response = await fetch(this.config.previewUrl || '/api/notes/preview', {
                method: 'POST',
                headers,
                body: JSON.stringify(payload),
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json().catch(() => ({}));
                const html = data?.data?.html;
                if (html) {
                    this.renderPreviewHtml(html);
                    this.announce('Podgląd wygenerowany');
                    return;
                }
                this.renderLocalPreview('Brak danych podglądu z serwera, użyto wersji lokalnej.');
                return;
            }

            if (response.status === 401 || response.status === 403) {
                this.announce('Brak dostępu do podglądu.');
                this.showToast('Brak dostępu do podglądu.', 'error');
                return;
            }

            this.renderLocalPreview('Nie udało się pobrać podglądu, pokazano wersję lokalną.');
        } catch (error) {
            console.error('Preview error:', error);
            this.renderLocalPreview('Błąd podglądu, pokazano wersję lokalną.');
        } finally {
            this.state.isPreviewing = false;
            this.updateButtonStates();
        }
    }

    renderPreviewHtml(html) {
        if (this.elements.previewContent) {
            this.elements.previewContent.innerHTML = html;
        }
        this.elements.previewSection?.classList.remove('hidden');
        this.elements.previewSection?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    renderLocalPreview(message) {
        if (this.elements.previewContent) {
            const basicHtml = this.escapeHtml(this.state.description)
                .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
                .replace(/\*(.+?)\*/g, '<em>$1</em>')
                .replace(/`(.+?)`/g, '<code>$1</code>')
                .replace(/\n/g, '<br>');
            this.elements.previewContent.innerHTML = `<h2>${this.escapeHtml(this.state.title || 'Podgląd')}</h2><div>${basicHtml || '<p class="text-slate-500">(Brak treści)</p>'}</div>`;
        }
        this.elements.previewSection?.classList.remove('hidden');
        this.elements.previewSection?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        this.announce(message);
        this.showToast(message, 'info');
    }

    // ---------- UI ----------
    updateButtonStates() {
        const busy = this.state.isSubmitting || this.state.isPreviewing;
        if (this.elements.previewBtn) {
            this.elements.previewBtn.disabled = busy;
        }

        // Find submit button dynamically
        const submitBtn = document.querySelector('[data-submit-btn]');
        if (submitBtn) {
            submitBtn.disabled = busy;
        }

        if (this.elements.statusIndicator) {
            if (this.state.isSubmitting) this.elements.statusIndicator.textContent = 'Zapisywanie...';
            else if (this.state.isPreviewing) this.elements.statusIndicator.textContent = 'Generowanie podglądu...';
            else this.elements.statusIndicator.textContent = '';
        }
        if (this.elements.savingSpinner) {
            this.elements.savingSpinner.classList.toggle('opacity-0', !busy);
            this.elements.savingSpinner.classList.toggle('opacity-100', busy);
        }
    }

    // ---------- Helpers ----------
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

    announce(message) {
        if (this.elements.ariaLive) {
            this.elements.ariaLive.textContent = message;
        }
    }

    showToast(message, variant = 'info') {
        const stack = document.getElementById('toast-stack');
        if (!stack) return;
        const el = document.createElement('div');
        el.className = 'min-w-[240px] max-w-sm rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg flex items-start gap-2 ' +
            (variant === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800');
        el.textContent = message;
        stack.appendChild(el);
        setTimeout(() => el.remove(), 3000);
    }

    redirectToLogin(message = 'Sesja wygasła. Zaloguj się ponownie.') {
        this.showToast(message, 'error');
        this.announce(message);
        setTimeout(() => {
            window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname);
        }, 1200);
    }
}

