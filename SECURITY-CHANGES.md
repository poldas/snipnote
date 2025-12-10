# Security Hardening Changes - Production

Data: 2025-12-10

## Podsumowanie zmian bezpieczeństwa

### ✅ Zmiany zastosowane

| # | Zmiana | Poziom | Wpływ na działanie | Może złamać? |
|---|--------|--------|-------------------|--------------|
| 1 | `forwardedHeaders.trustedIPs=172.16.0.0/12` | 🔴 WAŻNE | Identyczne | ❌ NIE |
| 2 | TLS 1.2 minimum | 🟡 ŚREDNIE | 99.9% przeglądarek OK | ❌ NIE |
| 3 | Security Headers (HSTS, XSS, etc) | 🟡 ŚREDNIE | Blokuje iframe | ⚠️ TAK (iframe) |
| 4 | `no-new-privileges` dla wszystkich | 🟢 NISKIE | Brak | ❌ NIE |
| 5 | Resource limits | 🟡 ŚREDNIE | Może wymagać tuning | ⚠️ TAK (jeśli za małe) |
| 6 | `read_only` filesystem | 🔴 WYSOKIE | Wymaga testów | ⚠️ TAK - **ODŁOŻONE** |

---

## Szczegóły zmian

### 1. Trusted Proxies (Traefik ↔ Symfony)

**Przed:**
```yaml
- "--entrypoints.websecure.forwardedHeaders.insecure=true"
```

**Po:**
```yaml
- "--entrypoints.websecure.forwardedHeaders.trustedIPs=172.16.0.0/12"
```

**Dlaczego:**
- "insecure=true" ufa WSZYSTKIM źródłom headerów X-Forwarded-*
- Attacker mógłby wysłać sfałszowany `X-Forwarded-Proto: https`
- Teraz Traefik ufa TYLKO sieci Docker (172.16-31.x.x)
- **Pasuje do Symfony `TRUSTED_PROXIES=172.16.0.0/12`**

**Testowanie:**
```bash
# Po wdrożeniu, sprawdź czy HTTPS linki działają
curl -I https://snipnote.pl/
# Powinno być 200 OK, a nie redirect loop
```

---

### 2. TLS 1.2 Minimum

**Dodano:**
```yaml
- "--entrypoints.websecure.http.tls.minVersion=VersionTLS12"
```

**Dlaczego:**
- TLS 1.0 i 1.1 mają znane luki (POODLE, BEAST)
- PCI DSS wymaga TLS 1.2+
- Wszystkie nowoczesne przeglądarki wspierają TLS 1.2 (od 2008)

**Kto może być zablokowany:**
- Internet Explorer 10 i starsze
- Android 4.3 i starsze
- **Praktycznie nikt w 2025 roku**

**Testowanie:**
```bash
# Test SSL labs (A+ rating expected)
https://www.ssllabs.com/ssltest/analyze.html?d=snipnote.pl
```

---

### 3. Security Headers

**Dodano middleware z headerami:**

```yaml
traefik.http.middlewares.security-headers.headers.stsSeconds: "31536000"           # HSTS 1 rok
traefik.http.middlewares.security-headers.headers.stsIncludeSubdomains: "true"     # HSTS dla subdomen
traefik.http.middlewares.security-headers.headers.stsPreload: "true"               # HSTS preload list
traefik.http.middlewares.security-headers.headers.frameDeny: "true"                # Blokuje iframe
traefik.http.middlewares.security-headers.headers.contentTypeNosniff: "true"       # Blokuje MIME sniffing
traefik.http.middlewares.security-headers.headers.browserXssFilter: "true"         # XSS protection
traefik.http.middlewares.security-headers.headers.referrerPolicy: "strict-origin-when-cross-origin"
```

**Co każdy header chroni:**

| Header | Chroni przed | Wpływ |
|--------|--------------|-------|
| HSTS | SSL stripping attacks | ✅ Wymusza HTTPS przez rok |
| X-Frame-Options | Clickjacking | ⚠️ Nie można embedować w iframe |
| X-Content-Type-Options | MIME type attacks | ✅ Brak wpływu |
| X-XSS-Protection | Cross-site scripting | ✅ Legacy protection |
| Referrer-Policy | Privacy leaks | ✅ Lepszy privacy |

