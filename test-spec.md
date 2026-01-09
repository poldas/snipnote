# Specyfikacja Testów E2E - Snipnote

Dokument opisuje aktualne scenariusze testowe zrealizowane w technologii Playwright oraz ich pokrycie wymagań zdefiniowanych w PRD.

## Infrastruktura i Izolacja
Wszystkie testy funkcjonalne korzystają z **dynamicznej izolacji kont** (`UserFactory`). Dla każdego pliku testowego tworzony jest unikalny użytkownik, co eliminuje konflikty w bazie danych i pozwala na stabilne uruchamianie testów w środowisku CI/CD. Testy wykorzystują również klienta **Mailpit** do przechwytywania i weryfikacji wiadomości email (rejestracja, reset hasła).

---

## 1. Moduł Autoryzacji (Auth)

### Scenariusz: Logowanie i Wylogowanie
**Plik:** `auth.login-logout.spec.ts`
- **Pokrycie PRD:** US-011 (Logowanie), US-012 (Wylogowanie)
- **Kroki:**
    1. Poprawne zalogowanie na dynamicznie utworzone konto.
    2. Weryfikacja przekierowania na dashboard i obecności adresu email.
    3. Wylogowanie i powrót na stronę główną.
    4. **Red Path:** Próba logowania błędnymi danymi.

### Scenariusz: Rejestracja z Weryfikacją Email
**Plik:** `auth.register.spec.ts`
- **Pokrycie PRD:** US-010 (Rejestracja)
- **Kroki:**
    1. Wypełnienie formularza rejestracji nowymi danymi.
    2. Weryfikacja przekierowania na stronę "Sprawdź skrzynkę".
    3. Pobranie linku aktywacyjnego z Mailpit.
    4. Potwierdzenie adresu email (kliknięcie linku).
    5. Zalogowanie na nowo utworzone konto.

### Scenariusz: Reset Hasła
**Plik:** `auth.password-reset.spec.ts`
- **Pokrycie PRD:** US-016 (Przypomnienie hasła), US-017 (Reset hasła)
- **Kroki:**
    1. Żądanie resetu hasła dla istniejącego użytkownika.
    2. Pobranie linku resetującego z Mailpit.
    3. Ustawienie nowego hasła.
    4. **Red Path:** Weryfikacja, że stare hasło już nie działa.
    5. **Green Path:** Zalogowanie przy użyciu nowego hasła.

### Scenariusz: Nawigacja i Formularze
**Plik:** `auth.navigation.spec.ts`
- **Cel:** Weryfikacja spójności nawigacji i elementów UI.
- **Kroki:**
    1. Przejście przez wszystkie strony autoryzacji (Login, Register, Forgot Password).
    2. Weryfikacja linków powrotnych i obecności wymaganych pól.

---

## 2. Moduł Notatek (Notes)

### Scenariusz: Wyszukiwanie i Filtrowanie (Search)
**Plik:** `notes.search.spec.ts`
- **Pokrycie PRD:** US-05 (Wyszukiwanie)
- **Kroki:**
    1. Przygotowanie danych: Utworzenie notatek o różnych statusach widoczności i z różną zawartością.
    2. **Text Search:** Weryfikacja wyszukiwania po tytule i opisie (w tym polskie znaki, unicode, frazy wielowyrazowe).
    3. **Visibility Filter:** Weryfikacja wyszukiwania w kontekście filtrów: Public, Private, Draft, Shared (For Me).
    4. **Label Search:** Weryfikacja składni `label:nazwa_etykiety`.
    5. **Empty State:** Weryfikacja komunikatu o braku wyników.
    6. **Reset:** Powrót do pełnej listy po wyczyszczeniu filtrów.

### Scenariusz: Tworzenie Notatek
**Plik:** `notes.create.spec.ts`
- **Pokrycie PRD:** US-01 (Tworzenie)
- **Kroki:**
    1. **Green Path:** Dodanie prywatnej notatki i weryfikacja jej obecności na dashboardzie.
    2. **Red Path:** Próba zapisu z pustym tytułem (weryfikacja komunikatów walidacji).

### Scenariusz: Edycja Notatki
**Plik:** `notes.edit.spec.ts`
- **Pokrycie PRD:** US-03 (Edycja)
- **Kroki:**
    1. Aktualizacja tytułu i treści istniejącej notatki.
    2. Weryfikacja zapisu zmian.

### Scenariusz: Współpraca (Collaboration)
**Plik:** `notes.collaboration.spec.ts`
- **Pokrycie PRD:** US-08 (Współpracownicy)
- **Kroki:**
    1. Właściciel tworzy prywatną notatkę.
    2. Właściciel dodaje współpracownika (inny użytkownik testowy).
    3. Współpracownik loguje się i sprawdza dostępność notatki w zakładce "For Me" (widok współdzielony).
    4. Weryfikacja braku dostępu dla użytkownika anonimowego.

