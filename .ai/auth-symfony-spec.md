## Moduł autentykacji (Symfony 8 + JWT) — specyfikacja architektury

Zakres: US-010 (rejestracja), US-011 (logowanie), US-012 (wylogowanie), US-015 (odświeżenie tokenu) + odzyskiwanie hasła. Wymagania muszą współgrać z istniejącą aplikacją: anonimowi mają dostęp wyłącznie do publicznego widoku notatki i publicznego katalogu użytkownika; wszystkie pozostałe widoki/akcje wymagają aktywnej sesji użytkownika.

### 1. Architektura interfejsu użytkownika (Twig + HTMX + Tailwind)
- **Layouty**:
  - `templates/auth/base_auth.html.twig`: lekki layout formularzy auth (brak topbaru notatek), CTA powrotu do landing page. Zawiera slot na komunikaty walidacji i globalne flash-e.
  - `templates/base.html.twig` (istniejący główny layout): rozszerzony o topbar sekcji użytkownika, gdy user zalogowany (email, przycisk `Wyloguj`, link do dashboardu). Dla anonimów topbar bez akcji na notatkach.
- **Strony/komponenty**:
  - `/auth/register`: formularz `email`, `hasło`, `potwierdzenie hasła` (opcjonalnie). Po sukcesie: automatyczne zalogowanie i redirect na dashboard.
  - `/auth/login`: formularz `email`, `hasło`, checkbox „Zapamiętaj mnie” (mapowany na dłuższy refresh token). Link do „Zapomniałeś hasła?”.
  - `/auth/forgot-password`: formularz z polem `email`; po sukcesie komunikat o wysłaniu linku (bez ujawniania istnienia konta).
  - `/auth/reset-password/{token}`: formularz `nowe hasło`, `powtórz hasło`.
  - Komponent błędów formularza (partial Twig) reużywalny między stronami auth i formularzami w aplikacji.
  - Moduł `topbar_user` (HTMX fragment): renderuje stan zalogowania (email + `Wyloguj`) lub CTA do logowania/rejestracji.
- **Scenariusze UI**:
  - Nie zalogowany użytkownik próbujący wejść na widok prywatny (dashboard, edycja notatki) jest przekierowany na `/auth/login` z flash `Musisz się zalogować`.
  - Po rejestracji/logowaniu: redirect na dashboard; jeśli istniał parametr `redirectTo` (np. wejście na chroniony URL) — powrót tam po zalogowaniu.
  - Błędy walidacji pokazane inline pod polami + lista zbiorcza na górze formularza.
  - Komunikaty sukcesu: flash na górze (`Zarejestrowano i zalogowano`, `Link do resetu hasła wysłany`, `Hasło zmienione`).
- **Walidacje UI** (wczesne, nie zastępują backendu):
  - Email: podstawowy regex, trimming.
  - Hasło: min 8 znaków, max 72 (bcrypt limit), wskazanie jeśli puste.
  - Potwierdzenie hasła: zgodność pól.
  - Pola wymagane mają komunikaty w języku polskim, zwięzłe („Pole jest wymagane”, „Niepoprawny email”, „Hasło musi mieć min. 8 znaków”).

### 2. Logika backendowa (Symfony 8, kontenery + Doctrine)
- **Encje / modele**:
  - `User` (id, email unikalny, password hash, createdAt, updatedAt, roles). Hash przez native password hasher (argon2id/bcrypt).
  - `RefreshToken` (gesdinet/jwt-refresh-token-bundle) z powiązaniem do `User`, datą ważności, token string, revokedAt.
  - `PasswordResetRequest` (custom): id, user, token (losowy, jednorazowy), expiresAt, usedAt.
- **Form/DTO do walidacji**:
  - `RegisterRequest` (email, password, confirmPassword).
  - `LoginRequest` (email, password, rememberMe).
  - `ForgotPasswordRequest` (email).
  - `ResetPasswordRequest` (token, password, confirmPassword).
  - `RefreshTokenRequest` (refresh_token z ciasteczka).
- **Kontrolery / endpointy (JSON + Twig)**:
  - `POST /auth/register` (HTML form + JSON HTMX): tworzy użytkownika, loguje, zwraca 302 do dashboardu lub 200 JSON success.
  - `POST /auth/login`: weryfikacja, wydanie pary tokenów, 302/200 analogicznie.
  - `POST /auth/logout`: unieważnia refresh token, usuwa cookies, redirect do landing page.
  - `POST /auth/token/refresh`: wymienia refresh na nowy access + refresh (rotacja), 200 JSON/HTMX fragment, 401 przy braku/invalid.
  - `POST /auth/password/forgot`: zawsze 200 z komunikatem; jeśli user istnieje, zapisuje `PasswordResetRequest` i wysyła mail.
  - `POST /auth/password/reset`: przyjmuje token, ustawia nowe hasło, unieważnia istniejące refresh tokeny użytkownika, auto-logowanie opcjonalne (tu: tak, zgodnie z UX szybkości).
