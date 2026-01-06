import { Controller } from '@hotwired/stimulus';
import { showToast, announce, copyToClipboard, escapeHtml } from 'ui_utils';

export default class extends Controller {
    connect() {
        this.abort = new AbortController();
        this.ariaLive = document.querySelector('[data-global-aria-live]');
        this.toastStack = document.getElementById('toast-stack');

        this.bindSearchForm();
        this.bindMobileMenu();
        this.bindCopyLinks();
        this.setupDeleteModal();
    }

    disconnect() {
        this.abort?.abort();
    }

    setupDeleteModal() {
        const modal = document.querySelector('[data-confirm-modal]');
        if (!modal) return;

        this.modal = modal;
        this.titleEl = modal.querySelector('[data-modal-title]');
        this.descEl = modal.querySelector('[data-modal-description]');
        this.errorEl = modal.querySelector('[data-modal-error]');
        this.confirmBtn = modal.querySelector('[data-modal-confirm]');
        this.cancelBtn = modal.querySelector('[data-modal-cancel]');
        this.spinner = modal.querySelector('[data-modal-spinner]');
        this.confirmLabel = modal.querySelector('[data-modal-confirm-label]');

        // Bind modal buttons once
        this.on(this.cancelBtn, 'click', () => this.closeModal());
        this.on(modal, 'click', (event) => {
            if (event.target === modal) this.closeModal();
        });

        this.on(this.confirmBtn, 'click', () => {
            if (!this.deleteUrl) return;
            this.confirmBtn.disabled = true;
            this.confirmBtn.classList.add('opacity-70', 'cursor-not-allowed');
            this.spinner?.classList.remove('hidden');
            if (this.confirmLabel) this.confirmLabel.textContent = 'Usuwanie...';
            this.performDelete(this.deleteUrl);
        });

        // Listen for delete button clicks via delegation on the controller element
        this.on(this.element, 'click', (event) => {
            const deleteBtn = event.target.closest('[data-delete-note]');
            if (!deleteBtn) return;

            event.preventDefault();
            const url = deleteBtn.getAttribute('data-delete-url');
            const title = deleteBtn.getAttribute('data-note-title');
            if (url) this.openModal(url, title);
        });
    }

    bindSearchForm() {
        const form = this.element.querySelector('[data-notes-search-form]');
        if (!form) return;

        const submitButton = form.querySelector('[data-search-submit]');
        const spinner = form.querySelector('[data-search-spinner]');

        this.on(form, 'submit', () => {
            if (submitButton) {
                submitButton.disabled = true;
                submitButton.classList.add('opacity-70', 'cursor-not-allowed');
            }
            spinner?.classList.remove('hidden');
        });
    }

    bindCopyLinks() {
        this.on(this.element, 'click', async (event) => {
            const copyBtn = event.target.closest('[data-copy-public-link]');
            if (!copyBtn) return;

            event.preventDefault();
            const link = copyBtn.getAttribute('data-link');

            const success = await copyToClipboard(link);
            if (success) {
                showToast('Skopiowano link do notatki', 'success');
                announce('Link do notatki skopiowany do schowka.');
            } else {
                showToast('Nie udało się skopiować linku', 'error');
            }
        });
    }

    bindMobileMenu() {
        const toggleBtn = this.element.querySelector('[data-mobile-menu-toggle]');
        const mobileMenu = this.element.querySelector('[data-mobile-menu]');

        if (!toggleBtn || !mobileMenu) return;

        this.on(toggleBtn, 'click', () => {
            mobileMenu.classList.toggle('hidden');
        });

        this.on(document, 'click', (event) => {
            if (!toggleBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    }

    openModal(url, title) {
        if (!this.modal) return;

        this.deleteUrl = url;
        this.lastFocused = document.activeElement;

        if (this.titleEl) {
            this.titleEl.textContent = 'Usunąć notatkę?';
        }
        if (this.descEl) {
            const noteName = title || '(Bez tytułu)';
            this.descEl.textContent = ''; // Clear
            this.descEl.appendChild(document.createTextNode('Czy na pewno chcesz usunąć notatkę: '));
            const span = document.createElement('span');
            span.className = 'font-bold text-indigo-600';
            span.textContent = noteName + "?";
            this.descEl.appendChild(span);
            const br = document.createElement('br');
            this.descEl.appendChild(br);
            this.descEl.appendChild(document.createTextNode('Operacja jest nieodwracalna. Czy chcesz kontynuować?'));
        }
        if (this.errorEl) {
            this.errorEl.classList.add('hidden');
            this.errorEl.textContent = '';
        }

        this.modal.classList.remove('hidden');
        this.modal.classList.add('flex');
        this.refreshFocusables();
        this.confirmBtn?.focus();
        document.addEventListener('keydown', this.onKeydown);
        announce('Potwierdź usunięcie notatki');
    }

    closeModal() {
        if (!this.modal) return;

        this.deleteUrl = null;
        this.modal.classList.add('hidden');
        this.modal.classList.remove('flex');
        document.removeEventListener('keydown', this.onKeydown);
        if (this.lastFocused instanceof HTMLElement) {
            this.lastFocused.focus();
        }

        // Reset button state
        if (this.confirmBtn) {
            this.confirmBtn.disabled = false;
            this.confirmBtn.classList.remove('opacity-70', 'cursor-not-allowed');
        }
        if (this.spinner) this.spinner.classList.add('hidden');
        if (this.confirmLabel) this.confirmLabel.textContent = 'Usuń';
    }

    onKeydown = (event) => {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.closeModal();
        }
        if (event.key === 'Tab' && this.focusables.length > 0) {
            const first = this.focusables[0];
            const last = this.focusables[this.focusables.length - 1];
            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }
    };

    refreshFocusables() {
        if (!this.modal) return;
        this.focusables = Array.from(
            this.modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])'),
        ).filter((el) => !el.hasAttribute('disabled') && !el.classList.contains('hidden'));
    }

    async performDelete(url) {
        const headers = { 'Content-Type': 'application/json' };
        const token = this.getAuthToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;

        try {
            const res = await fetch(url, { method: 'DELETE', headers });
            if (res.ok) {
                announce('Notatka usunięta');
                showToast('Notatka usunięta', 'success');
                setTimeout(() => window.location.reload(), 400);
                return;
            }
            if (res.status === 401) {
                window.location.href = '/login?redirect=' + encodeURIComponent(window.location.pathname + window.location.search);
                return;
            }
            const message = res.status === 403
                ? 'Brak uprawnień do usunięcia tej notatki.'
                : res.status === 404
                    ? 'Notatka nie istnieje.'
                    : 'Nie udało się usunąć notatki. Spróbuj ponownie.';
            this.showError(message);
            showToast(message, 'error');
        } catch (error) {
            this.showError('Błąd sieci. Spróbuj ponownie.');
            showToast('Błąd sieci. Spróbuj ponownie.', 'error');
        } finally {
            if (this.confirmBtn) {
                this.confirmBtn.disabled = false;
                this.confirmBtn.classList.remove('opacity-70', 'cursor-not-allowed');
            }
            this.spinner?.classList.add('hidden');
            if (this.confirmLabel) this.confirmLabel.textContent = 'Usuń';
        }
    }

    showError(message) {
        if (this.errorEl) {
            this.errorEl.textContent = message;
            this.errorEl.classList.remove('hidden');
        }
    }

    getAuthToken() {
        const local = localStorage.getItem('auth_token');
        if (local) return local;
        const match = document.cookie.match(/(?:^|; )auth_token=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    on(target, event, handler) {
        if (!target) return;
        target.addEventListener(event, handler, { signal: this.abort.signal });
    }
}