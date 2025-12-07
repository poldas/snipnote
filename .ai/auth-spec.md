# Specyfikacja architektury modułu rejestracji, logowania, wylogowania i odświeżania tokenu (Symfony 8 + Supabase Auth)

## Zakres i założenia
- Obejmuje US-010 (rejestracja), US-011 (logowanie), US-012 (wylogowanie), US-015 (odświeżenie tokenu) z PRD oraz spójność z resztą aplikacji (publiczny podgląd notatki i publiczny katalog dostępne bez logowania).
- Backend: Symfony 8 (PHP 8.4), Doctrine ORM 3.5, lexik/jwt-authentication-bundle; integracja z Supabase Auth jako zewnętrzny provider tożsamości (IdP). Refresh tokeny rotowane przez Supabase.
- Frontend: główny templating Twig + HTMX + Tailwind (zgodnie z tech-stack), formularze auth mogą pozostać czysto HTMX/Twig.
- Brak naruszenia istniejącej logiki dostępu: bez logowania dostępny jest wyłącznie publiczny widok notatki i publiczny katalog użytkownika.

## 1. Architektura interfejsu użytkownika

### Layouty i nawigacja
- `base_auth.html.twig`: lekki layout dla stron auth (bez głównego sidebaru/dash). Zawiera logo, nagłówek, linki do logowania/rejestracji, komunikaty flash/błędy. Tailwind dla siatki i typografii.
- `base_app.html.twig`: używany po zalogowaniu (dashboard, edycja notatek). W nawigacji: widoczny email użytkownika i przycisk `Wyloguj` (POST/HTMX).
- Widoki publiczne (notatka publiczna, katalog publiczny) pozostają dostępne bez auth; pasek nawigacji pokazuje CTA „Zaloguj / Zarejestruj”.

### Strony / komponenty (Twig + HTMX; opcjonalne wyspy React)
- `auth/register.html.twig` — formularz rejestracji: pola email, hasło, checkbox zgody (jeśli wymagany). Walidacja: format email, min. długość hasła (≥8). Obsługa błędów: inline pod polem + flash top.
- `auth/login.html.twig` — formularz logowania: email, hasło. Link „Nie pamiętasz hasła?” kieruje do resetu (Supabase magic link).
- `auth/reset-request.html.twig` — formularz email do resetu (wysyłka magic link z Supabase).
- `auth/reset-done.html.twig` — potwierdzenie wysłania maila.
- Przycisk `Wyloguj` w nav (HTMX POST do `/auth/logout`), po sukcesie redirect na landing.
- HTMX używany do: asynchroniczna walidacja (opcjonalnie), wyświetlanie błędów bez pełnego przeładowania, zachowanie SSR jako bazowe.
- Jeżeli używamy React wysp: komponent `AuthForm` (walidacja client-side, maskowanie submitu, spinner), montowany na stronach Astro/Twig; jednak priorytetem jest SSR/HTMX, React jest nieobowiązkowy.

### Walidacja i komunikaty
- Email: format RFC, komunikat „Podaj poprawny adres email”.
- Hasło: min. 8 znaków (zgodnie z PRD), komunikat „Hasło musi mieć co najmniej 8 znaków”.
- Błędy po stronie IdP (Supabase): mapowane na przyjazne komunikaty (np. duplikat email → „Konto z tym adresem już istnieje”).
- Przy nieudanym logowaniu: „Nieprawidłowy email lub hasło”.
- Przy odświeżaniu tokenu i jego wygaśnięciu: redirect do logowania z flash „Sesja wygasła, zaloguj się ponownie”.

### Scenariusze kluczowe (UX)
- Rejestracja: po sukcesie automatyczne zalogowanie, redirect na dashboard (pusty stan US-07).
- Logowanie: po sukcesie redirect na dashboard; jeśli użytkownik przyszedł z protected strony, redirect na nią.
- Wylogowanie: unieważnienie sesji + refresh tokenu; redirect na landing (publiczny).
- Reset hasła: użytkownik podaje email, Supabase wysyła magic link resetu; po kliknięciu w link następuje ustawienie nowego hasła w Supabase i powrót na stronę potwierdzenia/logowania.

