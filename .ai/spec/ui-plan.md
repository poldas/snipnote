# Architektura UI dla Notes Sharing MVP

## 1. Przegląd struktury UI

**Cel:** Prosty, czytelny interfejs oparty na Twig + HTMX + Tailwind: pełnostronicowe widoki, HTMX dla fragmentów/preview, sesje cookie + CSRF dla UI. Brak autosave — zapis tylko na kliknięcie „Zapisz”. Responsywność: topbar + chowany sidebar (mobile-first).

**Główne role UI względem API:** UI wywołuje następujące główne endpointy API (skrótowo, cel):

* `POST /api/auth/register` — rejestracja i automatyczne logowanie.
* `POST /api/auth/login` — logowanie (session cookie dla UI).
* `POST /api/auth/logout` — wylogowanie.
* `POST /api/auth/forgot-password` — wysłanie maila z linkiem resetu.
* `POST /api/auth/reset-password` — ustawienie nowego hasła na podstawie tokenu.
* `GET /api/notes` — lista notatek właściciela (dashboard, z paginacją/q/label).
* `POST /api/notes` — tworzenie notatki.
* `GET /api/notes/{id}` — odczyt (właściciel/współedytor).
* `PATCH /api/notes/{id}` — zapis edycji (tylko po kliknięciu „Zapisz”).
* `DELETE /api/notes/{id}` — usunięcie (owner only).
* `GET /api/public/notes/{url_token}` — publiczny odczyt po tokenie.
* `GET /api/public/users/{user_uuid}/notes` — publiczny katalog użytkownika.
* `POST /api/notes/preview` — server-side markdown preview (wywołanie HTMX).
* `/api/notes/{note_id}/collaborators` (GET/POST/DELETE) — zarządzanie współedytorami.

**Zasady UI → API:** formularze walidowane kliencko minimalnie + serwer (API) surowo waliduje; mapowanie błędów HTTP: 400 → inline, 403 → modal/alert, 409 → globalny alert, 500 → toast.

## 2. Lista widoków

### Widok: Landing / Login / Register

  **Ścieżki:** `/` (landing), `/login`, `/register`
  **Cel:** Szybkie zalogowanie/rejestracja i przekierowanie na dashboard.
  **Kluczowe informacje:** formularz email/hasło, walidacja błędów, CTA „Zarejestruj / Zaloguj”, krótka informacja o aplikacji Snipnote, link „Nie pamiętasz hasła?” prowadzący do resetu.
  **Kluczowe komponenty:** formularz z labelami, inline errors, przycisk submit, link do rejestracji/logowania/resetu hasła.
  **UX / dostępność / bezpieczeństwo:** focus-first input, aria-labely, komunikaty błędów zrozumiałe, CSRF token dla form, ograniczenie prób logowania (serwer).

### Widok: Zapomniane hasło (wysłanie linku)

  **Ścieżka:** `/forgot-password`
  **Cel:** Umożliwienie użytkownikowi zamówienia linku resetu hasła.
  **Kluczowe informacje:** pojedyncze pole email, komunikat o wysłaniu instrukcji niezależnie od istnienia konta.
  **Kluczowe komponenty:** formularz email, inline validation, komunikat sukcesu neutralny, link powrotu do logowania.
  **UX / dostępność / bezpieczeństwo:** brak ujawniania istnienia konta, CSRF dla formularza, rate limit po stronie API sygnalizowany w UI (toast/inline), focus na polu email.

### Widok: Reset hasła (ustawienie nowego)

  **Ścieżka:** `/reset-password?token=...`
  **Cel:** Ustawienie nowego hasła na podstawie tokenu z emaila.
  **Kluczowe informacje:** pola: nowe hasło (+ opcjonalne potwierdzenie), ukryty token lub query param, komunikaty o nieważnym/wyczerpanym tokenie.
  **Kluczowe komponenty:** formularz z hasłem, przycisk „Zapisz”, komunikat sukcesu, link do logowania po sukcesie.
  **UX / dostępność / bezpieczeństwo:** walidacja długości hasła, maskowanie wejścia, CSRF, jasny komunikat gdy token nieważny/zużyty, focus management na błędach.

