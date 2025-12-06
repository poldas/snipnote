\<analysis\>
**1. Podsumowanie specyfikacji API:**
Celem jest wdrożenie modułu `Notes` zawierającego 5 endpointów: tworzenie, odczyt (właściciel), odczyt publiczny (token), aktualizacja i usuwanie. Kluczowe aspekty to obsługa `url_token` (UUIDv4) z wykrywaniem kolizji, obsługa widoczności (public/private/draft) oraz powiązanie z użytkownikami (właściciel/współpracownik).

**2. Parametry (Wymagane vs Opcjonalne):**

  * **POST (Create):**
      * Wymagane: `title`, `description`.
      * Opcjonalne: `labels` (default `[]`), `visibility` (default `private`).
  * **PATCH (Update):**
      * Wszystkie pola opcjonalne (subset): `title`, `description`, `labels`, `visibility`.
  * **GET (Public):**
      * Wymagane: `url_token` w ścieżce.

**3. Niezbędne typy (DTO/Command):**

  * `CreateNoteDto`: Walidacja danych wejściowych przy tworzeniu.
  * `UpdateNoteDto`: Walidacja danych przy edycji (wszystkie pola nullable/optional).
  * `NoteResponseDto`: Ujednolicona struktura odpowiedzi (serialization group).

**4. Logika w Service (`NoteService`):**

  * Separacja logiki biznesowej od kontrolera.
  * **Generowanie UUID:** Metoda z mechanizmem "retry" w przypadku kolizji (transakcyjność).
  * **Sanityzacja:** Markdown powinien być sanitizowany przy odczycie lub zapisie (zależnie od strategii, tu specyfikacja mówi "rendered on client", ale API zwraca "markdown content", więc sanitizować trzeba HTML jeśli klient renderuje, lub ufać klientowi. Bezpieczniej sanitizować przy zapisie tagi script).
  * **Uprawnienia:** Logika sprawdzania `url_token` dla publicznego dostępu.

**5. Walidacja:**

  * Symfony Constraints: `NotBlank`, `Length` (max 255 title), `Choice` (visibility).
  * Walidacja logiczna: Sprawdzenie czy użytkownik jest właścicielem/współpracownikiem (Voter).

**6. Obsługa błędów:**

  * Standardowe wyjątki HTTP (Symfony).
  * Obsługa `UniqueConstraintViolationException` dla kolizji UUID (rzutowanie na 409 Conflict).

**7. Bezpieczeństwo:**

  * Auth: JWT (Lexik) / Supabase (zgodnie z `implementation_rules`, ale schema DB wskazuje na lokalne hasła - przyjmę podejście hybrydowe/agnostyczne: UserProvider ładuje usera).
  * IDOR: Zabezpieczenie endpointów `{id}` Voterem.
  * XSS: Ostrzeżenie o renderowaniu Markdown po stronie klienta.

**8. Scenariusze błędów:**

  * 409: Kolizja UUID (jeśli retry zawiedzie).
  * 403: Próba dostępu do prywatnej notatki przez publiczny endpoint.
  * 404: Nieznaleziony zasób.

\</analysis\>

# API Endpoint Implementation Plan: Notes API

## 1\. Przegląd punktu końcowego

Plan dotyczy wdrożenia pełnego CRUD dla zasobu `Notes` oraz specjalnego endpointu publicznego dostępu. Moduł umożliwia użytkownikom zarządzanie notatkami w formacie Markdown, z obsługą widoczności (publiczna/prywatna) oraz unikalnych tokenów URL (`url_token`). Kluczowym elementem jest zapewnienie spójności danych przy generowaniu UUID oraz bezpieczna autoryzacja dostępu.

## 2\. Szczegóły żądania

### 2.1 Create Note

  * **Metoda:** `POST`
  * **URL:** `/api/notes`
  * **Auth:** Wymagane (JWT)
  * **Body (JSON):**
    ```json
    {
      "title": "string (wymagane, max 255)",
      "description": "string (wymagane, markdown)",
      "labels": ["string[] (opcjonalne)"],
      "visibility": "enum(public|private|draft) (opcjonalne, default: private)"
    }
    ```

