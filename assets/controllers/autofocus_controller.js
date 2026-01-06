import { Controller } from '@hotwired/stimulus';

/*
 * Focuses the element only on desktop screens to prevent 
 * unwanted scrolling on mobile devices during initial load.
 */
export default class extends Controller {
    connect() {
        this.handleFocus();
    }

    handleFocus() {
        // Only autofocus if screen width is greater than 1024px (desktop)
        if (window.innerWidth >= 1024) {
            // Small delay to ensure browser has finished initial rendering/scrolling
            setTimeout(() => {
                this.element.focus();
            }, 100);
        }
    }
}
