````markdown
# API Endpoint Implementation Plan: GET /api/public/users/{user_uuid}/notes

## 1. Przegląd punktu końcowego

Publiczny punkt końcowy zwracający listę publicznych notatek (`visibility = 'public'`) dla wskazanego użytkownika zidentyfikowanego przez `user_uuid`. Obsługuje:
- paginację (`page`, `per_page`),
- wyszukiwanie pełnotekstowe (`q`),
- filtrowanie po pojedynczym labelu (`label`).

Brak wymogu uwierzytelnienia (publiczny katalog). Dane wykorzystywane przez frontend do widoku katalogu notatek użytkownika.

---

## 2. Szczegóły żądania

- **Metoda HTTP:** `GET`
- **Struktura URL:**  
  `/api/public/users/{user_uuid}/notes`

### Parametry

#### 2.1. Parametry ścieżki

- `user_uuid` (wymagany)
  - Typ: `string` (UUID v4)
  - Walidacja:
    - format UUID (np. `ramsey/uuid` lub własny validator),
    - w przypadku niepoprawnego formatu → `400 Bad Request`.

#### 2.2. Parametry query

- **Wymagane:** brak
- **Opcjonalne:**
  - `page`
    - Typ: integer
    - Domyślna wartość: `1`
    - Walidacja: `page >= 1`, brak wartości → domyślna, niepoprawny typ → `400`.
  - `per_page`
    - Typ: integer
    - Domyślna wartość: np. `20`
    - Walidacja:
      - `per_page >= 1`
      - `per_page <= 100` (limit anty-DoS)
      - błędna wartość → `400`.
  - `q`
    - Typ: string
    - Opis: fraza do wyszukiwania po `title` + `description` (pełnotekstowo po `search_vector_simple`).
    - Walidacja:
      - przycięcie do maks. długości, np. 255 znaków,
      - opcjonalne usunięcie nadmiarowych spacji,
      - pusta po przycięciu → ignorowana.
  - `label`
    - Typ: string
    - Opis: pojedynczy label, który musi występować w tablicy `labels` notatki.
    - Walidacja:
      - długość 1–64 znaki (lub zgodnie z zasadami labeli w systemie),
      - usunięcie białych znaków na początku/końcu,
      - pusta po przycięciu → ignorowana.

### Request Body

- Brak treści żądania (`GET`).

---

## 3. Wykorzystywane typy

### 3.1. DTO (read models)

1. **`PublicNoteListItemDto`**
   - Pola:
     - `string $title`
     - `string $descriptionExcerpt`
     - `string[] $labels`
     - `\DateTimeImmutable $createdAt`
     - `string $urlToken` (UUID w postaci string)
   - Cel: reprezentacja pojedynczej notatki w liście.

2. **`PaginationMetaDto`**
   - Pola:
     - `int $page`
     - `int $perPage`
     - `int $totalItems`
     - `int $totalPages`
   - Cel: meta-informacje dla frontend.

3. **`PublicNoteListResponseDto`**
   - Pola:
     - `PublicNoteListItemDto[] $data`
     - `PaginationMetaDto $meta`
     - (opcjonalnie) `string|null $message` — przy pustej liście / informacyjny komunikat.

4. **`PublicNotesQueryDto`** (wewnętrzny DTO dla serwisu)
   - Pola:
     - `string $userUuid`
     - `int $page`
     - `int $perPage`
     - `string|null $searchQuery`
     - `string|null $label`
   - Cel: zebrane, zwalidowane parametry wejściowe przekazywane do serwisu.

### 3.2. Command modele

- Dla `GET` nie tworzymy klasycznego Command, ale możemy użyć `PublicNotesQueryDto` jako „Query Model”.
- Alternatywy:
  1. **Wariant A (zalecany-MVP):** użyć `PublicNotesQueryDto` jako prostego DTO typu query.
  2. **Wariant B:** wprowadzić dedykowany `GetPublicUserNotesQuery` + handler (CQRS) – większa złożoność, lepsza skalowalność.

---

## 3. Szczegóły odpowiedzi

### 3.1. Struktura odpowiedzi 200