### Widok: Dashboard — lista notatek (z wyszukiwarką)

  **Ścieżka:** `/notes`
  **Cel:** Główny hub — przegląd własnych notatek, szybkie wejścia do edycji, tworzenie nowych.
  **Kluczowe informacje:** lista notatek (title, excerpt ≤255, labels, created_at), wyszukiwarka (q, obsługa `label:`), paginacja (10/strona), przycisk „Dodaj notatkę”.
  **Kluczowe komponenty:** topbar (search), sidebar (opcjonalne filtry), note-row (ikonki akcji: edytuj, usuń), paginacja.
  **UX / dostępność / bezpieczeństwo:** klikalne wiersze z role/button roles, czytelne CTA, potwierdzenie przy usuwaniu modal, mapowanie błędów API inline / toasty.

*Spełniane historyjki:* US-01 (po części), US-05, US-06 (lista -> delete), US-07.

### Widok: Tworzenie notatki (pełna strona)

  **Ścieżka:** `/notes/new`
  **Cel:** Utworzenie nowej notatki (tytuł, opis markdown, labels, visibility).
  **Kluczowe informacje:** pola: title (max 255), description (textarea, max 10000), labels (tag-input), visibility toggle (private/public), przycisk „Podgląd” (HTMX → `/api/notes/preview`), przycisk „Zapisz”.
  **Kluczowe komponenty:** formularz duży (accessible labels), toolbard minimalny markdown (wstawienia), tag-input z deduplikacją, alerty walidacji, sticky action bar (Save/Preview).
  **UX / dostępność / bezpieczeństwo:** aria-describedby dla błędów, ograniczenia długości inputów, serwerowa walidacja, CSRF, brak autosave.

*Spełniane historyjki:* US-01.

### Widok: Edycja notatki (pełna strona)

  **Ścieżka:** `/notes/{id}/edit`
  **Cel:** Edycja istniejącej notatki przez ownera lub collaborator.
  **Kluczowe informacje:** prefill pól (title, description, labels, visibility), sekcja Collaborators, przycisk „Podgląd”, przycisk „Zapisz”, akcje: „Usuń notatkę” (owner only).
  **Kluczowe komponenty:** formularz edycyjny, collaborators panel (lista + input email + remove), modal confirm dla delete, inline validation.
  **UX / dostępność / bezpieczeństwo:** dostęp kontrolowany (jeśli brak uprawnień → czytelny komunikat/redirect), focus management w modalach, potwierdzenie krytycznych akcji, serwerowe mapowanie błędów.

*Spełniane historyjki:* US-03, US-04, US-06, US-08, US-14.

### Widok: Publiczny odczyt notatki

  **Ścieżka:** `/n/{url_token}`
  **Cel:** Odwiedzający (anonim lub zalogowany) czytają notatkę (rendered markdown).
  **Kluczowe informacje:** title, rendered description (sanitizowane HTML), labels, created_at, link/CTA do edycji jeśli użytkownik ma uprawnienia.
  **Kluczowe komponenty:** content area z markup-safe container, copy-to-clipboard dla kodu (opcjonalne), CTA do logowania jeśli chcesz edytować.
  **UX / dostępność / bezpieczeństwo:** sanitizacja po stronie serwera, brak formularzy edycji, aria roles dla treści, czytelne komunikaty gdy private / invalid token (404/403 -> friendly message).

*Spełniane historyjki:* US-02.

### Widok: Publiczny katalog użytkownika

  **Ścieżka:** `/u/{uuid}`
  **Cel:** Przegląd publicznych notatek danego użytkownika (podgląd profilu).
  **Kluczowe informacje:** lista publicznych notatek (title, excerpt, labels, created_at), paginacja AJAX, wyszukiwarka (q + label:).
  **Kluczowe komponenty:** list items (NoteCard), paginacja AJAX (hx-get), search box (hx-get + hx-push-url), empty-state message („Notatki niedostępne lub nieprawidłowy link”).
  **UX / dostępność / bezpieczeństwo:** 
    - Wyszukiwanie i paginacja realizowane przez **GET**, co umożliwia **Deep Linking** i łatwe udostępnianie przefiltrowanych list.
    - Ochrona przed botami za pomocą pola **Honeypot**.
    - Zalogowany właściciel widzi baner informacyjny o trybie podglądu profilu publicznego.
    - Pusta galeria wizualnie spójna ze stroną błędu notatki (kontener `pn-error`).

*Spełniane historyjki:* US-09, US-12 (wylogowanie wpływa na widoki).

