# Production Deployment Scripts

Skrypty do zarządzania aplikacją na serwerze produkcyjnym VPS.

## Wymagania

- Docker i Docker Compose zainstalowane na serwerze
- Plik `.env` skonfigurowany w katalogu głównym projektu
- Uprawnienia wykonywania: `chmod +x bin/*.sh`

## Skrypty

### 1. `update-app.sh` - Aktualizacja aplikacji (ZALECANE)

**Kiedy używać:** Po wdrożeniu nowego kodu aplikacji (nowy obraz Docker)

**Co robi:**
- Pobiera najnowszy obraz aplikacji z GitHub Container Registry
- Restartuje TYLKO kontener aplikacji
- Zachowuje Traefik, sieci i certyfikaty nietknięte
- Minimalizuje downtime (~5 sekund)

**Użycie:**
```bash
./bin/update-app.sh
```

**Downtime:** ~5 sekund

---

### 2. `update-all.sh` - Rolling update wszystkich serwisów

**Kiedy używać:** Po zmianach w konfiguracji Docker Compose lub Traefik

**Co robi:**
- Pobiera wszystkie obrazy
- Wykonuje rolling update wszystkich kontenerów
- Usuwa stare "orphan" kontenery
- Zachowuje sieci i certyfikaty

**Użycie:**
```bash
./bin/update-all.sh
```

**Downtime:** ~10 sekund

---

### 3. `clean-restart.sh` - Pełne czyszczenie i restart

**Kiedy używać:** 
- Problemy z siecią Docker
- Problemy z cache kontenerów
- Awaryjne sytuacje
- NIE używaj rutynowo!

**Co robi:**
- Zatrzymuje wszystkie kontenery
- Usuwa sieci Docker
- Czyści cache obrazów
- Uruchamia wszystko od nowa
- **Zachowuje dane w bazie danych**

**Użycie:**
```bash
./bin/clean-restart.sh
```

**Downtime:** ~30-60 sekund

---

## Workflow wdrożenia

### Standardowy deploy nowej wersji:

```bash
# 1. GitHub Actions buduje nowy obraz (automatycznie przy push do branch 'deploy')
# 2. Na serwerze VPS:
cd ~/snipnote
./bin/update-app.sh
```

### Po zmianie konfiguracji (docker-compose.prod.yml):

```bash
# 1. Skopiuj nowy plik na serwer
scp docker-compose.prod.yml ubuntu@snipnote.pl:~/snipnote/

# 2. Na serwerze VPS:
cd ~/snipnote
./bin/update-all.sh
```

### Gdy coś nie działa:

```bash
cd ~/snipnote
./bin/clean-restart.sh
```

---

## Porównanie

| Sytuacja | Skrypt | Downtime | Zmienia IP? | Zmienia certyfikaty? |
|----------|--------|----------|-------------|---------------------|
| Nowy kod aplikacji | `update-app.sh` | ~5s | ❌ Nie | ❌ Nie |
| Zmiana konfiguracji | `update-all.sh` | ~10s | ❌ Nie | ❌ Nie |
| Problemy/awaria | `clean-restart.sh` | ~30s | ✅ Tak | ❌ Nie (zachowane) |

---

## Troubleshooting

### Problem: "no available server"

**Przyczyna:** Traefik nie może połączyć się z aplikacją (healthcheck fail lub problem z IP)

**Rozwiązanie:**
```bash
# Sprawdź logi
docker logs snipnote-traefik-1 --tail 50
docker logs snipnote-app-1 --tail 50

# Jeśli healthcheck timeout, spróbuj:
./bin/clean-restart.sh
```

### Problem: "404 page not found"

**Przyczyna:** Traefik nie widzi routera (problem z Docker provider)

**Rozwiązanie:**
```bash
# Sprawdź czy labels są poprawne
docker inspect snipnote-app-1 | grep traefik

# Restart Traefik
docker compose --env-file .env -f docker-compose.prod.yml restart traefik
```

### Problem: Aplikacja długo się uruchamia

**Przyczyna:** Symfony cache/migracje mogą potrzebować czasu

**Rozwiązanie:**
```bash
# Sprawdź logi aplikacji
docker logs snipnote-app-1 --follow

# Poczekaj 60 sekund po starcie
```

---

## Bezpieczeństwo

⚠️ **WAŻNE:** 
- NIE commituj pliku `.env` do repozytorium
- Certyfikaty Let's Encrypt są przechowywane w volume `traefik-letsencrypt`
- Baza danych jest w volume `database_data` (zachowywana nawet po `down`)

---

## Monitoring

Sprawdź status aplikacji:
```bash
# Status kontenerów
docker compose --env-file .env -f docker-compose.prod.yml ps

# Logi aplikacji
docker logs snipnote-app-1 --tail 50 --follow

# Logi Traefik
docker logs snipnote-traefik-1 --tail 50 --follow

# Test HTTPS
curl -I https://snipnote.pl/
```

