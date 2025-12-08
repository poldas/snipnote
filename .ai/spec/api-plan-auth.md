# API Endpoint Implementation Plan: Auth (register, login, refresh, logout, verify, reset)

## 1. Przegląd punktu końcowego
Zestaw endpointów JSON pod `/api/auth/*` obsługujących rejestrację, logowanie, odświeżanie i unieważnianie tokenów JWT, wysyłkę i obsługę linku weryfikacyjnego e-mail oraz reset hasła. UI korzysta z sesji; ten plan dotyczy kanału API z JWT.

## 2. Szczegóły żądania
- Metoda/URL:
  - POST `/api/auth/register`
  - POST `/api/auth/login`
  - POST `/api/auth/refresh`
  - POST `/api/auth/logout`
  - POST `/api/auth/forgot-password`
  - POST `/api/auth/reset-password`
  - POST `/api/auth/verify/resend`
  - GET  `/api/auth/verify/email` (proxy do VerifyEmailBundle; JSON rezultat)
- Parametry:
  - Wymagane:
    - register: `email`, `password`
    - login: `email`, `password`
    - refresh: `refresh_token`
    - logout: `refresh_token`
    - forgot-password: `email`
    - reset-password: `token`, `new_password`
    - verify/resend: `email`
    - verify/email: `signature`, `expires`, `id/email` (wg VerifyEmailBundle)
  - Opcjonalne:
    - register: `accept_terms` (jeśli prawnie wymagane)
    - login: `remember_me`, `device_fingerprint`
- Request Body (JSON):
  - register/login/refresh/logout/forgot/reset/resend: JSON zgodny z powyższym (camelCase).
  - verify/email: query params (GET).

## 3. Wykorzystywane typy
- Request DTOs: `RegisterRequestDTO`, `LoginRequestDTO`, `RefreshTokenRequestDTO`, `LogoutRequestDTO`, `ForgotPasswordRequestDTO`, `ResetPasswordRequestDTO`, `ResendVerifyRequestDTO`.
- Response DTOs: `AuthTokensDTO { access_token, refresh_token, expires_in }`, `MessageResponseDTO { message }`, `UserPublicDTO { uuid, email, isVerified, roles? }`.
- Commands/Services inputs: `CreateUserCommand`, `IssueTokensCommand`, `InvalidateRefreshCommand`, `SendVerifyEmailCommand`, `SendResetEmailCommand`, `ResetPasswordCommand`, `VerifyEmailCommand`.

## 3. Szczegóły odpowiedzi
- Sukces:
  - register: 201 `{ user, tokens }` (auto-login) + komunikat „verify email sent”.
  - login: 200 `{ tokens }`.
  - refresh: 200 `{ tokens }` (rotated).
  - logout: 204 no body.
  - forgot-password: 200 `{ message: generic }`.
  - reset-password: 200 `{ message, tokens }` (opcjonalnie auto-login tokens).
  - verify/resend: 200 `{ message }`.
  - verify/email: 200 `{ message, status: "verified" }` lub 400 `{ error }` jeśli sygnatura wygasła/nieważna.
- Błędy:
  - 400 walidacja/format tokenu.
  - 401 złe dane logowania, konto nieweryfikowane, refresh nieważny.
  - 429 rate limit.
  - 500 nieoczekiwane błędy.

## 4. Przepływ danych
- register: DTO → walidacja → `RegistrationService` (lowercase email, hash, persist user) → `EmailVerificationService` (signed link) → `AuthService::issueTokens` → response.
- login: DTO → rate limit → `AuthService::authenticate(email, password)` → check `isVerified` → `AuthService::issueTokens` → response.
- refresh: DTO → `RefreshTokenService::rotate(refresh)` → new access/refresh → response.
- logout: DTO → `RefreshTokenService::revoke(refresh)` → 204.
- forgot-password: DTO → generic 200 → `PasswordResetService::issueToken(email)` (if exists) → send mail.
- reset-password: DTO → validate token → set hash via `PasswordResetService::reset(token, newPassword)` → invalidate token → `AuthService::issueTokens`.
- verify/resend: DTO → if user exists & !verified → `EmailVerificationService::send(user)`, generic message.
- verify/email: query → `EmailVerificationService::handleSignedUrl()` → mark verified → response.

## 5. Względy bezpieczeństwa
- JWT HS256 via lexik/jwt; short-lived access (e.g., 15m); DB-backed refresh (rotated, single-use).
- `isVerified` enforced on login and refresh.
- Rate limiting: `/api/auth/login`, `/api/auth/forgot-password`, `/api/auth/verify/resend`.
- Normalizacja email do lowercase; unikalność via DB index `unique(lower(email))`.
- Password hashing: Symfony default (argon2id/bcrypt).
- Generic responses on forgot/resend to avoid user enumeration.
- Verify link signature + expiry (VerifyEmailBundle).
- HTTPS, secure/HttpOnly cookies only for UI; API tokens in Authorization header.
- CORS only if needed for external clients.

## 6. Obsługa błędów
- Walidacja DTO → 400 `{ field: [messages] }`.
- Auth failure / unverified / bad refresh → 401 `{ error: "invalid_credentials" }`.
- Rate limit → 429 `{ error: "too_many_requests" }`.
- Invalid/expired verify or reset token → 400/401 generic `{ error: "invalid_token" }`.
- Mail send failure → 500 with log; response generic without leaking details.
- Logging: Monolog channel `auth` for failures/suspicious attempts; audit optional.

## 7. Rozważania dotyczące wydajności
- DB indices: `lower(email)` unique, refresh token lookup indexed.
- Use lightweight DTO hydration; avoid N+1 (only user lookup).
- Mail sending async/queue if available; otherwise synchronous but measured.
- Token signing is CPU-light; ensure cache for rate limiter (Redis) nice to have.

## 8. Etapy wdrożenia
1. Dodać/zweryfikować konfigurację security firewalls (`api` stateless, JWT).
2. Utworzyć DTOs + walidatory (Email, Length, NotBlank).
3. Zaimplementować serwisy: RegistrationService, AuthService, RefreshTokenService, PasswordResetService, EmailVerificationService.
4. Dodać repo/encję RefreshToken (jeśli używana) + migrację DB.
5. Zaimplementować kontrolery `/api/auth/*` (cienkie, delegują do serwisów).
6. Podłączyć VerifyEmailBundle i ResetPasswordBundle do serwisów (re-use mail templates).
7. Dodać rate limiting na login/forgot/resend (na samy końcu, nice to have).
8. Testy: unit (serwisy).
9. Lint/QA: php-cs-fixer, phpstan lvl 5