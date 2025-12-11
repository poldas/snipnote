# API Endpoint Implementation Plan: Note Collaborators (`/api/notes/{note_id}/collaborators`)

## 1. Przegląd punktu końcowego

Grupa endpointów zarządza współpracownikami notatki:
- dodawanie współpracownika po emailu,
- usuwanie współpracownika po `id` lub `email`,
- listowanie współpracowników danej notatki.

Dostęp tylko dla właściciela notatki oraz współpracowników (z ograniczeniami przy usuwaniu).

## 2. Szczegóły żądania

### 2.1. Add collaborator (POST `/api/notes/{note_id}/collaborators`)

- Metoda HTTP: `POST`
- URL: `/api/notes/{note_id}/collaborators`
- Parametry:
  - Wymagane (path):
    - `note_id` (int, ID notatki)
  - Wymagane (body JSON):
    - `email` (string)
  - Opcjonalne: brak
- Nagłówki:
  - `Authorization: Bearer <jwt>`
  - `Content-Type: application/json`
- Request Body:
  ```json
  { "email": "collab@example.com" }
````

Walidacja wejścia (poziom HTTP/DTO):

* `note_id` > 0 (int).
* `email` niepusty, max np. 255, poprawny format RFC (Symfony Validator `Email`).
* Trimowanie spacji z `email`.

### 2.2. Remove collaborator (DELETE)

Dwa warianty:

**a) Po `collab_id`**

* Metoda: `DELETE`
* URL: `/api/notes/{note_id}/collaborators/{collab_id}`
* Parametry:

  * Path:

    * `note_id` (int)
    * `collab_id` (int, ID w `note_collaborators.id`)
* Nagłówki:

  * `Authorization: Bearer <jwt>`

**b) Po `email`**

* Metoda: `DELETE`
* URL: `/api/notes/{note_id}/collaborators?email=collab@example.com`
* Parametry:

  * Path:

    * `note_id` (int)
  * Query:

    * `email` (string, wymagany w tym wariancie)
* Nagłówki:

  * `Authorization: Bearer <jwt>`

Walidacja:

* `note_id`, `collab_id` > 0, jeżeli dotyczy.
* `email` jak wyżej (format, długość).
* Dokładnie jeden sposób identyfikacji (albo `collab_id` albo `email`).

### 2.3. List collaborators (GET `/api/notes/{note_id}/collaborators`)

* Metoda: `GET`
* URL: `/api/notes/{note_id}/collaborators`
* Parametry:

  * Path:

    * `note_id` (int)
* Nagłówki:

  * `Authorization: Bearer <jwt>`
* Brak body.

Walidacja:

* `note_id` > 0.

## 3. Wykorzystywane typy (DTO / Command / Query)

### 3.1. DTO

1. **`NoteCollaboratorDto`** (do odpowiedzi API)

   * `int $id`
   * `int $noteId`
   * `string $email`
   * `int|null $userId`
   * `\DateTimeImmutable $createdAt`

2. **`CollaboratorCollectionDto`**

   * `int $noteId`
   * `NoteCollaboratorDto[] $collaborators`

### 3.2. Command / Query Models

1. **`AddCollaboratorCommand`**

   * `int $noteId`
   * `string $email`
   * `int $currentUserId` (z JWT)
   * Semantyka:

     * Sprawdza uprawnienia (owner/collaborator).
     * Tworzy wpis w `note_collaborators`.
     * Jeżeli istnieje `users.email` (case-insensitive) → ustawia `user_id`.

2. **`RemoveCollaboratorByIdCommand`**

   * `int $noteId`
   * `int $collaboratorId`
   * `int $currentUserId`

3. **`RemoveCollaboratorByEmailCommand`**

   * `int $noteId`
   * `string $email`
   * `int $currentUserId`

4. **`ListCollaboratorsQuery`**

   * `int $noteId`
   * `int $currentUserId`

### 3.3. Serwisy / klasy domenowe

1. **`NoteCollaboratorService`**

   * `addCollaborator(AddCollaboratorCommand $cmd): NoteCollaboratorDto`
   * `removeById(RemoveCollaboratorByIdCommand $cmd): void`
   * `removeByEmail(RemoveCollaboratorByEmailCommand $cmd): void`
   * `listForNote(ListCollaboratorsQuery $query): CollaboratorCollectionDto`

2. **Repositoria:**

   * `NoteRepository` (już istniejące, rozszerzyć o helpery do pobierania + sprawdzania ownera).
   * `NoteCollaboratorRepository`:

     * `findByNoteAndId(int $noteId, int $collabId): ?NoteCollaborator`
     * `findByNoteAndEmail(int $noteId, string $email): ?NoteCollaborator`
     * `findAllByNote(int $noteId): NoteCollaborator[]`
     * `existsForNoteAndEmail(int $noteId, string $email): bool`

3. **Entity (Doctrine):**

   * `NoteCollaborator` odwzorowujący `note_collaborators`.

4. **Security:**

   * `NoteAccessVoter` lub metoda w serwisie do sprawdzania:

     * czy user jest ownerem (`notes.owner_id`),
     * czy user jest współpracownikiem (`note_collaborators.user_id`),
     * specjalna reguła dla self-removal.

## 4. Szczegóły odpowiedzi

### 4.1. Add collaborator (POST)

* Status: `201 Created`
* Body:

  ```json
  {
    "data": {
      "id": 45,
      "note_id": 10,
      "email": "collab@example.com",
      "user_id": 999,
      "created_at": "2025-12-07T17:00:00+00:00"
    }
  }
  ```

Kody błędów:

* `400` – nieprawidłowy email (format, długość), brak pola `email`.
* `401` – brak / nieprawidłowy JWT.
* `403` – użytkownik nie jest właścicielem ani współpracownikiem notatki.
* `404` – notatka nie istnieje lub nie jest dostępna.
* `409` – duplikat współpracownika dla `(note_id, lower(email))`.
* `500` – niespodziewany błąd serwera/DB.

### 4.2. Remove collaborator (DELETE)

* Status: `204 No Content` – poprawne usunięcie.
* Brak body.

Kody błędów:

* `400` – brak zarówno `email`, jak i `collab_id`, nieprawidłowy format id/email.
* `401` – brak / nieprawidłowy JWT.
* `403`:

  * użytkownik nie jest właścicielem ani współpracownikiem danej notatki,
  * współpracownik próbuje usunąć innego współpracownika niż siebie,
  * próba usunięcia właściciela (zabronione).
* `404` – notatka lub współpracownik nie istnieje / nie powiązany z notatką.
* `500` – błędy serwera/DB.

### 4.3. List collaborators (GET)

* Status: `200 OK`
* Body:

  ```json
  {
    "data": [
      {
        "id": 45,
        "note_id": 10,
        "email": "collab@example.com",
        "user_id": 999,
        "created_at": "..."
      },
      {
        "id": 46,
        "note_id": 10,
        "email": "another@example.com",
        "user_id": null,
        "created_at": "..."
      }
    ]
  }
  ```

Kody błędów:

* `401` – brak / nieprawidłowy JWT.
* `403` – brak dostępu do notatki.
* `404` – notatka nie istnieje.
* `500` – błąd serwera.

## 5. Przepływ danych

### 5.1. Add collaborator (POST)

1. Kontroler:

   * Pobiera `note_id` z path i `email` z JSON.
   * Tworzy `AddCollaboratorCommand` z `noteId`, `email`, `currentUserId`.
2. Serwis `NoteCollaboratorService`:

   * Sprawdza istnienie notatki (`NoteRepository`).
   * Sprawdza uprawnienia:

     * owner: `note.ownerId === currentUserId`,
     * lub współpracownik (po `note_collaborators.user_id`).
   * Normalizuje email dla unikalności: `lower(trim(email))`.
   * Sprawdza duplikat:

     * `existsForNoteAndEmail(noteId, normalizedEmail)` – jeśli true → `409`.
   * Szuka usera po emailu w `users` (case-insensitive):

     * `UserRepository->findByEmailCaseInsensitive(email)`:

       * jeśli znaleziony → `user_id = user.id`,
       * jeśli nie → `user_id = null`.
   * Tworzy encję `NoteCollaborator`, ustawia pola i zapisuje poprzez `NoteCollaboratorRepository`.
   * Mapuje encję do `NoteCollaboratorDto`.
3. Kontroler:

   * Opakowuje DTO w strukturę `{ "data": ... }` i zwraca `201`.

### 5.2. Remove collaborator (DELETE)

1. Kontroler:

   * Odczytuje `note_id` + (opcjonalnie) `collab_id` lub `email`.
   * Waliduje, że dokładnie jedna metoda identyfikacji jest użyta.
   * Tworzy odpowiedni Command (`RemoveCollaboratorByIdCommand` lub `RemoveCollaboratorByEmailCommand`).
2. Serwis:

   * Sprawdza istnienie notatki.
   * Sprawdza uprawnienia:

     * owner może usunąć dowolnego współpracownika (ale **nie** siebie jako ownera),
     * współpracownik może usunąć tylko swój własny wpis (self-removal).
   * Pobiera wpis `NoteCollaborator` po `id` lub `email`.
   * Jeżeli nie istnieje → `404`.
   * Weryfikuje reguły:

     * jeżeli `collaborator.user_id === note.owner_id` → 403 (owner nie może być współpracownikiem / nie usuwamy ownera).
     * jeżeli current user nie jest ownerem i nie jest `collaborator.user_id` → `403`.
   * Usuwa wpis (soft delete nie jest wymagany – `DELETE`).
3. Kontroler:

   * Zwraca `204`.

### 5.3. List collaborators (GET)

1. Kontroler:

   * Pobiera `note_id`.
   * Tworzy `ListCollaboratorsQuery`.
2. Serwis:

   * Sprawdza istnienie notatki.
   * Sprawdza uprawnienia (owner lub współpracownik).
   * Pobiera wszystkich `NoteCollaborator` dla `note_id` i mapuje do `NoteCollaboratorDto[]`.
   * Tworzy `CollaboratorCollectionDto`.
3. Kontroler:

   * Zwraca `200` z `{ "data": [...] }`.

## 6. Względy bezpieczeństwa

1. **Uwierzytelnianie**

   * JWT weryfikowany przez `lexik/jwt-authentication-bundle`.
   * Endpointy oznaczone jako wymagające autoryzacji w `security.yaml`.
   * Brak dostępu dla anonimowych: brak JWT → `401`.

2. **Autoryzacja**

   * Sprawdzenie uprawnień w serwisie lub `Voter`:

     * `NOTE_VIEW`/`NOTE_COLLABORATE` – owner lub współpracownik.
     * Przy usuwaniu:

       * owner może usuwać współpracowników (nie samego siebie jako ownera),
       * współpracownik może usunąć tylko swoje powiązanie (self-removal).
   * IDOR: zawsze wiązać `collab_id` i `email` z `note_id` przy zapytaniach (select with `note_id = :noteId`).

3. **Ochrona danych**

   * E-maile są danymi wrażliwymi:

     * zwracamy tylko email i `user_id` – bez dodatkowych szczegółów.
     * brak endpointu do wyszukiwania użytkowników po emailu; jedynie dodanie konkretnego adresu.
   * Minimalizacja informacji w errorach (bez dokładnych szczegółów o istnieniu użytkowników).

4. **Walidacja / sanityzacja**

   * Walidacja typów i długości (`note_id`, `collab_id` jako int, `email` ograniczony długością).
   * `email` – walidator `Email`, normalizacja spacji.

5. **Potencjalne zagrożenia**
   * Enumeration:
     * Przy błędach nie odróżniać „note nie istnieje” od „brak dostępu” w zbyt szczegółowy sposób (wystarczy `404`/`403`).
   * CSRF – API oparte o JWT, używane z JS → standardowe CORS, brak CSRF.

## 7. Obsługa błędów

### 7.1. Mapowanie wyjątków na HTTP

* Walidacja inputu:

  * `ValidationException` → `400`.
* Brak JWT / zły JWT:

  * Obsługiwane przez middleware → `401`.
* Brak uprawnień:

  * `AccessDeniedException` / custom domain exception → `403`.
* Nie znaleziono zasobu:

  * `EntityNotFoundException` (note / collaborator) → `404`.
* Duplikat:

  * Naruszenie constraintu `UNIQUE (note_id, lower(email))` lub explicit check → `409`.
* Pozostałe:

  * Nieobsłużone wyjątki → `500`.

### 7.2. Logowanie błędów

Tu brakuje jednoznacznej specyfikacji tabeli błędów → **nie wiem**, czy istnieje. Dwa możliwe warianty:

* **Wariant A (jeśli istnieje tabela `error_logs` lub podobna):**

  * Serwis centralny `ErrorLoggingService` zapisujący:

    * `timestamp`, `user_id`, `endpoint`, `http_method`, `status_code`, `error_code`, `stacktrace` (tylko dla 5xx).
  * Integracja w `KernelExceptionListener` – każdy nieoczekiwany wyjątek 5xx logowany do DB.

* **Wariant B (minimalny, bez dedykowanej tabeli):**

  * Logowanie przez `Monolog` do pliku + ewentualnie do kanału `doctrine` (błędy SQL).
  * Dodanie correlation id w nagłówku odpowiedzi, jeśli jest w projekcie.

## 8. Rozważania dotyczące wydajności

1. **Indeksy**

   * Dodać:

     * `CREATE UNIQUE INDEX ux_note_collaborators_note_email ON note_collaborators (note_id, lower(email));`
     * `CREATE INDEX idx_note_collaborators_note_id ON note_collaborators (note_id);`
   * Umożliwia szybkie:

     * sprawdzanie duplikatu,
     * listowanie współpracowników notatki,
     * wyszukiwanie po emailu w danej notatce.

2. **Zapytania**

   * Przy listowaniu – jeden select po `note_id`.
   * Przy dodawaniu – ograniczenie liczby zapytań:

     * 1 zapytanie do `notes` (lub lazy-association już w pamięci),
     * 1 zapytanie do `note_collaborators` (duplikat),
     * 1 zapytanie do `users` po email (case-insensitive).

3. **Batching / N+1**

   * Brak N+1 – pobieramy wszystko na raz per note.
   * Eager/lazy load adekwatnie (bez ładowania powiązanych obiektów nieużywanych w odpowiedzi).

4. **Skalowalność**

   * Endpointy są per-notatka, więc wolumen per user raczej mały.
   * W razie potrzeby – cache listy współpracowników na krótką TTL (np. przy bardzo częstych odczytach).

## 9. Etapy wdrożenia

1. **Model danych**

   * Zweryfikować istniejące DDL `note_collaborators`.
   * Dodać indeksy:

     * unique `(note_id, lower(email))`,
     * indeks na `note_id`.
   * Przygotować Doctrine migration (SQL + rollback).

2. **Entity i mapping**

   * Utworzyć encję `NoteCollaborator` z mappingiem atrybutowym do `note_collaborators`.
   * Zaktualizować `Note` (relacja `OneToMany` do `NoteCollaborator` – opcjonalne, jeśli potrzebne).

3. **Repositoria**

   * Dodać `NoteCollaboratorRepository` z metodami:

     * `findByNoteAndId`, `findByNoteAndEmail`, `findAllByNote`, `existsForNoteAndEmail`.
   * Upewnić się, że `UserRepository` ma metodę wyszukiwania po email (case-insensitive).

4. **DTO / Command / Query**

   * Utworzyć:

     * `NoteCollaboratorDto`, `CollaboratorCollectionDto`.
     * `AddCollaboratorCommand`, `RemoveCollaboratorByIdCommand`, `RemoveCollaboratorByEmailCommand`, `ListCollaboratorsQuery`.

5. **Serwis domenowy**

   * Zaimplementować `NoteCollaboratorService`:

     * logika walidacji, autoryzacji, obsługa duplikatów, mapowanie do DTO.
   * Dodać testy jednostkowe:

     * dodanie współpracownika (owner / collaborator),
     * duplikat → `409`,
     * brak uprawnień → `403`,
     * self-removal vs removal innych.

6. **Warstwa bezpieczeństwa**

   * Upewnić się, że JWT jest wymagany dla `/api/notes/*`.
   * Dodać/rozszerzyć `NoteAccessVoter` lub metodę w serwisie:

     * sprawdzanie owner/collaborator.
   * Testy integracyjne autoryzacji (401/403).

7. **Kontroler API**

   * Dodać kontroler `NoteCollaboratorController` z akcjami:

     * `POST /api/notes/{note_id}/collaborators`,
     * `DELETE /api/notes/{note_id}/collaborators/{collab_id}`,
     * `DELETE /api/notes/{note_id}/collaborators?email=...`,
     * `GET /api/notes/{note_id}/collaborators`.
   * Wstrzyknąć `NoteCollaboratorService`.
   * Mapować wyniki serwisu na JSON zgodnie ze specyfikacją.

8. **Obsługa błędów / logging**

   * Skonfigurować mapowanie wyjątków → kody statusu.
   * Jeśli projekt ma tabelę błędów – podpiąć logging w `KernelExceptionListener`.
   * Dodać testy request/response (Symfony WebTestCase) sprawdzające kody HTTP i payload.

9. **Weryfikacja i refaktoryzacja**

   * Uruchomić:

     * `./test.sh`,
     * `docker compose exec php php vendor/bin/phpstan`,
     * `docker compose exec php php vendor/bin/php-cs-fixer fix --dry-run`.
   * Poprawić ewentualne naruszenia reguł, uprościć logikę w kontrolerach (thin controllers).