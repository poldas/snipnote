# Production Deployment Guide

Kompletny przewodnik po wdrażaniu aplikacji na VPS produkcyjny.

---

## Skrypty deploymentu

### 1. `update-app.sh` - Szybki update aplikacji (ZALECANE)

**Kiedy używać:**
- Po wdrożeniu nowego kodu (GitHub Actions zbudował nowy obraz)
- Standardowy deploy w godzinach pracy
- **Minimalny downtime** (~5-10 sekund)

**Co robi:**
```bash
./bin/update-app.sh
```

1. ✅ Pokazuje obecny obraz aplikacji (SHA256 digest)
2. 📦 Pobiera najnowszy obraz z GitHub Container Registry
3. 📋 Pokazuje nowy obraz i datę stworzenia
4. 🔄 Restartuje TYLKO kontener aplikacji
5. ⏳ Czeka na healthcheck (max 60s)
6. ✅ Sprawdza status
7. 🧪 Testuje HTTPS

**Downtime:** ~5-10 sekund

**Zachowuje:**
- ✅ Traefik (bez restartu)
- ✅ Database (bez restartu)
- ✅ Sieci Docker (bez zmiany IP)
- ✅ Certyfikaty SSL
- ✅ Dane w bazie

**Idealny do:**
- Codzienne deploye
- Hotfixy
- Małe zmiany w kodzie

---

### 2. `update-all.sh` - Update wszystkich serwisów

**Kiedy używać:**
- Zmieniono `docker-compose.prod.yml`
- Update Traefika lub Postgres
- Chcesz zaktualizować wszystko

**Co robi:**
```bash
./bin/update-all.sh
```

1. 📋 Pokazuje obecne obrazy wszystkich serwisów
2. 📦 Pobiera najnowsze obrazy (app, traefik, postgres)
3. 📋 Pokazuje nowe obrazy z SHA256 digests
4. 🔄 Rolling update wszystkich kontenerów
5. ⏳ Czeka 30s na startup
6. ✅ Sprawdza status
7. 🧪 Testuje HTTPS

**Downtime:** ~10-20 sekund

**Zachowuje:**
- ✅ Sieci Docker (w większości przypadków)
- ✅ Certyfikaty SSL
- ✅ Dane w bazie

**Idealny do:**
- Update konfiguracji
- Aktualizacje zależności (Traefik, Postgres)
- Zmiana environment variables

---

### 3. `clean-restart.sh` - Pełne czyszczenie i restart

**Kiedy używać:**
- Problemy z siecią Docker (stare IP w cache)
- "no available server" który się nie naprawia
- Coś jest "dziwnie zepsute"
- **OSTATECZNOŚĆ** - nie używaj rutynowo!

**Co robi:**
```bash
./bin/clean-restart.sh
```

1. 🧹 Zatrzymuje wszystkie kontenery
2. 🗑️ Usuwa sieci Docker
3. 🗑️ Usuwa nieużywane obrazy
4. 📦 Pobiera najnowsze obrazy z GHCR
5. 📋 Pokazuje informacje o obrazie aplikacji
6. 🚀 Uruchamia wszystko od zera
7. ⏳ Czeka 30s na startup
8. ✅ Sprawdza status
9. 🧪 Testuje HTTPS
10. 📊 Sprawdza healthcheck

**Downtime:** ~30-60 sekund

**USUWA:**
- ❌ Sieci Docker (tworzy nowe z nowymi IP)
- ❌ Orphan containers
- ❌ Niezużywane obrazy

**ZACHOWUJE:**
- ✅ Certyfikaty SSL (volume: traefik-letsencrypt)
- ✅ Dane w bazie (volume: database_data)
- ✅ Cache aplikacji (volume: app_cache)

**Idealny do:**
- Troubleshooting problemów z siecią
- Po wielkich zmianach w architekturze
- Raz na miesiąc "refresh"

---

## Workflow standardowego deploy

### Po push do branch `deploy`:

```bash
# 1. GitHub Actions automatycznie buduje obraz
#    (sprawdź: https://github.com/poldas/snipnote/actions)

# 2. Na VPS, jako user z dostępem do Docker:
cd ~/snipnote

# 3. (Opcjonalne) Pull nowej konfiguracji z git
git pull origin deploy

# 4. Deploy nowego kodu
./bin/update-app.sh

# Output pokazuje:
# - Obecny obraz (SHA256)
# - Nowy obraz (SHA256)
# - Czy się zmienił
# - Status healthcheck
# - Test HTTPS

# 5. Jeśli wszystko OK, gotowe! ✅
```

---

## Weryfikacja czy deploy użył nowego obrazu

### Problem: Jak sprawdzić czy faktycznie deploy użył nowego kodu?

**Metoda 1: Porównaj SHA256 digest**

```bash
# Przed deploy:
docker inspect snipnote-app-1 --format='{{.Image}}' | cut -c1-19

# Po deploy:
docker inspect snipnote-app-1 --format='{{.Image}}' | cut -c1-19

# Powinny być różne!
```

**Metoda 2: Sprawdź Created date**

```bash
docker inspect snipnote-app-1 --format='Created: {{.Created}}'
# Output: Created: 2025-12-10T17:30:00Z

# Data powinna być świeża (kilka minut temu)
```

**Metoda 3: Sprawdź GitHub Container Registry**

```bash
# Jakie tagi są dostępne:
curl -s https://api.github.com/users/poldas/packages/container/snipnote/versions | jq '.[].metadata.container.tags'

# Output:
# ["prod", "sha-abc1234"]
```

**Metoda 4: Sprawdź w aplikacji**

```bash
# Jeśli masz endpoint z wersją:
curl -s https://snipnote.pl/api/version

# Lub sprawdź changelog:
curl -s https://snipnote.pl/ | grep -o 'version.*'
```

---

## Troubleshooting deploymentu

### Problem 1: "No available server" po deploy

**Objawy:**
```bash
$ curl https://snipnote.pl/
no available server
```

**Diagnoza:**
```bash
# 1. Sprawdź status kontenerów
docker ps

# 2. Sprawdź healthcheck
docker inspect snipnote-app-1 --format='{{.State.Health.Status}}'

# 3. Sprawdź logi Traefik
docker logs snipnote-traefik-1 --tail 50 | grep -i health

# 4. Sprawdź czy app odpowiada wewnętrznie
docker exec snipnote-app-1 curl -I http://localhost/
```

**Rozwiązanie:**
```bash
# Jeśli healthcheck = "unhealthy":
docker logs snipnote-app-1 --tail 100
# Sprawdź błędy PHP/Symfony

# Jeśli healthcheck = "starting" (za długo):
# Poczekaj 60s, może migracje trwają

# Jeśli Traefik widzi stare IP:
./bin/clean-restart.sh  # Ostateczność
```

---

### Problem 2: Aplikacja nie pobiera nowego obrazu

**Objawy:**
```bash
# Deploy wydaje się działać, ale kod jest stary
```

**Diagnoza:**
```bash
# 1. Sprawdź SHA256 obrazu
docker inspect snipnote-app-1 --format='{{.Image}}'

# 2. Sprawdź czy pull faktycznie zadziałał
docker compose --env-file .env -f docker-compose.prod.yml pull app
# Output powinno być: "Pulled" (nie "Up to date")
```

**Możliwe przyczyny:**

**A) GitHub Actions nie zbudował nowego obrazu**
```bash
# Sprawdź GitHub Actions:
https://github.com/poldas/snipnote/actions

# Jeśli build failed - napraw błędy i push ponownie
```

**B) Docker ma zakeszowany stary obraz**
```bash
# Force pull:
docker pull ghcr.io/poldas/snipnote:prod

# Sprawdź SHA256:
docker inspect ghcr.io/poldas/snipnote:prod --format='{{index .RepoDigests 0}}'

# Potem restart:
./bin/update-app.sh
```

**C) Używasz złego tagu**
```bash
# Sprawdź docker-compose.prod.yml:
cat docker-compose.prod.yml | grep APP_IMAGE_TAG

# Sprawdź .env:
cat .env | grep APP_IMAGE_TAG

# Powinno być: APP_IMAGE_TAG=prod (lub puste = domyślnie prod)
```

