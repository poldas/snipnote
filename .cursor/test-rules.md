# Standardy Testowania Snipnote (Test Rules)

Specyfikacja zasad i dobrych praktyk testowania dla projektu Snipnote. Każda zmiana w kodzie musi być zweryfikowana zgodnie z poniższymi rygorami.

## 1. Strategia i Piramida Testów
- **Testy Jednostkowe (Unit)**: Logika domenowa w serwisach i encjach. Szybkie, bez bazy danych (mockowanie).
- **Testy Integracyjne (Integration)**: Weryfikacja Voterów, Repozytoriów i przepływów API z użyciem testowej bazy PostgreSQL.
- **Testy E2E (Playwright)**: Krytyczne ścieżki użytkownika (Happy Path) oraz złożone interakcje UI (HTMX/JS).

## 2. Standardy Backend (PHPUnit 12 & Symfony 8)

### 2.1. Niezawodna Weryfikacja Tożsamości (Krytyczne)
Zasada zapobiegania IDOR:
- **Porównywanie Encji**: Zawsze weryfikuj tożsamość użytkownika przed wykonaniem akcji (Właściciel vs Współedytor).
- **Stabilność Tożsamości**: Pamiętaj, że w testach integracyjnych (po wyczyszczeniu Identity Map Doctrine) obiekty mogą mieć różne referencje, mimo że reprezentują ten sam rekord. Porównuj identyfikatory w sposób odporny na wartości `null` (nie dopuszczaj sytuacji `null === null`).

### 2.2. Implementacja Testów
- **Czerwona Ścieżka (Red Path)**: Każdy test musi sprawdzać scenariusze negatywne (403 Forbidden, 404 Not Found, 400 Validation Error).
- **Izolacja**: Testy nie mogą zależeć od siebie. Używaj `fixtures` lub `factories` do przygotowania danych.
- **PHPUnit 12**: 
    - Metody `DataProvider` muszą być `static`.
    - Używaj asercji statycznych: `self::assert...`.
    - Wymagane ścisłe typowanie parametrów i wyników w testach.

## 3. Standardy Frontend & E2E (Playwright)

### 3.1. Stabilność i Selektory
- **Test-ID**: Używaj wyłącznie atrybutów `data-testid` do lokalizowania elementów. Nie polegaj na klasach Tailwind ani strukturze CSS.
- **Asynchroniczność (HTMX)**: Testy muszą jawnie oczekiwać na zakończenie żądań HTMX (np. `waitForResponse`) lub pojawienie się elementów w DOM.

### 3.2. Izolacja Środowiska
- **Unique State**: Każdy test E2E powinien operować na unikalnym użytkowniku (User Isolation), aby uniknąć konfliktów w bazie danych podczas równoległego uruchamiania.
- **Mailpit**: Weryfikuj wysyłkę maili (reset hasła, rejestracja) poprzez API Mailpit.

## 4. Bezpieczeństwo i Jakość (Quality Gates)

### 4.1. Walidacja API
- Testuj odporność na "zatruty" payload (niepoprawne typy w JSON, brakujące pola).
- Weryfikuj, czy błędy systemowe (500) nie wyciekają do klienta (zawsze mapuj na czytelne 400/403/404).

### 4.2. Automatyzacja
- **PHPStan**: Kod testów musi przechodzić analizę statyczną na poziomie 6 (zgodnie z tech-stack).
- **Zero-Warning**: Testy muszą przechodzić bez żadnych ostrzeżeń (deprecations, notices).

## 5. Checklist deweloperski
1. Czy nowa logika ma pokrycie w testach jednostkowych?
2. Czy uprawnienia (Security/Voters) są przetestowane integracyjnie?
3. Czy krytyczny flow użytkownika został sprawdzony w E2E?
4. Czy testy przechodzą w czystym środowisku Docker (`./localbin/test.sh`)?