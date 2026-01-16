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
*   **Polityka Zero JS**: Strony logowania, rejestracji i resetu hasła działają bez JavaScriptu (standardowe formularze HTML + POST) dla maksymalnej niezawodności i bezpieczeństwa.
*   **Rejestracja**: Zakładanie konta za pomocą adresu email i hasła (min. 8 znaków).
*   **Logowanie**: Autoryzacja użytkownika z obsługą błędów i automatycznym zapamiętywaniem ostatniego maila w sesji (prefill).
*   **Odzyskiwanie Hasła**: Procedura resetowania zapomnianego hasła poprzez link wysyłany na email (token ważny 60 min).
    *   **Bezpieczeństwo**: System zawsze wyświetla ten sam komunikat o wysłaniu wiadomości, aby zapobiec wyciekowi bazy maili (user enumeration).
    *   **Obsługa Kont Niezweryfikowanych**: Jeśli użytkownik poda email do konta, które nie zostało jeszcze potwierdzone, zamiast linku do resetu hasła otrzyma **nowy link aktywacyjny**, aby umożliwić mu dokończenie rejestracji.
    *   **Brak Konta**: Jeśli podany adres nie istnieje w bazie, system nie wysyła żadnej wiadomości.
*   **Potwierdzenie Email**: Wymóg aktywacji konta przed pełnym dostępem do funkcji (krok 2 z 2 procesu rejestracji).
*   **Rate Limiting (Ochrona przed spamem)**:
    *   **Weryfikacja Email**: Limit **3 prób** ponownego wysłania linku na 15 minut. Po przekroczeniu, użytkownik widzi komunikat błędu i żądanie jest blokowane.
    *   **Reset Hasła**: Limit **5 prób** wysłania żądania resetu na 15 minut dla danego adresu IP.

### Dashboard (Panel Użytkownika) - Zalogowany
*   **Galeria Notatek**: Wyświetlanie notatek w formie kart (grid) z informacją o dacie modyfikacji, statusie i skrótem treści.
*   **Wyszukiwarka Globalna**: Główne pole wyszukiwania obsługujące tekst (tytuł/opis) oraz etykiety. Limit zapytania: 200 znaków.
*   **Panel Filtrów**: Szybki wybór zakresu widoczności (Wszystkie, Publiczne, Prywatne, Szkice, Udostępnione "For Me").
*   **Kopiowanie Linków**:
    *   Dla notatek **Publicznych** i **Prywatnych**: kopiuje link do widoku publicznego (`/n/{token}`).
    *   Dla **Szkiców (Draft)**: kopiuje link bezpośrednio do edytora (`/notes/{id}/edit`).
*   **Paginacja**: Obsługa **50 elementów** na stronę z nawigacją "Poprzednia/Następna".

### Publiczny Katalog Użytkownika - Anonimowy i Zalogowany
*   **Profil Twórcy**: Dostępny pod adresem `/u/{uuid}`. Prezentuje wyłącznie notatki oznaczone jako **Publiczne**.
*   **Bezpieczne Wyszukiwanie**: 
    *   Wyszukiwarka działa w oparciu o **HTMX + GET**, co umożliwia **Deep Linking** i synchronizację adresu URL.
    *   Ochrona przed botami realizowana przez pole typu **Honeypot**.
    *   Wymagany nagłówek `X-Requested-With: XMLHttpRequest` dla ochrony przed prostymi botami przy żądaniach AJAX.
*   **Pusta Galeria (Empty State)**: Gdy użytkownik nie posiada publicznych notatek, wyświetlany jest pełnoekranowy komunikat błędu (wizualnie spójny z brakiem notatki), informujący o niedostępności treści lub błędnym linku.
*   **Paginacja AJAX**: Przechodzenie między stronami wyników (50 na stronę) odbywa się bez przeładowania całej strony i synchronizuje adres URL.

### Edycja i Dodawanie Notatki
*   **Tworzenie**: Formularz nowej notatki (tytuł, edytor Markdown, tagi).
*   **Edycja**: Aktualizacja treści notatek posiadanych lub udostępnionych.
*   **Skróty Klawiszowe**:
    *   `Ctrl+S` (lub `Cmd+S` na Mac): Szybki zapis notatki i powrót do Dashboardu.
