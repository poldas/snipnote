import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ["content"];

    connect() {
        this.addBackToTopLinks();
    }

    addBackToTopLinks() {
        // Find all headings within the prose area (h2 to h6)
        const headings = this.element.querySelectorAll('h2, h3, h4, h5, h6');
        
        headings.forEach(heading => {
            if (heading.querySelector('.back-to-top')) return;

            const topLink = document.createElement('a');
            topLink.href = '#';
            topLink.className = 'back-to-top';
            topLink.innerHTML = '↑';
            topLink.title = 'Powrót do góry';
            topLink.setAttribute('aria-label', 'Powrót do góry');
            
            // Append after the permalink icon if exists, or at the start
            const permalink = heading.querySelector('.heading-permalink');
            if (permalink) {
                permalink.after(topLink);
            } else {
                heading.prepend(topLink);
            }
        });
    }
}
