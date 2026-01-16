Plan Implementacji: Usprawnienia UX (Focus & Tabindex)

  Cel: Implementacja automatycznego fokusu na tytule oraz wymuszenie specyficznej kolejności tabulacji (tytuł -> opis -> etykiety ->
  współpracownik -> usuwanie -> nawigacja) w formularzu notatki.
  Zasady: Zgodność z @.ai/agent-implement.md.

  1. Analiza wymagań

  Użytkownik wymaga ścisłej i logicznej ścieżki nawigacji klawiaturą, która priorytetyzuje kluczowe pola edycji przed elementami pomocniczymi (jak
  toolbar markdown) i kończy się na akcjach systemowych oraz nawigacji globalnej.

  Wymagana kolejność fokusu (Tab):
   1. Tytuł (Focus na start)
   2. Opis (Markdown)
   3. Dodawanie etykiet
   4. Pole email współpracownika (jeśli dostępne)
   5. Przycisk usunięcia notatki (jeśli dostępny)
   6. Nawigacja nagłówka: Logo (Dashboard) -> Moje notatki -> Mój katalog -> Wyloguj

  2. Kroki Implementacji

  Krok 1: Automatyczny fokus (JavaScript)
  W kontrolerze Stimulus obsługującym formularz (assets/controllers/note_form_controller.js), należy dodać automatyczne ustawienie fokusu na pole
  tytułu w momencie podłączenia kontrolera (connect).

   1 // assets/controllers/note_form_controller.js
   2 connect() {
   3     // ... istniejący kod ...
   4     if (this.elements.titleInput) {
   5         // requestAnimationFrame zapewnia, że element jest wyrenderowany
   6         requestAnimationFrame(() => this.elements.titleInput.focus());
   7     }
   8 }

  Krok 2: Ustawienie kolejności Tab (Szablony Twig)
  Należy przypisać atrybuty tabindex do kluczowych elementów w odpowiednich komponentach:

   1. Tytuł (templates/notes/components/title_field.html.twig):
       - Input: tabindex="1"
   2. Opis (templates/notes/components/markdown_textarea.html.twig):
       - Textarea: tabindex="2"
   3. Tagi (templates/notes/components/tag_input.html.twig):
       - Input: tabindex="3"
   4. Współpracownicy (templates/notes/components/collaborators_panel.html.twig):
       - Input email: tabindex="4"
   5. Danger Zone (templates/notes/components/danger_zone.html.twig):
       - Przycisk Usuń: tabindex="5"
   6. Nawigacja (templates/notes/components/notes_nav.html.twig):
       - Logo link: tabindex="6"
       - Moje notatki: tabindex="7"
       - Mój katalog: tabindex="8"
       - Wyloguj: tabindex="9"

  Krok 3: Aktualizacja dokumentacji projektu
  Zgodnie z zasadami, należy nanieść zmiany w plikach specyfikacji, aby odzwierciedlały nową logikę UX.

   1. `.ai/spec/prd.md`:
       - Dodać US-018: Dostępność i nawigacja klawiaturą opisującą powyższe zachowanie.
       - Dodać informację o zachowaniu Ctrl+S (zapis i pozostanie na stronie).
   2. `docs/funkcjonalnosci.md`:
       - Dodać sekcję Dostępność (Accessibility) z opisem fokusu, kolejności Tab oraz skrótów klawiszowych.

  Krok 4: Testy E2E (Playwright)
  Dodać zestaw testów w e2e/specs/notes.shortcuts.spec.ts (lub nowym pliku), który:
   1. Weryfikuje, czy po wejściu na /notes/new kursor jest w tytule.
   2. Symuluje naciskanie klawisza Tab i sprawdza, czy toBeFocused() trafia kolejno w: opis, tagi, współpracowników (jeśli edycja), przycisk usuń
      (jeśli edycja).

  3. Weryfikacja końcowa

  Po implementacji należy wykonać:
   1. npx playwright test - weryfikacja UX i regresji.
   2. ./localbin/phpstan.sh - czystość kodu.
   3. ./localbin/fix.sh - formatowanie.
   4. ./localbin/test.sh - testy jednostkowe backendu.
