import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.abort = new AbortController();
        this.currentConfirm = null;
        this.setupConfirmModal();
        this.setupCopyPublicLink();
        this.setupCollaborators();
        this.setupDangerZone();
    }

    disconnect() {
        this.abort?.abort();
        this.currentConfirm = null;
    }

    // ---------- Setup ----------
    setupCopyPublicLink() {
        const btn = this.q('[data-copy-public-link]');
        if (!btn) return;
        this.on(btn, 'click', async () => {
            const link = btn.getAttribute('data-link');
            if (!link) return;
            try {
                await navigator.clipboard.writeText(link);
                this.showToast('Skopiowano publiczny link', 'success');
                this.announce('Link publiczny skopiowany do schowka.');
            } catch (error) {
                console.warn('Clipboard error', error);
                this.showToast('Nie udało się skopiować linku', 'error');
            }
        });
    }

    setupCollaborators() {
        const panel = this.q('[data-collaborators-panel]');
        if (!panel) return;

        const addForm = this.q('[data-collaborator-add-form]', panel);
        const addBtn = this.q('[data-collaborator-add]', panel);
        const emailInput = this.q('[data-collaborator-email]', panel);
        const listEl = panel.querySelector('[data-collaborators-list]');
        const addUrl = panel.getAttribute('data-add-url');
        const redirectUrl = panel.getAttribute('data-redirect-url') || '/notes';
        const currentUserEmail = (panel.getAttribute('data-current-user-email') || '').toLowerCase();
        const ownerEmail = (panel.getAttribute('data-owner-email') || '').toLowerCase();
        const canEdit = panel.getAttribute('data-can-edit') === 'true';

        if (addForm && addUrl) {
            this.on(addForm, 'submit', async (event) => {
                event.preventDefault();
                if (!emailInput) return;
                const email = emailInput.value.trim();
                if (!email) {
                    this.showMessage(panel, 'Podaj email współedytora.', 'error');
                    return;
                }
                if (this.isDuplicateEmail(listEl, email)) {
                    this.showMessage(panel, 'Ten współedytor już istnieje.', 'error');
                    return;
                }
                this.toggleLoading(addBtn, true);
                try {
                    const res = await fetch(addUrl, {
                        method: 'POST',
                        headers: this.buildJsonHeaders(),
                        body: JSON.stringify({ email }),
                        credentials: 'same-origin',
                    });

                    if (res.ok) {
                        const data = await this.safeJson(res);
                        const created = data?.data;
                        if (created) {
                            const collaborator = {
                                id: created.id,
                                email: created.email,
                                isOwner: false,
                                isSelf: created.email?.toLowerCase() === currentUserEmail,
                                removeUrl: `${addUrl}/${created.id}`,
                            };
                            this.appendCollaboratorRow(listEl, collaborator, { canEdit, currentUserEmail, ownerEmail, redirectUrl });
                            emailInput.value = '';
                            this.showMessage(panel, 'Dodano współedytora.', 'success');
                            this.announce('Dodano współedytora.');
                        } else {
                            this.showMessage(panel, 'Dodano współedytora.', 'success');
                        }
                        return;
                    }

                    if (res.status === 400) {
                        const data = await this.safeJson(res);
                        const message = this.firstErrorMessage(data) || 'Nie udało się dodać współedytora.';
                        this.showMessage(panel, message, 'error');
                        this.announce(message);
                        return;
                    }
                    if (res.status === 401 || res.status === 403) {
                        this.showMessage(panel, 'Brak dostępu. Zaloguj się ponownie.', 'error');
                        this.announce('Brak dostępu.');
                        setTimeout(() => window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname), 500);
                        return;
                    }
                    this.showMessage(panel, 'Nie udało się dodać współedytora.', 'error');
                } catch (error) {
                    console.error('Add collaborator error', error);
                    this.showMessage(panel, 'Błąd sieci. Spróbuj ponownie.', 'error');
                } finally {
                    this.toggleLoading(addBtn, false);
                }
            });
        }

        if (listEl) {
            this.bindRemoveButtons(listEl, { canEdit, currentUserEmail, ownerEmail, redirectUrl, addUrl });
        }
    }

    setupDangerZone() {
        const zone = this.q('[data-danger-zone]');
        if (!zone) return;

        const deleteUrl = zone.getAttribute('data-delete-url');
        const regenerateUrl = zone.getAttribute('data-regenerate-url');
        const redirectUrl = zone.getAttribute('data-redirect-url') || '/notes';

        const deleteBtn = zone.querySelector('[data-open-delete-modal]');
        if (deleteBtn && deleteUrl) {
            this.on(deleteBtn, 'click', () => {
                this.openConfirm({
                    title: 'Usuń notatkę',
                    description: 'Operacja jest nieodwracalna. Czy chcesz kontynuować?',
                    confirmLabel: 'Usuń notatkę',
                    onConfirm: () => this.deleteNote(deleteUrl, redirectUrl),
                });
            });
        }

        const regenBtn = zone.querySelector('[data-open-regenerate-modal]');
        if (regenBtn && regenerateUrl) {
            this.on(regenBtn, 'click', () => {
                this.openConfirm({
                    title: 'Wygeneruj nowy URL',
                    description: 'Stary link przestanie działać natychmiast po potwierdzeniu.',
                    confirmLabel: 'Generuj nowy URL',
                    onConfirm: () => this.regenerateUrlToken(regenerateUrl),
                });
            });
        }
    }

    setupConfirmModal() {
        const modal = this.q('[data-confirm-modal]');
        if (!modal) return;

        const cancelBtn = this.q('[data-modal-cancel]', modal);
        const confirmBtn = this.q('[data-modal-confirm]', modal);

        if (cancelBtn) {
            this.on(cancelBtn, 'click', () => this.closeConfirm());
        }
        if (confirmBtn) {
            this.on(confirmBtn, 'click', () => {
                if (typeof this.currentConfirm === 'function') {
                    this.currentConfirm();
                }
            });
        }

        this.on(modal, 'click', (event) => {
            const surface = this.q('[data-modal-surface]', modal);
            if (surface && !surface.contains(event.target)) {
                this.closeConfirm();
            }
        });

        this.on(document, 'keydown', (event) => {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                this.closeConfirm();
            }
        });
    }

    // ---------- Actions ----------
    bindRemoveButtons(listEl, ctx) {
        const buttons = Array.from(listEl.querySelectorAll('[data-remove-collaborator]'));
        buttons.forEach((btn) => {
            this.on(btn, 'click', (event) => {
                event.preventDefault();
                const url = btn.getAttribute('data-remove-url');
                const isSelf = btn.getAttribute('data-self-remove') === 'true';
                if (!url) return;
                if (isSelf) {
                    this.openConfirm({
                        title: 'Usuń swój dostęp',
                        description: 'Utracisz możliwość edycji tej notatki.',
                        confirmLabel: 'Usuń mój dostęp',
                        onConfirm: () => this.removeCollaborator(url, true, ctx.redirectUrl),
                    });
                    return;
                }
                const row = btn.closest('[data-collaborator-row]');
                this.removeCollaborator(url, false, ctx.redirectUrl, listEl, row);
            });
        });
    }

    async removeCollaborator(url, isSelf, redirectUrl, listEl, rowEl) {
        this.toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: this.buildJsonHeaders(),
                credentials: 'same-origin',
            });

            if (res.ok || res.status === 204) {
                const msg = isSelf ? 'Usunięto Twój dostęp.' : 'Usunięto współedytora.';
                this.showToast(msg, 'success');
                this.announce(msg);
                if (isSelf) {
                    setTimeout(() => window.location.href = redirectUrl || '/notes', 400);
                } else {
                    rowEl?.remove();
                    this.ensureEmptyState(listEl);
                    this.closeConfirm();
                }
                return;
            }

            if (res.status === 401 || res.status === 403) {
                this.showToast('Brak uprawnień.', 'error');
                this.announce('Brak uprawnień.');
                setTimeout(() => window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname), 500);
                return;
            }

            const data = await this.safeJson(res);
            const message = this.firstErrorMessage(data) || 'Nie udało się usunąć współedytora.';
            this.showToast(message, 'error');
            this.announce(message);
        } catch (error) {
            console.error('Remove collaborator error', error);
            this.showToast('Błąd sieci przy usuwaniu.', 'error');
        } finally {
            this.toggleConfirmLoading(false);
        }
    }

    async deleteNote(url, redirectUrl) {
        this.toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: this.buildJsonHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok || res.status === 204) {
                this.showToast('Notatka została usunięta.', 'success');
                this.announce('Notatka została usunięta.');
                setTimeout(() => window.location.href = redirectUrl || '/notes', 400);
                return;
            }
            if (res.status === 401 || res.status === 403) {
                this.showToast('Brak uprawnień do usunięcia.', 'error');
                this.announce('Brak uprawnień do usunięcia.');
                return;
            }
            const data = await this.safeJson(res);
            const message = this.firstErrorMessage(data) || 'Nie udało się usunąć notatki.';
            this.showToast(message, 'error');
            this.announce(message);
        } catch (error) {
            console.error('Delete note error', error);
            this.showToast('Błąd sieci przy usuwaniu.', 'error');
        } finally {
            this.toggleConfirmLoading(false);
        }
    }

    async regenerateUrlToken(url) {
        this.toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: this.buildJsonHeaders(),
                credentials: 'same-origin',
            });
            if (res.ok) {
                this.showToast('Wygenerowano nowy URL.', 'success');
                this.announce('Wygenerowano nowy URL.');
                setTimeout(() => window.location.reload(), 400);
                return;
            }
            const data = await this.safeJson(res);
            const message = this.firstErrorMessage(data) || 'Nie udało się wygenerować nowego URL.';
            this.showToast(message, 'error');
            this.announce(message);
        } catch (error) {
            console.error('Regenerate URL error', error);
            this.showToast('Błąd sieci przy regeneracji.', 'error');
        } finally {
            this.toggleConfirmLoading(false);
        }
    }

    openConfirm({ title, description, confirmLabel, onConfirm }) {
        const modal = this.q('[data-confirm-modal]');
        if (!modal) return;
        const titleEl = this.q('[data-modal-title]', modal);
        const descEl = this.q('[data-modal-description]', modal);
        const confirmLabelEl = this.q('[data-modal-confirm-label]', modal);
        const errorEl = this.q('[data-modal-error]', modal);

        if (titleEl) titleEl.textContent = title || 'Potwierdź operację';
        if (descEl) descEl.textContent = description || '';
        if (confirmLabelEl) confirmLabelEl.textContent = confirmLabel || 'Potwierdź';
        errorEl?.classList.add('hidden');

        this.currentConfirm = onConfirm;
        modal.classList.remove('hidden');
        modal.classList.add('flex');

        const confirmBtn = this.q('[data-modal-confirm]', modal);
        confirmBtn?.focus();
        this.announce(title || 'Potwierdź operację');
    }

    closeConfirm() {
        const modal = this.q('[data-confirm-modal]');
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        this.toggleConfirmLoading(false);
        this.currentConfirm = null;
    }

    toggleConfirmLoading(isLoading) {
        const confirmBtn = this.q('[data-modal-confirm]');
        const spinner = this.q('[data-modal-spinner]');
        if (confirmBtn) confirmBtn.disabled = isLoading;
        spinner?.classList.toggle('hidden', !isLoading);
    }

    // ---------- Helpers ----------
    q(selector, scope = this.element) {
        return scope.querySelector(selector);
    }

    on(target, event, handler) {
        if (!target) return;
        target.addEventListener(event, handler, { signal: this.abort.signal });
    }

    buildJsonHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        const csrf = this.q('[data-csrf-token]')?.value || document.querySelector('[data-csrf-token]')?.value;
        if (csrf) headers['X-CSRF-Token'] = csrf;
        return headers;
    }

    async safeJson(response) {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    }

    firstErrorMessage(payload) {
        if (!payload || typeof payload !== 'object') return null;
        if (Array.isArray(payload.errors?._request) && payload.errors._request.length > 0) {
            return payload.errors._request[0];
        }
        if (payload.errors) {
            const firstKey = Object.keys(payload.errors)[0];
            const arr = payload.errors[firstKey];
            if (Array.isArray(arr) && arr.length > 0) {
                return arr[0];
            }
        }
        return null;
    }

    toggleLoading(btn, isLoading) {
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('opacity-60', isLoading);
    }

    showMessage(panel, message, variant = 'info') {
        const el = this.q('[data-collaborators-message]', panel);
        if (!el) {
            this.showToast(message, variant === 'success' ? 'success' : 'error');
            return;
        }
        const closeBtn = this.q('[data-collaborators-message-close]', el);
        const textEl = el.querySelector('[data-collaborators-message-text]') || el;

        textEl.textContent = message;
        el.classList.remove('hidden');
        el.classList.toggle('text-red-700', variant === 'error');
        el.classList.toggle('bg-red-50', variant === 'error');
        el.classList.toggle('border-red-200', variant === 'error');
        el.classList.toggle('text-emerald-700', variant === 'success');
        el.classList.toggle('bg-emerald-50', variant === 'success');
        el.classList.toggle('border-emerald-200', variant === 'success');

        if (closeBtn && !closeBtn.dataset.bound) {
            closeBtn.addEventListener('click', () => el.classList.add('hidden'), { once: true });
            closeBtn.dataset.bound = 'true';
        }
        setTimeout(() => el.classList.add('hidden'), 3000);
    }

    isDuplicateEmail(listEl, email) {
        if (!listEl) return false;
        const normalized = email.trim().toLowerCase();
        return Array.from(listEl.querySelectorAll('[data-collaborator-email]')).some((row) => row.getAttribute('data-collaborator-email') === normalized);
    }

    appendCollaboratorRow(listEl, collaborator, ctx) {
        if (!listEl) return;
        listEl.querySelector('[data-collaborators-empty]')?.remove();

        const row = document.createElement('div');
        row.className = 'px-4 py-3 flex flex-wrap items-center justify-between gap-3';
        row.setAttribute('data-collaborator-row', 'true');
        row.setAttribute('data-collaborator-id', collaborator.id ?? 'new');
        row.setAttribute('data-collaborator-email', (collaborator.email || '').toLowerCase());

        const role = collaborator.isOwner ? 'Właściciel' : collaborator.isSelf ? 'Ty' : 'Współedytor';
        const canRemove = ctx.canEdit && !collaborator.isOwner;
        const removeBtnHtml = canRemove
            ? `<div class="flex items-center gap-2">
                    <button type="button" class="text-sm text-red-600 hover:text-red-800 font-semibold" data-remove-collaborator data-remove-url="${collaborator.removeUrl || ''}" ${collaborator.isSelf ? 'data-self-remove="true"' : ''}>
                        ${collaborator.isSelf ? 'Usuń mój dostęp' : 'Usuń'}
                    </button>
               </div>`
            : '';

        row.innerHTML = `
            <div class="space-y-0.5">
                <p class="font-semibold text-slate-900">${this.escapeHtml(collaborator.email || '')}</p>
                <p class="text-xs text-slate-500">${role}</p>
            </div>
            ${removeBtnHtml}
        `;

        listEl.appendChild(row);
        if (canRemove) {
            const btn = row.querySelector('[data-remove-collaborator]');
            if (btn) {
                this.on(btn, 'click', (event) => {
                    event.preventDefault();
                    const url = btn.getAttribute('data-remove-url');
                    const isSelf = btn.getAttribute('data-self-remove') === 'true';
                    if (!url) return;
                    if (isSelf) {
                        this.openConfirm({
                            title: 'Usuń swój dostęp',
                            description: 'Utracisz możliwość edycji tej notatki.',
                            confirmLabel: 'Usuń mój dostęp',
                            onConfirm: () => this.removeCollaborator(url, true, ctx.redirectUrl),
                        });
                        return;
                    }
                    this.removeCollaborator(url, false, ctx.redirectUrl, listEl, row);
                });
            }
        }
    }

    ensureEmptyState(listEl) {
        if (!listEl) return;
        const rows = Array.from(listEl.querySelectorAll('[data-collaborator-row]'));
        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-4 py-3 text-sm text-slate-500';
            empty.setAttribute('data-collaborators-empty', 'true');
            empty.textContent = 'Brak współedytorów.';
            listEl.appendChild(empty);
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
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

    announce(message) {
        const region = document.querySelector('[data-global-aria-live]');
        if (region) {
            region.textContent = message;
        }
    }
}