```json
{
  "data": [
    {
      "title": "Note title",
      "description_excerpt": "Short excerpt of description...",
      "labels": ["work", "ideas"],
      "created_at": "2025-01-01T12:34:56+00:00",
      "url_token": "550e8400-e29b-41d4-a716-446655440000"
    }
  ],
  "meta": {
    "page": 1,
    "per_page": 20,
    "total_items": 42,
    "total_pages": 3
  }
}
````

### 3.2. Kody statusu

* `200 OK`

  * Lista notatek + meta. Przy braku notatek dla istniejącego użytkownika — `data: []`, `total_items = 0`.
* `400 Bad Request`

  * Niepoprawne parametry (np. złamany format UUID, ujemna strona, `per_page` > limitu).
* `404 Not Found`

  * Użytkownik o podanym `user_uuid` nie istnieje.
* `500 Internal Server Error`

  * Nieoczekiwane błędy (np. wyjątki w zapytaniu DB, runtime errors).

`401`/`403` — nieużywane (endpoint publiczny), ale trzeba zapewnić, by globalne middleware auth nie blokowało tego endpointu.

---

## 4. Przepływ danych

### 4.1. Ogólny flow

1. **Controller** (`PublicUserNotesController::list()`):

   * Odczytuje `user_uuid` z path.
   * Odczytuje i parsuje `page`, `per_page`, `q`, `label` z query.
   * Waliduje typy i wartości (prosty validator lub Symfony `Validator`).
   * Buduje `PublicNotesQueryDto`.

2. **Serwis domenowy** (np. `PublicNotesCatalogService`):

   * Metoda:
     `PublicNoteListResponseDto getPublicNotes(PublicNotesQueryDto $query): PublicNoteListResponseDto`
   * Kroki:

     1. Sprawdzenie istnienia użytkownika po `users.uuid`.

        * Jeśli brak → wyjątek domenowy `UserNotFoundException`.
     2. Zbudowanie zapytania do `notes`:

        * filtr: `notes.owner_id = users.id`
        * filtr: `notes.visibility = 'public'`
        * filtr: `label` (jeśli podany): `:label = ANY (notes.labels)`
        * filtr: `q` (jeśli podane):

          * wariant 1 (zalecany): `search_vector_simple @@ plainto_tsquery('simple', :q)`
          * wariant 2: fallback `ILIKE` po `title`/`description`, jeśli `search_vector_simple` nie wykorzystywany.
     3. Zliczenie `totalItems` (COUNT(*) w sub-zapytaniu lub osobne zapytanie).
     4. Wyliczenie offset/limit:

        * `offset = (page - 1) * perPage`
        * `limit = perPage`
     5. Pobranie danych:

        * tylko wymagane pola: `title`, `description`, `labels`, `created_at`, `url_token`.
        * ewentualnie użycie `PartialObject` lub `ResultSetMapping`/`DTO hydration`.
     6. Zbudowanie `PublicNoteListItemDto[]` z wyników.
     7. Zbudowanie `PaginationMetaDto` + `PublicNoteListResponseDto`.

3. **Controller**:

   * Mapuje `PublicNoteListResponseDto` do struktury JSON (snake_case klucze).
   * Zwraca `JsonResponse` z kodem `200`.

### 4.2. Warstwa repozytorium (Doctrine)

* `UserRepository`:

  * `findIdByUuid(string $uuid): ?int` — zwraca tylko `id`, bez pełnej encji (dla wydajności).
* `NoteRepository`:

  * `findPublicNotesForOwner(int $ownerId, ?string $search, ?string $label, int $page, int $perPage): PublicNotesResult`

    * gdzie `PublicNotesResult` to prosty obiekt zawierający:

      * `array $rows` (surowe wyniki),
      * `int $totalItems`.

Przykładowe kryteria w `QueryBuilder`:

* `where n.owner = :ownerId`
* `andWhere n.visibility = :visibilityPublic`
* `andWhere :label = ANY (n.labels)` (jeśli `label` != null)
* `andWhere n.searchVectorSimple @@ plainto_tsquery('simple', :q)` (jeśli `q` != null)

---

## 5. Względy bezpieczeństwa

1. **Brak auth, ale ścisłe filtrowanie:**

   * ZAWSZE filtr `visibility = 'public'`.
   * Nigdy nie zwracać:

     * `owner_id`,
     * wewnętrznych ID notatki,
     * żadnych danych wrażliwych.

2. **Walidacja wejścia:**

   * Format UUID dla `user_uuid`.
   * Ograniczenie `per_page` (np. max 100).
   * Ograniczenie długości `q`, `label` (uniknięcie dużych payloadów).
   * Obsługa błędów typu „niepoprawna wartość” → `400` zamiast `500`.

3. **Ochrona przed enumeracją UUID:**

   * UUID i tak jest trudny do zgadywania; dodatkowo:

     * brak różnicy w czasie odpowiedzi między „user exists no notes” a „user not exists” ma mniejsze znaczenie dla bezpieczeństwa; PRD wymaga 404 dla nieistniejącego użytkownika — akceptujemy to jako kompromis usability vs. privacy.

4. **SQL Injection:**

   * ZAWSZE parametry bindowane (Doctrine parametrized queries).
   * W przypadku `q`, `label` — nigdy nie konkatenować do SQL jako surowego tekstu.

5. **Logging i monitoring:**

   * Logowanie błędów przy `500` (Monolog).

---

## 6. Obsługa błędów

### 6.1. Mapowanie scenariuszy → kody statusu

1. **Nieistniejący użytkownik:**

   * `UserRepository::findIdByUuid()` zwraca `null`.
   * Serwis rzuca `UserNotFoundException`.
   * Controller (lub globalny ExceptionListener) mapuje na:

     * `404 Not Found`
     * body:

       ```json
       { "error": "user_not_found", "message": "No such user." }
       ```

2. **Brak publicznych notatek dla istniejącego użytkownika:**

   * `totalItems = 0`
   * Zwracamy `200 OK` z:

     ```json
     { "data": [], "meta": { ... } }
     ```
   * Opcjonalnie `meta.message` lub osobne pole `message`.

3. **Niepoprawne parametry requestu:**

   * Parser/validator zgłasza wyjątek (np. `InvalidRequestException`).
   * Mapowanie:

     * `400 Bad Request`
     * body:

       ```json
       {
         "error": "invalid_request",
         "message": "Invalid query parameters.",
         "details": {
           "page": "Must be >= 1",
           "per_page": "Must be <= 100"
         }
       }
       ```

4. **Błędy bazy danych / inne nieoczekiwane wyjątki:**

   * `500 Internal Server Error`
   * body:

     ```json
     { "error": "server_error", "message": "Unexpected server error." }
     ```
   * Szczegóły błędu tylko w logach, nie w odpowiedzi.

### 6.2. Logowanie błędów

W specyfikacji nie ma tabeli błędów — nie wiem, czy istnieje dedykowana tabela logów. Proponuję 2 warianty:

1. **Wariant A (MVP, zalecany):**

   * Logowanie przez Monolog (file/STDERR/centralny system logowania).
   * Konfiguracja:

     * poziom `error` dla `UserNotFoundException`, `InvalidRequestException`, `DBALException` itp.
   * Brak zmian w DB.
---

## 7. Rozważania dotyczące wydajności

1. **Indeksy DB (jeśli jeszcze nie istnieją):**

   * Na `users.uuid` (UNIQUE / index).
   * Na `notes.owner_id`.
   * Na `notes.visibility`.
   * Na `notes.search_vector_simple` (GIN index).
   * Na `notes.labels` (GIN index) dla szybkiego `ANY (labels)`.

2. **Minimalna selekcja kolumn:**

   * W zapytaniu pobierać tylko potrzebne pola: `title`, `description`, `labels`, `created_at`, `url_token`.
   * Generować `description_excerpt` w DB (np. `SUBSTRING`) lub w PHP (cięcie + stripping markdown).

3. **Paginated queries:**

   * `LIMIT` + `OFFSET` na bazie.
   * Dodatkowo można rozważyć keyset pagination w przyszłości, jeśli katalog urośnie.

4. **Cache:**

   * Możliwy prosty cache HTTP (publiczny katalog — dane nie są super wrażliwe):

     * `Cache-Control: public, max-age=60` (lub podobnie).
     * Ewentualnie ETag/Last-Modified po `updated_at` maks. z wyników.
   * Dla MVP można pominąć, dodać później.

5. **Trade-off:**

   * Więcej logiki w SQL (pełne generowanie excerpt, sortowanie) ⇒ mniej pracy PHP, większa złożoność SQL.
   * MVP: excerpt generować w PHP, sortować po `created_at DESC`.

---

## 8. Etapy wdrożenia

1. **Definicja kontraktu i DTO**

   * Dodać klasy:

     * `PublicNoteListItemDto`
     * `PaginationMetaDto`
     * `PublicNoteListResponseDto`
     * `PublicNotesQueryDto`
   * Ustalić mapowanie camelCase ↔ snake_case w odpowiedzi JSON (np. normalizer, custom serializer).

2. **Rozszerzenie repozytoriów**

   * `UserRepository`:

     * metoda `findIdByUuid(string $uuid): ?int`.
   * `NoteRepository`:

     * metoda `findPublicNotesForOwner(PublicNotesQueryDto $query): PublicNotesResult` lub osobne metody `countPublicNotesForOwner()` + `loadPublicNotesForOwner()`.
   * Dodać wymagane indeksy DB (jeśli brak):

     * migration dla indeksów na `users.uuid`, `notes.owner_id`, `notes.visibility`, `notes.search_vector_simple`, `notes.labels`.

3. **Implementacja serwisu `PublicNotesCatalogService`**

   * Konstruktor:

     * `UserRepository`
     * `NoteRepository`
   * Metoda `getPublicNotes(PublicNotesQueryDto $query): PublicNoteListResponseDto`:

     * Sprawdzenie użytkownika.
     * Budowa zapytania (filtry, search, label).
     * Liczenie total.
     * Pobranie strony wyników.
     * Mapowanie do DTO.

4. **Implementacja kontrolera**

   * Nowy kontroler, np. `src/Controller/Api/PublicUserNotesController.php`.
   * Route:

     ```php
     #[Route(
         '/api/public/users/{user_uuid}/notes',
         name: 'api_public_user_notes_list',
         methods: ['GET']
     )]
     ```
   * Kroki:

     * Odczyt i walidacja `user_uuid` + `page` + `per_page` + `q` + `label`.
     * Budowa `PublicNotesQueryDto`.
     * Wywołanie `PublicNotesCatalogService`.
     * Zwrócenie `JsonResponse` (200) z mapą DTO → JSON.

5. **Globalna obsługa wyjątków**

   * Upewnić się, że globalny `ExceptionListener`:

     * mapuje `UserNotFoundException` → 404.
     * mapuje `InvalidRequestException` → 400.
     * inne → 500.
   * Dodać logowanie Monologiem w handlerze wyjątków.

6. **Testy**

   * **Testy jednostkowe serwisu:**

     * istniejący user + notatki → poprawne dane, paginacja.
     * istniejący user + brak notatek → `data: []`.
     * nieistniejący user → `UserNotFoundException`.
     * filtr `label` + `q` → poprawne zawężenie.
   * **Testy integracyjne / funkcjonalne kontrolera (Symfony):**

     * `GET /api/public/users/{uuid}/notes` poprawne parametry → 200 + JSON.
     * `GET` z niepoprawnym `user_uuid` → 400.
     * `GET` z `page=0` lub `per_page=-1` → 400.
     * `GET` dla nieistniejącego `user_uuid` → 404.

7. **Konfiguracja bezpieczeństwa**

   * Upewnić się w `security.yaml`, że ścieżka `/api/public/users/*`:

     * albo nie przechodzi przez JWT auth,
     * albo ma `IS_AUTHENTICATED_ANONYMOUSLY`.
   * Dodać ewentualny rate limiter na ten endpoint.

8. **Monitoring i logowanie**

   * Sprawdzić, czy w Monolog są odpowiednie kanały dla API (np. `api`).