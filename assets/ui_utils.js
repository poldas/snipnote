/**
 * Common UI Utilities for Snipnote
 */

/**
 * Displays a toast notification in the #toast-stack container.
 */
export function showToast(message, variant = 'info') {
    const stack = document.getElementById('toast-stack');
    if (!stack) return;
    
    const el = document.createElement('div');
    el.className = `min-w-[240px] max-w-sm rounded-xl border px-4 py-3 text-sm font-semibold shadow-lg flex items-center justify-center gap-2 animate-fade-in ${
        variant === 'success'
            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
            : 'border-red-200 bg-red-50 text-red-800'
    }`;
    el.textContent = message;
    stack.appendChild(el);
    
    setTimeout(() => {
        el.classList.add('opacity-0', 'transition-opacity', 'duration-300');
        setTimeout(() => el.remove(), 300);
    }, 3000);
}

/**
 * Announces a message to screen readers.
 */
export function announce(message) {
    const region = document.querySelector('[data-global-aria-live]');
    if (region) {
        region.textContent = message;
    }
}

/**
 * Robust copy to clipboard function.
 */
export async function copyToClipboard(text) {
    if (!text) return false;

    if (navigator.clipboard && window.isSecureContext) {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (err) {
            console.warn('Clipboard API failed, falling back', err);
        }
    }

    try {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '0';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        const successful = document.execCommand('copy');
        document.body.removeChild(textArea);
        return successful;
    } catch (err) {
        console.error('Fallback copy failed', err);
        return false;
    }
}

/**
 * Escapes HTML special characters to prevent XSS.
 * @param {string} text 
 * @returns {string}
 */
export function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text ?? '';
    return div.innerHTML;
}
