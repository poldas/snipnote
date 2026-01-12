import { app } from './stimulus_bootstrap.js';
import publicTodo from './controllers/public_todo_controller.js';
import copy from './controllers/copy_controller.js';
import publicNav from './controllers/public_nav_controller.js';

// Only register controllers needed for public views
app.register('public-todo', publicTodo);
app.register('copy', copy);
app.register('public-nav', publicNav);