## 2. Logika backendowa (Symfony 8)

### Endpoints (HTTP)
- `POST /auth/register` — tworzy konto w Supabase (email+password), tworzy/aktualizuje encję `User` lokalnie (id z Supabase jako external_id), loguje użytkownika (ustawia session cookie + access token). Walidacja: email, hasło.
- `POST /auth/login` — deleguje do Supabase (email+password), pobiera access + refresh token, zapisuje w sesji/HttpOnly cookies. Synchronizuje profil w DB (email, timestamps).
- `POST /auth/logout` — wymaga sesji; usuwa sesję Symfony, usuwa ciasteczka tokenów, wywołuje Supabase sign-out (revocation refresh tokenu).
- `POST /auth/refresh` — wymaga refresh tokenu w HttpOnly cookie; woła Supabase refresh endpoint; ustawia nowy access + refresh (rotacja). Błąd → 401 i czyszczenie cookies.
- `POST /auth/reset/request` — przyjmuje email, woła Supabase password reset (magic link). Zwraca 200 niezależnie czy konto istnieje (brak informacji o istnieniu).
- `POST /auth/reset/complete` — obsługiwane po powrocie z magic linku Supabase (URL z tokenem) — endpoint przyjmuje nowy password, przekazuje do Supabase, po sukcesie redirect do loginu.

### Kontrolery i serwisy
- `AuthController` (HTML endpoints) — renderuje widoki, zarządza flashami; akcje register/login/logout/reset.
- `ApiAuthController` (JSON, jeśli wymagane) — ewentualne API dla SPA/HTMX; w MVP HTML wystarcza.
- `SupabaseAuthClient` — serwis integrujący się z Supabase REST (admin API key serwerowy): metody `signUp`, `signInWithPassword`, `refreshToken`, `signOut`, `resetPasswordForEmail`, `updateUserPassword`.
- `UserSynchronizer` — mapuje dane z Supabase do lokalnej encji `User` (external_id, email, timestamps). Tworzy, gdy nie istnieje.
- `TokenCookieManager` — ustawia/usuwa HttpOnly cookies: `sb-access-token` (krótkie TTL), `sb-refresh-token` (dłuższe TTL), flagi Secure/SameSite=Lax/Strict.
- `Security/UserProvider` — ładuje użytkownika na podstawie access tokenu (JWT z Supabase), waliduje sygnaturę (jwks z Supabase) i datę ważności. Może korzystać z lexik/jwt jako warstwy weryfikacji podpisu.
- `AccessDeniedHandler` — przekierowanie na login dla HTML; 401 JSON dla API.

### Walidacja
- Symfony Validator: `Email`, `Length(min=8)` na haśle; niestandardowy constraint `NotPwned` (opcjonalnie) można dodać później.
- CSRF: formularze auth zabezpieczone tokenem CSRF (Symfony form/guard).
- Rate limiting: throttle na `/auth/login`, `/auth/register`, `/auth/reset/request` (Symfony RateLimiter) per IP + email.

### Obsługa wyjątków
- Błędy Supabase (duplikat email, złe hasło, nieważny refresh) mapowane na 4xx i komunikaty przyjazne; logowanie po stronie serwera z kodem Supabase.
- Błędy sieci/IdP → 503 z komunikatem „Serwis logowania chwilowo niedostępny”; nie ujawnia szczegółów.
- Braki uprawnień na zasobach chronionych → redirect na `/auth/login?next=...` lub 401 JSON.

## 3. System autentykacji (Symfony 8 + Supabase Auth)

