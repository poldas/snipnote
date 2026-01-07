import { app } from './stimulus_bootstrap.js';
import autofocus from './controllers/autofocus_controller.js';

// Minimal Stimulus for Auth (only autofocus)
app.register('autofocus', autofocus);

// No Turbo, No HTMX here to ensure 100% standard form behavior