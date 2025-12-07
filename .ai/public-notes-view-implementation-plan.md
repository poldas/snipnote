# Plan implementacji widoku Publiczny odczyt notatki

## 1. Przegląd
Widok prezentuje publiczną notatkę pod URL `/notes/public/{url_token}`. Użytkownik (anonim lub zalogowany) widzi tytuł, wyrenderowany i zsanityzowany opis (markdown → HTML), labele oraz datę utworzenia. Jeśli ma uprawnienia edycji (właściciel lub współedytor), zobaczy CTA prowadzące do trybu edycji; w innym przypadku CTA do logowania/edytowania. Przy błędnym lub prywatnym URL wyświetlany jest przyjazny komunikat „Notatka niedostępna”.

## 2. Routing widoku
`GET /notes/public/{url_token}` – strona publicznej notatki (SSR Twig + HTMX fallback do ewentualnych częściowych odświeżeń, brak edycji).

## 3. Struktura komponentów
- LayoutPublicNotePage
  - PublicNoteHeader
  - PublicNoteMetaBar
  - PublicNoteContent
  - LabelsList
  - ActionsBar
  - EmptyOrErrorState (renderowany warunkowo przy 403/404/błędach sieci)

## 4. Szczegóły komponentów
### LayoutPublicNotePage
- Opis: Strona kontenera; odpowiada za pobranie danych, zarządzanie stanem ładowania/błędu oraz render dzieci.
- Główne elementy: sekcja wrapper, grid/stack, miejsca na nagłówek, metadane, treść, akcje.
- Obsługiwane interakcje: inicjalny fetch; ewentualne ponowienie po błędzie (przycisk „Spróbuj ponownie”).
- Obsługiwana walidacja: brak własnej walidacji; sprawdza jedynie obecność `url_token` w ścieżce.
- Typy: `PublicNoteViewModel`, `PublicNoteApiResponse`, `PublicNoteState`.
- Propsy: `urlToken` (string), ewentualnie `currentUserCanEdit` (bool, jeśli back-end przekazuje przez SSR kontekst auth).

### PublicNoteHeader
- Opis: Prezentuje tytuł notatki z semantycznym nagłówkiem.
- Główne elementy: `<h1>`, opcjonalne ikony statusu.
- Obsługiwane interakcje: brak.
- Walidacja: wyświetla placeholder „(Bez tytułu)” jeśli pole puste.
- Typy: `PublicNoteViewModel`.
- Propsy: `title` (string).

### PublicNoteMetaBar
- Opis: Pasek metadanych (data utworzenia, liczba labeli).
- Główne elementy: `<time datetime="...">`, małe badge z liczbą labeli.
- Obsługiwane interakcje: brak.
- Walidacja: poprawny format daty (ISO string → `Date`).
- Typy: `PublicNoteViewModel`.
- Propsy: `createdAt` (Date | string), `labelsCount` (number).

### PublicNoteContent
- Opis: Renderuje zsanityzowany HTML opisu w bezpiecznym kontenerze.
- Główne elementy: `<article>` z klasą prose (Tailwind), `data-htmx-target` jeśli HTMX copy-to-clipboard.
- Obsługiwane interakcje: selekcja/ kopiowanie tekstu; opcjonalny przycisk „Kopiuj kod” dla bloków `<code>`.
- Walidacja: brak HTML z niesprawdzonych źródeł; używa `{{ description|raw }}` tylko gdy serwer gwarantuje sanitizację.
- Typy: `PublicNoteViewModel`.
- Propsy: `descriptionHtml` (string, safe).

### LabelsList
- Opis: Lista labeli jako badge’y; ukryta gdy brak labeli.
- Główne elementy: `<ul>`, `<li>` badge.
- Obsługiwane interakcje: brak.
- Walidacja: filtruje puste stringi; maks. długość pojedynczej etykiety 100 znaków (zgodnie z domeną).
- Typy: `PublicNoteViewModel`.
- Propsy: `labels` (string[]).

### ActionsBar
- Opis: Sekcja akcji końcowych: CTA „Edytuj notatkę” (gdy uprawnienia) lub „Zaloguj, aby edytować”.
- Główne elementy: `<a>` button primary, secondary link do logowania.
- Obsługiwane interakcje: kliknięcie CTA.
- Walidacja: render warunkowy w zależności od `canEdit`.
- Typy: `PublicNoteActions`.
- Propsy: `canEdit` (bool), `editUrl` (string), `loginUrl` (string).

