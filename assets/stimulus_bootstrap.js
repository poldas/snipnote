import { startStimulusApp } from '@symfony/stimulus-bundle';

// Start the Stimulus application without pre-registering all controllers.
// Specific entry points (app.js, auth.js, public_note.js) will register what they need.
const app = startStimulusApp();

export { app };