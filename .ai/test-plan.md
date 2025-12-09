## Plan testów – Snipnote (MVP współdzielonych notatek)

### 1. Wprowadzenie i cele testowania
- Zapewnienie zgodności implementacji z PRD (widoczność notatek, współdzielenie, publiczny dostęp, auth).
- Wczesne wykrywanie regresji w usługach domenowych (notatki, współautorzy, auth/JWT).
- Potwierdzenie poprawności integracji UI (Twig + HTMX/Stimulus) z API.
- Walidacja bezpieczeństwa (dostęp, XSS w markdown, rotacja refresh tokenów, prywatność URL).

### 2. Zakres testów
- Backend HTTP/API: `/api/notes`, `/api/notes/{id}/collaborators`, auth (`/api/auth/*`), public API (`/api/public/*`), markdown preview.
- Frontend HTML: widoki Twig (`/login`, `/register`, `/notes`, `/notes/{id}/edit`, `/n/{token}`, katalog publiczny użytkownika).
- Logika domenowa: serwisy `NoteService`, `NoteCollaboratorService`, `AuthService`, `NotesQueryService`, `PublicNotesCatalogService`, `RefreshTokenService`.
- Bezpieczeństwo: autoryzacja (NoteVoter), widoczność prywatna/publiczna, rotacja tokenów, CSRF (custom token), walidacja wejścia.
- Dane i migracje: integralność encji `Note`, `NoteCollaborator`, `User`, `RefreshToken`, `NoteVisibility`; unikalność URL; kaskady przy usuwaniu.
- Niezakres: zaawansowana analityka, historię wersji, załączniki (nie w MVP).

### 3. Typy testów
- Testy jednostkowe (PHPUnit): serwisy domenowe, parser `NotesSearchParser`, DTO walidacje.
- Testy integracyjne (PHPUnit + Symfony test tools, test DB PostgreSQL): kontrolery API, repozytoria (filtrowanie, paginacja, widoczność), migracje.
- Testy e2e UI (Playwright/Cypress lub Symfony Panther): kluczowe ścieżki użytkownika (auth, tworzenie/edycja notatek, współdzielenie, podgląd publiczny, reset hasła).
- Testy bezpieczeństwa: dostępy właściciel/współautor/anonim, próby edycji po zmianie widoczności, rotacja refresh tokenów, XSS w markdown (sanity).
- Testy wydajnościowe lekkie: czas odpowiedzi listy notatek i katalogu (małe dane), brak N+1.
- Testy dostępności podstawowe: nawigacja klawiaturą, etykiety formularzy, kontrast głównych widoków.

### 4. Scenariusze testowe kluczowych funkcji
- **Rejestracja/logowanie/wylogowanie**: poprawne dane, duplikat email, brak weryfikacji email → odmowa logowania, przekierowania po login/logout.
- **Refresh token**: poprawna rotacja, próba użycia starego tokenu, revoke przy logout.
- **Tworzenie notatki**: wymagane pola, limity tytułu/opisu, labele z deduplikacją, domyślna prywatność, generacja unikalnego URL (kolizja → retry/wyjątek).
- **Edycja notatki**: zmiana tytułu/opisu/labeli/widoczności, zapis manualny, podgląd markdown (HTML bez JS, brak XSS), przekierowanie na dashboard.
- **Widoczność**: prywatna (anonim/brak dostępu mimo tokenu), publiczna (podgląd bez edycji), przełączanie i trwałość po zapisie.
- **Regeneracja URL**: wygenerowanie nowego tokenu, natychmiastowa nieważność starego, poprawne linki w UI.
- **Współautorzy**: dodanie email (walidacja, bez duplikatów), lista, usunięcie innego współautora (właściciel), samousunięcie współautora, brak możliwości usunięcia właściciela.
- **Usuwanie notatki**: tylko właściciel, potwierdzenie w UI, usunięcie powiązań (labele, współautorzy, URL, katalog publiczny), próba wejścia po usunięciu → błąd/dostęp zabroniony.
- **Dashboard i wyszukiwanie**: filtrowanie po tekście i labelach (OR), paginacja 10/strona, komunikat pustego stanu, sortowanie DESC, kliknięcie → edycja.
- **Publiczny katalog użytkownika**: lista publicznych notatek, paginacja, wyszukiwanie/label, brak użytkownika → komunikat.
- **Reset hasła**: link „Nie pamiętasz hasła?”, walidacja email, scenariusz token ważny/nieważny, ustawienie nowego hasła zgodnie z regułami.
- **Walidacja API**: błędne JSON, brak pól, niewłaściwe typy, limity długości; formaty odpowiedzi i kody HTTP.
- **Bezpieczeństwo klienta**: blokada edycji w publicznym widoku, poprawne linki `login?redirect=...`, brak CSRF przy żądaniach HTMX (token obecny).

