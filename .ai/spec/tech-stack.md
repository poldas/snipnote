### Tech stack / środowisko

#### Backend
- **PHP 8.4+** (Typowanie ścisłe, Promoted Properties, Readonly Classes).
- **Symfony 8.0** (Attributes dla routingu, DI, Doctrine mapping, Security).
- **Doctrine ORM 3.5** (Doctrine DBAL 4.x, doctrine/migrations).
- **Komponenty**: Asset Mapper, Stimulus, HTML Sanitizer, Rate Limiter.
- **Scaﬀolding**: symfony/maker-bundle.

#### Frontend
- **Twig** (Silnik szablonów).
- **Symfony UX Turbo & HTMX 2+** (Interaktywność bez pełnego przeładowania).
- **Tailwind CSS 3.4** (Styling utility-first, build przez tailwindcss CLI).
- **esbuild** (Minifikacja JS).
- **Asset Management**: Symfony Asset Mapper (brak Webpack/Vite w ścieżce renderowania).

#### Autoryzacja & Bezpieczeństwo
- **Symfony Security**: Passport/Authenticator system.
- **Baza Danych**: PostgreSQL (Encje User, sesje).
- **Hashing**: Argon2id (native password hasher).
- **API Security**: JWT (LexikJWT) + Refresh Tokens (Gesdinet).
- **Kontrola dostępu**: Role Hierarchy + Voters (reguły domenowe).

#### API / Komunikacja
- **Standard**: Klasyczne kontrolery Symfony zwracające HTML (HTMX) lub JSON (API).
- **Walidacja**: Strongly typed DTOs + Symfony Validator.

#### Docker / Infrastruktura
- **App**: `thecodingmachine/php:8.4.3-v4-apache` (PHP 8.4 + Apache).
- **DB**: `postgres:16-alpine`.
- **Narzędzia**: Mailpit (SMTP/Webmail do testów), CLI ready (`bin/console`, `phpunit`).

#### Testy / Jakość
- **PHPUnit 12+** (Unit & Integration tests).
- **PHPStan Level 6** (Analiza statyczna ze ścisłym typowaniem tablic).
- **E2E Playwright** (Testy przeglądarkowe, TypeScript).
- **Linting**: PHP-CS-Fixer (PSR-12).
- **CI/CD**: GitHub Actions.
