# Kompleksowa Specyfikacja Testów - Snipnote

Dokument opisuje strategię testowania aplikacji Snipnote, łącząc testy End-to-End (Playwright) oraz testy Backendowe (PHPUnit), mapując je na wymagania zdefiniowane w PRD.

---

## 1. Infrastruktura i Metodologia

### Izolacja i Dane Testowe
- **E2E (Playwright):** Korzysta z `UserFactory` do dynamicznego tworzenia kont. Każdy plik testowy to unikalny użytkownik, co zapewnia brak konfliktów w bazie danych. Wykorzystuje **Mailpit** do weryfikacji wiadomości email.
- **Backend (PHPUnit):** 
    - **Unit Tests:** Testy w izolacji (mocki), szybkie, sprawdzające logikę biznesową.
    - **Integration Tests:** Testy z użyciem bazy danych (SQLite/PostgreSQL w kontenerze), weryfikujące poprawność zapytań i integrację komponentów.

---

## 2. Pokrycie Historyjek Użytkownika (User Stories)

### Moduł Autoryzacji i Bezpieczeństwa

| ID | User Story | Testy E2E (Playwright) | Testy Backend (PHPUnit) |
| :--- | :--- | :--- | :--- |
| **US-10** | Rejestracja konta | `auth.register.spec.ts` | `AuthServiceTest.php` |
| **US-11** | Logowanie | `auth.login-logout.spec.ts` | `AuthServiceTest.php` |
| **US-12** | Wylogowanie | `auth.login-logout.spec.ts` | - |
| **US-15** | Przypomnienie hasła | `auth.password-reset.spec.ts` | `PasswordResetServiceTest.php` |
| **US-16** | Reset hasła | `auth.password-reset.spec.ts` | `PasswordResetServiceTest.php` |
| **US-17** | **Rate Limiting** | *Manualnie zweryfikowane* | `AuthPageControllerRateLimiterUnitTest.php` |

### Moduł Notatek (Notes)

| ID | User Story | Testy E2E (Playwright) | Testy Backend (PHPUnit) |
| :--- | :--- | :--- | :--- |
| **US-01** | Tworzenie notatki | `notes.create.spec.ts` | `NoteServiceTest.php` |
| **US-02** | Widok publiczny | `notes.visibility.spec.ts` | `NoteVoterTest.php` |
| **US-03** | Edycja notatki | `notes.edit.spec.ts` | `NoteServiceTest.php` |
| **US-04** | Zmiana widoczności | `notes.visibility.spec.ts` | `NoteServiceIntegrationTest.php` |
| **US-05** | Wyszukiwanie | `notes.search.spec.ts` | `NoteRepositoryTest.php` |
| **US-06** | Usuwanie notatki | `notes.interactions.spec.ts` | `NoteServiceTest.php` |
| **US-07** | Dashboard (pusty) | `notes.interactions.spec.ts` | - |
| **US-08** | Współedytorzy | `notes.collaboration.spec.ts` | `NoteCollaboratorServiceIntegrationTest.php` |
| **US-13** | **Samousunięcie** | `notes.collaboration-advanced.spec.ts` | `NoteCollaboratorServiceTest.php` |

---

## 3. Szczegółowe Scenariusze Testowe

### 3.1. Testy Backendowe (Logika i Integracja)
**Lokalizacja:** `tests/`

- **NoteCollaboratorServiceTest:** Weryfikacja logiki dodawania/usuwania współpracowników, w tym zasada, że współpracownik może usunąć tylko siebie, a właściciel każdego.
- **NoteRepositoryTest:** Testy skomplikowanych zapytań SQL dla wyszukiwania pełnotekstowego (TSVector) i filtrowania po etykietach.
- **NoteVoterTest:** Kluczowe testy uprawnień (Security) – kto może widzieć, edytować lub usuwać notatkę w zależności od statusu (Public/Private/Draft).
- **AuthPageControllerRateLimiterUnitTest:** Testy ochrony przed nadużyciami – sprawdzanie czy kontroler poprawnie blokuje żądania po przekroczeniu limitu prób.

