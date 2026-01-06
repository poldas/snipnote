# Specyfikacja Funkcjonalności Snipnote

Snipnote to aplikacja MVP do tworzenia, organizowania i bezpiecznego udostępniania notatek z pełnym wsparciem Markdown.

## 1. Widoki Aplikacji

### Strona Główna (Landing Page) - Niezalogowany
*   **Hero Section**: Prezentacja głównego hasła aplikacji ("Notuj. Udostępniaj. Współpracuj").
*   **Zintegrowany Formularz Logowania**: Możliwość szybkiego powrotu do pracy bezpośrednio z pierwszej strony (ukrywa linki rejestracji, by nie dublować nawigacji).
*   **Sekcja Funkcji**: Prezentacja 6 kluczowych korzyści (Szybkość, Bezpieczeństwo, Udostępnianie, Wyszukiwanie, Wspólne notatki, Markdown).
*   **Sekcja "Jak to działa?"**: Edukacja użytkownika w 3 prostych krokach (Twórz, Organizuj, Udostępniaj).
*   **Nawigacja**: Szybki dostęp do logowania i rejestracji.

### Strony Autoryzacji (Auth)
*   **Rejestracja**: Zakładanie konta za pomocą adresu email i hasła (wymagana walidacja siły hasła).
*   **Logowanie**: Autoryzacja użytkownika z obsługą błędów i przekierowaniem na docelową stronę (parametr `redirect`).
*   **Odzyskiwanie Hasła**: Procedura resetowania zapomnianego hasła poprzez link wysyłany na email (token ważny 60 min).
*   **Potwierdzenie Email**: Wymóg aktywacji konta przed pełnym dostępem do funkcji tworzenia (krok 2 z 2 procesu rejestracji).

### Dashboard (Panel Użytkownika) - Zalogowany
*   **Galeria Notatek**: Wyświetlanie notatek w formie kart (grid) z informacją o dacie utworzenia, widoczności i skrótem treści (excerpt).
*   **Wyszukiwarka Globalna**: Główne pole wyszukiwania obsługujące tekst i etykiety.
*   **Panel Filtrów**: Szybki wybór zakresu widoczności (Wszystkie, Publiczne, Prywatne, Szkice, Udostępnione "For Me").
*   **Paginacja**: Obsługa 10 elementów na stronę z nawigacją "Poprzednia/Następna".

### Edycja i Dodawanie Notatki
*   **Tworzenie**: Formularz nowej notatki (tytuł, edytor Markdown, tagi).
*   **Edycja**: Aktualizacja treści notatek posiadanych lub udostępnionych.
*   **Zarządzanie Współedytorami**: Sekcja do dodawania/usuwania osób po adresie email.
*   **Strefa Zagrożenia**: Osobna sekcja dla operacji nieodwracalnych (usuwanie, regeneracja URL).

### Widok Publiczny Notatki - Anonimowy
*   **Reader Mode**: Przejrzysty układ do odczytu z wyrenderowanym Markdownem.
*   **Status Dostępności**: Automatyczne sprawdzanie, czy notatka nie zmieniła statusu na prywatną lub czy link nie został zregenerowany.

## 2. Szczegółowe Zasady Biznesowe (Business Rules)

### Zarządzanie Widocznością
1.  **Prywatna (Private)**: Dostęp ma tylko właściciel oraz osoby na liście współedytorów.
2.  **Publiczna (Public)**: Dostęp do odczytu ma każdy, kto posiada unikalny URL. Edycja zarezerwowana dla właściciela i współedytorów.
3.  **Szkic (Draft)**: Notatka widoczna **wyłącznie** dla właściciela. Współedytorzy tracą do niej dostęp do czasu zmiany statusu na wyższy.

### Logika Wyszukiwania i Sortowania
1.  **Sortowanie Domyślne**: Wszystkie listy notatek (Dashboard, Katalog) są zawsze sortowane malejąco po dacie utworzenia (`createdAt DESC`).
2.  **Wyszukiwanie Tekstowe**: Szuka podanej frazy w polach `title` oraz `description`.
3.  **Wyszukiwanie po Etykietach**:
    *   Użycie prefiksu `label:`.
    *   Wiele etykiet rozdzielonych przecinkiem (np. `label:kuchnia,przepis`).
    *   Logika **OR**: Notatka zostaje wyświetlona, jeśli posiada przynajmniej jedną z wymienionych etykiet.
4.  **Łączenie**: Możliwe jest łączenie wyszukiwania tekstowego z etykietami.

### Zarządzanie Dostępem (Uprawnienia)
| Akcja | Właściciel | Współedytor | Anonim |
| :--- | :---: | :---: | :---: |
| Odczyt notatki prywatnej | TAK | TAK | NIE |
| Odczyt notatki publicznej | TAK | TAK | TAK |
| Odczyt szkicu (Draft) | TAK | NIE | NIE |
| Edycja treści / tagów | TAK | TAK | NIE |
| Zmiana statusu widoczności | TAK | TAK | NIE |
| Zarządzanie współedytorami | TAK | TAK | NIE |
| Regeneracja URL notatki | TAK | TAK | NIE |
| Usuwanie notatki | TAK | NIE | NIE |
| Samousunięcie z notatki | N/A | TAK | NIE |

### Zasady Techniczne
1.  **Zapis Jawny**: Brak mechanizmu Auto-save. Każda zmiana wymaga kliknięcia "Zapisz".
2.  **Unikalny URL**: Generowany przy pierwszym zapisie. Regeneracja natychmiast unieważnia poprzedni link (404/403 dla starego linku).
3.  **Współpraca**: Dodanie adresu email, który nie istnieje w systemie, pozwala tej osobie na dostęp do notatki natychmiast po zarejestrowaniu konta na ten email.
4.  **Tokeny**: Access Token (krótki), Refresh Token (długi, rotowany przy każdym użyciu).

## 3. Komponenty Systemowe

### Komponenty Globalne
*   **Logo**: Zintegrowany element nawigacyjny z aurą hover.
*   **Alert**: Uniwersalny banner (Error/Success/Warning/Info) z obsługą przycisków akcji.
*   **Badge**: Statusy (Publiczne, Prywatne, Szkic, For Me).
*   **ConfirmModal**: Systemowe okno potwierdzeń z efektem glass-morphism.

### Komponenty Notatek
*   **NotesNav**: Nagłówek z mailem użytkownika i menu mobilnym.
*   **NoteCard / NoteRow**: Dwa tryby wyświetlania notatki na liście.
*   **NoteForm**: Centralny komponent formularza (Tytuł, Toggle Widoczności, Edytor, Tagi).
*   **PublicLink**: Sekcja zarządzania linkiem z funkcją "Kopiuj do schowka" (z fallbackiem).
*   **CollaboratorsPanel**: Interaktywna lista współedytorów z potwierdzeniem usunięcia.
*   **StickyActionBar**: Dolny pasek zapisu (Zapewnia dostępność przycisku Zapisz na długich notatkach).

### Formularze i Pola
*   **FormField**: Uniwersalne pole z animacją cienia (focus/hover).
*   **MarkdownToolbar**: Pasek narzędzi wspomagający formatowanie (Bold, Italic, Link, Code, Listy).
*   **SmartAutofocus**: Mechanizm nadający fokus na pole tylko na urządzeniach Desktop.