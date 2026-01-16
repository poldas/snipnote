# Dokument wymagań produktu (PRD) - Notes Sharing MVP

## 1. Przegląd produktu
Notes Sharing MVP to aplikacja internetowa umożliwiająca tworzenie, publiczne udostępnianie i współdzielenie notatek różnego typu (np. przepisy, checklisty, kod, artykuły, definicje). Użytkownik może udostępniać notatki innym osobom poprzez email. Notatki mogą być prywatne lub publiczne, dostępne poprzez unikalny URL. Aplikacja umożliwia współedytowanie notatek z innymi zalogowanymi użytkownikami.

Aplikacja ma być prosta, nowoczesna, wygodna i szybka w użytkowaniu. Jest to MVP, którego celem jest działający produkt, minimalizm funkcjonalny oraz szybkie wdrożenie.

Backend: PHP 8.2 + Symfony 7 + Doctrine ORM + PostgreSQL + Docker, z możliwością stosowania prostego podejścia Domain Driven Design (entity → service → repository).
Frontend: Tailwind (newest stable version) + Twig (newest stable version) + Htmx 2+

## 2. Problem użytkownika
Użytkownicy potrzebują miejsca, gdzie mogą:
- przechowywać i edytować własne notatki (różnego typu),
- szybko udostępnić wybrane notatki innym osobom,
- współdzielić edycję z innymi bez skomplikowanych narzędzi,
- udostępniać treści bez konieczności zakładania konta przez odbiorcę (tylko odczyt publiczny),
- zachować prywatność tam, gdzie to wymagane (notatki prywatne).

Istniejące narzędzia często:
- są zbyt rozbudowane (zbyt wiele funkcji, konieczność konfiguracji),
- nie mają możliwości uruchomienia aplikacji lokalnie,
- albo zbyt proste (brak współedycji, brak zarządzania widocznością i wyszukiwaniem).

## 3. Wymagania funkcjonalne
1. Tworzenie notatek zawierających:
   - tytuł (wymagany, max 255 znaków),
   - opis w formacie markdown (wymagany, max ok. 10000 znaków),
   - opcjonalne labele (pełny unicode bez emotek, lokalne dla notatki).
2. Edycja notatek (tytuł, opis, labele) dostępna dla właściciela i współedytorów.
3. Zapis zmian wyłącznie po kliknięciu przycisku „Zapisz” (brak auto-save).
4. Generowanie unikalnego, losowego URL notatki przy pierwszym zapisie.
5. Widoczność notatki:
   - prywatna (dostęp: właściciel + współedytorzy),
   - publiczna (dostęp do odczytu dla każdego znającego URL; edycja tylko dla właściciela i współedytorów).
6. Udostępnianie notatek innym użytkownikom:
   - poprzez dodanie ich adresu email do listy współedytorów,
   - dostęp przyznawany po zalogowaniu na konto z tym adresem email.
7. Współedytorzy mają takie same uprawnienia jak właściciel, z wyjątkiem usuwania notatki.
8. Możliwość usunięcia własnego dostępu przez współedytora (samousunięcie z listy współedytorów).
9. Wyszukiwanie notatek w dashboardzie zalogowanego użytkownika po:
    - tytule,
    - opisie,
    - labelach (za pomocą prefiksu "label:" i listy labeli rozdzielonych przecinkami, logika OR).
    - Paginacja: domyślnie 50 notatek na stronę.
10. Publiczny katalog użytkownika:
    - dostępny przez losowy UUID użytkownika (np. `/u/{uuid}`),
    - zawiera **wyłącznie** publiczne notatki tego użytkownika (nawet dla zalogowanego właściciela),
    - bezpieczne wyszukiwanie HTMX (POST + CSRF),
    - paginacja (domyślnie 50 notatek na stronę).
11. Widoki „brak danych”:
    - gdy użytkownik nie ma żadnych notatek w dashboardzie: przyjazny komunikat i link do dodania notatki,
    - gdy katalog użytkownika jest pusty (brak publicznych treści): pełnoekranowy komunikat o niedostępności, wizualnie identyczny z błędem 404 notatki.
12. Podgląd markdown:
    - prosty przycisk „Podgląd” w edycji notatki (bez live preview).
13. Możliwość skopiowania treści publicznej notatki (np. kod, przepis) bez ograniczeń.
14. Usunięcie notatki przez właściciela usuwa wszystkie powiązania:
    - labele, współedytorów, URL, wpis w katalogu publicznym.
