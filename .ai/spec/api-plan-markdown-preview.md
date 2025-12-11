# API Endpoint Implementation Plan: POST /api/notes/preview

## 1. Przegląd punktu końcowego

Endpoint służy do serwerowego renderowania podglądu markdown dla opisu notatki.  
Nie zapisuje nic w bazie danych, służy wyłącznie do konwersji markdown → bezpieczny HTML, który może być wyświetlany w UI (HTMX/Twig).  
Opis może być długi (tekst artykułu, typowo 10 000–50 000 znaków).

---

## 2. Szczegóły żądania

- **Metoda HTTP:** `POST`
- **Struktura URL:** `/api/notes/preview`
- **Auth:** wymagane (Symfony Security + użytkownicy z tabeli `users`, JWT przez `lexik/jwt-authentication-bundle` lub klasyczna sesja – zależnie od reszty API)
- **Nagłówki:**
  - `Authorization: Bearer <jwt>` (jeśli API jest JWT)
  - `Content-Type: application/json`
  - `Accept: application/json` (rekomendowane)

### Parametry

- **Wymagane (body JSON):**
  - `description: string`
    - markdown, długość typowo 10 000–50 000 znaków
    - musi być akceptowany w całości (bez odrzucania z powodu „zbyt długie”, dopóki mieści się w sensownych limitach serwera)

- **Opcjonalne:** brak w specyfikacji (dodatkowe pola ignorowane).

### Request Body

```json
{
  "description": "markdown text"
}
````

### Walidacja

* `description`:

  * wymagane (`NotBlank`)
  * typ: string (`Type('string')`)
  * **maksymalna długość:** ustalić na poziomie aplikacji co najmniej `10000` znaków
    (`Assert\Length(max=10000)` – zgodnie z wymaganiem, aby akceptować teksty rzędu 10k–50k)
  * po `trim` nie może być pusty

---

## 3. Wykorzystywane typy

### 3.1. DTO

1. **`NotesMarkdownPreviewRequestDto`**

   * Pola:

     * `public string $description`
   * Adnotacje walidacyjne:

     * `#[Assert\NotBlank]`
     * `#[Assert\Type('string')]`
     * `#[Assert\Length(max: 10000)]`

2. **`NotesMarkdownPreviewResponseDto`**

   * Pola:

     * `public string $html`

### 3.2. Command Model

1. **`GenerateMarkdownPreviewCommand`**

   * Pola:

     * `public string $description`
   * Tworzenie na podstawie `NotesMarkdownPreviewRequestDto`.

---

## 3. Szczegóły odpowiedzi

### Sukces (200)

* **Status:** `200 OK`
* **Body:**

```json
{
  "data": {
    "html": "<p>...</p>"
  }
}
```

* `html`:

  * wynik renderowania markdown
  * HTML po sanitizacji (usunięte skrypty, niebezpieczne atrybuty)

### Błędy

* `400 Bad Request`

  * brak pola `description`
  * `description` pusty po `trim`
  * zły typ (`description` nie jest stringiem)
  * `description` > 10000 znaków
  * nieprawidłowy JSON
* `401 Unauthorized`

  * brak / błędny token / brak sesji
* `415 Unsupported Media Type`

  * `Content-Type` ≠ `application/json`
* `429 Too Many Requests` (jeśli włączony limiter)
* `500 Internal Server Error`

  * nieoczekiwany wyjątek (parser markdown, sanitizer, błąd wewnętrzny)

Przykładowa odpowiedź błędu walidacji:

```json
{
  "error": {
    "code": "validation_failed",
    "message": "Invalid request data.",
    "details": {
      "description": ["This value is too long. It should have 10000 characters or less."]
    }
  }
}
```

---

## 4. Przepływ danych

1. **Klient (HTMX/Twig/JS)**

   * Użytkownik edytuje długi tekst markdown (10k–50k znaków).
   * Po kliknięciu „Podgląd” (lub debounce) wysyłane jest:

     * `POST /api/notes/preview`
     * body JSON z pełnym `description`.

2. **Kontroler Symfony (np. `NotesPreviewController`)**

   * Sprawdza `Content-Type`.
   * Dekoduje JSON (np. `json_decode` z obsługą błędów).
   * Tworzy `NotesMarkdownPreviewRequestDto` (ręcznie lub przez mapper).
   * Waliduje DTO (`ValidatorInterface`).
   * Tworzy `GenerateMarkdownPreviewCommand`.
   * Wywołuje serwis renderujący markdown.

3. **Serwis domenowy – np. `MarkdownPreviewService`**

   * Metoda:
     `public function renderPreview(GenerateMarkdownPreviewCommand $command): NotesMarkdownPreviewResponseDto`
   * Kroki:

     * pobranie `description`
     * render markdown → HTML (np. `league/commonmark` – do ustalenia)

       * parser skonfigurowany pod długie teksty (bez rozszerzeń o dużym koszcie, jeśli niepotrzebne)
     * sanitizacja HTML (np. `HtmlSanitizerInterface`, konfiguracja whitelist)
     * utworzenie `NotesMarkdownPreviewResponseDto` z finalnym `html`.

4. **Odpowiedź**

   * Kontroler opakowuje DTO w JSON `{ "data": { "html": ... } }`.
   * Zwraca `JsonResponse` 200.

5. **Baza danych**

   * Brak interakcji z `users`, `notes`, `note_collaborators` – endpoint nie zapisuje danych.

---

## 5. Względy bezpieczeństwa

1. **Uwierzytelnianie**

   * Wykorzystanie istniejącej konfiguracji Symfony Security:

     * użytkownicy z tabeli `users` (`email`, `password_hash`)
     * JWT (lexik/jwt-authentication-bundle).
   * Endpoint wymaga zalogowanego użytkownika.