### Widok: Collaborators management (część edycji)

  **Ścieżka:** komponent w `/notes/{id}/edit`
  **Cel:** Dodawanie/usuwanie współedytorów przez email; self-removal.
  **Kluczowe informacje:** lista współedytorów (email, jeśli przywiązane user_id), input email, przycisk dodaj, remove action (self removal).
  **Kluczowe komponenty:** collaborator-row, email input z walidacją, confirmation przy remove self.
  **UX / dostępność / bezpieczeństwo:** enforce unique `(note_id, lower(email))` na serwerze, komunikaty 409 → alert, natychmiastowy loss of access przy self-remove.

*Spełniane historyjki:* US-08, US-14.

### Widok: Modal potwierdzeń i toasty

  **Ścieżka:** globalne komponenty (używane wszędzie)
  **Cel:** Potwierdzenia krytycznych akcji (usuń), globalne powiadomienia.
  **Kluczowe informacje:** jasne komunikaty, konsekwencje akcji, przyciski Confirm/Cancel.
  **Kluczowe komponenty:** accessible modal (focus trap), toast system, inline error banners.
  **UX / dostępność / bezpieczeństwo:** aria-modal, keyboard support, focus return, CSRF protected actions.


## 3. Mapa podróży użytkownika

**Główny przypadek użycia — „Utwórz → Udostępnij → Publiczny odczyt” (krok po kroku):**

1. Użytkownik: `/login` (lub `/register`) → POST `/api/auth/login` → sesja cookie.
2. Redirect → `/dashboard` (GET `/api/notes?page=1&per_page=10`).
3. Kliknij „Dodaj notatkę” → `/notes/new`.
4. Wypełnij: title, description, labels, ustaw visibility (domyślnie private).
5. Kliknij „Zapisz” → POST `/api/notes` → otrzymanie `url_token` na serwerze (w odpowiedzi).
6. Po pierwszym zapisie: interfejs może pokazać link do publicznego widoku (jeśli public) lub informację, że notatka jest prywatna.
7. Aby udostępnić: w edycji dodaj collaborator email → POST `/api/notes/{id}/collaborators` → zapis.
8. Jeżeli visibility → public i url_token istnieje → odwiedzający może wejść na `/p/{url_token}` i czytać (GET `/api/public/notes/{url_token}`).

**Skrócone przejścia między widokami:**

* Landing → Login/Register → Dashboard
* Dashboard → (New) → Create → Dashboard (po zapisie)
* Dashboard → Edit → Save → Dashboard
* Edit → Public view (Preview) → Edit
* Public link → (if authorized) → Edit

## 4. Układ i struktura nawigacji

**Główne elementy:**

  **Topbar**: logo/brand (lewy), global search (`q`, obsługa `label:`) (środek/prawo), użytkownik menu (avatar, dropdown: Dashboard, Profile (future), Logout).
  **Sidebar (chowany)**: linki (Dashboard, Nowa notatka, Publiczny katalog (opcjonalny)), stan chowania zapamiętywany (localStorage).
  **Główna treść:** kontekstowo wypełniana przez pełnostronicowe widoki.
  **Breadcrumbs (opcjonalne):** Dashboard → Notatki → Edycja / Public view — pomocne przy nawigacji i dostępności.
  **Footer (minimalny):** linki prawne, kontakt (statyczne).

**Nawigacja i reguły:**

* Widoki chronione (Dashboard, Edit, Create) → jeśli brak sesji → redirect do `/login`.
* Public views dostępne anonimowo.
* Krytyczne operacje (delete) dostępne w edycji z modalem confirm.

## 5. Kluczowe komponenty

1. **Topbar + Search**

   Behavior: globalne `q` wyszukiwanie; parsing `label:`; debounce; accessible input; sends GET `/api/notes?q=...`.
2. **Sidebar (collapsible)**

   Mobile-first, accessible toggle; items with aria-current.
3. **NoteRow (lista)**

   Title (link), excerpt (255), labels (chips), created_at, action icons (edit/delete), keyboard navigable.
4. **Large Editor Form**

   Title input, large textarea (aria), markdown toolbar (minimal JS), labels tag-input (unicode support), visibility toggle, Save & Preview buttons.
5. **Markdown Preview (HTMX fragment)**

   HTMX `POST /api/notes/preview` -> inserts sanitized HTML fragment; accessible alt text for code blocks; preview in full page or side panel.
