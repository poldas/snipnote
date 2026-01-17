import './styles/dist/common.css';
import htmx from 'htmx.org';
import { app } from './stimulus_bootstrap.js';

// Register controllers needed for Authenticated area
import noteForm from './controllers/note_form_controller.js';
import editNote from './controllers/edit_note_controller.js';
import notesDashboard from './controllers/notes_dashboard_controller.js';
import publicTodo from './controllers/public_todo_controller.js';
import copy from './controllers/copy_controller.js';

app.register('note-form', noteForm);
app.register('edit-note', editNote);
app.register('notes-dashboard', notesDashboard);
app.register('public-todo', publicTodo);
app.register('copy', copy);

// Ensure HX requests always declare X-Requested-With for backend listeners
document.body?.addEventListener('htmx:configRequest', (event) => {
    event.detail.headers['X-Requested-With'] = 'XMLHttpRequest';
});