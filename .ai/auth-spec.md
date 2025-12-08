# Specyfikacja architektury autentykacji (UI + API)

## Kontekst i cele
- Zakres: US-010 Rejestracja, US-011 Logowanie, US-012 Wylogowanie; zintegrowane z istniejącym UI (Twig + HTMX) i backendem Symfony 8 + Doctrine/PostgreSQL.
- UI używa wyłącznie sesji Symfony (cookie + CSRF). JWT używany tylko dla `/api/*`.
- Wspólne źródło prawdy: encja `User` w PostgreSQL zarządzana przez Doctrine.
- Maile (verify/reset) współdzielone między UI i API; jedyna zmienna między środowiskami to `MAILER_DSN`.

## 1. Architektura interfejsu użytkownika (Twig + HTMX)

### Layouty i tryby
- **Anon layout**: strony `/login`, `/register`, `/forgot-password`, `/reset-password/{token}`, `/verify/email`. Minimalny topbar (logo + link do logowania/rejestracji), bez sidebaru. Formularze z CSRF.
- **Auth layout**: widoki aplikacji (`/notes/*`). Menu użytkownika zawiera „Wyloguj” (POST z CSRF).
- **HTMX**: używane do wstrzykiwania fragmentów z błędami walidacji (hx-target na wrapperze formularza) i ewentualnych toastów; pełne przeładowania dla przejść między widokami.

### Strony i komponenty
- **Login (`/login`, GET/POST)**: pola email, hasło; checkbox „Zapamiętaj” (remember_me). Po sukcesie redirect na `/notes`. Przy `isVerified=false` zamiast logowania renderowana strona „Sprawdź maila, aby aktywować konto” (bez ujawniania stanu konta innym).
- **Register (`/register`, GET/POST)**: pola email, hasło (min. 8), checkbox zgody (jeśli wymagane prawnie). Po sukcesie: automatyczne zalogowanie sesyjne, redirect na stronę „Sprawdź maila” (blokada akcji wymagających weryfikacji). Komunikat o wysłaniu maila aktywacyjnego.
- **Verify email (`/verify/email?signature=...`)**: wynik VerifyEmailBundle (success → redirect do `/notes` lub strony potwierdzenia; failure → komunikat „link wygasł / nieważny” + przycisk „Wyślij ponownie”).
- **Forgot password (`/forgot-password`, GET/POST)**: pole email; po submit zawsze ten sam komunikat „Jeśli konto istnieje, wysłaliśmy instrukcje”. Brak ujawniania istnienia konta.
- **Reset password (`/reset-password/{token}`, GET/POST)**: pola nowe hasło + powtórzenie; po sukcesie automatyczne zalogowanie i redirect na `/notes`.
- **Logout (`/logout`, POST)**: formularz ukryty w menu użytkownika, CSRF.

### Walidacja i komunikaty (UI)
- Email: format RFC, komunikat „Podaj poprawny adres email”.
- Hasło: min. 8 znaków (PSR-12 msg), komunikat „Hasło musi mieć min. 8 znaków”.
- Pole wymagane: „To pole jest wymagane”.
- Błędy serwera 400: inline pod polem; 401/403: globalny alert „Brak dostępu / konto nieweryfikowane”; 429: alert „Za dużo prób, spróbuj ponownie później”; 5xx: toast „Coś poszło nie tak”.

### Scenariusze kluczowe
- Nowy użytkownik → `/register` → po sukcesie komunikat „Sprawdź maila” → klik link w mailu → `/verify/email` → redirect do `/notes`.
- Istniejący, niezweryfikowany → próba logowania → komunikat o konieczności weryfikacji + link do ponownego wysłania maila.
- Zapomniane hasło → `/forgot-password` → otrzymany link → `/reset-password/{token}` → ustawienie nowego hasła → redirect `/notes`.
- Wylogowanie → POST `/logout` → redirect na landing.

## 2. Logika backendowa

### Model danych (Doctrine)
- Encja `User` (tabela `users`): id (uuid/ulid), email (unikalny, lowercase), `password` (hash), `roles` (JSON), `isVerified` (bool), `createdAt/updatedAt`, opcjonalnie `lastLoginAt`.
- Provider: Doctrine user provider (email jako identyfikator).

### Walidacja (Symfony Validator)
- Email: `Email` + `NotBlank` + `Length(max=180)`.
- Hasło: `NotBlank`, `Length(min=8, max=4096)`.
- Verify/Reset tokeny walidowane przez bundlowe constrainty.
- Błędy mapowane na form errors (HTML) lub JSON `{field: [messages...]}`.

### Endpoints i rozdzielenie warstw
HTML (sesja, CSRF, Twig):
- `GET /login` (SecurityController::loginForm) + `POST /login` (Symfony authenticator).
- `POST /logout` (route only, handled by firewall).
- `GET|POST /register` (RegistrationController) → tworzy usera, hashuje hasło, wywołuje VerifyEmailHelper → wysyła mail; po sukcesie loguje użytkownika i redirect do „verify notice”.
- `GET /verify/email` (VerificationController) → VerifyEmailBundle; w razie błędu generuje formularz „wyślij ponownie”.
- `GET|POST /forgot-password` (ResetPasswordController::request) → ResetPasswordBundle request.
- `GET|POST /reset-password/{token}` (ResetPasswordController::reset) → ustawia nowe hasło, loguje użytkownika, unieważnia token.

