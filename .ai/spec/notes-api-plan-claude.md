<analysis>
## Analiza specyfikacji API Notes

### Kluczowe punkty specyfikacji:
1. **CREATE** (`POST /api/notes`) - wymaga auth, generuje UUIDv4 `url_token`, domyślnie `visibility=private`
2. **READ by ID** (`GET /api/notes/{id}`) - wymaga auth (owner/collaborator)
3. **PUBLIC READ** (`GET /api/public/notes/{url_token}`) - bez auth, tylko dla `visibility=public`
4. **UPDATE** (`PATCH /api/notes/{id}`) - wymaga auth (owner/collaborator), częściowa aktualizacja
5. **DELETE** (`DELETE /api/notes/{id}`) - wymaga auth (tylko owner), CASCADE na `note_collaborators`

### Parametry wymagane i opcjonalne:

**CREATE:**
- Wymagane: `title`, `description`
- Opcjonalne: `labels` (domyślnie `[]`), `visibility` (domyślnie `private`)

**READ by ID:**
- Path param: `id` (wymagany)

**PUBLIC READ:**
- Path param: `url_token` (wymagany)

**UPDATE:**
- Path param: `id` (wymagany)
- Body: dowolny podzbiór: `title`, `description`, `labels`, `visibility`

**DELETE:**
- Path param: `id` (wymagany)

### Niezbędne typy DTO i Command:

1. **CreateNoteCommand** - walidacja danych wejściowych dla POST
2. **UpdateNoteCommand** - walidacja danych wejściowych dla PATCH
3. **NoteResponseDTO** - pełna reprezentacja notatki (authenticated)
4. **PublicNoteResponseDTO** - okrojona reprezentacja dla publicznego dostępu

### Logika w serwisach:

**NoteService** (nowy):
- `createNote(CreateNoteCommand, User): Note` - obsługa UUID collision (retry logic)
- `getNoteById(int, User): Note` - sprawdzenie uprawnień (owner/collaborator)
- `getPublicNoteByToken(string): Note` - tylko dla public notes
- `updateNote(int, UpdateNoteCommand, User): Note` - sprawdzenie uprawnień
- `deleteNote(int, User): void` - sprawdzenie uprawnień (tylko owner)

**CollaboratorService** (helper):
- `isCollaborator(Note, User): bool` - sprawdza czy user jest collaboratorem

### Walidacja:

1. **CreateNoteCommand:**
   - `title`: NOT NULL, max 255 znaków
   - `description`: NOT NULL, max np. 50000 znaków (do ustalenia)
   - `labels`: array of strings, każdy label max 100 znaków
   - `visibility`: enum ('public', 'private', 'draft')

2. **UpdateNoteCommand:**
   - Wszystkie pola opcjonalne, ale jeśli podane - te same reguły co CREATE

3. **UUID collision:**
   - Retry logic w serwisie (max 3 próby)
   - Zwrócenie 409 jeśli nie uda się wygenerować unikalnego UUID

### Mapowanie błędów:

- 400: walidacja (długość title/description, nieprawidłowy visibility enum)
- 401: brak JWT lub nieprawidłowy token
- 403: authenticated, ale brak uprawnień (nie owner/collaborator)
- 404: zasób nie istnieje
- 409: UUID collision (retry exhausted)
- 500: nieoczekiwane błędy

### Zagrożenia bezpieczeństwa:

1. **Authorization bypass** - weryfikacja owner/collaborator przy każdej operacji
2. **UUID enumeration** - publiczne endpointy używają UUID, nie ID (dobra praktyka)
3. **Mass assignment** - użycie DTO/Command zamiast bezpośredniego bindowania request → Entity
4. **XSS w markdown** - sanityzacja przy renderowaniu (na poziomie frontend, ale weryfikacja długości na backend)
5. **SQL injection** - Doctrine ORM chroni, ale QueryBuilder z parametrami
6. **Rate limiting** - rozważyć dla PUBLIC endpoint (nie w MVP)

### Scenariusze błędów:

1. **CREATE:**
   - 400: pusty title, za długi title/description, nieprawidłowy visibility
   - 401: brak JWT
   - 409: UUID collision po 3 próbach
   - 500: błąd DB