---

### Problem 3: Deployment trwa bardzo długo

**Objawy:**
```bash
./bin/update-app.sh
# Wisi na "Waiting for app to be healthy..."
# Po 60s: "Warning: Timeout waiting for healthy status"
```

**Diagnoza:**
```bash
# 1. Sprawdź logi aplikacji (realtime)
docker logs snipnote-app-1 --follow

# 2. Co się dzieje?
# - Migracje trwają długo?
# - Cache warmup timeout?
# - Błąd połączenia z bazą?
```

**Rozwiązania:**

**A) Migracje trwają długo (>60s)**
```bash
# W docker-compose.prod.yml zwiększ healthcheck start-period:
healthcheck:
  start-period: 120s  # Było 40s
```

**B) Cache warmup timeout**
```bash
# W docker-compose.prod.yml zwiększ memory limit:
deploy:
  resources:
    limits:
      memory: 1G  # Było 512M
```

**C) Błąd połączenia z bazą**
```bash
# Sprawdź czy database jest healthy:
docker ps | grep database

# Sprawdź logi database:
docker logs snipnote-database-1 --tail 50
```

---

### Problem 4: Rollback - jak wrócić do poprzedniej wersji?

**Metoda 1: Użyj poprzedniego SHA tagu**

```bash
# 1. Znajdź poprzedni SHA tag w GitHub:
https://github.com/poldas/snipnote/actions
# Kliknij w poprzedni successful build
# Skopiuj SHA (np. "sha-abc1234")

# 2. W .env ustaw:
APP_IMAGE_TAG=sha-abc1234

# 3. Deploy:
./bin/update-app.sh
```

**Metoda 2: Git revert + rebuild**

```bash
# 1. Znajdź commit do cofnięcia
git log --oneline

# 2. Revert commita
git revert <commit-hash>

# 3. Push do deploy branch (GitHub Actions zbuduje nowy obraz)
git push origin deploy

# 4. Poczekaj na GitHub Actions (~5 min)

# 5. Deploy:
./bin/update-app.sh
```

**Metoda 3: Restore z backupu (jeśli jest broken)**

```bash
# 1. Restore bazy danych
cat backup-20251210.sql | docker exec -i snipnote-database-1 psql -U app -d app

# 2. Rollback kodu (metoda 1 lub 2)

# 3. Clean restart
./bin/clean-restart.sh
```

---

## Monitoring po deploy

### Pierwsze 5 minut:

```bash
# 1. Sprawdź czy kontenery są healthy
watch -n 5 'docker ps'

# 2. Monitoruj logi aplikacji
docker logs snipnote-app-1 --follow

# 3. Testuj endpoint
watch -n 10 'curl -I https://snipnote.pl/'

# 4. Sprawdź resource usage
watch -n 5 'docker stats --no-stream'
```

### Pierwsze 24 godziny:

```bash
# Co 1h sprawdź:

# 1. Czy kontenery są up
docker ps

# 2. Czy są błędy w logach
docker logs snipnote-app-1 --since 1h | grep -i error

# 3. Memory usage (czy nie OOM)
docker stats --no-stream | grep snipnote

# 4. Disk space
df -h /var/lib/docker
```

---

## Automatyzacja deploymentu

### Opcja 1: Webhook + deploy skrypt

```bash
# Na VPS, stwórz prosty webhook listener:
cat > /home/ubuntu/deploy-webhook.sh << 'EOF'
#!/bin/bash
# Webhook receiver dla GitHub Actions

cd /home/ubuntu/snipnote
git pull origin deploy
./bin/update-app.sh

# Wyślij notyfikację (opcjonalne)
curl -X POST https://discord.com/api/webhooks/YOUR_WEBHOOK \
  -H "Content-Type: application/json" \
  -d '{"content": "✅ snipnote.pl deployed successfully"}'
EOF

chmod +x /home/ubuntu/deploy-webhook.sh
```