15. Logowanie i rejestracja:
    - rejestracja przez email i hasło,
    - weryfikacja email w MVP,
    - logowanie przez email i hasło,
    - możliwość wylogowania.
16. Odświeżenie tokenu dostępu przy użyciu refresh tokenu:
    - krótkotrwały access token, dłużej ważny refresh token,
    - endpoint do wymiany refresh tokenu na nowy access token (bez ponownego logowania),
    - rotacja refresh tokenów i unieważnianie przy wylogowaniu / podejrzeniu wycieku.
17. Przypomnienie i reset hasła:
    - formularz „Nie pamiętasz hasła?” przyjmuje email i zwraca informację o wysłaniu instrukcji,
    - email resetujący zawiera jednorazowy token ważny przez ograniczony czas (np. 30–60 min),
    - formularz resetu pozwala ustawić nowe hasło po poprawnym tokenie,
    - token jest jednokrotnego użytku; po użyciu lub wygaśnięciu jest unieważniany.

## 4. Zasady biznesowe (reguły domenowe)
1. Logika biznesowa powinna być realizowana w warstwie domenowej, niezależnej od szczegółów frameworka i infrastruktury (DDD-friendly).
2. Spójność danych związanych z notatką (treść, widoczność, współdzielenie, URL, labele) powinna być utrzymywana w ramach jasno zdefiniowanego modelu domenowego (agregat lub zestaw agregatów).
3. Współedytor może:
   - edytować treść notatki,
   - edytować labele,
   - zmieniać widoczność notatki,
   - dodawać i usuwać innych współedytorów (w tym siebie),
   - nie może usunąć całej notatki.
5. Po usunięciu siebie z listy współedytorów użytkownik natychmiast traci dostęp do notatki.
6. Osoby niezalogowane nigdy nie mogą edytować notatek (również publicznych).
7. Notatka prywatna musi być niewidoczna dla osób niezalogowanych oraz zalogowanych, które nie są właścicielami ani współedytorami, nawet przy poprawnym URL.
8. Notatka publiczna:
   - jest dostępna do odczytu dla wszystkich, którzy znają URL,
   - pozostaje edytowalna tylko dla właściciela i współedytorów.
9. Labele są przypisane do konkretnej notatki (nie ma globalnego słownika labeli per użytkownik w MVP).
10. Wyszukiwanie po labelach odbywa się z użyciem prefiksu "label:"; podanie wielu labeli oznacza logikę OR (notatka pasuje, jeśli ma co najmniej jedną z podanych etykiet).
11. Zapis notatki (tworzenie/edycja) jest operacją wywoływaną jawnie przez użytkownika (przycisk „Zapisz”); brak autozapisów.
12. Zmiana widoczności notatki nie modyfikuje listy współedytorów (publiczność dotyczy wyłącznie odczytu dla anonimów).
13. Usunięcie notatki przez właściciela:
    - usuwa wszystkie połączone dane (URL, współedytorów, labele),
    - powoduje utratę dostępu dla wszystkich współedytorów.
14. Tokeny dostępu są krótkotrwałe; refresh tokeny są rotowane przy odświeżeniu i unieważniane przy wylogowaniu. Odświeżenie wymaga ważnego refresh tokenu, nie wymaga podania hasła; odświeżenie nie daje dodatkowych uprawnień ponad to, co ma użytkownik.

## 5. Granice produktu (zakres MVP)
- Brak historii wersji, cofania zmian i diffów.
- Brak rozbudowanych profili użytkowników (awatar, bio, social links).
- Brak globalnego wyszukiwania po wszystkich użytkownikach.
- Brak integracji z mediami społecznościowymi i zewnętrznymi providerami (OAuth, SSO).
- Brak importu/eksportu notatek (np. PDF, Markdown, JSON).
- Brak panelu administracyjnego i moderacji treści.
- Brak załączników (pliki, obrazy) w notatkach.
- Brak szczegółowej telemetrii, analityki i monitoringu w MVP.
- Brak skomplikowanego mechanizmu rozwiązywania konfliktów (ostatni zapis wygrywa).
- Rejestracja i logowanie ograniczone do prostego schematu email + hasło, bez weryfikacji email.

## 6. Historyjki użytkowników

### US-01: Tworzenie notatki
Tytuł: Tworzenie nowej notatki

Opis:
Jako zalogowany użytkownik
Chcę móc utworzyć nową notatkę z tytułem, opisem i opcjonalnymi labelami
Aby zapisywać i organizować własne treści