### 2.2 Read Note (Owner/Collaborator)

  * **Metoda:** `GET`
  * **URL:** `/api/notes/{id}`
  * **Auth:** Wymagane
  * **Parametry:** `id` (int, wymagane)

### 2.3 Public Read Note

  * **Metoda:** `GET`
  * **URL:** `/api/public/notes/{url_token}`
  * **Auth:** Brak (Public)
  * **Parametry:** `url_token` (UUIDv4, wymagane)

### 2.4 Update Note

  * **Metoda:** `PATCH`
  * **URL:** `/api/notes/{id}`
  * **Auth:** Wymagane
  * **Body (JSON):** Podzbiór pól z Create Note.

### 2.5 Delete Note

  * **Metoda:** `DELETE`
  * **URL:** `/api/notes/{id}`
  * **Auth:** Wymagane (Tylko właściciel)

## 3\. Wykorzystywane typy (DTO)

Należy utworzyć następujące klasy DTO w katalogu `src/DTO/Note`:

1.  **`CreateNoteDto`**

      * `title`: string, `#[Assert\NotBlank]`, `#[Assert\Length(max: 255)]`
      * `description`: string, `#[Assert\NotBlank]`
      * `labels`: array, `#[Assert\All([new Assert\Type('string')])]`
      * `visibility`: string, `#[Assert\Choice(callback: [NoteVisibility::class, 'values'])]`

2.  **`UpdateNoteDto`**

      * Wszystkie pola jak wyżej, ale nullable (dla metody PATCH).

3.  **`NoteResponseDto`** (lub użycie grup serializacji na encji)

      * Mapuje encję `Note` do JSON.
      * Pola: `id`, `owner_id`, `url_token`, `title`, `description`, `labels`, `visibility`, `created_at`, `updated_at`.

## 3\. Szczegóły odpowiedzi

  * **200 OK:** Zwraca obiekt notatki (lub zaktualizowany obiekt).
  * **201 Created:** Zwraca nowo utworzoną notatkę.
  * **204 No Content:** Pomyślne usunięcie.
  * **400 Bad Request:** Błąd walidacji danych wejściowych.
  * **401 Unauthorized:** Brak lub nieprawidłowy token JWT.
  * **403 Forbidden:** Brak uprawnień do zasobu (np. próba edycji cudzej notatki) lub notatka nie jest publiczna.
  * **404 Not Found:** Zasób nie istnieje.
  * **409 Conflict:** Kolizja `url_token` (bardzo rzadkie, po wyczerpaniu prób retry).

## 4\. Przepływ danych

1.  **Request:** Kontroler odbiera żądanie i mapuje JSON na DTO (`MapRequestPayload`).
2.  **Validation:** Symfony Validator sprawdza poprawność DTO.
3.  **Security (Voter):**
      * Dla `Create`: Sprawdza czy użytkownik jest zalogowany.
      * Dla `Read/Update/Delete`: `NoteVoter` sprawdza relację `owner_id` lub wpis w `note_collaborators`.
4.  **Service Layer (`NoteService`):**
      * Przetwarza dane.
      * **Generacja UUID:** W pętli (max 3 próby) generuje UUIDv4 i próbuje zapisać w DB. Jeśli `UniqueConstraintViolationException` -\> ponów.
5.  **Database:** Doctrine zapisuje encję w tabeli `notes`.
6.  **Response:** Kontroler zwraca DTO odpowiedzi zserializowane do JSON.