*   **Dynamiczne Tłumaczenia**: Opisy widoczności oraz pola formularza są zarządzane przez system tłumaczeń Symfony, co pozwala na łatwą lokalizację aplikacji.
*   **Wzbogacony Markdown**: Wsparcie dla tabel, obrazków z zewnętrznych serwisów, list zadań oraz automatycznych linków do nagłówków (permalinki).
*   **Zarządzanie Współedytorami**: Sekcja do dodawania/usuwania osób po adresie email.
*   **Strefa Zagrożenia**: Sekcja dla operacji nieodwracalnych (usuwanie notatki). Zawiera wyraźne ostrzeżenia: "Operacje nieodwracalne. Używaj ostrożnie.".

### Widok Publiczny Notatki - Anonimowy
*   **Reader Mode**: Przejrzysty układ do odczytu z wyrenderowanym Markdownem.
*   **Interaktywne Listy zadań**: Publiczne notatki z tagiem `todo` pozwalają użytkownikom na lokalną interakcję (zapis w `localStorage`).
*   **Status Dostępności**: Automatyczne sprawdzanie uprawnień (prywatne notatki wymagają zalogowania, szkice są niedostępne publicznie).

## 2. Szczegółowe Zasady Biznesowe (Business Rules)

### Zarządzanie Widocznością
1.  **Prywatna (Private)**: Dostęp do odczytu i edycji ma właściciel oraz osoby na liście współedytorów. Widok publiczny (`/n/`) dostępny tylko po zalogowaniu dla uprawnionych.
2.  **Publiczna (Public)**: Dostęp do odczytu ma każdy (również niezalogowani). Edycja zarezerwowana dla właściciela i współedytorów.
3.  **Szkic (Draft)**: Notatka widoczna **wyłącznie** dla właściciela. Współedytorzy tracą do niej dostęp do czasu zmiany statusu. Brak widoku publicznego.

### Logika Wyszukiwania i Sortowania
1.  **Sortowanie Domyślne**: Wszystkie listy notatek (Dashboard, Katalog, Udostępnione) są zawsze sortowane malejąco po dacie **ostatniej modyfikacji** (`updatedAt DESC`). Każda edycja treści powoduje przesunięcie notatki na górę listy.
3.  **Wyszukiwanie po Etykietach**:
    *   Użycie prefiksu `label:`.
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
| Usuwanie notatki | TAK | NIE | NIE |
| Samousunięcie z notatki | N/A | TAK | NIE |

### Zasady Techniczne
1.  **Limity Danych**: Tytuł do 255 znaków, Opis do **100 000 znaków** (Markdown).
2.  **Zapis Jawny**: Brak mechanizmu Auto-save. Każda zmiana wymaga kliknięcia "Zapisz".
3.  **Asset Security**: Wykorzystanie `importmap_safe` do ukrywania wewnętrznej struktury JavaScript przed użytkownikami niezalogowanymi.
4.  **Współpraca**: Dodanie adresu email, który nie istnieje w systemie, pozwala tej osobie na dostęp do notatki natychmiast po zarejestrowaniu konta na ten email.
5.  **Bezpieczeństwo**: Własna implementacja JWT (Access Token) z rotacją Refresh Tokenów.

## 3. Komponenty Systemowe

### Komponenty Globalne
*   **Logo**: Zintegrowany element nawigacyjny z aurą hover i animacją skali.
*   **Alert**: Uniwersalny banner (Error/Success/Warning/Info) z obsługą przycisków akcji.
*   **Badge**: Statusy (Publiczne, Prywatne, Szkic, For Me).
*   **ConfirmModal**: Systemowe okno potwierdzeń (usuwanie notatek, usuwanie współpracowników).

### Komponenty Notatek
*   **NotesNav**: Nagłówek z mailem użytkownika, menu mobilnym i szybkim dodawaniem.
*   **NoteCard**: Karta notatki w galerii (grid).
*   **NoteForm**: Formularz z dynamicznym licznikiem znaków i tagami (Client-side validation).
*   **TagInput**: Komponent z obsługą chipów, deduplikacją (case-insensitive) i specjalnymi motywami (`todo`, `recipe`).
*   **PublicTodo**: Specjalistyczny kontroler JS do obsługi list zadań z mergowaniem stanu lokalnego i zdalnego.
*   **StickyActionBar**: Pasek akcji przypięty do dołu ekranu dla wygody edycji długich treści.

### Formularze i Pola
*   **FormField**: Uniwersalne pole wejściowe (modern-input) z animacją cienia.
*   **MarkdownToolbar**: Wsparcie dla formatowania bezpośrednio nad polem tekstowym.