6. **Collaborators Panel**

   List of collaborator rows, add-email input (validation), remove buttons, indicator owner vs collaborator, self-remove confirmation.
7. **Modal (Confirm)**

   Reusable accessible modal with focus trap; used for delete/self-removal.
8. **Toasts & Global Alerts**

   For 409/500 and transient messages; accessible `role="status"`.
9. **Pagination**

   Classic page numbers, keyboard accessible, shows current page and total pages.
10. **Empty states**

    Dashboard empty: CTA „Dodaj notatkę”; Public catalog empty: „Nie ma takiego użytkownika”.
11. **Copy-to-clipboard helper**

    For public note code blocks / url copying with aria-live feedback.
12. **ARIA & a11y helpers**

    Error summarizer for form (aria-invalid, aria-describedby), skip-links, semantic headings.



## Mapowanie historyjek (PRD) na widoki / elementy UI

  **US-01 (Tworzenie notatki)** → `/notes/new`, Editor Form, Save button, labels tag-input.
  **US-02 (Wyświetlenie publicznej notatki)** → `/p/{url_token}`, sanitized render, copy allowed.
  **US-03 (Edycja notatki)** → `/notes/{id}/edit`, Editor Form prefilled, Save.
  **US-04 (Zmiana widoczności)** → visibility toggle w edycji + save.
  **US-05 (Wyszukiwanie)** → Topbar Search + Dashboard list + parsing `label:`.
  **US-06 (Usuwanie notatki)** → Delete action w Dashboard i Edit, modal confirm.
  **US-07 (Dashboard pusty)** → Empty state z CTA.
  **US-08 (Udostępnienie współedytorowi)** → Collaborators Panel w edycji.
  **US-09 (Publiczny katalog)** → `/u/{uuid}`, public list + search + pagination.
  **US-10/US-11/US-12 (Rejestracja, Logowanie, Wylogowanie)** → Landing/Login/Register flows + topbar user menu.
  **US-14 (Usunięcie własnego dostępu)** → Collaborators Panel → remove self → redirect to dashboard on success.
  **US-16 (Przypomnienie hasła)** → link z login/register → `/forgot-password` → POST `/api/auth/forgot-password`.
  **US-17 (Reset hasła)** → `/reset-password?token=...` → POST `/api/auth/reset-password`.



## Potencjalne punkty bólu użytkownika i rozwiązania UI

1. **Niejasne komunikaty błędów (400/409/403):**

   Rozwiązanie: precyzyjne inline errors + global alerty; mapping kodów HTTP do przyjaznych komunikatów.
2. **Utrata pracy (brak autosave):**

   Rozwiązanie: widoczny komunikat „Brak autosave — pamiętaj kliknąć Zapisz”; disabled Save jeśli walidacja.
3. **Złożoność współdzielenia (kto ma dostęp):**

   Rozwiązanie: jasne oznaczenia w Collaborators Panel: owner vs collaborator; przy self-remove potwierdzenie konsekwencji.
4. **Zła obsługa labeli (unicode, duplikaty):**

   Rozwiązanie: tag-input z dedupe case-insensitive; walidacja i normalizacja przed wysłaniem.
5. **Dostępność publicznej treści (XSS):**

   Rozwiązanie: server-side sanitization + whitelist; klient renderuje bez wykonania skryptów.
6. **Słaba nawigacja na mobile:**

   Rozwiązanie: chowany sidebar, sticky topbar, duże CTA, touch-friendly elements.


## Zgodność z planem API — krótka kontrola zgodności

 Wszystkie widoki odczytów i zapisu odzwierciedlają endpoints z API planu.
 Operacje krytyczne (delete, add collab) wywoływane przez dedykowane endpointy i zabezpieczone voterami/auth.
 Wyszukiwanie `label:` i paginacja 10/strona zmapowane na UI i query params.
 Preview markdown używa `POST /api/notes/preview` przez HTMX, a public view używa `/api/public/notes/{url_token}`.


**Podsumowanie:** architektura UI jest prosta, zgodna z PRD i API: pełnostronicowe widoki, HTMX do fragmentów (preview, collaborators), topbar search, dashboard jako centralny punkt, pełna obsługa walidacji, modalów i dostępności. Implementacja powinna skupić się na czytelności, minimalnej JS (toolbar), silnej warstwie walidacji i jasno komunikowanych błędach.
