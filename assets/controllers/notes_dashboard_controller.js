import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    connect() {
        this.abort = new AbortController();
        this.ariaLive = document.querySelector('[data-global-aria-live]');
        this.toastStack = document.getElementById('toast-stack');

        this.bindSearchForm();
        this.bindDeleteFlow();
        this.bindMobileMenu();
        this.bindCopyLinks();
    }

    disconnect() {
        this.abort?.abort();
    }


    bindSearchForm() {
        const form = document.querySelector('[data-notes-search-form]');
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
        // Use delegation on the controller element for dynamically rendered buttons
        this.on(this.element, 'click', async (event) => {
            const copyBtn = event.target.closest('[data-copy-public-link]');
            if (!copyBtn) return;

            event.preventDefault();
            // Removed stopPropagation to allow other handlers to work

            const link = copyBtn.getAttribute('data-link');
            if (!link) return;

            try {
                await navigator.clipboard.writeText(link);
                this.showToast('Skopiowano link do notatki', 'success');
                this.announce('Link do notatki skopiowany do schowka.');
            } catch (error) {
                console.warn('Clipboard error:', error);
                this.showToast('Nie udało się skopiować linku', 'error');
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

        // Close menu when clicking outside
        this.on(document, 'click', (event) => {
            if (!toggleBtn.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
    }

    bindDeleteFlow() {
        this.on(document, 'click', (event) => {
            const target = event.target;
            if (!(target instanceof HTMLElement)) return;
            const deleteBtn = target.closest('[data-delete-note]');
            console.log('Delete check - target:', target, 'deleteBtn:', deleteBtn);
            if (!deleteBtn) return;

            // Find modal dynamically
            const modal = document.getElementById('delete-confirm-modal');
            if (!modal) return;

            const url = deleteBtn.getAttribute('data-delete-url');
            const title = deleteBtn.getAttribute('data-note-title');
            if (!url) return;

            // Set up modal elements dynamically
            this.titleEl = modal.querySelector('[data-delete-note-title]');
            this.errorEl = modal.querySelector('[data-modal-error]');
            this.confirmBtn = modal.querySelector('[data-modal-confirm]');
            this.cancelBtn = modal.querySelector('[data-modal-cancel]');
            this.spinner = modal.querySelector('[data-modal-spinner]');
            this.confirmLabel = modal.querySelector('[data-modal-confirm-label]');

            // Set up event listeners for modal
            this.cancelBtn?.addEventListener('click', () => this.closeModal());
            this.on(modal, 'click', (event) => {
                if (event.target === modal) {
                    this.closeModal();
                }
            });

            this.confirmBtn?.addEventListener('click', () => {
                if (!this.deleteUrl) return;
                this.confirmBtn.disabled = true;
                this.confirmBtn.classList.add('opacity-70', 'cursor-not-allowed');
                this.spinner?.classList.remove('hidden');
                if (this.confirmLabel) this.confirmLabel.textContent = 'Usuwanie...';
                this.performDelete(this.deleteUrl);
            });

            this.openModal(url, title);
        });
    }

    openModal(url, title) {
        const modal = document.getElementById('delete-confirm-modal');
        if (!modal) return;

        this.deleteUrl = url;
        this.lastFocused = document.activeElement;
        if (this.titleEl) {
            this.titleEl.textContent = title || '(Bez tytułu)';
        }
        if (this.errorEl) {
            this.errorEl.classList.add('hidden');
            this.errorEl.textContent = '';
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        this.refreshFocusables();
        this.confirmBtn?.focus();
        document.addEventListener('keydown', this.onKeydown);
        this.announce('Potwierdź usunięcie notatki');
    }

    closeModal() {
        const modal = document.getElementById('delete-confirm-modal');
        if (!modal) return;

        this.deleteUrl = null;
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.removeEventListener('keydown', this.onKeydown);
        if (this.lastFocused instanceof HTMLElement) {
            this.lastFocused.focus();
        }
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
        if (event.key === 'Enter' && document.activeElement === this.confirmBtn) {
            event.preventDefault();
            this.confirmBtn?.click();
        }
    };

    refreshFocusables() {
        const modal = document.getElementById('delete-confirm-modal');
        if (!modal) {
            this.focusables = [];
            return;
        }
        this.focusables = Array.from(
            modal.querySelectorAll('button, [href], [tabindex]:not([tabindex="-1"])'),
        ).filter((el) => !el.hasAttribute('disabled') && !el.classList.contains('hidden'));
    }

    async performDelete(url) {
        const headers = { 'Content-Type': 'application/json' };
        const token = this.getAuthToken();
        if (token) headers['Authorization'] = `Bearer ${token}`;

        try {
            const res = await fetch(url, { method: 'DELETE', headers });
            if (res.ok) {
                this.announce('Notatka usunięta');
                this.showToast('Notatka usunięta', 'success');
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
            this.showToast(message, 'error');
        } catch (error) {
            this.showError('Błąd sieci. Spróbuj ponownie.');
            this.showToast('Błąd sieci. Spróbuj ponownie.', 'error');
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
        } else {
            alert(message);
        }
    }

    getAuthToken() {
        const local = localStorage.getItem('auth_token');
        if (local) return local;
        const match = document.cookie.match(/(?:^|; )auth_token=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : null;
    }

    announce(message) {
        if (!this.ariaLive) return;
        this.ariaLive.textContent = message;
    }

    showToast(message, variant = 'info') {
        if (!this.toastStack) return;
        const toast = document.createElement('div');
        toast.className = 'min-w-[240px] max-w-sm rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg flex items-start gap-2 ' +
            (variant === 'success'
                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                : 'border-red-200 bg-red-50 text-red-800');
        toast.textContent = message;
        this.toastStack.appendChild(toast);
        setTimeout(() => toast.remove(), 3000);
    }

    on(target, event, handler) {
        if (!target) return;
        target.addEventListener(event, handler, { signal: this.abort.signal });
    }
}

