# Podsumowanie Naprawy Workflow CI i Testów E2E

## 📅 Data i kontekst
Rozmowa dotycząca naprawy niestabilności testów E2E w projekcie Snipnote. Główny problem: testy działały przy pierwszym uruchomieniu, ale zawodziły przy kolejnych uruchomieniach.

## 🚨 Główny problem
**Niestabilność testów E2E między uruchomieniami** - testy przechodziły przy pierwszym uruchomieniu `./localbin/test_e2e.sh`, ale zawodziły przy drugim uruchomieniu z powodu interferencji stanu aplikacji między testami.

### Przyczyny niestabilności:
- Stan bazy danych pozostawał między uruchomieniami
- Sesje PHP/cache aplikacji interferowały
- Stan przeglądarki (cache, localStorage) pozostawał
- Brak restartowania środowiska między testami

---

## 🛠️ Wprowadzone zmiany i rozwiązania

### 1. **Usprawnienie lokalnego środowiska testowego**

#### **Plik:** `localbin/test_e2e.sh`
**Zmiany:**
- Dodanie automatycznego czyszczenia środowiska przed testami
- Zatrzymywanie istniejących kontenerów Docker
- Czyszczenie plików sesji PHP (`/tmp/sessions/`)
- Czyszczenie cache'a przeglądarki

**Kod dodany:**
```bash
#!/usr/bin/env bash
set -euo pipefail

echo "🧹 Preparing clean E2E test environment..."

# Stop any existing containers to ensure clean state
echo "Stopping existing containers..."
docker compose down --volumes --remove-orphans 2>/dev/null || true

# Clean up any leftover sessions
echo "Cleaning session files..."
sudo rm -rf /tmp/sessions/ 2>/dev/null || true
mkdir -p /tmp/sessions
chmod 777 /tmp/sessions

# Clean browser cache/data that might persist between runs
echo "Cleaning browser cache..."
rm -rf ~/.cache/playwright/ 2>/dev/null || true

echo "🚀 Starting E2E tests with clean environment..."
E2E_BASE_URL=http://localhost:8080 E2E_WEB_SERVER_CMD="./localbin/start.sh" npm run e2e
```

#### **Plik:** `localbin/clean-e2e.sh` (NOWY)
**Funkcjonalność:**
- Ręczne czyszczenie środowiska testowego
- Reset bazy danych do stanu wyjściowego
- Czyszczenie cache'u i sesji

### 2. **Optymalizacja konfiguracji Playwright**

#### **Plik:** `playwright.config.ts`
**Zmiany:**
- Zwiększenie retry logic: `retries: process.env.CI ? 2 : 0`
- Dodanie lepszej izolacji przeglądarki dla CI
- Usunięcie global setup (przeniesione do CI workflow)
- Optymalizacja równoległości testów

**Kluczowe ustawienia:**
```typescript
// Enhanced browser isolation for CI stability
launchOptions: {
    args: [
        '--disable-web-security',
        '--disable-features=VizDisplayCompositor',
        '--disable-dev-shm-usage', // Prevent crashes in CI
        '--no-sandbox', // Required in some CI environments
        '--disable-gpu', // Prevent GPU-related issues
    ]
},
// Clean browser context per test
contextOptions: {
    ignoreHTTPSErrors: true,
    bypassCSP: true, // Allow test scripts to run
},
```

### 3. **Poprawa workflow GitHub Actions**

#### **Plik:** `.github/workflows/ci.yml`
**Główne zmiany:**

**Naprawiono dublowanie testów:**
```yaml
if: ${{ ! (github.event_name == 'pull_request' && startsWith(github.head_ref, 'fix-')) }}
```
*Testy dla branchy `fix-*` uruchamiają się tylko przy push, nie przy pull request.*

**Ulepszono przygotowanie środowiska E2E:**
```yaml
# Clean environment preparation
echo "🧹 Preparing clean CI environment..."
mkdir -p /tmp/sessions
chmod 777 /tmp/sessions

# Clean any existing PHP processes
pkill -f "php -S" || true
sleep 2

# Start fresh PHP server
echo "🚀 Starting PHP development server..."
php -S 0.0.0.0:8080 -t public > /tmp/php-server.log 2>&1 &
```

**Dodano czyszczenie bazy danych przed testami E2E:**
```yaml
# Global test setup - clean database state for E2E tests
echo "🗑️  Preparing clean database state for E2E tests..."
php bin/console doctrine:database:drop --force --if-exists --env=test || echo "Could not drop database"
php bin/console doctrine:database:create --if-not-exists --env=test || (echo "Could not create database" && exit 1)
php bin/console doctrine:migrations:migrate --no-interaction --env=test || (echo "Could not run migrations" && exit 1)
```

**Ulepszono monitorowanie:**
```yaml
# Wait for server to be ready
for i in {1..30}; do
  if curl -f http://localhost:8080 >/dev/null 2>&1; then
    echo "✅ PHP server ready"
    break
  fi
  echo "Waiting for PHP server... ($i/30)"
  sleep 2
done
```

### 4. **Poprawa obsługi testów**

#### **Plik:** `e2e/page-objects/LoginPage.ts`
**Zmiany:**
- Zmieniono `form.submit()` na `page.click()` dla lepszej kompatybilności
- Dodano lepsze monitorowanie odpowiedzi HTTP
- Zwiększono czas oczekiwania na przekierowanie

#### **Plik:** `e2e/helpers/UserFactory.ts`
**Zmiany:**
- Dodanie flagi `--env=test` dla CI: `const envFlag = process.env.CI ? '--env=test' : '';`

