import { Controller } from '@hotwired/stimulus';
import { showToast, announce, copyToClipboard } from 'ui_utils';

export default class extends Controller {
    static values = {
        content: String,
        successMessage: { type: String, default: 'Skopiowano do schowka' }
    }

    async copy(event) {
        event.preventDefault();
        const btn = event.currentTarget;
        const contentToCopy = this.contentValue;
        
        if (!contentToCopy) return;

        const success = await copyToClipboard(contentToCopy);
        
        if (success) {
            showToast(this.successMessageValue, 'success');
            announce(this.successMessageValue);
            
            // Opcjonalna animacja przycisku
            if (btn && btn.dataset.showSuccess === 'true') {
                btn.classList.add('text-emerald-600', 'bg-emerald-50');
                setTimeout(() => {
                    btn.classList.remove('text-emerald-600', 'bg-emerald-50');
                }, 1000);
            }
        } else {
            showToast('Nie udało się skopiować', 'error');
        }
    }
}
