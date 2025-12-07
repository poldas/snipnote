# API Endpoint Implementation Plan: GET /api/notes (List notes — owner dashboard)

## 1. Przegląd punktu końcowego

Endpoint zwraca listę notatek należących do aktualnie uwierzytelnionego użytkownika (dashboard właściciela). Obsługuje:
- paginację (page, per_page),
- wyszukiwanie full-text po tytule/opisie (q),
- filtrowanie po labelkach (label; OR pomiędzy wieloma labelkami),
- sortowanie domyślnie po `created_at DESC`.

---

## 2. Szczegóły żądania

- **Metoda HTTP:** `GET`
- **URL:** `/api/notes`
- **Auth:** wymagane poprawne JWT (Supabase Auth → weryfikacja po stronie Symfony, użytkownik dostępny jako `User` w SecurityContext).

### Parametry zapytania

- **Wymagane:**
  - brak (ale wymagana jest autoryzacja).

- **Opcjonalne:**
  - `page` — numer strony:
    - typ: integer (> 0),
    - domyślnie: wartość z konfiguracji, np. `1`.
  - `per_page` — liczba wyników na stronę:
    - typ: integer (> 0),
    - domyślnie: z konfiguracji, np. `20`,
    - maksymalna dozwolona wartość: z konfiguracji, np. `100` (ochrona przed nadużyciem).
  - `q` — tekst wyszukiwania:
    - typ: string (max długość np. 255 znaków),
    - używany do wyszukiwania full-text na `search_vector_simple`, fallback ILIKE.
  - `label` — powtarzalny parametr labelki:
    - typ: string,
    - `?label=work&label=personal` → OR logic (`labels && ARRAY['work','personal']`).

- **Request Body:** brak (query params tylko).

---

## 3. Wykorzystywane typy

### 3.1. DTO wejściowe

**`ListNotesQueryDto`** (Request DTO z query params):

