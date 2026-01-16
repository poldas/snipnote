### Code Simplicity & DDD Alignment
- **Problem**: Complex inline logic in Controllers or Repositories (e.g., conditional visibility checks mixed with query logic) makes code hard to read and test.
- **Rule**: Keep code linear and simple. Avoid "clever" convoluted logic.
- **Solution**: Encapsulate business rules (like "is this public?") in dedicated Repository methods or Domain Services. The Repository should be the sole guardian of data filtering logic.
- **Future DDD**: To prepare for DDD migration, ensure Repositories strictly handle data retrieval based on clear criteria (DTOs), and Controllers remain thin orchestrators.