- **Walidacja backend** (Symfony Validator):
  - Email: `NotBlank`, `Email`, `UniqueEntity(User.email)`.
  - Hasło: `NotBlank`, `Length(min=8, max=72)`.
  - Confirm password: `Expression` equality.
  - Reset token: istnienie + niewygasły + nieużyty.
- **Obsługa błędów**:
  - 400 dla walidacji, z mapą pól; 401 dla błędnych poświadczeń / nieważnego refresh tokenu; 403 dla zablokowanych zasobów; 404 dla tokenów resetu nieistniejących.
  - Globalny listener HTTP -> flash przy HTML, JSON body przy HTMX/JS.

### 3. System autentykacji (Symfony Security + JWT)
- **Tokeny i cookies**:
  - Access token (JWT, krótki TTL, np. 15 min) w `Authorization: Bearer` dla API i w `HttpOnly, SameSite=Lax` cookie `access_token` dla formularzy HTML.
  - Refresh token w `HttpOnly, SameSite=Lax` cookie `refresh_token`, dłuższy TTL (np. 7–30 dni), rotowany przy każdym odświeżeniu, zapisany w bazie (gesdinet).
  - Przy `rememberMe=true` ustaw dłuższy TTL refresh tokenu.
- **Firewalle / access control**:
  - `main` firewall z `jwt` authenticator (lexik) + `json_login`/custom login form authenticator.
  - Access control: `^/(auth|_profiler)` dostęp publiczny; `^/notes/public` i `^/users/{uuid}/catalog` publiczne; wszystko inne `IS_AUTHENTICATED_FULLY`.
  - Voter dla reguł notatek (już istniejący lub do uzupełnienia) korzysta z identyfikacji użytkownika z JWT.
- **Rejestracja**:
  - Serwis `UserRegistrationService`: tworzy usera, hash hasło, zapis, wywołuje `TokenIssuer` do wygenerowania pary tokenów + ustawienie cookies w odpowiedzi.
- **Logowanie**:
  - Serwis `LoginService`: waliduje poświadczenia, w razie sukcesu wydaje access/refresh; w razie błędu rzuca `BadCredentialsException`.
- **Odświeżenie tokenu**:
  - Endpoint używa `RefreshTokenManager` (gesdinet) do walidacji i rotacji; stare refresh tokeny są unieważniane; access token generowany przez `JWTTokenManagerInterface`.
- **Wylogowanie**:
  - Usunięcie cookies + unieważnienie refresh tokenu w bazie; access token pozostaje nieważny po TTL (brak blacklisty access tokenów w MVP).
- **Reset hasła**:
  - `PasswordResetService`: tworzy żądanie, generuje jednorazowy token (losowy 32-64B base64url), zapisuje expiresAt (np. 1h), wysyła mail z linkiem `/auth/reset-password/{token}`.
  - Zmiana hasła unieważnia wszystkie refresh tokeny użytkownika.

### 4. Integracja z UI i HTMX
- Formularze Twig działają klasycznie (POST + redirect). Dodatkowo atrybut `hx-post` dla częściowych odpowiedzi JSON/HTML fragment (np. inline błędy) bez pełnego przeładowania.
- Po odświeżeniu tokenu w tle (np. okresowy request HTMX) UI nie musi znać tokenu — cookies są `HttpOnly`.
- Błędy 401 na wywołaniach HTMX w panelu -> handler JS przeładowuje na `/auth/login?redirectTo=...`.

### 5. Bezpieczeństwo i polityki
- Hash hasła: `password_hash` (argon2id preferred), pepper opcjonalny przez ENV.
- Rate limiting: na login, register, forgot-password i token/refresh (np. Symfony RateLimiter).
- Brak ujawniania istnienia konta: endpoint forgot-password zawsze 200.
- SameSite=Lax + `Secure` na cookies w prod; CSRF na formularzach HTML (`_token`); CORS tylko jeśli potrzeba API publicznego (domyślnie wyłączone).
- Logi bezpieczeństwa (Monolog kanał `security`) dla prób logowania, resetów, odświeżeń.

### 6. Testy (poziom specyfikacji)
- Testy jednostkowe: serwisy `UserRegistrationService`, `LoginService`, `PasswordResetService`, `TokenIssuer`.
- Testy integracyjne: scenariusze end-to-end rejestracji, logowania, wylogowania, odświeżenia, resetu hasła; przypadki brzegowe (duplikat email, zły token resetu, wygasły refresh token).
- Testy funkcjonalne UI (Symfony Panther lub BrowserKit) dla formularzy, przekierowań i komunikatów błędów.

### 7. Komponenty/kontrakty do implementacji
- Serwisy: `UserRegistrationService`, `LoginService`, `TokenIssuer`, `RefreshTokenRotator`, `PasswordResetService`, `LogoutService`.
- Kontrolery: `AuthController` (register/login/logout), `TokenController` (refresh), `PasswordResetController` (request/reset).
- Twig partiale: `components/form_errors.html.twig`, `components/flash.html.twig`, `components/topbar_user.html.twig`.
- Zdarzenia/domenowe: opcjonalnie `UserRegistered`, `PasswordResetRequested`, `PasswordResetCompleted` dla logowania audytu/monitoringu.

