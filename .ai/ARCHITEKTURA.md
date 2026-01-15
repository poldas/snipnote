# Dokumentacja Architektury Snipnote

## Izolacja i Bezpieczeństwo Frontend (AssetMapper)

### Mechanizm importmap_safe
W celu zapobieżenia wyciekowi informacji o wewnętrznej strukturze aplikacji (ścieżki do kontrolerów edytora, dashboardu, htmx itp.) do użytkowników niezalogowanych, wprowadzono mechanizm filtrowania mapy importów.

- **Plik**: `src/Twig/ImportMapExtension.php`
- **Serwis**: Skonfigurowany w `config/services.yaml` z wstrzykniętym `@asset_mapper.importmap.renderer`.
- **Funkcja Twig**: `importmap_safe(entrypoint, excludePatterns)`

#### Działanie:
Standardowa funkcja `{{ importmap() }}` w Symfony renderuje pełną mapę wszystkich zasobów zdefiniowanych w `importmap.php`. Funkcja `importmap_safe` przechwytuje ten wygenerowany kod HTML i za pomocą wyrażeń regularnych parsuje JSON znajdujący się wewnątrz tagu `<script type="importmap">`. Następnie usuwa wszystkie klucze, które zawierają zdefiniowane wzorce (np. 'note_form', 'dashboard').

#### Zastosowanie:
Używane w `templates/layout_public.html.twig`, aby na publicznych widokach notatek ładować jedynie niezbędny JavaScript (np. obsługę list TODO), ukrywając jednocześnie ścieżki do reszty aplikacji.

---

## Logika Widoków (Layouty)

Aplikacja korzysta z trzech wyspecjalizowanych layoutów bazowych, co pozwala na precyzyjne zarządzanie zasobami:

1.  **layout_auth.html.twig**:
    - Przeznaczenie: Landing Page, Logowanie, Rejestracja, Reset hasła.
    - Charakterystyka: **Zero JS Policy**. Wszystkie procesy oparte na standardowym HTML i POST.
2.  **layout_dashboard.html.twig**:
    - Przeznaczenie: Dashboard użytkownika, edytor notatek.
    - Charakterystyka: Pełny zestaw narzędzi (HTMX, Turbo, Edytor).
3.  **layout_public.html.twig**:
    - Przeznaczenie: Widok publicznej notatki.
    - Charakterystyka: Wykorzystuje `importmap_safe` do ładowania tylko potrzebnych skryptów (np. `public_note.js`).

---

## Interaktywne Listy zadań (Public Todo)

### Synchronizacja i Mergowanie (Merge Logic)
Widok publiczny notatki TODO pozwala użytkownikom na lokalną interakcję z listą zadań (zapis w `localStorage`).

- **Problem**: Zmiany wprowadzone przez autora w edytorze (dodanie/usunięcie zadań) były nadpisywane przez pełny zrzut z `localStorage`.
- **Rozwiązanie**: Kontroler `public_todo_controller.js` paruje zadania z Markdowna (Remote) z tymi z pamięci lokalnej (Local) używając treści zadania jako klucza. Pozwala to na zachowanie postępów użytkownika (wykonane/usunięte) przy jednoczesnym przyjęciu nowych zadań od autora.
---

## Jakość Kodu i Testowanie

System CI/CD oraz lokalne skrypty wykorzystują zestaw narzędzi do zapewnienia jakości kodu, spójności i testowania.

### Analiza Statyczna (PHPStan)

PHPStan jest używany do wykrywania błędów w kodzie bez jego uruchamiania.

-   **Poziom Analizy**: `6`
-   **Sprawdzane Ścieżki**: `bin/`, `config/`, `public/`, `src/`, `tests/`
-   **Konfiguracja**: `phpstan.dist.neon`

```yaml
parameters:
    level: 6
    paths:
        - bin/
        - config/
        - public/
        - src/
        - tests/
```

### Testy Jednostkowe i Integracyjne (PHPUnit)

PHPUnit jest wykorzystywany do uruchamiania testów jednostkowych i integracyjnych.

-   **Bootstrap**: `tests/bootstrap.php` jest używany do inicjalizacji środowiska testowego.
-   **Zestaw Testów**: Wszystkie pliki w katalogu `tests/` są traktowane jako testy.
-   **Środowisko**: Testy są uruchamiane w środowisku `test`, co jest zdefiniowane w `phpunit.dist.xml`.
-   **Konfiguracja**: `phpunit.dist.xml`

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         colors="true"
         failOnDeprecation="true"
         failOnNotice="true"
         failOnWarning="true"
         bootstrap="tests/bootstrap.php"
         cacheDirectory=".phpunit.cache"
>
    <php>
        <ini name="display_errors" value="1" />
        <ini name="error_reporting" value="-1" />
        <server name="APP_ENV" value="test" force="true" />
        <server name="KERNEL_CLASS" value="App\Kernel" />
        <server name="SHELL_VERBOSITY" value="-1" />
    </php>
    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>
</phpunit>
```

### Pokrycie Kodu (Code Coverage)

Podczas działania procesu CI w GitHub Actions, generowane są raporty pokrycia kodu.

-   **Polecenie w CI**: `./bin/phpunit --coverage-clover var/coverage/clover.xml --coverage-html var/coverage/html`
-   **Formaty Raportów**:
    -   `clover.xml`: Do analizy maszynowej.
    -   `html`: Do przeglądania przez deweloperów.
-   **Katalog Docelowy**: `var/coverage/`

### Weryfikacja Progu Pokrycia

Po wygenerowaniu raportu, dedykowany skrypt sprawdza, czy pokrycie kodu metodami osiągnęło wymagany próg.

-   **Skrypt**: `bin/check-coverage.php`
-   **Wymagany Próg**: `55%`
-   **Polecenie w CI**: `php bin/check-coverage.php var/coverage/clover.xml 55`
-   **Logika**: Skrypt analizuje plik `clover.xml`, oblicza procent pokrytych metod i kończy działanie z błędem, jeśli próg nie został osiągnięty.

```php
#!/usr/bin/env php
<?php
// Simplified for documentation
$inputFile = $argv[1] ?? 'var/coverage/clover.xml';
$threshold = (float) ($argv[2] ?? 55.0);

$xml = simplexml_load_file($inputFile);
$metrics = $xml->xpath('//project/metrics');
$metric = $metrics[0];
$coveredMethods = (int) $metric['coveredmethods'];
$totalMethods   = (int) $metric['methods'];
$percentage = round(($coveredMethods / $totalMethods) * 100, 2);

if ($percentage < $threshold) {
    exit(1); // Fail
}
exit(0); // Pass
```