Kryteria akceptacji:
- Formularz tworzenia notatki zawiera pola: tytuł (wymagane), opis (wymagane), labele (opcjonalne).
- Tytuł nie może przekroczyć 255 znaków; przy przekroczeniu wyświetlany jest czytelny komunikat błędu.
- Opis nie może przekroczyć założonego limitu (ok. 10000 znaków); przy przekroczeniu wyświetlany jest komunikat błędu.
- Po kliknięciu „Zapisz” notatka zostaje utworzona i zapisana w systemie.
- Przy pierwszym zapisie notatki generowany jest unikalny, losowy URL.
- Domyślnie widoczność nowej notatki jest ustawiona na prywatną.
- Tworzenie notatki jest możliwe tylko dla zalogowanego użytkownika; niezalogowany nie widzi formularza lub jest przekierowany do logowania.
- Mogę dodać label z dowolnym tekstem zawierającym znaki alfanumeryczne.
- Duplikaty labeli są scalane (dodanie już istniejącego labela wyświetli tylko jeden).


### US-02: Wyświetlenie publicznej notatki po URL
Tytuł: Widok pojedynczej publicznej notatki

Opis:
Jako osoba niezalogowana lub zalogowana bez dostępu do edycji
Chcę móc zobaczyć treść publicznej notatki po jej URL
Aby przeczytać udostępnioną treść bez konieczności zakładania konta

Kryteria akceptacji:
- Wejście na URL publicznej notatki wyświetla stronę zawierającą co najmniej: tytuł, opis (markdown w formie renderowanej), labele oraz datę utworzenia.
- Widok nie umożliwia edycji notatki (brak przycisków edycji, zapisu, zarządzania współedytorami).
- Treść notatki może być swobodnie kopiowana (np. kod, przepis, tekst).
- Jeśli notatka została ustawiona jako prywatna lub URL jest nieważny, wyświetlany jest przyjazny komunikat o braku dostępu / błędnym linku.
- Jeśli użytkownik jest zalogowany i posiada uprawnienia (właściciel lub współedytor), widzi przyciski pozwalające na przejście do trybu edycji tej notatki.
- W przypadku błędnego lub nieistniejącego URL notatki wyświetlany jest komunikat „Notatka niedostępna” lub podobny przyjazny komunikat.

### US-03: Edycja notatki
Tytuł: Edycja istniejącej notatki

Opis:
Jako zalogowany użytkownik (właściciel lub współedytor notatki)
Chcę móc edytować tytuł, opis i labele istniejącej notatki
Aby aktualizować jej treść i metadane

Kryteria akceptacji:
- Formularz edycji wyświetla aktualne wartości tytułu, opisu i labeli.
- Po kliknięciu w przycisk "Podgląd" można przejść na widok notatki (US-02: Wyświetlenie publicznej notatki po URL).
- Edycja jest możliwa tylko dla właściciela i współedytorów; inni zalogowani użytkownicy nie mogą wejść w tryb edycji.
- Po kliknięciu „Zapisz” zmiany są zapisywane i widoczne w widoku notatki oraz w dashboardzie, po zapisie następuje przekierowanie na dashboard.
- Jeśli użytkownik próbuje edytować notatkę bez uprawnień, otrzymuje komunikat o braku dostępu.
- Walidacje pól (długość tytułu, opisu) działają analogicznie jak przy tworzeniu.

### US-04: Zmiana widoczności notatki
Tytuł: Ustawienie widoczności notatki

Opis:
Jako właściciel lub współedytor notatki
Chcę móc zmienić widoczność notatki między prywatną a publiczną
Aby kontrolować, kto może ją odczytać

Kryteria akceptacji:
- Użytkownik z uprawnieniami (właściciel lub współedytor) widzi przełącznik widoczności (np. prywatna/publiczna).
- Ustawienie widoczności na publiczną powoduje, że notatka jest dostępna do odczytu przez wszystkich znających URL (bez logowania).
- Ustawienie widoczności na prywatną powoduje, że dostęp mają tylko właściciel i współedytorzy, a niezalogowani oraz inni zalogowani użytkownicy nie mogą zobaczyć notatki, nawet z poprawnym URL.
- Zmiana widoczności nie modyfikuje listy współedytorów.
- Zmiana widoczności jest zapisywana dopiero po kliknięciu „Zapisz”.

