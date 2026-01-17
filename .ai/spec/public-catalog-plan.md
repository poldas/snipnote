# Specyfikacja Implementacji: US-09 Publiczny Katalog Użytkownika (v2.0)

## 1. Cel biznesowy
Stworzenie publicznej wizytówki (profilu) użytkownika, która pozwala na przeglądanie udostępnionych treści. Dla właściciela katalog pełni rolę alternatywnego, publicznego widoku dashboardu z pełnym dostępem do swoich treści.

## 2. Szczegółowa User Story
**Jako** użytkownik (zalogowany lub gość)
**Chcę** wejść na stronę `/u/{uuid}`
**Aby** przeglądać notatki przypisane do danego profilu.

### Kryteria Akceptacji:
- **Routing**: Adres `/u/{uuid}` (UUIDv4 użytkownika).
- **Widok Gościa/Innych**: Widzi tylko notatki `visibility: public`.
- **Widok Właściciela**: Widzi `public`, `private` oraz te, gdzie jest `collaborator`.
- **Paginacja**: 50 elementów na stronę.
- **Wyszukiwanie**: Pełnotekstowe + `label:tag1,tag2` (logika OR).
- **HTMX**: Wyszukiwanie i paginacja odświeżają tylko kontener z listą (fragment HTML).
- **UX**: 
    - Nazwa profilu: Część adresu email przed znakiem `@`.
    - `NoteCard`: Ukryte przyciski akcji dla gości, widoczne dla właściciela.
- **Błędy**: 404 przy błędnym UUID z przyjaznym szablonem.

## 3. Architektura Techniczna

### 3.1. Warstwa Danych (Database & Repository)
- **Baza danych**: Brak zmian (używamy `users.uuid`).
- **NoteRepository**: Nowa metoda `findForCatalog(User $catalogOwner, ?User $viewer, NoteSearchCriteria $criteria)`.
    - **Logika SQL**:
        - Jeśli `$catalogOwner === $viewer`: 
          `WHERE (n.owner = :owner OR collabs.user = :viewer)`
        - Jeśli `$catalogOwner !== $viewer`:
          `WHERE (n.owner = :owner AND n.visibility = 'public')`

### 3.2. API & Controller
- **Controller**: `App\Controller\Public\CatalogController`.
- **Parametry**: 
    - `q`: string (search query).
    - `page`: int (default 1).
- **DTO**: `App\DTO\Note\NoteSearchCriteria` – reużywalny obiekt do mapowania parametrów wyszukiwania z Request.

### 3.3. Frontend (Twig + Tailwind + HTMX)
- **Layout**: Użycie `layout_public.html.twig`.
- **Stylistyka (Zgodnie z ui-colors.md)**:
    - **Tło**: Subtelny, animowany gradient `from-indigo-50 via-purple-50 to-cyan-50` (podobnie jak na stronie głównej).
    - **Karty Notatek**: Efekt szkła (`bg-white/90 backdrop-blur-lg`) z delikatnym cieniem.
    - **Wyszukiwarka**: Styl `input-modern` (shadow-md, transition-all, fokus z poświatą indygo).
    - **Przyciski**: `Primary Gradient` dla głównych akcji, `Secondary/Glass` dla filtrowania.
- **Widok**: `templates/public/catalog.html.twig`.
- **Komponenty**:
    - Reużycie `templates/notes/components/_note_card.html.twig`.
    - WAŻNE: `NoteCard` musi ukrywać przyciski edycji/usuwania dla gości, ale wyświetlać je dla właściciela.
- **Wyszukiwarka**: Uproszczone pole wyszukiwania w katalogu, wyśrodkowane, zintegrowane z nagłówkiem profilu.

## 4. Analiza Bezpieczeństwa (Security Check)
- **IDOR**: Porównanie `$requestedUuid` z `$this->getUser()->getUuid()` musi odbywać się wyłącznie w kontrolerze.
- **XSS**: Treść notatek w katalogu (renderowany Markdown) przechodzi przez `HtmlSanitizer` (już wdrożony w systemie).
- **Information Leakage**: Zapytanie SQL dla gościa musi mieć twardy warunek `visibility = 'public'`. Nawet przy wyszukiwaniu po tagach, baza nie może zwrócić prywatnych rekordów.
- **Rate Limiting**: Paginacja 50/strona jest bezpieczna, pod warunkiem, że pobieramy tylko `excerpt` opisu, a nie pełne 100kb danych na każdy z 50 rekordów.

## 5. Zmiany w istniejącym kodzie
1. **`NoteRepository`**: Refaktoryzacja metody wyszukiwania, aby była reużywalna (Dashboard i Katalog).
2. **`NoteCard` Component**: Warunkowe renderowanie przycisków akcji.
3. **`layout_public.html.twig`**: Upewnienie się, że zawiera niezbędne meta-tagi dla SEO profilu.

## 6. Plan Implementacji (Krok po kroku)

### Krok 1: Rozszerzenie NoteRepository
Zaimplementowanie wydajnego zapytania uwzględniającego kontekst właściciela vs gościa oraz paginację 50/strona.

### Krok 2: Kontroler i Routing
Stworzenie `CatalogController` z obsługą UUID i mapowaniem na `User`. Implementacja logiki `isOwner`.

### Krok 3: Szablony Twig
- Stworzenie widoku katalogu z wyszukiwarką.
- Integracja HTMX (targetowanie kontenera `#catalog-grid`).
- Modyfikacja `NoteCard` dla trybu `readonly`.

### Krok 4: Testy (Zgodnie z test-rules.md)
- **Unit**: Test `NoteRepository` dla różnych ról przeglądającego.
- **E2E**:
    - Gość wchodzi na `/u/{uuid}` -> Widzi 50 publicznych notatek.
    - Gość szuka `label:secret` -> Dostaje 0 wyników (mimo że owner ma takie notatki prywatne).
    - Owner wchodzi na swój `/u/{uuid}` -> Widzi wszystkie notatki (łącznie z prywatnymi).
    - Błędny UUID -> 404.

### Krok 5: Optymalizacja
Dodanie indeksu na `notes.visibility` i `notes.updated_at` (jeśli nie istnieją), aby paginacja przy 50 elementach była błyskawiczna.

## 7. Dodatkowe biblioteki
- Brak nowych bibliotek. Wykorzystujemy `symfony/uid` oraz `lexik/jwt-authentication-bundle` (jeśli potrzebne do autoryzacji w API).