2. **READ by ID:**
   - 401: brak JWT
   - 403: user nie jest owner ani collaborator
   - 404: note nie istnieje
   - 500: błąd DB

3. **PUBLIC READ:**
   - 404: note nie istnieje LUB istnieje ale nie jest public (zgodnie ze spec - 403/404 do wyboru, wybieram 404 dla security through obscurity)
   - 500: błąd DB

4. **UPDATE:**
   - 400: walidacja pól
   - 401: brak JWT
   - 403: user nie jest owner ani collaborator
   - 404: note nie istnieje
   - 500: błąd DB

5. **DELETE:**
   - 401: brak JWT
   - 403: user nie jest owner (tylko owner może usunąć)
   - 404: note nie istnieje
   - 500: błąd DB
</analysis>

# API Endpoint Implementation Plan: Notes Management

## 1. Przegląd punktu końcowego

Moduł **Notes** umożliwia użytkownikom tworzenie, odczyt, aktualizację i usuwanie notatek z obsługą markdown, etykiet oraz trzech poziomów widoczności (`public`, `private`, `draft`). Obsługuje także publiczny dostęp do notatek przez unikalny token UUID oraz system współpracy (collaborators).

**Endpointy:**
- `POST /api/notes` - tworzenie notatki
- `GET /api/notes/{id}` - odczyt notatki (authenticated)
- `GET /api/public/notes/{url_token}` - odczyt publicznej notatki (bez auth)
- `PATCH /api/notes/{id}` - aktualizacja notatki
- `DELETE /api/notes/{id}` - usunięcie notatki

## 2. Szczegóły żądania

### CREATE Note
- **Metoda HTTP:** POST
- **Struktura URL:** `/api/notes`
- **Autoryzacja:** Wymagana (JWT)
- **Parametry:**
  - Wymagane: `title` (string, max 255 znaków), `description` (string, max 50000 znaków)
  - Opcjonalne: `labels` (array of strings, każdy max 100 znaków), `visibility` (enum: `public`/`private`/`draft`, domyślnie `private`)
- **Request Body:**
```json
{
  "title": "My recipe",
  "description": "Markdown content...",
  "labels": ["dessert żółć", "baking kick"],
  "visibility": "private"
}
```

### READ Note by ID
- **Metoda HTTP:** GET
- **Struktura URL:** `/api/notes/{id}`
- **Autoryzacja:** Wymagana (JWT, owner lub collaborator)
- **Parametry:**
  - Wymagane: `id` (path parameter, integer)

### PUBLIC READ by Token
- **Metoda HTTP:** GET
- **Struktura URL:** `/api/public/notes/{url_token}`
- **Autoryzacja:** Brak
- **Parametry:**
  - Wymagane: `url_token` (path parameter, UUID)

### UPDATE Note
- **Metoda HTTP:** PATCH
- **Struktura URL:** `/api/notes/{id}`
- **Autoryzacja:** Wymagana (JWT, owner lub collaborator)
- **Parametry:**
  - Wymagane: `id` (path parameter, integer)
  - Opcjonalne: dowolny podzbiór: `title`, `description`, `labels`, `visibility`
- **Request Body:**
```json
{
  "title": "Updated title",
  "description": "Updated markdown",
  "labels": ["new-label"],
  "visibility": "public"
}
```

### DELETE Note
- **Metoda HTTP:** DELETE
- **Struktura URL:** `/api/notes/{id}`
- **Autoryzacja:** Wymagana (JWT, tylko owner)
- **Parametry:**
  - Wymagane: `id` (path parameter, integer)

## 3. Wykorzystywane typy

### Command Models
```php
// src/Command/Note/CreateNoteCommand.php
final readonly class CreateNoteCommand
{
    public function __construct(
        public string $title,
        public string $description,
        public array $labels = [],
        public string $visibility = 'private'
    ) {}
}

// src/Command/Note/UpdateNoteCommand.php
final readonly class UpdateNoteCommand
{
    public function __construct(
        public ?string $title = null,
        public ?string $description = null,
        public ?array $labels = null,
        public ?string $visibility = null
    ) {}
}
```