### EmptyOrErrorState
- Opis: Przyjazny komunikat gdy 403/404/inny błąd.
- Główne elementy: ikonka/emoji, nagłówek, opis, link powrotny.
- Obsługiwane interakcje: przycisk „Spróbuj ponownie” (ponawia fetch), link do strony głównej.
- Walidacja: mapowanie kodu błędu na treść komunikatu.
- Typy: `PublicNoteErrorState`.
- Propsy: `status` (403|404|0), `onRetry?` (fn).

## 5. Typy
- `PublicNoteApiResponse`: `{ data: { title: string; description: string; labels: string[]; created_at: string } }`
- `PublicNoteViewModel`: `{ title: string; descriptionHtml: string; labels: string[]; createdAt: string; canEdit: boolean; editUrl?: string; loginUrl?: string }`
- `PublicNoteState`: `{ status: 'loading'|'ready'|'error'; note?: PublicNoteViewModel; errorCode?: 403|404|0 }`
- `PublicNoteActions`: `{ canEdit: boolean; editUrl?: string; loginUrl?: string }`
- `PublicNoteErrorState`: `{ statusCode: 403|404|0; message: string; ctaLabel?: string }`

## 6. Zarządzanie stanem
- SSR preferowane: dane notatki wstrzyknięte do kontekstu Twig (np. `noteJson`) lub HTMX `hx-get` do `/api/public/notes/{token}` przy pierwszym załadowaniu.
- Stan lokalny JS (minimalny, vanilla/HTMX): `loading` (początkowo true gdy fetch klientowy), `note`, `errorCode`.
- Brak globalnego store. Custom hook niepotrzebny; prosty inicjalizator skryptu inline lub mały moduł JS do copy-to-clipboard przy blokach `<code>`.

## 7. Integracja API
- Endpoint: `GET /api/public/notes/{url_token}` (publiczny).
- Wejście: `url_token` z path.
- Wyjście (200): `PublicNoteApiResponse`. Błędy: 403 (notatka prywatna), 404 (nie znaleziono), inne 5xx.
- Mapowanie do VM: `description` to już HTML z backendu (sanityzowany), `created_at` → string/Date, `labels` bez zmian.
- Fetch: SSR preferowane; alternatywnie HTMX `hx-get` w kontenerze, obsługa `hx-target` na kontener treści i błędów.

## 8. Interakcje użytkownika
- Wejście na stronę: automatyczne pobranie i render danych.
- Klik „Edytuj notatkę”: przekierowanie do `/notes/{url_token}/edit` (lub ścieżka zdefiniowana w aplikacji).
- Klik „Zaloguj, aby edytować”: przekierowanie do `/login?redirect=/notes/public/{token}`.
- Klik „Spróbuj ponownie” przy błędzie: powtórny fetch.
- Kopiowanie kodu: opcjonalny przycisk przy blokach `<code>` (JS dodany po renderze).

## 9. Warunki i walidacja
- Brak formularzy; walidacja sprowadza się do:
  - Sprawdzenie obecności `url_token` w ścieżce (routing).
  - Sprawdzenie statusu odpowiedzi: 200 → render, 403/404 → widok błędu.
  - Dane: fallback na placeholdery gdy pola puste (`title`, `description`).
  - Sanitization: zakładamy bezpieczeństwo po stronie backendu; na froncie nie wstrzykujemy niesprawdzonego HTML.

## 10. Obsługa błędów
- 403: komunikat „Notatka niedostępna (prywatna)”.
- 404: komunikat „Notatka niedostępna lub link nieprawidłowy”.
- 0/5xx: „Problem z połączeniem. Spróbuj ponownie.”
- UX: prosty EmptyOrErrorState, CTA „Spróbuj ponownie” dla błędów sieciowych.

## 11. Kroki implementacji
1. Dodać routing Twig dla `/notes/public/{url_token}` i kontroler widoku SSR (pobiera dane z API lub bezpośrednio z serwisu, przekazuje VM do szablonu).
2. Stworzyć szablon Twig `public_note.html.twig` z układem komponentów: header, meta, labels, content, actions, error placeholder.
3. Zaimplementować blok renderujący dane z VM; opis wstawiać jako `|raw` (serwer gwarantuje sanitizację).
4. Dodać warunkowe CTA: jeśli `canEdit` → link „Edytuj”, inaczej „Zaloguj, aby edytować”.
5. Dodać stan błędu: jeśli kontroler przekazuje `errorCode`, render `EmptyOrErrorState`.
6. (Opcjonalnie) Dodać lekki JS do kopiowania bloków `<code>` (delegacja na `article`).
7. Dodać podstawowe style Tailwind (prose dla treści, badge dla labeli, layout responsive).
8. Przetestować ścieżki: 200 (publiczna notatka), 403 (prywatna), 404 (zły token); sprawdzić CTA zależnie od uprawnień.

