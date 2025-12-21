import './styles/dist/tailwind.css';
import htmx from 'htmx.org';
import './stimulus_bootstrap.js';

// Ensure HX requests always declare X-Requested-With for backend listeners
document.body?.addEventListener('htmx:configRequest', (event) => {
    event.detail.headers['X-Requested-With'] = 'XMLHttpRequest';
});