2. **Autoryzacja**

   * Brak powiązania z konkretną notatką → wystarczy „is_authenticated”.
   * W razie potrzeby można ograniczyć do określonych ról (np. `ROLE_USER`).

3. **Sanitizacja HTML**

   * Bezwarunkowa sanitizacja wyniku markdown:

     * whitelist tagów i atrybutów
     * usunięcie `script`, `style`, `on*`, `javascript:` itp.
   * Konfiguracja sanitizera współdzielona z resztą aplikacji (podgląd ma dawać dokładnie to, co zostanie zapisane / wyświetlone).

4. **Wielkość inputu**

   * Aplikacja akceptuje opisy 10k–50k znaków:

     * walidacja `Length(max=10000)` w DTO
     * w serwerze (nginx/Apache) sensowny `client_max_body_size` (np. kilka MB) – wystarczający dla takich tekstów.
   * Brak sztucznego ucinania tekstu w serwisie.

5. **Rate limiting**

   * Opcjonalny limiter per użytkownik:

     * np. X żądań na 30 sekund
     * ochrona przed zalaniem serwera długimi tekstami.

6. **Logowanie**

   * W logach NIE przechowujemy pełnego `description` (dane mogą być wrażliwe).
   * Logujemy:

     * user id
     * długość tekstu
     * skrócony fragment (np. pierwsze 100 znaków, jeśli potrzebne) albo tylko info „preview requested”.

---

## 6. Obsługa błędów

### Walidacja

* Błędy walidacji DTO:

  * `description` brak / pusty / za długi / zły typ → 400
* Format błędu spójny z globalnym wyjątko-handlerem (jeśli istnieje).

### Format/Content-Type

* Nieprawidłowy JSON:

  * złapany w kontrolerze → 400, `code: "invalid_json"`.
* `Content-Type` różny od `application/json`:

  * 415 z krótkim komunikatem.

### Auth

* Brak tokena / niepoprawna sesja:

  * 401, `code: "unauthorized"`.

### Błędy wewnętrzne

* Wyjątki parsera markdown lub sanitizera:

  * logowanie przez `Monolog`.
  * odpowiedź 500 z ogólnym komunikatem.

### Rejestracja w tabeli błędów

* Brak zdefiniowanej tabeli błędów → na razie:

  * tylko logi (Monolog, profiler w dev).

---

## 7. Rozważania dotyczące wydajności

1. **Długie teksty (10k–50k znaków)**

   * Parser markdown musi być wydajny dla artykułów:

     * re-użycie instancji parsera (serwis singleton, nie new per request).
     * ograniczenie niepotrzebnych rozszerzeń (np. rozbudowane tabelki, jeżeli nieużywane).
   * Sanitizer również wstrzykiwany jako serwis.

2. **Brak IO do DB**

   * Endpoint jest CPU + pamięć (parsowanie, sanitizacja), bez DB.
   * Potencjalne wąskie gardła:

     * liczba jednoczesnych żądań podglądu
     * długość tekstu:

3. **Rate limiting**

   * Ograniczenie częstotliwości użycia endpointu:

     * zapobiega „ciągłemu” odświeżaniu podglądu na każdą literę.

4. **Możliwe cache (opcjonalnie, później)**

   * Cache w pamięci (np. hash `description` ⇒ HTML) wewnątrz serwisu:

     * przy długich tekstach często prze-renderowywanych bez zmian.
   * Na poziomie MVP – bez cache, tylko czysty render.

---

## 8. Etapy wdrożenia

1. **Ustalenia techniczne**

   * Zatwierdzenie:

     * maksymalnej długości `description` (propozycja: 10000 znaków).
     * listy dozwolonych tagów/atrybutów w sanitizerze.
     * formatu błędów globalnie w API (jeśli nie jest jeszcze ustalony).

2. **Zależności**

   * Dodanie / konfiguracja:

     * parsera markdown (np. `league/commonmark`).
     * `HtmlSanitizer` (Symfony) z profilem „markdown_display”.
   * Rejestracja serwisów w kontenerze DI (autowiring).

3. **DTO + Command**

   * Utworzenie:

     * `NotesMarkdownPreviewRequestDto` (z adnotacjami walidacyjnymi).
     * `NotesMarkdownPreviewResponseDto`.
     * `GenerateMarkdownPreviewCommand`.

4. **Serwis domenowy**

   * Implementacja `MarkdownPreviewService`:

     * wstrzyknięcie parsera markdown, sanitizera, loggera.
     * metoda `renderPreview()`:

       * render markdown → HTML
       * sanitizacja
       * obsługa wyjątków (log, rzucenie własnego `MarkdownPreviewException` lub zwrócenie błędu).

5. **Kontroler**

   * Utworzenie `NotesPreviewController` (lub dodanie akcji do istniejącego kontrolera API notatek):

     * `#[Route('/api/notes/preview', name: 'api_notes_preview', methods: ['POST'])]`
     * dekodowanie JSON, DTO, walidacja, command, call serwisu, mapowanie DTO na JSON.

6. **Security + rate limiting**

   * Konfiguracja security:

     * endpoint za firewallem wymagającym zalogowanego użytkownika.

7. **Globalna obsługa błędów**

   * Upewnienie się, że:

     * wyjątki walidacyjne → 400,
     * auth → 401,
     * reszta → 500.
   * Format odpowiedzi zunifikowany.

8. **Testy**
   * Jednostkowe:
     * `MarkdownPreviewService`:
       * poprawne renderowanie krótkiego i długiego (np. ~10000 znaków) tekstu.
       * usuwanie niebezpiecznego HTML.
   * Funkcjonalne:
     * ścieżki 200, 400 (brak/za długi/pusty), 401, 415.