### 5. Środowisko testowe
- Docker Compose (php-fpm 8.2/8.4, nginx, PostgreSQL); osobne bazy `snipnote_test`.
- Konfiguracja Symfony `APP_ENV=test`, `APP_SECRET` testowy, `JWT_SECRET` stub.
- Migracje uruchamiane przed testami: `php bin/console doctrine:migrations:migrate --env=test`.
- Dane startowe: Fixtures/Factory (jeśli brak, tworzenie w testach) dla użytkowników, notatek, współautorów, refresh tokenów.
- Testy e2e: serwer dev (`symfony server:start` lub `php -S`) + Playwright/Cypress z chromem headless; izolacja danych przez reset DB między scenariuszami.

### 6. Narzędzia do testowania
- PHPUnit (już obecny w repo) + Symfony Test Pack.
- PhpUnit data providers dla wariantów walidacji.
- Testcontainers lub docker-compose do izolacji PostgreSQL (opcjonalnie).
- Playwright/Cypress dla e2e; Axe-core dla dostępności podstawowej.
- PHPStan (poziom 5) i PHP-CS-Fixer – sanity po zmianach.
- K6/Artillery do lekkich testów wydajności (lista/podgląd publiczny).

### 7. Harmonogram testów
- Dzień 1: konfiguracja środowiska testowego, migracje, dane przykładowe.
- Dni 2–3: pokrycie jednostkowe serwisów (notes, collaborators, auth, parser), walidacje DTO.
- Dni 4–5: testy integracyjne API (CRUD notatek, współautorzy, auth, katalog publiczny, markdown preview).
- Dni 6–7: e2e UI krytyczne ścieżki (auth, create/edit/delete, share, public view, reset hasła).
- Dzień 8: bezpieczeństwo (dostępy, XSS, rotacja tokenów), dostępność, lekkie wydajnościowe.
- Dzień 9: regresja pełna na stabilnej gałęzi, raport, poprawki.

### 8. Kryteria akceptacji testów
- 100% przechodzące testy jednostkowe i integracyjne w CI.
- Pokrycie krytycznych ścieżek e2e min. 80% user stories z PRD.
- Brak otwartych blockerów/krytycznych; wysokie/średnie z opisanym obejściem lub naprawione.
- Wyniki bezpieczeństwa: brak nieszczelności widoczności, rotacja refresh tokenów działa, brak XSS w markdown podglądzie.
- Wydajność: kluczowe endpointy <500 ms na danych testowych przy 10 równoległych użytkownikach.

### 9. Role i odpowiedzialności
- QA: projekt planu, implementacja i wykonanie testów, raportowanie defektów.
- Dev backend: wsparcie w mockach/fixtures, naprawa defektów API/domeny.
- Dev frontend: naprawa problemów UI/HTMX/Stimulus/Twig, dostępność.
- DevOps: utrzymanie środowisk, CI (phpunit, phpstan, e2e), tajne dane (JWT_SECRET).
- PM/PO: akceptacja kryteriów, priorytetyzacja defektów.

### 10. Procedury raportowania błędów
- Rejestracja w trackerze (np. Jira/GitHub Issues) z krokami, oczekiwanym vs. rzeczywistym, logami (HTTP trace, screenshot, payloady, userId/noteId, token krótkotrwały).
- Klasyfikacja: Blocker/Krytyczny/Wysoki/Średni/Niski; tagi: `backend`, `frontend`, `security`, `performance`, `ux`, `regression`.
- Załączniki: curl/HTTP transcript, dump DB (minimalny), screenshot lub nagranie e2e.
- Weryfikacja naprawy: test odtworzeniowy + regresja obszaru; oznaczenie wersji/commit SHA.
- Cotygodniowy raport jakości: liczba defektów wg poziomu, czas naprawy, obszary ryzyka otwarte.

