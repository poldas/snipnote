import htmx from 'htmx.org';
import './stimulus_public.js';

// Ensure HX requests always declare X-Requested-With for backend listeners
document.body?.addEventListener('htmx:configRequest', (event) => {
    event.detail.headers['X-Requested-With'] = 'XMLHttpRequest';
    
    // Remove empty search parameter to keep URL clean
    if (event.detail.parameters.q === '') {
        delete event.detail.parameters.q;
    }
});