### DTOs
```php
// src/DTO/Note/NoteResponseDTO.php
final readonly class NoteResponseDTO
{
    public function __construct(
        public int $id,
        public int $owner_id,
        public string $url_token,
        public string $title,
        public string $description,
        public array $labels,
        public string $visibility,
        public string $created_at,
        public string $updated_at
    ) {}
}

// src/DTO/Note/PublicNoteResponseDTO.php
final readonly class PublicNoteResponseDTO
{
    public function __construct(
        public string $title,
        public string $description,
        public array $labels,
        public string $created_at
    ) {}
}
```

## 4. Szczegóły odpowiedzi

### CREATE (201)
```json
{
  "data": {
    "id": 10,
    "owner_id": 123,
    "url_token": "uuid-v4",
    "title": "...",
    "description": "...",
    "labels": ["dessert żółć", "baking kick"],
    "visibility": "private",
    "created_at": "2025-01-15T10:30:00+00:00",
    "updated_at": "2025-01-15T10:30:00+00:00"
  }
}
```

### READ by ID (200)
Identyczna struktura jak CREATE (201)

### PUBLIC READ (200)
```json
{
  "data": {
    "title": "...",
    "description": "...",
    "labels": [...],
    "created_at": "2025-01-15T10:30:00+00:00"
  }
}
```

### UPDATE (200)
Identyczna struktura jak CREATE (201)

### DELETE (204)
Brak treści odpowiedzi

## 5. Przepływ danych

### CREATE Note Flow:
1. **Controller** odbiera żądanie → walidacja JWT (Symfony Security)
2. **Controller** tworzy `CreateNoteCommand` z request body
3. **Validator** sprawdza constraints (length, enum)
4. **NoteService::createNote()** →
   - Generuje UUIDv4 dla `url_token`
   - Rozpoczyna transakcję DB
   - Próbuje zapisać Note entity
   - Jeśli UNIQUE constraint violation (UUID collision) → retry (max 3 próby)
   - Jeśli sukces → commit + zwróć Note
   - Jeśli retry exhausted → rzuć `UuidCollisionException` (409)
5. **Controller** mapuje Note → `NoteResponseDTO` → JSON response (201)

### READ by ID Flow:
1. **Controller** odbiera żądanie → walidacja JWT
2. **NoteService::getNoteById(id, user)** →
   - Pobiera Note z DB (`NoteRepository::find(id)`)
   - Sprawdza `isOwnerOrCollaborator(note, user)`
   - Jeśli nie → rzuć `AccessDeniedException` (403)
   - Jeśli nie istnieje → 404
3. **Controller** mapuje Note → `NoteResponseDTO` → JSON response (200)

### PUBLIC READ Flow:
1. **Controller** odbiera żądanie (bez auth)
2. **NoteService::getPublicNoteByToken(url_token)** →
   - Pobiera Note z DB (`NoteRepository::findOneBy(['url_token' => $token])`)
   - Sprawdza `visibility === 'public'`
   - Jeśli nie public LUB nie istnieje → rzuć `NotFoundException` (404)
3. **Controller** mapuje Note → `PublicNoteResponseDTO` → JSON response (200)

### UPDATE Flow:
1. **Controller** odbiera żądanie → walidacja JWT
2. **Controller** tworzy `UpdateNoteCommand` z request body
3. **Validator** sprawdza constraints
4. **NoteService::updateNote(id, command, user)** →
   - Pobiera Note + sprawdza uprawnienia (jak w READ)
   - Aktualizuje tylko podane pola
   - Zapisuje `updated_at`
   - Flush do DB
5. **Controller** mapuje Note → `NoteResponseDTO` → JSON response (200)

### DELETE Flow:
1. **Controller** odbiera żądanie → walidacja JWT
2. **NoteService::deleteNote(id, user)** →
   - Pobiera Note
   - Sprawdza `isOwner(note, user)` (tylko owner może usunąć)
   - Jeśli nie owner → 403
   - `EntityManager::remove()` → DB CASCADE usuwa `note_collaborators`
3. **Controller** zwraca 204 No Content

## 6. Względy bezpieczeństwa