**⚠️ UWAGA - X-Frame-Options: DENY:**
- Nie możesz teraz embedować snipnote.pl w iframe
- Jeśli potrzebujesz iframe, zmień na:
  ```yaml
  headers.customFrameOptionsValue: "SAMEORIGIN"
  ```

**Testowanie:**
```bash
# Sprawdź headery
curl -I https://snipnote.pl/ | grep -E "Strict-Transport|X-Frame|X-Content"

# Test security headers
https://securityheaders.com/?q=snipnote.pl
```

---

### 4. No New Privileges

**Dodano dla wszystkich serwisów:**
```yaml
security_opt:
  - no-new-privileges:true
```

**Dlaczego:**
- Blokuje proces przed uzyskaniem nowych uprawnień (setuid/setgid)
- Jeśli attacker zhackuje kontener, nie może eskalować do root
- **Linux kernel feature**, nie wymaga żadnych zmian w aplikacji

**Wpływ na działanie:**
- ✅ ZERO - aplikacja nie używa setuid/setgid
- ✅ Apache i Postgres działają normalnie

**Testowanie:**
```bash
# Sprawdź czy działa po wdrożeniu
docker inspect snipnote-app-1 | grep NoNewPrivileges
# Powinno być: "NoNewPrivileges": true
```

---

### 5. Resource Limits

**Dodano dla wszystkich serwisów:**

```yaml
# App (Symfony + Apache)
limits: 512M memory, 1.0 CPU
reservations: 256M memory, 0.5 CPU

# Database (PostgreSQL)
limits: 512M memory, 1.0 CPU
reservations: 256M memory, 0.25 CPU

# Traefik (Proxy)
limits: 256M memory, 0.5 CPU
reservations: 128M memory, 0.25 CPU
```

**Dlaczego:**
- Zapobiega jednemu kontenerowi od zużycia wszystkich zasobów serwera
- Chroni przed DoS (Denial of Service)
- Chroni przed memory leaks

**⚠️ UWAGA - Może wymagać tuning:**
- Symfony cache warmup może potrzebować 300-400MB
- Pod dużym obciążeniem może potrzebować więcej
- Jeśli przekroczy limit → Docker restartuje kontener (OOM)

**Monitoring:**
```bash
# Sprawdź zużycie zasobów
docker stats

# Output:
# NAME              CPU %   MEM USAGE / LIMIT   MEM %
# snipnote-app-1    5%      180MB / 512MB       35%
# snipnote-db-1     2%      120MB / 512MB       23%
# snipnote-traefik  1%      50MB / 256MB        19%
```

**Jak zwiększyć limity (jeśli potrzeba):**
```yaml
# W docker-compose.prod.yml
deploy:
  resources:
    limits:
      memory: 1G    # Zwiększ jeśli app używa > 80%
```

**Testowanie:**
```bash
# Po wdrożeniu, monitoruj przez 24h
watch -n 5 'docker stats --no-stream'

# Jeśli app często jest blisko limitu, zwiększ
```

---

## Zmiany które NIE zostały zastosowane (wyjaśnienie)

### ❌ `USER www-data` w Dockerfile

**Dlaczego NIE:**
- Entrypoint wykonuje `cache:clear` i `doctrine:migrations:migrate` które potrzebują root
- Apache image jest zaprojektowany do działania jako root
- Apache **automatycznie** przełącza workery na `www-data` dla requestów HTTP
- **To jest standardowa praktyka dla Apache w Docker**

**Co już jest bezpieczne:**
- PHP workery działają jako `www-data`
- Pliki aplikacji są owned przez `www-data` (linia 52 w Dockerfile)
- Tylko master proces Apache działa jako root (potrzebny do bindowania portu 80)

---

### ⚠️ `read_only: true` filesystem (ODŁOŻONE NA PÓŹNIEJ)

**Dlaczego ODŁOŻONE:**
- Wymaga precyzyjnej konfiguracji tmpfs i volumes
- Na produkcji wystąpił błąd: "Unable to write in /var/www/html/var/cache/prod"
- Wymaga dokładnych testów lokalnych przed wdrożeniem
- Za dużo zmian naraz może złamać aplikację

**Konfiguracja która jest potrzebna:**
```yaml
read_only: true
tmpfs:
  - /tmp:size=100M,mode=1777
  - /var/run:size=10M,mode=755
  - /var/log/apache2:size=50M,mode=755
volumes:
  - app_cache:/var/www/html/var
```