## 5\. Względy bezpieczeństwa

  * **Autoryzacja (Voters):** Implementacja `NoteVoter` obsługująca atrybuty: `NOTE_VIEW`, `NOTE_EDIT`, `NOTE_DELETE`.
      * `NOTE_VIEW`: Właściciel LUB Collaborator LUB (jeśli public endpoint i visibility=public).
      * `NOTE_DELETE`: Tylko właściciel.
  * **Sanityzacja danych:** Choć markdown renderowany jest na kliencie, zaleca się podstawowe filtrowanie tagów HTML w `description` przed zapisem, aby zapobiec Stored XSS jeśli klient zawiedzie w sanityzacji.
  * **Enumeracja ID:** Endpointy używają sekwencyjnego ID (`BIGSERIAL`). Voter musi bezwzględnie blokować dostęp do ID, które nie należą do użytkownika. Publiczny endpoint używa tylko losowego UUID (`url_token`).
  * **Rate Limiting:** Zalecane nałożenie limitu na endpointy tworzenia notatek (np. 60/min) per IP/User.

## 6\. Obsługa błędów

  * **Validacja:** Automatyczna odpowiedź Symfony (400) z listą błędnych pól.
  * **Kolizja UUID:**
      * Mechanizm: `try-catch` w serwisie wokół `flush()`.
      * Akcja: Ponowienie generacji tokenu.
      * Ostateczność: Rzucenie wyjątku niestandardowego konwertowanego na `409 Conflict`.
  * **Brak zasobu:** `EntityNotFoundException` konwertowane na `404`.

## 7\. Rozważania dotyczące wydajności

  * **Indeksy:** Tabela `notes` posiada indeksy na `owner_id` (FK) oraz `url_token` (UNIQUE). Należy upewnić się, że zapytania po `url_token` używają indeksu.
  * **N+1 Query:** Przy pobieraniu listy notatek (jeśli będzie w przyszłości), należy uważać na dociąganie `labels` lub `collaborators`. Tutaj (single fetch) nie jest to krytyczne.
  * **Payload Size:** Walidacja długości `description` jest kluczowa, aby nie zapychać bazy ogromnymi blobami tekstu.
  * **Transakcje:** Operacja `Create` musi być atomowa.

## 8\. Etapy wdrożenia

### Krok 1: Przygotowanie warstwy danych i Encji

1.  Utwórz/Zaktualizuj encję `Note` zgodnie ze schematem SQL (dodaj brakujące pola, enum `NoteVisibility`).
2.  Wygeneruj migrację Doctrine.
3.  Zaktualizuj encję `User` (relacja OneToMany do `Note`).

### Krok 2: Warstwa DTO i Serwisów

1.  Stwórz `CreateNoteDto` i `UpdateNoteDto` z atrybutami walidacji.
2.  Zaimplementuj `NoteService`:
      * Metoda `createNote` z logiką retry dla UUID.
      * Metoda `updateNote`.
      * Metoda `getPublicNote` (szukanie po tokenie + check visibility).

### Krok 3: Security (Voters)

1.  Stwórz `NoteVoter`.
2.  Zaimplementuj logikę sprawdzającą `owner_id` vs `user->getId()`.
3.  (Opcjonalnie) Przygotuj logikę pod `note_collaborators` (nawet jeśli tabela pusta na start).

### Krok 4: Kontrolery API (Private)

1.  Stwórz `NoteController` (`/api/notes`).
2.  Zaimplementuj metody `create`, `read`, `update`, `delete`.
3.  Podepnij atrybuty `#[IsGranted]` oraz mapowanie payloadu `#[MapRequestPayload]`.

### Krok 5: Kontroler API (Public)

1.  Stwórz `PublicNoteController` (`/api/public/notes`).
2.  Zaimplementuj metodę `readByToken`.
3.  Dodaj logikę zwracającą 404 lub 403 jeśli notatka istnieje ale jest `private`.

### Krok 6: Testy

1.  Napisz testy integracyjne (`KernelTestCase` / `WebTestCase`):
      * Tworzenie notatki (sukces).
      * Dostęp do cudzej notatki (403).
      * Dostęp publiczny (sukces dla public, 403 dla private).
      * Walidacja pól (400).