### Autoryzacja i Uwierzytelnianie:
1. **JWT Verification** - Symfony Security + lexik/jwt-authentication-bundle
   - Middleware weryfikuje JWT przy każdym żądaniu (oprócz PUBLIC READ)
   - User object dostępny przez `#[CurrentUser]` attribute lub `$this->getUser()`

2. **Ownership Check** - NoteVoter:
   ```php
   // src/Security/Voter/NoteVoter.php
   class NoteVoter extends Voter
   {
       const VIEW = 'NOTE_VIEW';
       const EDIT = 'NOTE_EDIT';
       const DELETE = 'NOTE_DELETE';
       
       protected function supports(string $attribute, mixed $subject): bool
       {
           return $subject instanceof Note 
               && in_array($attribute, [self::VIEW, self::EDIT, self::DELETE]);
       }
       
       protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
       {
           $user = $token->getUser();
           $note = $subject;
           
           return match($attribute) {
               self::VIEW, self::EDIT => $this->canViewOrEdit($note, $user),
               self::DELETE => $note->getOwner() === $user,
               default => false
           };
       }
       
       private function canViewOrEdit(Note $note, User $user): bool
       {
           if ($note->getOwner() === $user) {
               return true;
           }
           // Check collaborators table
           return $this->collaboratorRepository->isCollaborator($note, $user);
       }
   }
   ```

3. **Mass Assignment Protection** - użycie Command pattern zamiast bindowania request → Entity

4. **UUID vs ID exposure** - publiczne endpointy używają UUID, nie DB ID (security through obscurity)

5. **Input Validation** - Symfony Validator constraints:
   ```php
   #[Assert\NotBlank]
   #[Assert\Length(max: 255)]
   public string $title;
   
   #[Assert\NotBlank]
   #[Assert\Length(max: 50000)]
   public string $description;
   
   #[Assert\Choice(choices: ['public', 'private', 'draft'])]
   public string $visibility;
   ```

6. **XSS Protection** - markdown renderowany po stronie klienta, backend nie renderuje HTML

## 7. Obsługa błędów

### Mapowanie błędów:

| Kod | Scenariusz | Response Body |
|-----|-----------|---------------|
| 400 | Walidacja nie powiodła się (title/description za długie, nieprawidłowy visibility) | `{"error": "Validation failed", "details": {...}}` |
| 401 | Brak JWT lub nieprawidłowy token | `{"error": "Unauthorized"}` |
| 403 | Użytkownik authenticated, ale brak uprawnień (nie owner/collaborator) | `{"error": "Forbidden"}` |
| 404 | Note nie istnieje (lub istnieje ale nie jest public w PUBLIC READ) | `{"error": "Not found"}` |
| 409 | UUID collision po 3 próbach | `{"error": "UUID generation failed, please retry"}` |
| 500 | Nieoczekiwany błąd serwera | `{"error": "Internal server error"}` |

### Exception Handling Strategy:
```php
// src/EventListener/ExceptionListener.php
class ExceptionListener
{
    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        
        $response = match(true) {
            $exception instanceof ValidationException => 
                new JsonResponse(['error' => 'Validation failed', 'details' => $exception->getErrors()], 400),
            $exception instanceof AccessDeniedException => 
                new JsonResponse(['error' => 'Forbidden'], 403),
            $exception instanceof NotFoundHttpException => 
                new JsonResponse(['error' => 'Not found'], 404),
            $exception instanceof UuidCollisionException => 
                new JsonResponse(['error' => 'UUID generation failed'], 409),
            default => 
                new JsonResponse(['error' => 'Internal server error'], 500)
        };
        
        $event->setResponse($response);
    }
}
```

## 8. Rozważania dotyczące wydajności

### Potencjalne wąskie gardła:
1. **UUID collision retry logic** - może spowolnić CREATE, ale prawdopodobieństwo kolizji UUID jest ekstremalnie niskie (~10^-18)
2. **Collaborator check** - JOIN na `note_collaborators` przy każdym READ/UPDATE
3. **Full-text search** - `search_vector_simple` (tsvector) będzie używany w przyszłości dla wyszukiwania

### Strategie optymalizacji:

1. **Indeksy DB:**
   ```sql
   CREATE INDEX idx_notes_owner_id ON notes(owner_id);
   CREATE UNIQUE INDEX idx_notes_url_token ON notes(url_token);
   CREATE INDEX idx_note_collaborators_note_id ON note_collaborators(note_id);
   CREATE INDEX idx_note_collaborators_email ON note_collaborators(email);
   CREATE INDEX idx_notes_visibility ON notes(visibility); -- dla public queries
   ```

2. **Eager loading collaborators** jeśli często używane:
   ```php
   $qb->leftJoin('n.collaborators', 'c')->addSelect('c');
   ```

3. **Cache dla public notes** - rozważyć Redis/Symfony Cache dla `GET /api/public/notes/{token}` (MVP: brak cache)

4. **Pagination** - dla list notatek (poza scope tego dokumentu, ale konieczne dla `/api/notes` list endpoint)

5. **Query profiling** - użyj Symfony Profiler + Doctrine query logger do identyfikacji N+1 queries

### Trade-offs:
- **UUID retry logic**: zwiększa złożoność ale zapewnia unikalność bez auto-increment gaps
- **Voter pattern**: czytelność kodu vs niewielki overhead na sprawdzenie uprawnień przy każdym żądaniu
- **No caching MVP**: szybsze wdrożenie, opóźniony cache layer do przyszłej iteracji

## 9. Etapy wdrożenia

### Patch 1: Entity + Repository (~100 LOC)
```
notes: add Note entity and repository

Files:
- src/Entity/Note.php (Doctrine attributes, relationships)
- src/Entity/NoteVisibility.php (backed enum)
- src/Repository/NoteRepository.php (findOneByToken method)
- migrations/VersionXXX.php (CREATE TABLE notes)
```

### Patch 2: Commands + DTOs (~80 LOC)
```
notes: add command models and response DTOs

Files:
- src/Command/Note/CreateNoteCommand.php
- src/Command/Note/UpdateNoteCommand.php
- src/DTO/Note/NoteResponseDTO.php
- src/DTO/Note/PublicNoteResponseDTO.php
```

### Patch 3: NoteService (~200 LOC)
```
notes: implement NoteService with UUID retry logic

Files:
- src/Service/NoteService.php
- src/Exception/UuidCollisionException.php
- tests/Service/NoteServiceTest.php (unit tests dla retry logic)

Trade-offs: Retry logic w serwisie (nie w repo) dla czytelności. Max 3 próby to kompromis między retry exhaustion a performance.
```

### Patch 4: NoteVoter (~100 LOC)
```
notes: add NoteVoter for authorization

Files:
- src/Security/Voter/NoteVoter.php
- tests/Security/Voter/NoteVoterTest.php

Trade-offs: Voter pattern vs manual checks - Voter daje spójność ale dodaje abstraction layer.
```

### Patch 5: Controllers (~250 LOC)
```
notes: implement CRUD controllers

Files:
- src/Controller/Api/NoteController.php (CREATE, READ, UPDATE, DELETE)
- src/Controller/Api/PublicNoteController.php (PUBLIC READ)
- tests/Controller/Api/NoteControllerTest.php (integration tests)
```

### Patch 6: Exception Listener (~50 LOC)
```
notes: add global exception listener for JSON errors

Files:
- src/EventListener/ExceptionListener.php
- config/services.yaml (register listener)
```

### Patch 7: DB Indexes (~30 LOC)
```
notes: add performance indexes

Files:
- migrations/VersionYYY.php (CREATE INDEX statements)
- docs/rollback.sql (DROP INDEX statements)

Trade-offs: Indeksy przyspieszają queries ale spowalniają INSERT/UPDATE. W przypadku notes write:read ratio jest akceptowalny.
```

### Rollback Plan:
```sql
-- Rollback migration
DROP INDEX IF EXISTS idx_notes_visibility;
DROP INDEX IF EXISTS idx_note_collaborators_email;
DROP INDEX IF EXISTS idx_note_collaborators_note_id;
DROP TABLE IF EXISTS notes CASCADE;
DROP TYPE IF EXISTS note_visibility;
```

### Lint & Static Analysis:
```bash
vendor/bin/php-cs-fixer fix src/
vendor/bin/phpstan analyse src/ --level=5
vendor/bin/phpunit tests/
```