### US-05: Wyszukiwanie notatek w dashboardzie
Tytuł: Wyszukiwanie notatek po tekście i labelach

Opis:
Jako zalogowany użytkownik
Chcę móc wyszukiwać swoje notatki po tytule, opisie i labelach
Aby szybko odnaleźć potrzebną treść

Kryteria akceptacji:
- Dashboard zawiera jedno pole wyszukiwania tekstowego.
- Bez prefiksu wyszukiwanie obejmuje tytuł i opis notatek.
- Użycie prefiksu "label:" pozwala podać jedną lub więcej nazw labeli rozdzielonych przecinkami.
- Wyszukiwanie po wielu labelach działa w logice OR (notatka pasuje, jeśli ma co najmniej jedną z podanych etykiet).
- Wyszukiwanie jest ograniczone do notatek, do których użytkownik ma dostęp (tylko własne).
- Wyniki wyszukiwania są paginowane (domyślnie 50 na stronę).
- W przypadku braku wyników wyświetlany jest przyjazny komunikat informujący o braku pasujących notatek.
- Sortownie zawsze jest od najnowszej notatki, nie ma potrzeby zmiany sortowania.
- Kliknięcie w notatkę z listy powoduje przejście na stronę edycji.

### US-06: Usuwanie notatki
Tytuł: Usunięcie notatki

Opis:
Jako właściciel notatki
Chcę mieć możliwość usunięcia notatki
Aby móc pozbyć się niepotrzebnych lub błędnych treści

Kryteria akceptacji:
- Usuwanie notatki jest dostępne tylko dla właściciela (współedytor nie widzi opcji „Usuń notatkę”).
- Usuwanie notatki jest możliwe przez widok edycji notatki jak też na liście notatek zalogowanego użytkownika.
- Po potwierdzeniu usunięcia notatka jest trwale usuwana z systemu.
- Wraz z notatką usuwane są wszystkie powiązane dane: labele, współedytorzy, powiązany URL, obecność w katalogu publicznym.
- Po usunięciu notatki właściciel jest przekierowany na dashboard.
- Współedytorzy tracą dostęp do notatki; próba wejścia na poprzedni URL skutkuje brakiem dostępu/komunikatem błędu.
- Usuwanie jest operacją nieodwracalną w ramach MVP (brak kosza).
- Przed usunięciem notatki użytkownik musi potwierdzić operację (np. popup / modal „Czy na pewno?”).

### US-07: Dashboard bez notatek
Tytuł: Pusty stan listy notatek

Opis:
Jako nowy użytkownik
Chcę zobaczyć przyjazny ekran zamiast pustej listy notatek
Aby wiedzieć, że mogę rozpocząć od dodania pierwszej notatki

Kryteria akceptacji:
- Jeśli użytkownik nie ma żadnych własnych notatek, dashboard wyświetla komunikat „Nie ma jeszcze notatek”.
- Obok komunikatu widoczny jest przycisk „Dodaj notatkę”.
- Jeśli użytkownik ma współdzielone notatki, ale nie ma własnych, dashboard wyświetla komunikat „Nie ma jeszcze notatek”.

### US-08: Udostępnienie notatki współedytorowi
Tytuł: Dodanie współedytora po emailu

Opis:
Jako właściciel lub współedytor notatki
Chcę móc dodać współedytora, podając jego adres email
Aby umożliwić mu edycję notatki

Kryteria akceptacji:
- W widoku edycji notatki dostępna jest sekcja „Współedytorzy” z polem do dodania emaila z prostą walidacją na email.
- Po wpisaniu poprawnego adresu email i zapisaniu, email jest dopisany do listy współedytorów.
- Jeśli użytkownik z podanym emailem posiada konto i jest zalogowany, po wejściu na URL edycji notatki może ją edytować.
- Współedytor dodany w ten sposób ma takie same uprawnienia jak właściciel, z wyjątkiem możliwości usunięcia notatki.
- Jeśli email jest podany wielokrotnie, system nie tworzy duplikatów na liście współedytorów.
- Jeśli współedytor zostanie usunięty z listy i później dodany ponownie, ponownie uzyskuje dostęp.

### US-09: Przegląd publicznego katalogu użytkownika
Tytuł: Publiczny profil i wyszukiwarka treści

Opis:
Jako osoba niezalogowana lub zalogowana
Chcę móc zobaczyć publiczne notatki danego użytkownika oraz przeszukiwać je
Aby móc korzystać z udostępnionej bazy wiedzy danej osoby