### 3.2. Testy E2E (Interfejs i Flow)
**Lokalizacja:** `e2e/specs/`

- **notes.collaboration-advanced.spec.ts:**
    - **Self-Removal:** Współpracownik usuwa swój dostęp i traci możliwość edycji (redirect na dashboard).
    - **Owner Removal:** Właściciel usuwa współpracownika i wiersz znika z listy w czasie rzeczywistym.
- **notes.themes.spec.ts:** Weryfikacja wizualna specjalnych motywów (np. `todo`, `recipe`) – czy odpowiednie etykiety zmieniają wygląd notatki.
### Scenariusz: Interakcje UI i Logika JS
**Plik:** `ui.interaction.spec.ts`
- **Pokrycie PRD:** UX Enhancements
- **Kroki:**
    1. **Markdown Toolbar:** Weryfikacja wstawiania formatowania (pogrubienie, lista).
    2. **Tag Input:** Weryfikacja dodawania, usuwania i deduplikacji etykiet (case-insensitive).
    3. **Client Validation:** Weryfikacja blokady wysyłania formularza przy błędach walidacji (bez requestu do API).
    4. **Public Todo (Local):** Weryfikacja dodawania lokalnych zadań w widoku publicznym (interakcja JS).

### Scenariusz: Bezpieczeństwo - Ochrona XSS
**Pliki:** `notes.security-xss.spec.ts`, `MarkdownXssTest.php`
- **Cel:** Weryfikacja neutralizacji złośliwego kodu w opisie notatki.
- **Kroki:**
    1. Próba wstrzyknięcia tagów `<script>`, atrybutów `onerror` oraz pseudo-protokołów `javascript:`.
    2. Weryfikacja sanitizacji na poziomie backendu (PHPUnit).
    3. Weryfikacja braku wykonania kodu w przeglądarce (Playwright) w widoku publicznym.

### Scenariusz: Walidacja API (Robustness & Red Path)
**Pliki:** `ApiValidationTest.php`, `ApiRedPathTest.php`
- **Cel:** Weryfikacja odporności API na błędne dane, próby nieautoryzowanego dostępu oraz stabilność formatu JSON.
- **Kroki:**
    1. Przesłanie uszkodzonego JSON (Malformed).
    2. Przesłanie żądań z brakującymi polami obowiązkowymi.
    3. Testowanie limitów długości oraz niepoprawnych wartości Enum.
    4. **Bezpieczeństwo:** Próby dostępu do cudzych notatek (ID Manipulation).
    5. **Stabilność:** Przesyłanie błędnych typów danych (np. string zamiast array).
- **Wyniki:** API poprawnie odrzuca błędny JSON (400) i brak tokena (401). Wykryto braki w mapowaniu błędów typu oraz uprawnień (zwracane 500 zamiast 400/403).

---

## 3. Moduł Landing Page

| ID | Status | Uwagi |
| :--- | :--- | :--- |
| US-01 - US-08 | ✅ Pokryte | Pełne testy E2E i Backend |
| US-09 (Katalog) | ❌ Brak | Funkcjonalność nieprzetestowana |
| US-10 - US-12 | ✅ Pokryte | Pełne flow rejestracji i logowania |
| US-13 (Samousunięcie)| ✅ Pokryte | Testy E2E i Unit (Service) |
| US-15 - US-16 | ✅ Pokryte | Flow resetu hasła z Mailpit |
| US-17 (Rate Limit) | ✅ Pokryte | **Kluczowe pokrycie Unit Testami** |
| Security (XSS) | ✅ Pokryte | Testy Backend + E2E |
| Walidacja API | ✅ Pokryte | Pełne mapowanie błędów 400/403/404 |

---

## 5. Rekomendacje (Next Steps)
1. **Weryfikacja US-09:** Dodanie testów dla publicznego katalogu użytkownika.
2. **CI/CD Alignment:** Upewnienie się, że skrypty CI używają `doctrine:dbal:run-sql` zamiast przestarzałego `doctrine:query:sql`.