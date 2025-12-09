## Plan wdrożenia na VPS (Docker)

### 1) Przygotowanie VPS
- Zaktualizuj system: `apt update && apt upgrade -y`.
- Zainstaluj podstawy: `apt install -y curl git ufw ca-certificates gnupg`.
- Włącz podstawowy firewall (przykład UFW): `ufw allow 22/tcp`, `ufw allow 80,443/tcp`, `ufw enable`.
- Utwórz użytkownika nie-root do pracy i dodaj do `docker` group.

### 2) Docker + Compose plugin
- Jeśli Docker już jest, upewnij się, że ma Compose plugin (`docker compose version`). W razie braku zainstaluj zgodnie z dokumentacją Docker dla dystrybucji (pakiet `docker-compose-plugin` lub binarka Compose v2).

### 3) Struktura katalogu na serwerze
- `/opt/snipnote/` – repo/prod config.
- `/opt/snipnote/.env.prod` – zmienne środowiskowe (poza repo).
- `/opt/snipnote/secrets/` – klucze JWT, itp. (własność `root`, chmod 600).
- `/opt/snipnote/data/` – wolumeny (PostgreSQL, ewentualnie redis).

### 4) Konfiguracja zmiennych i sekretów
- Utwórz `.env.prod` (nie commituj):
  - `APP_ENV=prod`
  - `APP_DEBUG=0`
  - `APP_SECRET=...`
  - `DATABASE_URL=postgresql://user:pass@postgres:5432/snipnote?serverVersion=16&charset=utf8`
  - `TRUSTED_PROXIES=172.16.0.0/12` (sieć docker)
  - `TRUSTED_HOSTS=twoja-domena.pl`
- Wygeneruj klucze JWT dla lexik: `php bin/console lexik:jwt:generate-keypair --skip-if-exists --no-interaction` (lokalnie) i skopiuj do `/opt/snipnote/secrets/jwt/` albo generuj na VPS.
- Refresh token secret trzymaj jako plik/zmienna w `secrets/`.

### 5) Build i rejestr obrazów
- Skonfiguruj w repo `Dockerfile` multi-stage (builder → php-fpm runtime z opcache).
- Dodaj `docker-compose.prod.yml` z usługami: `nginx` (lub Traefik), `app` (php-fpm), `postgres`, opcjonalnie `redis`.
- Pipeline lokalnie lub w CI:
  - `composer install --no-dev --optimize-autoloader`
  - Build assetów (Tailwind) jeśli potrzebne.
  - `docker build -t ghcr.io/<org>/snipnote:<tag> .`
  - Push do registry (np. GHCR) i zaloguj się na VPS `echo TOKEN | docker login ghcr.io -u USER --password-stdin`.

### 6) Reverse proxy i TLS
- Najprościej: Traefik lub Caddy w Compose (automatyczne Let’s Encrypt). Alternatywnie Nginx + certbot/lego.
- Wystaw tylko porty 80/443 na host, reszta usług w sieci wewnętrznej.
- Dodaj nagłówki security: HSTS, CSP (co najmniej `default-src 'self'` + potrzebne źródła), `X-Frame-Options DENY`, `X-Content-Type-Options nosniff`, `Referrer-Policy strict-origin-when-cross-origin`.
- Ustaw limit rozmiaru uploadu i rate limit dla endpointów auth.

### 7) Uruchomienie na VPS (bez CI/CD)
- Sklonuj repo do `/opt/snipnote`.
- Umieść `.env.prod` i katalog `secrets/` (właściwe uprawnienia).
- `docker compose -f docker-compose.prod.yml pull` (jeśli używasz zewn. registry) lub `docker compose -f docker-compose.prod.yml build`.
- `docker compose -f docker-compose.prod.yml up -d`.
- Wykonaj migracje: `docker compose -f docker-compose.prod.yml exec app php bin/console doctrine:migrations:migrate --no-interaction`.
- Warmup cache: `docker compose -f docker-compose.prod.yml exec app php bin/console cache:warmup`.

### 8) Backup i utrzymanie
- Backup DB: cron na host lub sidecar z `pg_dump` do zaszyfrowanego storage (np. `pg_dump | age | rclone`).
- Rotacja logów: forwarduj do loki/vector lub przynajmniej logrotate na host.
- Aktualizacje: `docker compose pull && docker compose up -d` po nowym tagu; okresowo `apt upgrade`.

### 9) Monitoring i healthcheck
- Dodaj endpoint health (np. `/_health`) i skonfiguruj `HEALTHCHECK` w Compose dla `app` i `nginx/traefik`.
- Minimum: `docker ps`, `docker logs`. Lepsze: cAdvisor + Node Exporter + Prometheus + Grafana; alerty (CPU/RAM/disk, HTTP 5xx).

### 10) Testy po wdrożeniu
- Smoke: `curl -I https://twoja-domena.pl` (200/301), sprawdź TLS i HSTS.
- Sprawdź logowanie, generację/odświeżanie JWT, zapis/odczyt notatek.
- Playwright E2E (jeśli dostępne) uruchomione na nowym środowisku.

### 11) Opcjonalne uproszczenie (Caddy/Traefik)
- Jeśli chcesz minimalnej konfiguracji: użyj Traefik/Caddy w Compose do TLS i routing; usuń ręczne certy. Wtedy w `nginx` nie potrzeba custom config, Traefik/Caddy wystawia 80/443 i przekazuje do `app`.

