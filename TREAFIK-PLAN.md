## Plan wdrożenia uproszczonego reverse proxy z Traefik (Compose)

Cel: zastąpić ręczne TLS/nginx prostą warstwą Traefik działającą w tym samym `docker compose`, terminującą TLS (Let's Encrypt) i routującą do usługi `app`.

### Założenia
- Domeny: `app.snipnote.pl` (HTTP→HTTPS redirect, cert LE).  
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
   - Router `snipnote-secure`: rule `Host(\`app.snipnote.com\`)`, entrypoint `websecure`, tls resolver `letsencrypt`.
   - Service port `80` (target).  
   - Middleware redirect HTTP→HTTPS obsłuży Traefik globalnie; ewentualnie rate-limit na `/api/auth/*` można dodać jako osobny middleware.

4) Usuń/wyłącz nginx z prod Compose (jeśli istnieje); `app` łączy się z Traefik siecią `proxy`. Pozostaw PostgreSQL/redis bez zmian (sieć wewnętrzna).

5) Konfiguracja env dla Traefik:
   - `TRAEFIK_ACME_EMAIL=<cert-admin-email>`
   - `TRAEFIK_DOMAIN=app.snipnote.com` (użyte w labels/command)  
   - Opcjonalnie wprowadzić `.env.prod` na VPS z tymi wartościami.

6) DNS: skieruj `A/AAAA` domeny na adres VPS. Upewnij się, że porty 80/443 są otwarte w firewallu.

7) Uruchomienie:
   ```bash
   docker compose -f docker-compose.prod.yml up -d traefik app
   ```
   Sprawdź logi Traefika dla issuance LE.

8) Smoke test:
   - `curl -I https://app.snipnote.com` → 200/302, poprawny cert od Let's Encrypt.
   - Sprawdź dostęp do `/` i `/api/auth/login` (401/expected).

### Dalsze opcje
- Dodaj middleware security headers (HSTS, X-Content-Type-Options, Referrer-Policy).  
