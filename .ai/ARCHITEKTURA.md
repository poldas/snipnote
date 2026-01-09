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