Kryteria akceptacji:
- Wejście na URL `/u/{uuid}` wyświetla listę publicznych notatek właściciela.
- **Zasada Widoczności**: Katalog ZAWSZE pokazuje tylko notatki o statusie `public`. Zalogowany właściciel widzi w tym miejscu swój podgląd publiczny (bez notatek prywatnych/szkiców).
- **Bezpieczeństwo**: Wyszukiwanie w katalogu odbywa się asynchronicznie (HTMX) metodą POST z wymaganym tokenem CSRF i nagłówkiem AJAX, co utrudnia automatyczne pobieranie danych (scraping).
- **Deep Linking**: Parametry wyszukiwania (`q`) nie są widoczne w URL, aby zachować czystość linków publicznych i prywatność zapytań.
- **Empty State**: Jeśli użytkownik nie ma publicznych notatek, wyświetlany jest wyśrodkowany kontener `pn-error` z komunikatem "Notatki niedostępne lub nieprawidłowy link".
- Lista jest paginowana (50/strona), a nawigacja między stronami zachowuje filtry wyszukiwania bez zmiany adresu URL.

### US-010: Rejestracja konta
Tytuł: Rejestracja użytkownika przez email

Opis:
Jako nowy użytkownik
Chcę zarejestrować konto za pomocą adresu email i hasła  
Aby móc tworzyć i edytować własne notatki

Kryteria akceptacji:
- Formularz rejestracji zawiera pola: email, hasło.
- Email jest walidowany pod kątem poprawnego formatu.
- Hasło musi spełniać minimalne wymagania (np. min. 8 znaków; szczegółowe zasady mogą być doprecyzowane technicznie).
- Jeśli email jest już zarejestrowany, użytkownik otrzymuje komunikat o duplikacie.
- Po poprawnej rejestracji użytkownik jest automatycznie zalogowany.
- Po rejestracji użytkownik trafia na dashboard (listę swoich notatek; na początku pustą).
- Weryfikacja realizowana jest poprzez link aktywacyjny wysyłany mailem.

### US-011: Logowanie użytkownika
Tytuł: Logowanie istniejącego użytkownika

Opis:
Jako istniejący użytkownik
Chcę móc zalogować się używając swojego emaila i hasła
Aby uzyskać dostęp do swoich notatek i notatek współdzielonych

Kryteria akceptacji:
- Formularz logowania zawiera pola: email, hasło.
- Podanie poprawnych danych logowania powoduje zalogowanie i przekierowanie na dashboard użytkownika.
- Podanie niepoprawnych danych wyświetla czytelny komunikat o błędnych danych logowania.
- Po zalogowaniu użytkownik ma dostęp do swoich notatek.
- Próba wejścia na zasób wymagający logowania (np. edycja notatki) przez niezalogowanego użytkownika powoduje przekierowanie na ekran logowania.

### US-012: Wylogowanie użytkownika
Tytuł: Wylogowanie z aplikacji

Opis:
Jako zalogowany użytkownik
Chcę mieć możliwość wylogowania się z aplikacji
Aby zabezpieczyć swoje konto przed nieautoryzowanym dostępem

Kryteria akceptacji:
- W nawigacji lub menu użytkownika widoczna jest opcja „Wyloguj” dla zalogowanego użytkownika.
- Kliknięcie „Wyloguj” powoduje unieważnienie sesji użytkownika.
- Po wylogowaniu użytkownik jest przekierowany na stronę startową/landing page.
- Próba wejścia po wylogowaniu na strony wymagające logowania (np. dashboard, edycja notatki) skutkuje przekierowaniem na ekran logowania.
- Opcja „Wyloguj” nie jest widoczna dla użytkowników niezalogowanych.

### US-013: Usunięcie własnego dostępu współedytora
Tytuł: Współedytor usuwa siebie z notatki

Opis:
Jako współedytor notatki
Chcę móc usunąć swój dostęp do notatki
Aby przestać mieć możliwość jej edycji

Kryteria akceptacji:
- Współedytor widzi siebie na liście współedytorów i może wybrać opcję usunięcia swojego adresu email.
- Po usunięciu własnego wpisu na liście współedytorów współedytor traci natychmiast uprawnienia do notatki.
- Po zapisaniu zmian współedytor jest przekierowany na swój dashboard (lista jego notatek).
- Przy ponownej próbie wejścia na URL edycji notatki użytkownik widzi komunikat o braku dostępu.
- Właściciel nadal widzi notatkę i może ponownie dodać tego współedytora w przyszłości.

