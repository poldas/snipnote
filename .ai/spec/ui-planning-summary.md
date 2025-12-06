<conversation_summary>
<decisions>
1. Dashboard wyświetla tylko własne notatki użytkownika — współdzielonych nie pokazujemy.
2. Główna nawigacja: topbar + chowany sidebar. Sidebar domyślnie ukryty na mobilu, widoczny na desktopie; stan może być zapamiętywany.
3. Edycja notatki realizowana w pełnoekranowym widoku, nie w modalu.
4. Podgląd markdown wywoływany przyciskiem „Podgląd”, renderowany po stronie serwera przez dedykowany endpoint.
5. Zarządzanie współedytorami: sekcja w widoku edycji, lista + pole email + możliwość usunięcia siebie. Bez dodatkowych paneli.
6. Walidacja: błędy inline przy polach, globalne alerty/toasty dla błędów serwera; mapowanie kodów błędów API jest precyzyjne.
7. Brak autosave — zapis tylko na przycisk „Zapisz”.
8. Klawiaturowe skróty nie są wymagane.
9. Autoryzacja: sesje cookie + CSRF; JWT wyłącznie dla API-klientów, UI korzysta z sesji.
10. Publiczne strony nie mają agresywnego cache — odświeżenie po operacjach krytycznych (zmiana visibility, regeneracja URL).
11. Lista notatek w dashboardzie: duże, czytelne wiersze (title, excerpt, labels, data, ikony akcji).
12. Paginacja klasyczna — numery stron, zgodne z offset pagination API.
13. Labele: pełny unicode, w tym spacje; deduplikacja case-insensitive; walidacja i trimowanie.
14. Toolbar markdown: wstawianie znaczników (bold, italic, linki, listy, tabele, sekcje, code snippets).
15. Biblioteka markdown: league/commonmark + rozszerzenia (tabele, autolink, task-lists, fenced code blocks, GFM).
16. Sanitizacja HTML: Symfony HTML Sanitizer z whitelistą tagów/atrybutów — przed wysyłką do klienta.
17. Wyszukiwanie: jeżeli zapytanie zawiera labele, muszą być na końcu; parsing: `q=<tekst>`, `labels=<lista>`.
18. Pole opisu: duże, wygodne, estetyczne, nie zwykłe textarea; przewijalne, przyjazne wizualnie.
19. Regeneracja URL: modal ostrzegający, po potwierdzeniu wywołanie endpointu i reload strony.
20. Obsługa błędów API: 400 inline, 409 globalny alert, 403 modal/alert, 500 toast.
</decisions>

<matched_recommendations>
1. Używanie topbara + chowanego sidebara jako spójnego elementu nawigacji.
2. Dashboard jako kompaktowa, ale duża i czytelna lista wierszy z meta-danymi.
3. Edycja i tworzenie notatki w pełnoekranowym widoku.
4. Podgląd markdown tylko na żądanie, przez HTMX + server-side preview.
5. Zarządzanie współedytorami przez prostą listę i pole email, bez pod-stron.
6. Walidacje inline + globalne alerty dla błędów serwera.
7. Obsługa labeIi: unicode, case-insensitive dedupe, parser zapytania dla wyszukiwania.
8. Wybór league/commonmark jako głównej biblioteki markdown z rozszerzeniami.
9. Server-side sanitizacja HTML jako standard bezpieczeństwa.
10. Modal dla krytycznych akcji (usuwanie, regeneracja URL).
11. Brak WYSIWYG — toolbar markdown jako minimalny enhancement.
12. Paginacja tradycyjna, nie „infinite scroll”.
13. Sesje cookie + CSRF jako główny mechanizm auth dla UI.
14. Brak autosave — wyłącznie manualny zapis zmian.
15. Publiczne strony bez długiego cache, z pełnym refresh po krytycznych operacjach.
</matched_recommendations>

<ui_architecture_planning_summary>
Poniżej przedstawiono końcową, scaloną architekturę UI MVP zgodną z całą rozmową oraz podjętymi decyzjami.

### A. Główne wymagania architektury UI
- UI oparte na Twig + HTMX + Tailwind.
- Interfejs prosty, czytelny, minimalny, zoptymalizowany pod MVP.
- Główny model pracy: pełnostronicowe widoki, HTMX do częściowych aktualizacji.
- Zgodność z PRD: brak autosave, edycja jawna, pełna kontrola uprawnień.
- Markdown edytowany w textarea, renderowany serwerowo + sanitizowany.
- Wyszukiwanie obejmuje tytuł/opis + labele; UI musi rozpoznawać format zapytań.
- Dashboard jest centralnym hubem użytkownika: lista notatek + wyszukiwarka.

### B. Kluczowe widoki i przepływy
**1. Landing page / login / register**
- Proste formularze, walidacje inline.
- Po logowaniu przekierowanie na dashboard.

**2. Dashboard (lista notatek)**
- Duże, czytelne wiersze z tytułem, excerpt (255), labelami, datą, ikonami akcji.
- Wyszukiwanie w górnym panelu.
- Domyślna paginacja 10/strona.
- Brak notatek → stan pusty: komunikat + CTA „Dodaj notatkę”.
- Akcje: edycja, usunięcie (modal potwierdzający).

**3. Widok tworzenia/edycji notatki**
- Pola: tytuł, opis (duże i przyjazne), labele (tag-input unicode).
- Toolbar markdown z wstawianiem znaczników (bold/italic/code/tabele/listy/linki/separatory).
- Przycisk „Podgląd” — wywołuje server-side preview HTML.
- Sekcja współedytorów: lista + input email + usuwanie siebie.
- Przycisk zmiany widoczności (prywatna/publiczna).
- Akcja „Generuj nowy URL” — modal → potwierdzenie → zapis → reload.
- Po zapisie przekierowanie na dashboard + toast „Zapisano”.

**4. Publiczny widok notatki**
- Renderowany markdown (server-side + sanitized).
- Tytuł, opis, labele, data.
- Jeśli użytkownik ma uprawnienia → link do edycji.
- Jeśli notatka prywatna lub URL nieważny → komunikat o braku dostępu.

**5. Publiczny katalog użytkownika**
- Lista publicznych notatek z excerptami.
- Paginacja + wyszukiwarka kompatybilna z `label:`.
- Brak notatek / użytkownika → komunikat „Nie ma takiego użytkownika”.

### C. Integracja z API i zarządzanie stanem
- UI używa sesji cookie i CSRF.
- HTMX używany do: preview markdown, list tagów, fragmentów formularzy.
- Pełne reloady dla krytycznych operacji (regeneracja URL, zmiana visibility).
- Brak globalnego store — każdy widok ładowany server-side.
- Walidacje: klienckie minimalne + serwer authoritative.
- Błędy HTTP mapowane do odpowiednich elementów UI (inline/alert/modal).

### D. Responsywność, dostępność, bezpieczeństwo
- Layout: topbar + chowany sidebar (mobile-first).
- Komponenty w Tailwind: grid/stack, czytelna typografia, duże wiersze.
- Wszystkie formularze mają label, błędy inline, focus management.
- Markdown sanitizowany na serwerze przed renderem.
- Brak JS ciężkiego — HTMX + minimalnie JS dla markdown toolbara.
</ui_architecture_planning_summary>
</conversation_summary>
