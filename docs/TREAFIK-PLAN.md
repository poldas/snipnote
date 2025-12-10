## Plan wdrożenia uproszczonego reverse proxy z Traefik (Compose)

Cel: zastąpić ręczne TLS/nginx prostą warstwą Traefik działającą w tym samym `docker compose`, terminującą TLS (Let's Encrypt) i routującą do usługi `app`.

### Założenia
- Domeny: `snipnote.pl` (HTTP→HTTPS redirect, cert LE).  
- App słucha na porcie 80 w kontenerze (`app:80`).  
- Host wystawia tylko 80/443.  
- Certy przechowywane w wolumenie Traefika.

### Kroki implementacji
1) Dodaj sieć i wolumeny w `docker-compose.prod.yml` (lub nowym pliku override):
   - Sieć `proxy` (external lub tworzona w Compose) współdzielona z `app`.
   - Wolumen `traefik-letsencrypt` na `acme.json`.

2) Dodaj usługę `traefik`:
   - Image: `traefik:v3.1`.
   - Ports: `80:80`, `443:443`.
   - Command (lub labels/env) włączające providers.docker, entrypoints `web`/`websecure`, redirect web→websecure.
   - ACME TLS resolver z HTTP-01 na `web`; plik `acme.json` w wolumenie (chmod 600).
   - Optional: access log, security headers middleware (CSP/HSTS) do dodania później.

3) Otaguj usługę `app` labelami Traefika:
   - `traefik.enable=true`
   - Router `snipnote-secure`: rule `Host(\`snipnote.pl\`)`, entrypoint `websecure`, tls resolver `letsencrypt`.
   - Service port `80` (target).  
   - Middleware redirect HTTP→HTTPS obsłuży Traefik globalnie; ewentualnie rate-limit na `/api/auth/*` można dodać jako osobny middleware.

4) Usuń/wyłącz nginx z prod Compose (jeśli istnieje); `app` łączy się z Traefik siecią `proxy`. Pozostaw PostgreSQL/redis bez zmian (sieć wewnętrzna).

5) Konfiguracja env dla Traefik:
   - `TRAEFIK_ACME_EMAIL=<cert-admin-email>`
   - `TRAEFIK_DOMAIN=snipnote.pl` (użyte w labels/command)  
   - Opcjonalnie wprowadzić `.env.prod` na VPS z tymi wartościami.

6) DNS: skieruj `A/AAAA` domeny na adres VPS. Upewnij się, że porty 80/443 są otwarte w firewallu.

### Status prac (zrobione)
- Dodany `Dockerfile.prod` (Apache, opcache, healthcheck).
- Dodany `docker-compose.prod.yml` z Traefik v3.1, siecią `proxy`, wolumenem `traefik-letsencrypt`, Postgres bez otwartych portów, labelki hosta `snipnote.pl`.
- Dodany `env.prod.example` z wymaganymi zmiennymi (`TRAEFIK_DOMAIN=snipnote.pl`, `TRAEFIK_ACME_EMAIL=dany@dany.com`, sekrety JWT/APP/VERIFY, DB, APP_IMAGE+APP_IMAGE_TAG) oraz zawężonym `TRUSTED_PROXIES=172.16.0.0/12`.
- Dodany workflow `.github/workflows/build-prod-image.yml` do build & push obrazu na GHCR (`prod` + `sha-<short>`).

### Komendy (gdzie wykonać)
- **Build + push obrazu (CI lub lokalnie)** — lokalnie lub w Actions runnerze:
  ```bash
  docker buildx build --platform linux/amd64,linux/arm64 -t ghcr.io/poldas/snipnote:prod -f Dockerfile.prod . --push
  ```
- **Logowanie do GHCR (VPS lub lokalnie jeśli pull z prywatnego)**:
  ```bash
  echo "$PAT" | docker login ghcr.io -u <gh-username> --password-stdin
  ```
- **Deploy na VPS (z plikiem env.prod)** — na VPS, w katalogu repo:
  ```bash
  docker compose --env-file env.prod -f docker-compose.prod.yml pull
  docker compose --env-file env.prod -f docker-compose.prod.yml up -d traefik database app
  docker compose --env-file env.prod -f docker-compose.prod.yml logs -f traefik
  ```
- **Smoke testy (VPS lub z zewnątrz po DNS)**:
  ```bash
  curl -I https://snipnote.pl
  curl -I https://snipnote.pl/api/auth/login
  ```

### Instrukcja krok po kroku (prod na VPS)
1. DNS: ustaw A/AAAA `snipnote.pl` na IP VPS; otwórz porty 80/443 w firewallu.
2. Sekrety: skopiuj `env.prod.example` → `env.prod` na VPS, uzupełnij `APP_SECRET`, `JWT_SECRET`, `VERIFY_EMAIL_SECRET`, dane DB, `APP_IMAGE=ghcr.io/poldas/snipnote`, `APP_IMAGE_TAG=prod` (lub `sha-<short>` z GHCR), `TRAEFIK_ACME_EMAIL=dany@dany.com`.
3. (Jeśli prywatny obraz) `docker login ghcr.io` na VPS z PAT posiadającym packages:read.
4. Na VPS: `docker compose --env-file env.prod -f docker-compose.prod.yml pull`.
5. Uruchom: `docker compose --env-file env.prod -f docker-compose.prod.yml up -d traefik database app`.
6. Sprawdź logi Traefika pod kątem wydania certyfikatu LE: `docker compose --env-file env.prod -f docker-compose.prod.yml logs -f traefik`.
7. Smoke testy: `curl -I https://snipnote.pl` (cert LE, 200/302) oraz `curl -I https://snipnote.pl/api/auth/login` (401 oczekiwany).
8. Aktualizacje: po nowym buildzie w GHCR wykonaj na VPS `docker compose --env-file env.prod -f docker-compose.prod.yml pull && docker compose --env-file env.prod -f docker-compose.prod.yml up -d app`.

### Dalsze opcje
- Dodaj middleware security headers (HSTS, X-Content-Type-Options, Referrer-Policy).  
*** End Patch***
