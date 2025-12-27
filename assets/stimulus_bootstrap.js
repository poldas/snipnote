import { startStimulusApp } from '@symfony/stimulus-bundle';
import noteForm from './controllers/note_form_controller.js';
import editNote from './controllers/edit_note_controller.js';
import notesDashboard from './controllers/notes_dashboard_controller.js';

const app = startStimulusApp();
app.register('note-form', noteForm);
app.register('edit-note', editNote);
app.register('notes-dashboard', notesDashboard);