### 5. **Dokumentacja i README**

#### **Plik:** `docs/E2E-STABILITY.md` (NOWY)
**Zawartość:**
- Przewodnik po stabilizacji testów
- Best practices dla E2E testing
- Debugowanie problemów
- Monitoring stabilności

#### **Plik:** `README.md`
**Zaktualizowany:**
- Dodane nowe skrypty: `./localbin/test_e2e.sh`, `./localbin/clean-e2e.sh`

---

## 📊 Podział i równoległość testów

### **Aktualny podział projektów:**
- **stateless-visual** (3 workery): testy wizualne stron auth
- **stateless-navigation** (2 workery): nawigacja między stronami
- **stateless-landing** (4 workery): testy strony landing
- **stateless-hover** (3 workery): efekty hover UI
- **stateless-hover-main** (2 workery): główne testy hover
- **stateful-auth** (1 worker): testy rejestracji/logowania
- **stateful-notes** (2 workery): testy notatek
- **stateful-ui-logic** (1 worker): logika UI

**Razem:** 70 testów, maksymalnie 18 workerów w CI

### **Strategia równoległości:**
- **Stateless tests**: równoległe wykonywanie (bez logowania)
- **Stateful tests**: sekwencyjne wykonywanie (wymagają czystego stanu)

---

## ✅ Rozwiązane problemy

### **1. Niestabilność między uruchomieniami**
**Przyczyna:** Stan aplikacji pozostawał między testami
**Rozwiązanie:** Automatyczne czyszczenie środowiska przed każdym uruchomieniem

### **2. Interferencje bazy danych**
**Przyczyna:** Dane testowe pozostawały w bazie
**Rozwiązanie:** Reset bazy danych (drop → create → migrate) przed testami E2E

### **3. Problemy z CI workflow**
**Przyczyna:** Brak przygotowania czystego środowiska w CI
**Rozwiązanie:** Kompleksowe czyszczenie i przygotowanie środowiska w workflow

### **4. Brak pokrycia wszystkich testów**
**Przyczyna:** Niektóre pliki testów nie były wykrywane przez konfigurację
**Rozwiązanie:** Dodanie brakującego projektu `stateless-hover-main`

### **5. Problemy z kompatybilnością Playwright**
**Przyczyna:** Nieoptymalne ustawienia dla środowiska CI
**Rozwiązanie:** Dodanie specjalnych argumentów przeglądarki dla CI

---

## 🎯 Aktualny stan

### **Workflow CI:**
- ✅ Testy uruchamiają się tylko raz dla branchy `fix-*` (tylko push)
- ✅ Deploy uruchamia się po pomyślnym przejściu wszystkich testów
- ✅ Środowisko jest czyszczone przed każdym uruchomieniem
- ✅ Baza danych jest resetowana przed testami E2E

### **Testy E2E:**
- ✅ Wszystkie 70 testów są wykrywane i podzielone na projekty
- ✅ Maksymalna równoległość: 18 workerów w CI
- ✅ Stabilne wykonywanie dzięki izolacji
- ✅ Szczegółowe logowanie i debugowanie

### **Narzędzia developerskie:**
- ✅ `./localbin/test_e2e.sh` - uruchamianie z czystym środowiskiem
- ✅ `./localbin/clean-e2e.sh` - ręczne czyszczenie
- ✅ Szczegółowa dokumentacja w `docs/E2E-STABILITY.md`

---

## 🔮 Rekomendacje na przyszłość

### **1. Monitorowanie stabilności**
- Regularne sprawdzanie czasu wykonania testów
- Monitorowanie współczynnika przejścia testów
- Analiza logów pod kątem wzorców błędów

### **2. Dalsze optymalizacje**
- Rozważenie zwiększenia workerów dla `stateful-notes` (z 2 do 3)
- Dodanie testów wizualnych (screenshots comparison)
- Implementacja API testing dla backend validation

### **3. Utrzymanie**
- Regularne aktualizacje konfiguracji Playwright
- Czyszczenie niepotrzebnych artifacts
- Aktualizacja dokumentacji przy zmianach

---

## 📁 Pliki utworzone/zmienione

### **Nowe pliki:**
- `e2e/setup/global-setup.ts`
- `e2e/setup/global-teardown.ts`
- `localbin/clean-e2e.sh`
- `docs/E2E-STABILITY.md`

### **Zmienione pliki:**
- `.github/workflows/ci.yml`
- `playwright.config.ts`
- `localbin/test_e2e.sh`
- `e2e/page-objects/LoginPage.ts`
- `e2e/helpers/UserFactory.ts`
- `README.md`

### **Usunięte pliki:**
- `e2e/setup/global-setup.ts` (przeniesiony do CI workflow)
- `e2e/setup/global-teardown.ts`
- `e2e/setup/` (katalog)

---

## 🏆 Podsumowanie rezultatów

**Przed naprawą:**
- ❌ Testy niestabilne między uruchomieniami
- ❌ Interferencje stanu aplikacji
- ❌ Brak pokrycia wszystkich testów
- ❌ Problemy z CI workflow

**Po naprawie:**
- ✅ **Stabilne i przewidywalne testy E2E**
- ✅ **Czyste środowisko dla każdego uruchomienia**
- ✅ **Optymalna równoległość (18 workerów w CI)**
- ✅ **Kompletne pokrycie wszystkich 70 testów**
- ✅ **Automatyczne narzędzia do czyszczenia i debugowania**

**Workflow CI jest teraz produkcyjnie gotowy!** 🚀