API (JWT, bez sesji; JSON):
- `POST /api/auth/login` → weryfikacja email/hasło + `isVerified`; zwraca `{access_token, refresh_token?, expires_in}` (HS256, `sub`, `exp`, `iat`). 401 na błędne dane lub nieweryfikowane konto (bez różnicowania przyczyny).
- `POST /api/auth/refresh` (jeśli użyty gesdinet/jwt-refresh-token-bundle) → zwrot zrotowanego refresh + nowy access.
- `POST /api/auth/logout` → unieważnia refresh (np. usunięcie rekordu) i zwraca 204.
- Mechanizm reset/verify współdzielony: API może korzystać z tych samych usług (np. event listener wysyłający mail) lub wystawiać dodatkowe JSON endpoints proxy do bundli jeśli potrzebne.

### Obsługa wyjątków i bezpieczeństwo informacji
- Reset hasła: zawsze 200 z identycznym komunikatem, brak sygnalizacji istnienia konta.
- Verify email: błędy podpisu/wygaszenia → komunikat ogólny + opcja ponownego wysłania.
- Logowanie: odpowiedź 401 bez potwierdzenia, czy email istnieje lub czy konto zweryfikowane (dla API); dla UI komunikat o weryfikacji, ale bez potwierdzenia istnienia adresu.
- Rate limiting: na `/login` i `/api/auth/login` (np. `LoginRateLimiter`).

### Integracja e-mail (VerifyEmailBundle, ResetPasswordBundle)
- Wspólny `MailerInterface` + `EMAIL_FROM` (konfiguracja parameters.yaml). Szablony Twig dla maili (verify/reset) dostępne zarówno dla UI, jak i API wywołujące te same serwisy.
- Konfiguracja środowisk:
  - DEV: `MAILER_DSN=smtp://mailpit:1025` (kontener `axllent/mailpit`).
  - PROD: `MAILER_DSN` na zewnętrzny SMTP (Mailgun/SendGrid/AWS SES lub firmowy).
- Kod nie zależy od środowiska; różni się wyłącznie wartość `MAILER_DSN`.

## 3. System autentykacji i autoryzacji

### Symfony Security
- Firewalle:
  - `main` (UI): form_login authenticator + remember_me, session storage, CSRF w formach, access_control wymuszający auth na `/notes/**`, `/collaborators/**`, itp.
  - `api` (stateless): `JwtAuthenticator` (LexikJWT), bez sesji/CSRF, ścieżki `/api/**`.
- Access control:
  - Public: `/`, `/login`, `/register`, `/verify/email`, `/forgot-password`, `/reset-password/*`, `/p/*`, `/u/*`.
  - Auth required: `/notes/**` itp.; dodatkowo `isVerified()` voter/constraint blokuje nieweryfikowanych.
- Password hashing: `UserPasswordHasherInterface` (argon2id/bcrypt per Symfony defaults).
- `isVerified` enforced:
  - UI: po form_login, jeżeli `!user.isVerified`, następuje wylogowanie + redirect do „verify notice”.
  - API: login/refresh odrzuca nieweryfikowane konto (401).

### JWT (tylko API)
- Format: HS256; payload minimalnie `sub` (user id/email), `exp`, `iat`, opcjonalnie `roles`.
- Generacja: na `POST /api/auth/login`; czas życia krótki (np. 15m). Refresh token dłuższy (DB-backed, rotowany).
- Weryfikacja: `JwtAuthenticator` + `UserProvider` (Doctrine). Brak użycia JWT w UI/HTML.

### Autoryzacja domenowa
- Votery (np. `NoteVoter`): sprawdzają owner/współedytor; wykorzystywane w kontrolerach UI i API.
- Role hierarchy minimalna (`ROLE_USER` domyślna).

### Dodatkowe mechanizmy bezpieczeństwa
- CSRF w formularzach HTML (login, register, forgot, reset, logout).
- HSTS/secure cookies w prod, `SameSite=Lax`, `HttpOnly`.
- Audit/logi: zdarzenia auth (successful/failed login, password reset, verify) logowane przez Monolog.

## 4. Kontrakty UI ↔ backend (streszczenie)
- Formularze HTML wysyłają standardowe POST (nie XHR) z CSRF, otrzymują redirecty + flash messages; HTMX używany tylko do fragmentów z błędami w miejscu.
- API przyjmuje/zwra­ca JSON; kody: 200/201 na sukces, 204 na logout, 400 na walidację, 401 na auth/verify fail, 429 na rate-limit, 500 na błąd serwera.
- Brak CORS wymaganego dla UI (ten sam origin). API może wymagać CORS wyłącznie dla klientów zewnętrznych (out of MVP).

## 5. Testowalność i nieinwazyjność
- Architektura nie zmienia istniejących widoków notatek; dodaje wyłącznie nowe ścieżki auth i sprawdzenie `isVerified`.
- Weryfikacja: e2e ścieżki register→verify→login, login fail (bad credentials), login fail (unverified), forgot/reset flow, JWT login/refresh, logout (sesja i refresh).

