## Główny problem
Możliwość udostępnienia notatki, przepisu, listy todo, artykułu osobie lub grupie osób, dzięki czemu można podzielić się, albo samemu mieć dostęp w jednym miejscu do przepisów, code snippetów, zbioru przysłów, czy własnych pomysłów.
Jako zwykły użytkownik, mogę przeglądać i wyszukiwać udostępnione notatki danego użytkownika, a jako zalogowany wszystkie swoje notatki.
Możliwość zapraszania użytkowników do wspólnego edytowania danej notatki.

## Najmniejszy zestaw funkcjonalności
- Możliwość dodania notatki (po wcześniejszym zalogowaniu) zawierające: tytuł, opis (używając zaawansowanego markdown), przykładowe notatki: tekst, przepis, checklista, code snippet, artykuł, powiedzenie / myśl i inne, które mogą być notatką.

- Notatka powinna zawierać unikalny url, wygenerowany automatycznie podczas zapisu, url powinien być widoczny i możliwy do skopiowania, url można wygenerować wielokrotnie na żądanie przyciskiem "Generuj url".

- Notatka może być prywatna, wtedy jest możliwa do zobaczenia tylko dla zalogowanego właściciela,
albo publiczna, wtedy jest dostępna dla wszystkich, którzy znają url.

- Zalogowany użytkownik może zarządzać (CRUD) swoimi notatkami (edycja, generowanie urla, widoczność notatki) i labelami (tylko z poziomu edycji notatki, dodawanie i usuwanie, jeżeli label już istnieje powinien być zmergowany / nadpisany).

- Każdy zalogowany użytkownik dostaje swój unikalny url ID, który wskazuje na jego katalog dostępnych notatek, możliwych do wyszukania przez niezalogowanych użytkowników (po wejściu na wcześniej otrzymanu url)

- Jako właściciel notatki mogę ją udostępnić wielu osobom wpisując email osoby, której udostępniam (może być wiele maili), jeżeli osoba posiada konto i jest zalogowana, to po wejściu na url edycji notatki, może ją edytować (ale wyszukiwać może tylko własne notatki)

- Notatka może, ale nie musi zawierać etykiety (labele), labele mogą być wieloczłonowe i mogą zawierać polskie, jak i inne znaki używane w alfabecie.

- Zalogowany użytkownik widzi dashboard, na którym może wyszukać swoje notatki po opisie, tytule, labelach.

- Jeżeli nie ma notatek to jest przyjazny widok z komunikatem "Nie ma jeszcze notatek" i link do dodania nowej notatki

- Domyślnie jest 10 notatek na stronę z paginacją dostępną w urlu

- Notatki są posortowane od najnowszej i na liście notatek zawierają tytuł (po kliknięciu przechodzi na widok notatki), labele, skrócony do 255 znaków opis i datę utworzenia notatki, wszystko powinno być przedstawione UX friendly i responsive dla przeglądarek.

- Wygląd widoku notatki powinien być elegancki jak na 21 wiek, UX/UI friendly, przyjazny dla oka i przede wszystkim czytelny

- Jako osoba niezalogowana chcę móc wyszukać publiczne notatki danego użytkownika, analogicznie jak robi to zalogowany użytkownik, jeżeli nie ma takich notatek pokazuje się user friendly komunikat "Nie ma takiego użytkownika"

## Co NIE wchodzi w zakres MVP
Rozbudowane profile i zarządzanie
Historii wersji i panel admina
Telemetria i monitoring
Rozwiązywanie konfliktów podczas zapisu synchronizacji, ostatni wygrywa
Integracje mediami społecznościowymi itd.
Importy i exporty notatek
Akcje na kilku elementach jednocześnie
Udostępnianie linków i zasobów

## Kryteria sukcesu
Jako użytkownik mogę dodać notatki z różną widocznością i załącznikami.
Mogę wyszukać te notatki jako osoba zalogowana i je wyedytować.
Mogę dodać poprzez email użytkowników, którzy po zalogowaniu także mogą edytować notatki (ale wyszukiwać mogą jedynie własne).
Jako niezalogowany użytkownik, mogę wyszukać notatki publiczne danego użytkownika znając url do jego katalogu notatek.

## ZAŁOŻENIA:
Do markdown użyj gotowej bibliotek i taką samą zasadą kieruj się mając wątpliwości jak coś zaimplementować, to jest MVP.

Aplikacja powinnna mieć minimalistyczny, ale mieć nowoczesny UI/UX wygląd, powinien być przyjazny dla oka i korzystać z nowych technologii w js, css i html.

Obsługa błędów powinna być UI/UX friendly

Wszelkie wątpliwości powinny być rozwiązywane na korzyść prostoty i MVP pamiętając, że aplikacja MUSI działać.