```php
final class ListNotesQueryDto
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $q,
        /** @var string[] */
        public readonly array $labels
    ) {}
}
````

Walidacja (Symfony Validator — atrybuty):

* `page`: `NotBlank`, `Positive`.
* `perPage`: `NotBlank`, `Positive`, `LessThanOrEqual(maxPerPage)`.
* `q`: `Length(max=255)` (opcjonalne, `null` lub niepusty string).
* `labels`: tablica stringów, każdy `Length(max=64)` (przykład; długość możesz dopasować do wymagań produktu).

**Command/Query Model dla warstwy domenowej**

**`ListNotesQuery`** (Command/Query model przekazywany do serwisu):

```php
final class ListNotesQuery
{
    public function __construct(
        public readonly int $ownerId,
        public readonly int $page,
        public readonly int $perPage,
        public readonly ?string $q,
        /** @var string[] */
        public readonly array $labels
    ) {}
}
```

> DTO → walidacja → mapowanie do `ListNotesQuery`.

### 3.2. DTO wyjściowe

**`NoteSummaryDto`** (pojedyncza notatka na liście dashboardu):

```php
final class NoteSummaryDto
{
    public function __construct(
        public readonly int $id,
        public readonly string $urlToken,
        public readonly string $title,
        public readonly string $description,
        /** @var string[] */
        public readonly array $labels,
        public readonly string $visibility,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt
    ) {}
}
```

* `visibility` — string z enumu `note_visibility` (`public|private|draft`), mapowany 1:1.
* `description` — pełny lub skrócony (w zależności od wymagań UI; plan zakłada pełny, ewentualne skrócenie po stronie frontend).

**`PaginationMetaDto`**:

```php
final class PaginationMetaDto
{
    public function __construct(
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $total
    ) {}
}
```

**`NotesListResponseDto`** (wynik endpointu):

```php
final class NotesListResponseDto
{
    /**
     * @param NoteSummaryDto[] $data
     */
    public function __construct(
        public readonly array $data,
        public readonly PaginationMetaDto $meta
    ) {}
}
```

---

## 4. Szczegóły odpowiedzi

### 4.1. Sukces (`200 OK`)

Struktura JSON:

```json
{
  "data": [
    {
      "id": 123,
      "url_token": "d6f0b0aa-6bf6-4d0c-82b2-6d8a37e6dcff",
      "title": "My note",
      "description": "Markdown content...",
      "labels": ["work", "personal"],
      "visibility": "private",
      "created_at": "2025-01-01T10:00:00+00:00",
      "updated_at": "2025-01-02T08:30:00+00:00"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total": 42
  }
}
```

Mapowanie z DTO:

* `NoteSummaryDto` → snake_case w JSON (`url_token`, `created_at`).
* `NotesListResponseDto::meta` → `page`, `per_page`, `total`.

### 4.2. Kody statusu

* `200 OK` — poprawna odpowiedź z listą notatek (może być pusta lista).
* `400 Bad Request` — nieprawidłowe parametry wejściowe (walidacja DTO).
* `401 Unauthorized` — brak/niepoprawny JWT, brak zalogowanego użytkownika.
* `500 Internal Server Error` — nieprzewidziany błąd po stronie serwera (np. błąd DB, nieobsłużony wyjątek).

---

## 5. Przepływ danych

1. **Uwierzytelnianie / Security:**

   * Middleware/Authenticator weryfikuje JWT z Supabase.
   * Po sukcesie dostępny `User` w `SecurityToken` (np. `getUser()->getId()`).

2. **Kontroler (`NotesController::listOwnedNotes`):**

   * Route: `#[Route('/api/notes', name: 'api_notes_list', methods: ['GET'])]`.
   * Wyciąga query params z `Request`:

     * `page`, `per_page`, `q`, wszystkie `label`.
   * Tworzy `ListNotesQueryDto`.
   * Waliduje DTO (Symfony Validator).
   * Mapuje DTO do `ListNotesQuery` (uzupełnia `ownerId` z `Security`).

3. **Serwis aplikacyjny (`NotesQueryService`):**

   ```php
   final class NotesQueryService
   {
       public function __construct(
           private readonly NoteRepository $noteRepository
       ) {}

       public function listOwnedNotes(ListNotesQuery $query): NotesListResponseDto
       {
           // repo → wyniki + total
           // mapowanie encji Notes → NoteSummaryDto
           // złożenie NotesListResponseDto
       }
   }
   ```

   * Wywołuje metodę repozytorium, np.:

     * `findPaginatedForOwnerWithFilters(ListNotesQuery $query): PaginatedResult`.
   * Mapuje `PaginatedResult` na:

     * tablicę `NoteSummaryDto`,
     * `PaginationMetaDto`,
     * `NotesListResponseDto`.

4. **Warstwa repozytorium (`NoteRepository`):**

   * Implementacja zapytania Doctrine:

     * `WHERE n.owner = :ownerId`
     * opcjonalnie `AND` filter dla `q`:

       * preferowane: `n.searchVectorSimple @@ plainto_tsquery('simple', :q)`.
       * fallback: `(n.title ILIKE :pattern OR n.description ILIKE :pattern)`.
     * opcjonalnie `AND n.labels && :labelsArray` jeśli `labels` niepuste.
     * sortowanie: `ORDER BY n.createdAt DESC`.
     * paginacja: `setFirstResult(($page-1) * $perPage)`, `setMaxResults($perPage)`.
   * Równoległe obliczenie `total` (COUNT) przy tych samych filtrach (bez paginacji).

   **PaginatedResult** (wewnętrzny obiekt):

   ```php
   final class PaginatedResult
   {
       /**
        * @param Notes[] $items
        */
       public function __construct(
           public readonly array $items,
           public readonly int $total
       ) {}
   }
   ```

5. **Serializacja odpowiedzi:**

   * Controller zwraca `JsonResponse` z danych z `NotesListResponseDto`.
   * Serializacja może być:

     * ręczna (`array_map` → `json_encode`),
     * lub przez Symfony Serializer, z konfiguracją mapowania camelCase → snake_case.

---

## 6. Względy bezpieczeństwa

1. **Uwierzytelnianie:**

   * Endpoint oznaczony w konfiguracji security jako wymagający JWT (np. `IS_AUTHENTICATED_FULLY`).
   * Brak tokenu / niepoprawny token → automatycznie `401 Unauthorized`.

2. **Autoryzacja / izolacja danych:**

   * Każde zapytanie repozytorium **musi** zawierać `owner_id = :currentUserId`.
   * Brak możliwości nadpisania `ownerId` z query params (tylko z SecurityContext).

3. **Wstrzykiwanie SQL / FTS:**

   * `q` zawsze przekazywane jako parametr zapytania (bind param).
   * Dla FTS: użycie `plainto_tsquery` po stronie DB z parametrem, bez składania surowego SQL z inputu.
   * Dla ILIKE fallback: `:pattern = '%' . $q . '%'` budowane po stronie PHP, ale bindowane jako parametr.

4. **Walidacja wartości paginacji:**

   * Odrzucanie skrajnych wartości `per_page` (zbyt duże) — ochrona przed DoS/slow queries.

5. **Brak ujawniania wrażliwych danych:**

   * DTO `NoteSummaryDto` nie zwraca `owner_id`, danych współpracowników (`note_collaborators`) ani żadnych wewnętrznych identyfikatorów użytkownika.

6. **Logowanie i monitoring:**

   * Błędy walidacji i 5xx logowane przez Monolog.
   * Zwykłe Exception bez logowania w bazie, czy tabeli, zwykła standardowa obsługa błędów

---

## 7. Obsługa błędów

### 7.1. Scenariusze błędów

1. **Brak autoryzacji:**

   * Brak/niepoprawny JWT.
   * Odpowiedź: `401 Unauthorized`.
   * Treść (przykład):

     ```json
     {
       "error": "unauthorized",
       "message": "Authentication token is missing or invalid."
     }
     ```

2. **Walidacja parametrów query (`400 Bad Request`):**

   * `page` lub `per_page` nie są liczbami, są ≤0 lub przekraczają dopuszczalny limit.
   * `q` przekracza maksymalną długość.
   * `label` puste ciągi lub przekraczają długość.
   * Odpowiedź:

     ```json
     {
       "error": "validation_error",
       "message": "Invalid query parameters.",
       "details": {
         "page": ["This value should be positive."],
         "per_page": ["This value should be less than or equal to 100."]
       }
     }
     ```

3. **Błędy DB / wewnętrzne (`500 Internal Server Error`):**

   * Błąd Doctrine, błąd połączenia z DB, inne nieprzechwycone wyjątki.

   * Odpowiedź:

     ```json
     {
       "error": "internal_server_error",
       "message": "Unexpected error occurred."
     }
     ```

   * Logowanie:

     * Monolog (minimum: poziom `error`)

4. **Nietypowe przypadki:**

   * `page` poza zakresem (np. większe niż maksymalna liczba stron):

     * Odpowiedź `200 OK` z pustą tablicą `data` i poprawnym `meta.total` (preferowane),
     * brak potrzeby 404 dla brakujących danych w liście.

---

## 8. Rozważania dotyczące wydajności

1. **Indeksy DB:**

   * GIN index na `search_vector_simple`:

     * `CREATE INDEX idx_notes_search_vector_simple ON notes USING GIN (search_vector_simple);`
   * GIN index na `labels`:

     * `CREATE INDEX idx_notes_labels ON notes USING GIN (labels);`
   * BTREE index na `(owner_id, created_at DESC)`:

     * `CREATE INDEX idx_notes_owner_created_at ON notes (owner_id, created_at DESC);`

2. **Paginacja offsetowa:**

   * Standard `OFFSET/LIMIT` przy małej/średniej liczbie rekordów per user.
   * Jeśli w przyszłości ilość rośnie:

     * rozważ keyset pagination po `created_at` dla dużych zbiorów.

3. **Ograniczenie `per_page`:**

   * Twardy limit w walidacji (np. 100) → kontrola kosztu zapytań i payloadu.

4. **Wykorzystanie `COUNT(*)`:**

   * W przypadku bardzo dużych tabel można:

     * cache’ować `total` dla często używanych filtrów,
     * lub odłożyć pełną dokładność (np. approximate count) — do decyzji produktowej.

5. **N+1 queries:**

   * Endpoint bazuje na samej tabeli `notes` (brak joinów na `note_collaborators`), co minimalizuje ryzyko N+1.

---

## 9. Etapy wdrożenia

1. **Definicja DTO i modeli:**

   * Dodać `ListNotesQueryDto`, `ListNotesQuery`, `NoteSummaryDto`, `PaginationMetaDto`, `NotesListResponseDto` w odpowiednim module (np. `App\Notes\Application\Dto` / `App\Notes\Application\Query`).

2. **Walidacja DTO wejściowego:**

   * Dodać atrybuty Symfony Validator do `ListNotesQueryDto`.
   * Skonfigurować integrację z kontrolerem (manualnie lub przez custom `ArgumentValueResolver` dla DTO).

3. **Serwis zapytań (`NotesQueryService`):**

   * Utworzyć serwis:

     * metoda `listOwnedNotes(ListNotesQuery $query): NotesListResponseDto`.
   * Zarejestrować w DI (autowiring).

4. **Rozszerzenie repozytorium (`NoteRepository`):**

   * Dodać metody:

     * `findPaginatedForOwnerWithFilters(ListNotesQuery $query): PaginatedResult`.
   * Zaimplementować filtrowanie po:

     * `ownerId`,
     * `q` (FTS + ILIKE fallback),
     * `labels` (`labels && ARRAY[...]`),
     * sortowaniu po `created_at DESC`,
     * paginacji offsetowej.

5. **Kontroler API:**

   * Utworzyć/metodę w `NotesController`:

     * route `GET /api/notes`.
   * Wykonanie kroków:

     1. Pobranie aktualnego użytkownika (`$this->getUser()`).
     2. Zbudowanie `ListNotesQueryDto` z query params.
     3. Walidacja DTO, w razie błędów → `400` z payloadem błędów.
     4. Mapowanie DTO → `ListNotesQuery` (uzupełnienie `ownerId`).
     5. Wywołanie `NotesQueryService::listOwnedNotes`.
     6. Serializacja wynikowego `NotesListResponseDto` do JSON (`200 OK`).

6. **Indeksy i migracje (opcjonalnie na tym etapie, ale zalecane):**

   * Dodać Doctrine Migrations dla indeksów:

     * `idx_notes_search_vector_simple`,
     * `idx_notes_labels`,
     * `idx_notes_owner_created_at`.
   * W opisie migracji odnotować plan rollbacku (DROP INDEX).

7. **Obsługa błędów i logowanie:**

   * Upewnić się, że globalny handler wyjątków zwraca spójny JSON dla 500.

8. **Testy:**

   * **Testy integracyjne kontrolera:**

     * 401 dla braku tokena.
     * 400 dla nieprawidłowych parametrów (`page=0`, `per_page=0`, `per_page > max`).
     * 200:

       * bez filtrów (lista wszystkich notatek właściciela),
       * z `q` (sprawdzenie filtrowania po tytule/opisie),
       * z `label` (sprawdzenie OR logic),
       * z paginacją (`page`, `per_page`) — poprawne `meta.total`.
   * **Testy repozytorium:**

     * poprawne generowanie zapytań z filtrami,
     * poprawny `total` przy różnych kombinacjach filtrów.