**Oraz volume w sekcji volumes:**
```yaml
volumes:
  database_data:
  traefik-letsencrypt:
  app_cache:  # ← Dodać
```

**Plan wdrożenia:**
1. ✅ Naprawiono entrypoint.sh (dodano mkdir i chown)
2. ⏳ Przetestować lokalnie z read_only
3. ⏳ Deploy na staging (jeśli dostępny)
4. ⏳ Deploy na produkcję w godzinach low traffic

**Status:** Zakomentowane w docker-compose.prod.yml jako TODO

---

## Wdrożenie na produkcję

### Krok 1: Backup (ZAWSZE przed zmianami!)

```bash
# Backup bazy danych
docker exec snipnote-database-1 pg_dump -U app app > backup-$(date +%Y%m%d).sql

# Backup wolumenów
docker run --rm -v snipnote_database_data:/data -v $(pwd):/backup alpine tar czf /backup/database-backup-$(date +%Y%m%d).tar.gz /data
```

### Krok 2: Commituj zmiany

```bash
git add docker-compose.prod.yml Dockerfile.prod SECURITY-CHANGES.md
git commit -m "Security hardening: trusted proxies, TLS 1.2, security headers, resource limits"
git push origin deploy
```

### Krok 3: Deploy na VPS

```bash
# Na VPS
cd ~/snipnote
git pull origin deploy

# Użyj update-all.sh bo zmienialiśmy docker-compose.prod.yml
./bin/update-all.sh
```

### Krok 4: Weryfikacja

```bash
# 1. Sprawdź czy wszystko działa
curl -I https://snipnote.pl/
# Oczekiwane: HTTP/2 200

# 2. Sprawdź security headers
curl -I https://snipnote.pl/ | grep -E "Strict-Transport|X-Frame"
# Oczekiwane: powinny być obecne

# 3. Monitoruj zasoby
docker stats --no-stream

# 4. Sprawdź logi
docker logs snipnote-app-1 --tail 50
docker logs snipnote-traefik-1 --tail 50
```

### Krok 5: Monitorowanie (48h)

```bash
# Co 5 minut przez 48h
watch -n 300 'docker stats --no-stream && echo "---" && docker ps'

# Jeśli widzisz OOM (Out of Memory) kills:
# - Zwiększ memory limits w docker-compose.prod.yml
# - Redeploy z ./bin/update-all.sh
```

---

## Rollback (jeśli coś pójdzie nie tak)

### Szybki rollback do poprzedniej wersji:

```bash
# 1. Wróć do poprzedniego commita
git revert HEAD

# 2. Deploy
./bin/update-all.sh

# 3. Restore backup bazy (jeśli potrzeba)
cat backup-20251210.sql | docker exec -i snipnote-database-1 psql -U app -d app
```

---

## Dalsze usprawnienia (przyszłość)

### 🔴 Wysokie priority:
1. **Rate limiting w Traefik** - ochrona przed brute force
2. **Fail2ban** - blokowanie złośliwych IP
3. **Automatyczne backupy** - codzienne backupy bazy

### 🟡 Średnie priority:
4. **Monitoring (Prometheus + Grafana)** - metryki w czasie rzeczywistym
5. **Log aggregation (ELK/Loki)** - centralne logi
6. **Secrets management (Vault)** - bezpieczne przechowywanie sekretów

### 🟢 Niskie priority:
7. **WAF (Web Application Firewall)** - zaawansowana ochrona
8. **Container scanning** - skanowanie obrazów pod kątem CVE
9. **SELinux/AppArmor** - dodatkowa izolacja

---

## Checklista przed deploy

- [ ] Backup bazy danych wykonany
- [ ] Backup wolumenów wykonany
- [ ] Zmiany commitnięte do git
- [ ] Plan rollback przygotowany
- [ ] Testy lokalne przeszły (jeśli masz staging)
- [ ] Monitoring przygotowany (docker stats)
- [ ] Okno maintenance zaplanowane (opcjonalne, ~5min downtime)

---

## Kontakt / Pytania

Jeśli masz pytania o którąkolwiek zmianę:
1. Sprawdź ten dokument
2. Testuj lokalnie najpierw
3. Monitoruj przez 48h po deploy
4. Rollback jeśli coś nie działa

**Pamiętaj:** Bezpieczeństwo to proces, nie cel. Regularnie update'uj obrazy Docker i monitoruj logi.