**W GitHub Actions workflow dodaj:**
```yaml
- name: Trigger deploy on VPS
  run: |
    curl -X POST https://snipnote.pl/deploy-webhook \
      -H "Authorization: Bearer ${{ secrets.DEPLOY_TOKEN }}"
```

### Opcja 2: Cron job do auto-update

```bash
# Deploy co noc o 3:00 (jeśli jest nowy obraz)
crontab -e

# Dodaj:
0 3 * * * cd /home/ubuntu/snipnote && ./bin/update-app.sh >> /var/log/snipnote-deploy.log 2>&1
```

### Opcja 3: Watchtower (auto-update kontenerów)

**NIE POLECAM** dla produkcji - lepiej mieć kontrolę!

---

## Checklist przed każdym deployem

- [ ] GitHub Actions build successful
- [ ] Tests passed (jeśli są)
- [ ] Backup bazy danych wykonany (jeśli breaking changes)
- [ ] Low traffic time (jeśli możliwe)
- [ ] Monitoring włączony
- [ ] Rollback plan przygotowany
- [ ] Changelog/release notes zaktualizowane

---

## Checklist po deploy

- [ ] Aplikacja odpowiada (curl test)
- [ ] Logi bez błędów (first 5 min)
- [ ] Healthcheck = healthy
- [ ] Resource usage normalny
- [ ] Key features działają (manual smoke test)
- [ ] Email z powiadomieniem wysłany (opcjonalne)

---

## Best practices

1. **Deploy w godzinach niskiego ruchu** (3:00-6:00)
2. **Zawsze testuj staging najpierw** (jeśli masz)
3. **Backup przed każdym deploy** (database)
4. **Monitoruj przez 24h po deploy**
5. **Jeden feature = jeden deploy** (nie łącz wielkich zmian)
6. **Używaj `update-app.sh` rutynowo** (szybkie, bezpieczne)
7. **`clean-restart.sh` tylko w awaryjnych sytuacjach**
8. **Git tag po każdym deploy** (łatwiejszy rollback)

---

## FAQ

### Q: Jak często powinienem deployować?

**A:** Zależy od zmian:
- Hotfix: natychmiast
- Features: 1-3x dziennie (w low traffic hours)
- Security updates: ASAP

### Q: Czy mogę deployować w godzinach szczytu?

**A:** Tak, `update-app.sh` ma ~5-10s downtime. Ale lepiej w low traffic.

### Q: Co jeśli deploy failuje?

**A:** 
1. Sprawdź logi: `docker logs snipnote-app-1`
2. Rollback: użyj poprzedniego SHA tagu
3. Clean restart: `./bin/clean-restart.sh`

### Q: Jak sprawdzić SHA obecnego obrazu?

**A:** `docker inspect snipnote-app-1 --format='{{.Image}}'`

### Q: Czy mogę deployować bez downtime?

**A:** Prawie - `update-app.sh` ma ~5-10s downtime (restart kontenera). Dla zero-downtime potrzebujesz:
- Blue-green deployment
- Load balancer z 2+ instancjami

---

## Dodatki

### Aliasy dla ~/.bashrc (VPS):

```bash
# Deploy shortcuts
alias deploy='cd ~/snipnote && ./bin/update-app.sh'
alias deploy-all='cd ~/snipnote && ./bin/update-all.sh'
alias deploy-clean='cd ~/snipnote && ./bin/clean-restart.sh'

# Monitoring shortcuts
alias logs-app='docker logs snipnote-app-1 --follow'
alias logs-traefik='docker logs snipnote-traefik-1 --follow'
alias status='cd ~/snipnote && docker compose --env-file .env -f docker-compose.prod.yml ps'
```

Po dodaniu:
```bash
source ~/.bashrc

# Teraz możesz:
deploy        # Szybki update
logs-app      # Live logs
status        # Status kontenerów
```

---

## Kontakt w razie problemów

1. Sprawdź logi
2. Sprawdź ten guide
3. Sprawdź SECURITY-CHANGES.md
4. Google error message
5. Rollback i debug lokalnie

**Pamiętaj:** Zawsze możesz rollback! Nie panikuj.

