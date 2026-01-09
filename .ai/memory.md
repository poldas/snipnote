# Snipnote - Project Memory & Lessons Learned

## Security & Frontend Isolation

### AssetMapper & ImportMap Security
- **Problem**: Symfony AssetMapper (`importmap()`) by default exposes all defined JS paths in the HTML source.
- **Solution**: Always use a filtering mechanism (like the custom `importmap_safe` Twig function) on public-facing pages (Landing, Login, Register, Public Note).
- **Rule**: Explicitly exclude dashboard, editor, and administrative JS controllers from the import map for unauthenticated users to prevent information disclosure about internal application structure.

### Zero JS Policy for Auth Pages
- **Decision**: All authentication pages (Login, Register, Forgot Password, Reset Password) and the Landing page must function 100% without JavaScript.
- **Implementation**: Rely on standard HTML forms and POST requests. Do not load Stimulus, HTMX, or Turbo on these pages unless absolutely necessary for a critical non-form feature.

## Frontend Architecture & Stability

### Stimulus Stability in Dynamic UI
- **Context**: When rendering HTML dynamically via JavaScript (e.g., `innerHTML` in a loop).
- **Rule**: Never use manual `addEventListener`. It leads to memory leaks and "unbinding" issues when the DOM is refreshed.
- **Best Practice**: Use declarative Stimulus actions (`data-action`) and parameters. 
- **Parameters**: Use the `data-[controller]-[name]-param` syntax. In the controller, access these via `event.params.name` for reliable ID retrieval.

### Unified Modal System
- **Architecture**: Use a single, unified modal system instead of duplicating structures.
- **Components**:
    - `modal_controller.js`: Central logic for opening, closing, and handling confirmations.
    - `templates/components/modal.html.twig`: A base "glassmorphism" template that supports both `info` and `confirm` modes.
- **Mobile UX**: Always use `items-start sm:items-center` and `overflow-y-auto` on the modal backdrop to ensure the content is scrollable and not cut off on small viewports.

## E2E Testing & Stability (Playwright)

### Database Contention & Parallelism
- **Problem**: High parallel runs (many workers) cause deadlocks and session collisions in PostgreSQL when multiple tests modify the same user's data.
- **Solution**: Implement "User-per-Spec" dynamic isolation. 
- **Strategy**: Split tests into projects:
    - **UI Project**: Read-only tests (visual, smoke). Can run in high parallel (4+ workers).
    - **Functional Project**: Write tests (creation, editing). Should run with reduced parallelism (1-2 workers) or strict sequential execution to ensure 100% determinism.

### Race Conditions with Stimulus Initialization
- **Problem**: Playwright interacts with elements (clicks) before Stimulus `connect()` has finished attaching event listeners.
- **Best Practice**: Use Page Objects that wait for UI stability. After navigation, add a micro-delay (200-500ms) or wait for a specific "ready" state before clicking submit buttons to ensure JS events are correctly intercepted.

### Interacting with Custom Controls (sr-only)
- **Problem**: Standard `click()` on labels sometimes fails to trigger the underlying hidden radio/checkbox in complex Stimulus forms.
- **Solution**: Use `check({ force: true })` or direct property manipulation via `evaluate()` for hidden inputs. This guarantees the logical state is set regardless of CSS overlays.

### Clean E2E Code (No Try-Catch)
- **Rule**: Avoid `try...catch` blocks inside POM methods for control flow.
- **Reason**: It masks original stack traces and complicates debugging. Playwright's native assertions (`toHaveURL`, `toBeVisible`) with generous timeouts (20-30s) are more reliable and provide better trace data.

### Waiting for Dynamic Elements (waitFor vs isVisible)
- **Problem**: Using `if (await locator.isVisible())` is flaky for elements rendered by JavaScript (e.g., Stimulus targets) because it returns `false` immediately if the script hasn't finished rendering.
- **Rule**: Always use `await locator.waitFor({ state: 'visible' })` before conditional logic or interactions with dynamic elements. This forces Playwright to wait for the DOM to settle and ensures the element is truly ready.

## Domain Logic

### Intelligent Todo Synchronization (Merge)
- **Logic**: When combining local user progress (`localStorage`) with updates from the author (`Markdown` from backend).
- **Key**: Use the task content (`text`) as the unique identifier.
- **Behavior**: Preserve the user's `completed` and `deleted` status for existing tasks while seamlessly adopting new tasks or removals made by the author in the editor.