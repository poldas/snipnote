(function () {
    'use strict';

    const selectors = {
        copyLinkBtn: '[data-copy-public-link]',
        collaboratorsPanel: '[data-collaborators-panel]',
        addForm: '[data-collaborator-add-form]',
        addEmailInput: '[data-collaborator-email]',
        addBtn: '[data-collaborator-add]',
        removeBtn: '[data-remove-collaborator]',
        confirmModal: '[data-confirm-modal]',
        modalTitle: '[data-modal-title]',
        modalDescription: '[data-modal-description]',
        modalConfirm: '[data-modal-confirm]',
        modalConfirmLabel: '[data-modal-confirm-label]',
        modalCancel: '[data-modal-cancel]',
        modalSpinner: '[data-modal-spinner]',
        modalError: '[data-modal-error]',
        modalSurface: '[data-modal-surface]',
        dangerZone: '[data-danger-zone]',
        collaboratorsMessage: '[data-collaborators-message]',
        collaboratorsEmpty: '[data-collaborators-empty]',
        collaboratorsMessageClose: '[data-collaborators-message-close]',
    };

    let currentConfirm = null;

    function $(selector, scope = document) {
        return scope.querySelector(selector);
    }

    function $all(selector, scope = document) {
        return Array.from(scope.querySelectorAll(selector));
    }

    function announce(message) {
        const region = document.querySelector('[data-global-aria-live]');
        if (region) {
            region.textContent = message;
        }
    }

    function showToast(message, variant = 'info') {
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

    function getCsrfToken() {
        const input = $('[data-csrf-token]');
        return input ? input.value : null;
    }

    // ---------- Public Link ----------
    function setupCopyPublicLink() {
        const btn = $(selectors.copyLinkBtn);
        if (!btn) return;
        btn.addEventListener('click', async () => {
            const link = btn.getAttribute('data-link');
            if (!link) return;
            try {
                await navigator.clipboard.writeText(link);
                showToast('Skopiowano publiczny link', 'success');
                announce('Link publiczny skopiowany do schowka.');
            } catch (error) {
                console.warn('Clipboard error', error);
                showToast('Nie udało się skopiować linku', 'error');
            }
        });
    }

    // ---------- Collaborators ----------
    function setupCollaborators() {
        const panel = $(selectors.collaboratorsPanel);
        if (!panel) return;

        const addForm = $(selectors.addForm, panel);
        const addBtn = $(selectors.addBtn, panel);
        const emailInput = $(selectors.addEmailInput, panel);
        const listEl = panel.querySelector('[data-collaborators-list]');
        const addUrl = panel.getAttribute('data-add-url');
        const redirectUrl = panel.getAttribute('data-redirect-url') || '/notes';
        const currentUserEmail = (panel.getAttribute('data-current-user-email') || '').toLowerCase();
        const ownerEmail = (panel.getAttribute('data-owner-email') || '').toLowerCase();
        const canEdit = panel.getAttribute('data-can-edit') === 'true';

        if (addForm && addUrl) {
            addForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                if (!emailInput) return;
                const email = emailInput.value.trim();
                if (email === '') {
                    showMessage(panel, 'Podaj email współedytora.', 'error');
                    return;
                }
                if (isDuplicateEmail(listEl, email)) {
                    showMessage(panel, 'Ten współedytor już istnieje.', 'error');
                    return;
                }
                toggleLoading(addBtn, true);
                try {
                    const res = await fetch(addUrl, {
                        method: 'POST',
                        headers: buildJsonHeaders(),
                        body: JSON.stringify({ email }),
                        credentials: 'same-origin',
                    });

                    if (res.ok) {
                        const data = await safeJson(res);
                        const created = data?.data;
                        if (created) {
                            const collaborator = {
                                id: created.id,
                                email: created.email,
                                isOwner: false,
                                isSelf: created.email?.toLowerCase() === currentUserEmail,
                                removeUrl: `${addUrl}/${created.id}`,
                            };
                            appendCollaboratorRow(listEl, collaborator, { canEdit, currentUserEmail, ownerEmail, redirectUrl });
                            if (emailInput) emailInput.value = '';
                            showMessage(panel, 'Dodano współedytora.', 'success');
                            announce('Dodano współedytora.');
                        } else {
                            showMessage(panel, 'Dodano współedytora.', 'success');
                        }
                        return;
                    }

                    if (res.status === 400) {
                        const data = await safeJson(res);
                        const message = firstErrorMessage(data) || 'Nie udało się dodać współedytora.';
                        showMessage(panel, message, 'error');
                        announce(message);
                        return;
                    }

                    if (res.status === 401 || res.status === 403) {
                        showMessage(panel, 'Brak dostępu. Zaloguj się ponownie.', 'error');
                        announce('Brak dostępu.');
                        setTimeout(() => window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname), 500);
                        return;
                    }

                    showMessage(panel, 'Nie udało się dodać współedytora.', 'error');
                } catch (error) {
                    console.error('Add collaborator error', error);
                    showMessage(panel, 'Błąd sieci. Spróbuj ponownie.', 'error');
                } finally {
                    toggleLoading(addBtn, false);
                }
            });
        }

        if (listEl) {
            $all(selectors.removeBtn, listEl).forEach((btn) => {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = btn.getAttribute('data-remove-url');
                    const isSelf = btn.getAttribute('data-self-remove') === 'true';
                    if (!url) return;
                    if (isSelf) {
                        openConfirm({
                            title: 'Usuń swój dostęp',
                            description: 'Utracisz możliwość edycji tej notatki.',
                            confirmLabel: 'Usuń mój dostęp',
                            onConfirm: () => removeCollaborator(url, true, redirectUrl),
                        });
                        return;
                    }
                    removeCollaborator(url, false, redirectUrl, listEl, btn.closest('[data-collaborator-row]'));
                });
            });
        }
    }

    async function removeCollaborator(url, isSelf, redirectUrl, listEl, rowEl) {
        toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: buildJsonHeaders(),
                credentials: 'same-origin',
            });

            if (res.ok || res.status === 204) {
                const msg = isSelf ? 'Usunięto Twój dostęp.' : 'Usunięto współedytora.';
                showToast(msg, 'success');
                announce(msg);
                if (isSelf) {
                    setTimeout(() => window.location.href = redirectUrl || '/notes', 400);
                } else {
                    if (rowEl && rowEl.parentElement) {
                        rowEl.remove();
                        ensureEmptyState(listEl);
                    }
                    closeConfirm();
                }
                return;
            }

            if (res.status === 401 || res.status === 403) {
                showToast('Brak uprawnień.', 'error');
                announce('Brak uprawnień.');
                setTimeout(() => window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname), 500);
                return;
            }

            const data = await safeJson(res);
            const message = firstErrorMessage(data) || 'Nie udało się usunąć współedytora.';
            showToast(message, 'error');
            announce(message);
        } catch (error) {
            console.error('Remove collaborator error', error);
            showToast('Błąd sieci przy usuwaniu.', 'error');
        } finally {
            toggleConfirmLoading(false);
        }
    }

    // ---------- Danger Zone ----------
    function setupDangerZone() {
        const zone = $(selectors.dangerZone);
        if (!zone) return;

        const deleteUrl = zone.getAttribute('data-delete-url');
        const regenerateUrl = zone.getAttribute('data-regenerate-url');
        const redirectUrl = zone.getAttribute('data-redirect-url') || '/notes';

        const deleteBtn = zone.querySelector('[data-open-delete-modal]');
        if (deleteBtn && deleteUrl) {
            deleteBtn.addEventListener('click', () => {
                openConfirm({
                    title: 'Usuń notatkę',
                    description: 'Operacja jest nieodwracalna. Czy chcesz kontynuować?',
                    confirmLabel: 'Usuń notatkę',
                    onConfirm: () => deleteNote(deleteUrl, redirectUrl),
                });
            });
        }

        const regenBtn = zone.querySelector('[data-open-regenerate-modal]');
        if (regenBtn && regenerateUrl) {
            regenBtn.addEventListener('click', () => {
                openConfirm({
                    title: 'Wygeneruj nowy URL',
                    description: 'Stary link przestanie działać natychmiast po potwierdzeniu.',
                    confirmLabel: 'Generuj nowy URL',
                    onConfirm: () => regenerateUrlToken(regenerateUrl),
                });
            });
        }
    }

    async function deleteNote(url, redirectUrl) {
        toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'DELETE',
                headers: buildJsonHeaders(),
                credentials: 'same-origin',
            });

            if (res.ok || res.status === 204) {
                showToast('Notatka została usunięta.', 'success');
                announce('Notatka została usunięta.');
                setTimeout(() => window.location.href = redirectUrl || '/notes', 400);
                return;
            }

            if (res.status === 401 || res.status === 403) {
                showToast('Brak uprawnień do usunięcia.', 'error');
                announce('Brak uprawnień do usunięcia.');
                return;
            }

            const data = await safeJson(res);
            const message = firstErrorMessage(data) || 'Nie udało się usunąć notatki.';
            showToast(message, 'error');
            announce(message);
        } catch (error) {
            console.error('Delete note error', error);
            showToast('Błąd sieci przy usuwaniu.', 'error');
        } finally {
            toggleConfirmLoading(false);
        }
    }

    async function regenerateUrlToken(url) {
        toggleConfirmLoading(true);
        try {
            const res = await fetch(url, {
                method: 'POST',
                headers: buildJsonHeaders(),
                credentials: 'same-origin',
            });

            if (res.ok) {
                showToast('Wygenerowano nowy URL.', 'success');
                announce('Wygenerowano nowy URL.');
                setTimeout(() => window.location.reload(), 400);
                return;
            }

            const data = await safeJson(res);
            const message = firstErrorMessage(data) || 'Nie udało się wygenerować nowego URL.';
            showToast(message, 'error');
            announce(message);
        } catch (error) {
            console.error('Regenerate URL error', error);
            showToast('Błąd sieci przy regeneracji.', 'error');
        } finally {
            toggleConfirmLoading(false);
        }
    }

    // ---------- Confirm Modal ----------
    function openConfirm({ title, description, confirmLabel, onConfirm }) {
        const modal = $(selectors.confirmModal);
        if (!modal) return;

        $(selectors.modalTitle, modal).textContent = title || 'Potwierdź operację';
        $(selectors.modalDescription, modal).textContent = description || '';
        $(selectors.modalConfirmLabel, modal).textContent = confirmLabel || 'Potwierdź';
        $(selectors.modalError, modal).classList.add('hidden');

        currentConfirm = onConfirm;
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        const confirmBtn = $(selectors.modalConfirm, modal);
        if (confirmBtn) {
            confirmBtn.focus();
        } else {
            modal.focus();
        }
        announce(title || 'Potwierdź operację');
    }

    function closeConfirm() {
        const modal = $(selectors.confirmModal);
        if (!modal) return;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        toggleConfirmLoading(false);
        currentConfirm = null;
    }

    function toggleConfirmLoading(isLoading) {
        const confirmBtn = $(selectors.modalConfirm);
        const spinner = $(selectors.modalSpinner);
        if (confirmBtn) {
            confirmBtn.disabled = isLoading;
        }
        if (spinner) {
            spinner.classList.toggle('hidden', !isLoading);
        }
    }

    function setupConfirmModal() {
        const modal = $(selectors.confirmModal);
        if (!modal) return;

        const cancelBtn = $(selectors.modalCancel, modal);
        const confirmBtn = $(selectors.modalConfirm, modal);

        if (cancelBtn) {
            cancelBtn.addEventListener('click', closeConfirm);
        }
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => {
                if (typeof currentConfirm === 'function') {
                    currentConfirm();
                }
            });
        }

        modal.addEventListener('click', (e) => {
            const surface = $(selectors.modalSurface, modal);
            if (surface && !surface.contains(e.target)) {
                closeConfirm();
            }
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && !modal.classList.contains('hidden')) {
                closeConfirm();
            }
        });
    }

    // ---------- Helpers ----------
    function buildJsonHeaders() {
        const headers = { 'Content-Type': 'application/json' };
        const csrf = getCsrfToken();
        if (csrf) headers['X-CSRF-Token'] = csrf;
        return headers;
    }

    async function safeJson(response) {
        try {
            return await response.json();
        } catch (error) {
            return {};
        }
    }

    function firstErrorMessage(payload) {
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

    function toggleLoading(btn, isLoading) {
        if (!btn) return;
        btn.disabled = isLoading;
        btn.classList.toggle('opacity-60', isLoading);
    }

    function showMessage(panel, message, variant = 'info') {
        const el = $(selectors.collaboratorsMessage, panel);
        if (!el) {
            showToast(message, variant === 'success' ? 'success' : 'error');
            return;
        }
        const closeBtn = $(selectors.collaboratorsMessageClose, el);
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
            closeBtn.addEventListener('click', () => {
                el.classList.add('hidden');
            });
            closeBtn.dataset.bound = 'true';
        }

        setTimeout(() => {
            el.classList.add('hidden');
        }, 3000);
    }

    function isDuplicateEmail(listEl, email) {
        if (!listEl) return false;
        const normalized = email.trim().toLowerCase();
        return $all('[data-collaborator-email]', listEl).some((row) => row.getAttribute('data-collaborator-email') === normalized);
    }

    function appendCollaboratorRow(listEl, collaborator, ctx) {
        if (!listEl) return;
        const empty = $(selectors.collaboratorsEmpty, listEl);
        if (empty) empty.remove();
        const row = document.createElement('div');
        row.className = 'px-4 py-3 flex flex-wrap items-center justify-between gap-3';
        row.setAttribute('data-collaborator-row', 'true');
        row.setAttribute('data-collaborator-id', collaborator.id ?? 'new');
        row.setAttribute('data-collaborator-email', (collaborator.email || '').toLowerCase());

        const role = collaborator.isOwner
            ? 'Właściciel'
            : collaborator.isSelf
                ? 'Ty'
                : 'Współedytor';

        const canRemove = ctx.canEdit && !collaborator.isOwner;
        const removeBtnHtml = canRemove
            ? `<div class="flex items-center gap-2">
                    <button type="button"
                        class="text-sm text-red-600 hover:text-red-800 font-semibold"
                        data-remove-collaborator
                        data-remove-url="${collaborator.removeUrl || ''}"
                        ${collaborator.isSelf ? 'data-self-remove="true"' : ''}>
                        ${collaborator.isSelf ? 'Usuń mój dostęp' : 'Usuń'}
                    </button>
               </div>`
            : '';

        row.innerHTML = `
            <div class="space-y-0.5">
                <p class="font-semibold text-slate-900">${escapeHtmlSafe(collaborator.email || '')}</p>
                <p class="text-xs text-slate-500">${role}</p>
            </div>
            ${removeBtnHtml}
        `;

        listEl.appendChild(row);

        if (canRemove) {
            const btn = row.querySelector('[data-remove-collaborator]');
            if (btn) {
                btn.addEventListener('click', (e) => {
                    e.preventDefault();
                    const url = btn.getAttribute('data-remove-url');
                    const isSelf = btn.getAttribute('data-self-remove') === 'true';
                    if (!url) return;
                    if (isSelf) {
                        openConfirm({
                            title: 'Usuń swój dostęp',
                            description: 'Utracisz możliwość edycji tej notatki.',
                            confirmLabel: 'Usuń mój dostęp',
                            onConfirm: () => removeCollaborator(url, true, ctx.redirectUrl),
                        });
                        return;
                    }
                    removeCollaborator(url, false, ctx.redirectUrl, listEl, row);
                });
            }
        }
    }

    function ensureEmptyState(listEl) {
        if (!listEl) return;
        const rows = $all('[data-collaborator-row]', listEl);
        if (rows.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'px-4 py-3 text-sm text-slate-500';
            empty.setAttribute('data-collaborators-empty', 'true');
            empty.textContent = 'Brak współedytorów.';
            listEl.appendChild(empty);
        }
    }

    function escapeHtmlSafe(text) {
        const div = document.createElement('div');
        div.textContent = text == null ? '' : String(text);
        return div.innerHTML;
    }

    // ---------- Init ----------
    function resetInitializationFlag() {
        if (document.body) {
            document.body.removeAttribute('data-edit-note-initialized');
        }
        closeConfirm();
        currentConfirm = null;
    }

    function init() {
        const hasEditSurface = document.querySelector(selectors.confirmModal)
            || document.querySelector(selectors.collaboratorsPanel)
            || document.querySelector(selectors.dangerZone);

        if (!hasEditSurface) {
            return;
        }

        if (document.body?.dataset.editNoteInitialized === 'true') {
            return;
        }
        if (document.body) {
            document.body.dataset.editNoteInitialized = 'true';
        }

        currentConfirm = null;
        setupConfirmModal();
        setupCopyPublicLink();
        setupCollaborators();
        setupDangerZone();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    document.addEventListener('turbo:load', init);
    document.addEventListener('turbo:before-cache', resetInitializationFlag);
})();