### US-014: Odświeżenie tokenu dostępu
Tytuł: Pozyskanie nowego access tokenu z refresh tokenu

Opis:
Jako zalogowany użytkownik z wygasłym lub wygasającym access tokenem
Chcę otrzymać nowy access token przy użyciu ważnego refresh tokenu
Aby nie musieć ponownie podawać hasła w trakcie sesji

Kryteria akceptacji:
- Odświeżenie odbywa się przez dedykowany endpoint, bez podawania emaila i hasła; wymagany jest refresh token.
- Po poprawnym odświeżeniu użytkownik otrzymuje nowy access token oraz zrotowany refresh token (z nową datą ważności).
- Jeśli refresh token jest nieważny, wygasły lub zablokowany, użytkownik dostaje komunikat o konieczności ponownego logowania (401) i nie otrzymuje nowych tokenów.
- Wylogowanie unieważnia refresh token, co uniemożliwia dalsze odświeżanie bez ponownego logowania.
- Odświeżanie jest ograniczone mechanizmem rate limiting, aby zapobiegać nadużyciom.

### US-015: Przypomnienie hasła
Tytuł: Wysłanie linku resetu hasła

Opis:
Jako użytkownik, który nie pamięta hasła
Chcę otrzymać email z linkiem do jego zresetowania
Aby móc odzyskać dostęp do konta

Kryteria akceptacji:
- Na stronach logowania/rejestracji dostępny jest link „Nie pamiętasz hasła?” prowadzący do formularza z polem email.
- Podanie poprawnego emaila (niezależnie od istnienia konta) zwraca komunikat o wysłaniu instrukcji, bez ujawniania statusu konta.
- Podanie niepoprawnego formatu emaila zwraca komunikat walidacyjny.
- Wysłany email zawiera jednorazowy token resetu ważny ograniczony czas (np. 30–60 min).
- Wielokrotne żądania resetu mogą być ograniczane mechanizmem rate limiting.

### US-016: Reset hasła po tokenie
Tytuł: Ustawienie nowego hasła po otrzymanym linku

Opis:
Jako użytkownik, który otrzymał link resetu
Chcę ustawić nowe hasło
Aby ponownie móc się zalogować

Kryteria akceptacji:
- Wejście na link z tokenem resetu wyświetla formularz z polami: nowe hasło, potwierdzenie hasła (opcjonalnie), przycisk „Zapisz”.
- Token jest jednorazowy i ma ograniczoną ważność; próba użycia nieważnego/zużytego tokenu zwraca komunikat o konieczności ponownego wygenerowania.
- Nowe hasło musi spełniać minimalne wymagania (np. min. 8 znaków); błędy walidacji są wyświetlane.
- Po pomyślnym resecie użytkownik jest automatycznie logowany lub przekierowany do logowania z informacją o sukcesie (decyzja techniczna).
- Po użyciu token staje się nieważny.

### US-017: Ochrona przed nadużyciami (Rate Limiting)
Tytuł: Limity żądań dla wrażliwych akcji

Opis:
Jako system
Chcę ograniczać liczbę wysyłanych wiadomości email (weryfikacja, reset hasła)
Aby zapobiec nadużyciom, spamowi i kosztom infrastruktury

Kryteria akceptacji:
- Akcja ponownego wysłania linku weryfikacyjnego jest ograniczona (np. 3 próby na 15 min).
- Akcja żądania resetu hasła jest ograniczona (np. 5 próby na 15 min na IP).
- Po przekroczeniu limitu użytkownik otrzymuje jasny komunikat o blokadzie czasowej.
- Limity są niezależne dla różnych typów akcji.

## 7. Metryki sukcesu
W ramach MVP nie definiuje się szczegółowych metryk biznesowych ani rozbudowanej analityki. Sukces MVP jest rozumiany jako:

- Spełnienie wszystkich opisanych wymagań funkcjonalnych i reguł domenowych.
- Poprawne działanie mechanizmu widoczności i dostępu (prywatne/publiczne, współedytorzy).
- Możliwość tworzenia, wyszukiwania, udostępniania i współdzielenia notatek bez błędów krytycznych.
- Intuicyjność interfejsu na tyle, aby użytkownik nie potrzebował dokumentacji do podstawowych działań.
- Prosty i profesjonalny kod zgodny ze standardami.