### Model danych
- `User` (Doctrine): `id` (UUID lokalny), `externalId` (Supabase user id UUID), `email` (unique), `createdAt`, `updatedAt`, ewentualnie `lastLoginAt`. Hasło nie jest przechowywane lokalnie (delegacja do Supabase).
- Brak ról rozbudowanych w MVP; rola domyślna `ROLE_USER`. Uprawnienia do notatek realizowane istniejącymi Voterami/domeną.

### Przepływy tokenów
- Supabase wydaje `access_token` (krótki) i `refresh_token` (dłuższy). Oba trzymane w HttpOnly Secure cookies.
- Przy każdym żądaniu chronionym: middleware/guard odczytuje access token z cookie, waliduje podpis (JWKS), datę, audience. Po sukcesie ładuje `User` z repo przez `externalId`.
- Przy wygaśnięciu access tokenu: front (HTMX) może wykonać `POST /auth/refresh` (np. przez polling przed ważnymi akcjami) lub serwer może zainicjować 401 i klient reaguje redirectem do loginu jeśli refresh zawiedzie.
- Rotacja refresh tokenów: każda udana wymiana wydaje nowy refresh; poprzedni jest unieważniany przez Supabase. Logout unieważnia refresh.

### Wylogowanie
- `POST /auth/logout`:
  - usuwa sesję Symfony;
  - usuwa cookies `sb-access-token`, `sb-refresh-token`;
  - wywołuje Supabase `signOut` (revocation refresh tokenu) z aktualnym refresh tokenem;
  - redirect 302 na stronę publiczną (landing/publiczny katalog).

### Odzyskiwanie / reset hasła
- `POST /auth/reset/request`: deleguje do Supabase `resetPasswordForEmail`. Odpowiedź 200 niezależnie od istnienia konta.
- Użytkownik klika w magic link od Supabase (zawiera oobCode/token); trafia na front (route `auth/reset/complete`). Front przesyła token + nowe hasło do backendu, backend woła Supabase `updateUserPassword`. Po sukcesie flash „Hasło zmienione, zaloguj się” i redirect na login.
- Bezpieczeństwo: link jednorazowy, ograniczony czasowo, weryfikacja po stronie Supabase.

### Zgodność z istniejącymi funkcjami aplikacji
- Publiczne ścieżki (widok notatki po URL, katalog publiczny użytkownika) nie wymagają auth; middleware pomija wymóg tokenu.
- Chronione ścieżki (dashboard, edycja notatki, zmiana widoczności, współdzielenie) wymagają ważnego access tokenu; brak tokenu → redirect do loginu.
- Reguły domenowe notatek pozostają bez zmian; auth dostarcza tożsamość i id użytkownika (externalId) do istniejących Voterów/serwisów domenowych.

### Monitorowanie i diagnostyka
- Logowanie zdarzeń auth (rejestracja, logowanie, odświeżenie, błędy) do Monolog z kontekstem `externalId`, `email`, kodem Supabase.
- Opcjonalnie middleware mierzący czasy odpowiedzi Supabase do obserwowalności.

## Kluczowe kontrakty / moduły
- Serwis: `SupabaseAuthClient` (HTTP client + DTO odpowiedzi).
- Serwis: `TokenCookieManager` (ustawianie/rotacja HttpOnly cookies).
- Serwis: `UserSynchronizer` (sync profilu).
- Guard/middleware: `SupabaseJwtAuthenticator` (weryfikacja access tokenu, ładowanie użytkownika).
- Kontrolery: `AuthController` (HTML), `ApiAuthController` (JSON opcjonalnie).
- Walidatory: `Email`, `Length` dla hasła, `CsrfTokenManager`, `RateLimiter`.
- Szablony: `base_auth.html.twig`, `auth/register.html.twig`, `auth/login.html.twig`, `auth/reset-request.html.twig`, `auth/reset-done.html.twig`; komponenty HTMX/React wyspy (opcjonalnie) `AuthForm`.

