### Code Simplicity & DDD Alignment
- **Problem**: Complex inline logic in Controllers or Repositories (e.g., conditional visibility checks mixed with query logic) makes code hard to read and test.
- **Rule**: Keep code linear and simple. Avoid "clever" convoluted logic.
- **Solution**: Encapsulate business rules (like "is this public?") in dedicated Repository methods or Domain Services. The Repository should be the sole guardian of data filtering logic.
- Future DDD: To prepare for DDD migration, ensure Repositories strictly handle data retrieval based on clear criteria (DTOs), and Controllers remain thin orchestrators.

### Static Analysis & Type Safety
- **Problem**: Redundant type casting (e.g., `(string) $request->query->get()`) adds noise and triggers PHPStan `cast.useless` errors.
- **Rule**: Trust PHPStan's type inference, especially with Symfony plugins.
- **Solution**: Remove redundant casts. If a method returns `string|null` and you provide a default string, PHPStan correctly infers it as `string`.

### E2E Testing Stability (Playwright)
- **Problem**: Tests failing randomly due to overlapping UI (Toasts), race conditions, or identical timestamps in database sorting. Masking failures with `try-catch` in PageObjects.
- **Rule**: Never mask UI failures. Manage UI state explicitly.
- **Solution**: 
    - Implement `toast.expectHidden()` to ensure no overlays block clicks.
    - Use explicit waiting for UI list updates before submitting forms (e.g., waiting for a collaborator chip).
    - Add small intentional delays (500ms) between write operations in sorting tests to ensure unique `updatedAt` values.
    - Avoid JS-click fallbacks; if Playwright can't click it, find out why (is it visible? is it stable?).
- **Defensive UI Implementation**: Always use `event.preventDefault()` in JavaScript handlers for components inside forms (like tag inputs) to prevent accidental trigger of the main form submission, which is a common cause of flaky E2E tests (detached elements).
- **Outcome**: Deterministic tests that fail only when there is a real bug, with clear error logs.
