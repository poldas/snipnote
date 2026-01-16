### Code Simplicity & DDD Alignment
- **Problem**: Complex inline logic in Controllers or Repositories (e.g., conditional visibility checks mixed with query logic) makes code hard to read and test.
- **Rule**: Keep code linear and simple. Avoid "clever" convoluted logic.
- **Solution**: Encapsulate business rules (like "is this public?") in dedicated Repository methods or Domain Services. The Repository should be the sole guardian of data filtering logic.
- Future DDD: To prepare for DDD migration, ensure Repositories strictly handle data retrieval based on clear criteria (DTOs), and Controllers remain thin orchestrators.

### Static Analysis & Type Safety
- **Problem**: Redundant type casting (e.g., `(string) $request->query->get()`) adds noise and triggers PHPStan `cast.useless` errors.
- **Rule**: Trust PHPStan's type inference, especially with Symfony plugins.
- **Solution**: Remove redundant casts. If a method returns `string|null` and you provide a default string, PHPStan correctly infers it as `string`.