### Scenariusz: Interakcje na Dashboardzie
**Plik:** `notes.interactions.spec.ts`
- **Pokrycie PRD:** US-06 (Usuwanie), US-07 (Stan pusty)
- **Kroki:**
    1. Weryfikacja komunikatu "Nie ma jeszcze notatek" dla nowego konta.
    2. Test anulowania usuwania notatki (anulowanie w modalu).

### Scenariusz: Widoczność (Permissions)
**Pliki:** `notes.visibility.public.spec.ts`, `notes.visibility.private.spec.ts`, `notes.visibility.draft.spec.ts`
- **Pokrycie PRD:** US-02 (Widok publiczny), US-04 (Statusy)
- **Kroki:**
    1. Weryfikacja dostępu do notatki **Publicznej** dla każdego (również niezalogowanych).
    2. Weryfikacja blokady dostępu do notatki **Prywatnej** dla osób trzecich.
    3. Weryfikacja całkowitej blokady widoku publicznego dla notatki **Draft**.

### Scenariusz: Motywy Wizualne
**Plik:** `notes.themes.spec.ts`
- **Pokrycie PRD:** US-01 (Etykiety - wpływ na wygląd)
- **Kroki:**
    1. Test motywu **TODO** (etykieta `todo`).
    2. Test motywu **Przepis** (etykieta `recipe`).

### Scenariusz: Interakcje UI i Logika JS
**Plik:** `ui.interaction.spec.ts`
- **Pokrycie PRD:** UX Enhancements
- **Kroki:**
    1. **Markdown Toolbar:** Weryfikacja wstawiania formatowania (pogrubienie, lista).
    2. **Tag Input:** Weryfikacja dodawania, usuwania i deduplikacji etykiet (case-insensitive).
    3. **Client Validation:** Weryfikacja blokady wysyłania formularza przy błędach walidacji (bez requestu do API).
    4. **Public Todo (Local):** Weryfikacja dodawania lokalnych zadań w widoku publicznym (interakcja JS).

---

## 3. Moduł Landing Page

### Scenariusz: UX & Visual
**Pliki:** `landing.*.spec.ts`, `auth.visual.spec.ts`, `ui.hover-effects.spec.ts`
- **Kroki:**
    1. Smoke tests sekcji strony głównej.
    2. Weryfikacja identyfikacji wizualnej i responsywności (Screenshot testing).
    3. Testy stanów hover i animacji (Landing, Login, Register).

---

## Podsumowanie pokrycia User Stories (PRD)

| ID | Tytuł | Status | Uwagi |
| :--- | :--- | :--- | :--- |
| US-01 | Tworzenie notatki | ✅ Pokryte | Green & Red Path |
| US-02 | Widok publicznej notatki | ✅ Pokryte | Pełna weryfikacja uprawnień |
| US-03 | Edycja notatki | ✅ Pokryte | Zmiana treści i metadanych |
| US-04 | Zmiana widoczności | ✅ Pokryte | Wszystkie statusy (Pub/Priv/Draft) |
| US-05 | Wyszukiwanie | ✅ Pokryte | Pełna weryfikacja: tekst, polskie znaki, etykiety |
| US-06 | Usuwanie notatki | ✅ Pokryte | Test modala i akcji |
| US-07 | Dashboard bez notatek | ✅ Pokryte | Dedykowane spece `interactions` |
| US-08 | Współedytorzy | ✅ Pokryte | Pełny flow udostępniania i dostępu |
| US-09 | Publiczny katalog | ❌ Brak | Funkcjonalność nieprzetestowana |
| US-10 | Rejestracja konta | ✅ Pokryte | Pełny flow z weryfikacją email |
| US-11 | Logowanie | ✅ Pokryte | Pełny flow (UserFactory) |
| US-12 | Wylogowanie | ✅ Pokryte | Pełny flow |
| US-13 | Regeneracja URL | ❌ Brak | Brak testu zmiany tokena URL |
| US-14 | Samousunięcie współedytora | ❌ Brak | Brak testu rezygnacji ze współpracy |
| US-16 | Przypomnienie hasła | ✅ Pokryte | Flow z wysyłką emaila |
| US-17 | Reset hasła | ✅ Pokryte | Flow z ustawieniem nowego hasła |

---

## Analiza braków i rekomendowane testy (Next Steps)

Na podstawie analizy obecnego stanu, rekomenduje się dodanie następujących scenariuszy:

1.  **Publiczny Katalog (US-09):**
    *   Weryfikacja, czy notatki publiczne pojawiają się w ogólnym katalogu (jeśli zaimplementowany).
    *   Sprawdzenie paginacji w katalogu.

2.  **Zarządzanie Linkiem Publicznym (use case dla US-13):**
    *   Weryfikacja przycisku "Regeneruj link" w edycji notatki.
    *   Sprawdzenie, czy stary link przestaje działać.

4.  **Zarządzanie Współpracą (Advanced):**
    *   Usuwanie współpracownika przez właściciela.
    *   Rezygnacja z dostępu przez współpracownika (US-14).
