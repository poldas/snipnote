# E2E Test Stability Guide

## Problem z niestabilnością testów

Testy E2E mogą być niestabilne z powodu interferencji stanu między uruchomieniami:

- **Stan bazy danych** - pozostałe dane z poprzednich testów
- **Sesje PHP/cache aplikacji** - nieoczyszczone dane sesyjne
- **Stan przeglądarki** - cache, localStorage, cookies
- **Zasoby systemowe** - CPU/pamięć z poprzednich uruchomień
- **Timing** - wrażliwość na opóźnienia

## Mechanizmy stabilizacji

### 1. Global Setup/Teardown

**Lokalizacja:** `e2e/setup/global-setup.ts`, `e2e/setup/global-teardown.ts`

**Funkcjonalność:**
- Resetuje bazę danych do czystego stanu przed każdym uruchomieniem testów
- Gwarantuje identyczne warunki początkowe

### 2. Enhanced Test Script

**Lokalizacja:** `localbin/test_e2e.sh`

**Usprawnienia:**
- Zatrzymuje istniejące kontenery przed uruchomieniem
- Czyści pliki sesji PHP
- Czyści cache przeglądarki
- Uruchamia testy w czystym środowisku

### 3. Clean Script

**Lokalizacja:** `localbin/clean-e2e.sh`

**Użycie:**
```bash
./localbin/clean-e2e.sh  # Ręczne czyszczenie środowiska
```

**Funkcjonalność:**
- Zatrzymuje wszystkie kontenery
- Czyści sesje, cache przeglądarki
- Resetuje bazę danych testowej

### 4. Playwright Configuration

**Usprawnienia:**
- Retry logic: 2 próby w CI dla lepszej stabilności
- Enhanced browser isolation
- Clean context per test
- Dodatkowe argumenty przeglądarki dla CI

### 5. CI Workflow

**Usprawnienia:**
- Lepszy monitoring gotowości serwera PHP
- Dodatkowe czyszczenie cache'a przeglądarki
- Retry logika dla testów

## Best Practices

### Przed uruchomieniem testów:
```bash
# Wyczyść środowisko
./localbin/clean-e2e.sh

# Lub uruchom testy (robi czyszczenie automatycznie)
./localbin/test_e2e.sh
```

### W przypadku problemów:
1. Sprawdź logi: `cat /tmp/php-server.log`
2. Wyczyść ręcznie: `./localbin/clean-e2e.sh`
3. Sprawdź stan bazy: `php bin/console doctrine:query:dql "SELECT COUNT(u) FROM App\Entity\User u" --env=test`

### Debugowanie:
```bash
# Z włączonym debugowaniem
DEBUG=pw:api,pw:browser ./localbin/test_e2e.sh

# Tylko wybrane testy
npm run e2e -- --grep "login"
```

## Monitoring stabilności

Testy powinny być:
- **Idempotentne** - wielokrotne uruchomienie daje ten sam wynik
- **Izolowane** - nie zależą od stanu innych testów
- **Deterministyczne** - zawsze ten sam wynik dla tych samych danych

Jeśli testy nadal zawodzą, sprawdź:
- Stan bazy danych między uruchomieniami
- Cache aplikacji
- Sesje przeglądarki
- Timing wrażliwe operacje