## Raport bezpieczeństwa (Symfony 8 / PHP 8) – Snipnote

### Stan dostępu i autoryzacji
- Firewall `api` jest stateless i używa wyłącznie `JwtAuthenticator`; `access_control` wymaga `ROLE_USER` dla `^/api`, więc brak Bearer skutkuje 401 (test potwierdzony). Publiczne ścieżki `/api/public` wyłączone z zabezpieczeń.
- Firewall `main` obsługuje `form_login` (CSRF on) oraz `JwtAuthenticator`; `entry_point` wskazuje `form_login`, więc żądania UI dostają redirect 302 → login, natomiast API kończy 401 z JWT entrypointem.
- Kontrolery UI (`NotesPageController`) ręcznie sprawdzają `CurrentUser` i przekierowują niezalogowanych na `/login?redirect=...`.
- API `NoteController` wymaga `#[CurrentUser] User`; brak użytkownika kończy się `AccessDeniedException('Authentication required')` → 401. Operacje na notatkach wykonują się tylko dla uwierzytelnionych.
- Szablony dashboardu i formularzy używają CSRF dla logout; formularz tworzenia notatki działa na API (Bearer), nie na sesji.

### Uwagi i ryzyka
- Access token jest wstrzykiwany w Twig (`/notes/new`), zapisywany w `localStorage` i w ciasteczku JavaScript (bez HttpOnly/Secure/SameSite). Każde XSS pozwoli na kradzież tokenu i wykonywanie akcji na API. Zalecenie: serwować krótki access token w HttpOnly/SameSite=Lax cookie ustawianym po stronie serwera lub używać samej sesji dla UI.
- Brak CSRF dla wywołań API (POST/PATCH/DELETE) – zakładamy, że JWT chroni przed CSRF; wymaga utrzymania tokenu poza kontekstem przeglądarki lub w HttpOnly cookie + `SameSite=Lax`.
- Token jest generowany na GET `/notes/new` dla każdego zalogowanego; jeśli niepotrzebny, można ograniczyć generację do momentu faktycznego zapisu lub dodać krótsze TTL/Scope.
- Nie ma wymuszenia `isVerified` na poziomie UI kontroli dostępu; backend auth (`AuthService::login`) odrzuca nieweryfikowanych, ale warto dodać guard/voter na `main`, jeśli w przyszłości pojawi się inny mechanizm logowania.

### Rekomendowane poprawki
- Przenieść dostarczanie access tokenu do HttpOnly cookie ustawianego przez backend (np. endpoint `/api/auth/token` lub po logowaniu), z `SameSite=Lax` i `Secure` w prod; nie przechowywać w `localStorage`.
- Jeśli pozostajemy przy JS-cookies: minimalizować czas życia access tokenu i dodać Content Security Policy, by ograniczyć ryzyko XSS wykradania tokenów.
- Rozważyć dodanie prostego CSRF (np. nagłówek `X-CSRF-Token` powiązany z sesją) lub w pełni polegać na sesji dla UI (zamiast JWT) dla ścieżek `/notes/**`.
- Dodawać `expires`/`max-age` do JS-cookie, aby nie zostawał po zamknięciu przeglądarki; już czyszczony przy logout/login, ale session-only cookie byłoby bezpieczniejsze.

