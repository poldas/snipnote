# Plan implementacji widoku Tworzenie notatki

## 1. Przegląd
Widok pełnoekranowy pod `/notes/new` umożliwiający zalogowanemu użytkownikowi utworzenie notatki z tytułem, opisem markdown, labelami oraz widocznością (domyślnie prywatna), z akcjami Podgląd (HTMX) i Zapisz (POST `/api/notes`).

## 2. Routing widoku
- Ścieżka: `/notes/new`
- Dostęp: tylko zalogowani (guard w kontrolerze/routerze + sprawdzenie sesji; niezalogowany → redirect do logowania / blokada formularza).

## 3. Struktura komponentów
- `NewNotePage`
  - `NoteForm` (formularz główny)
    - `TitleField`
    - `VisibilityToggle`
    - `MarkdownTextarea` (+ mini-toolbar)
    - `TagInput`
    - `ValidationAlertList`
  - `StickyActionBar`
    - przyciski: „Podgląd”, „Zapisz”
    - wskaźniki stanu (loading/disabled)

## 4. Szczegóły komponentów
### NewNotePage
- Opis: Layout strony, ładuje CSRF/tokeny, podaje dane startowe do formularza, zbiera wynik submitu.
- Główne elementy: kontener, nagłówek „Nowa notatka”, slot na `NoteForm`, placeholder na globalne alerty.
- Obsługiwane interakcje: inicjalizacja stanu formularza, przekazanie handlerów `onPreview`, `onSubmit`.
- Obsługiwana walidacja: brak własnej (deleguje do pól).
- Typy: `NoteFormState`, `NotePayload`, `NoteErrors`.
- Propsy: none (entry route).

### NoteForm
- Opis: Agreguje pola, obsługuje submit/preview, zarządza walidacją klienta i mapowaniem do payloadu API.
- Główne elementy: `<form>`, pola: `TitleField`, `VisibilityToggle`, `MarkdownTextarea`, `TagInput`, lista błędów; układ responsywny.
- Obsługiwane interakcje: onChange pól, onSubmit (zapis), onPreview (HTMX request), deduplikacja labeli, limit znaków.
- Obsługiwana walidacja: długość tytułu ≤255, opis ≤10000, wymagane tytuł/opis, visibility ∈ {private, public, draft?}, labele opcjonalne; przed wysłaniem trim i filtr pustych.
- Typy: `NoteFormState`, `NoteFieldError`, `NoteValidationResult`, `PreviewResponse`.
- Propsy: `onSubmit(payload: NotePayload)`, `onPreview(payload: NotePayload)`, `initialVisibility` (default `private`), `csrfToken?`.

### TitleField
- Opis: Pole tekstowe z etykietą i licznikiem znaków.
- Główne elementy: `<label>`, `<input type="text">`, licznik.
- Obsługiwane interakcje: wpisywanie, blur (trim opcjonalnie), wyświetlenie błędów.
- Obsługiwana walidacja: required, max 255.
- Typy: używa `NoteFieldError`.
- Propsy: `value`, `onChange`, `error`.

### VisibilityToggle
- Opis: Przełącznik private/public (opcjonalnie draft, ale domyślnie private).
- Główne elementy: switch/segmented buttons z opisem dostępności.
- Obsługiwane interakcje: click/change.
- Obsługiwana walidacja: wartość dozwolona (private/public/draft).
- Typy: `VisibilityOption`.
- Propsy: `value`, `onChange`.

### MarkdownTextarea
- Opis: Duże `<textarea>` z minimalnym toolbar (np. bold/italic/link/code) i licznik znaków.
- Główne elementy: textarea, toolbar (buttony wstawiające markdown), aria-describedby dla błędów.
- Obsługiwane interakcje: wpisywanie, skróty toolbaru (insert), focus/blur.
- Obsługiwana walidacja: required, max 10000.
- Typy: `NoteFieldError`.
- Propsy: `value`, `onChange`, `error`, `maxLength`.

### TagInput
- Opis: Wpisywanie labeli, deduplikacja, podgląd jako chipy, usuwanie chipów.
- Główne elementy: input tekstowy, lista chipów z przyciskiem usuwania.
- Obsługiwane interakcje: dodanie Enter/Comma, blur, usuwanie tagu, deduplikacja (case-sensitive? najlepiej case-insensitive).
- Obsługiwana walidacja: brak twardych limitów poza długością tekstu (użyć rozsądnego max, np. 64), filtr pustych.
- Typy: `LabelItem`.
- Propsy: `values: string[]`, `onChange(values: string[])`.

### ValidationAlertList
- Opis: Lista błędów serwerowych/klienckich powiązana z aria-describedby.
- Główne elementy: `<div role="alert">` z listą.
- Obsługiwane interakcje: brak (display-only).
- Obsługiwana walidacja: renderuje przekazane błędy.
- Typy: `NoteErrors`.
- Propsy: `errors`.

### StickyActionBar
- Opis: Dolny pasek klejący ze zbiorczymi akcjami.
- Główne elementy: kontener sticky bottom, button „Podgląd”, button primary „Zapisz”.
- Obsługiwane interakcje: click preview, click submit, disabled przy pending.
- Obsługiwana walidacja: nie dotyczy (deleguje).
- Typy: none specyficzne.
- Propsy: `onPreview`, `onSubmit`, `isSubmitting`, `isPreviewing`, `hasErrors`.

## 5. Typy
- `VisibilityOption` = `"private" | "public" | "draft"`.
- `LabelItem` = `string`.
- `NotePayload` = `{ title: string; description: string; labels: string[]; visibility: VisibilityOption; }`.
- `NoteFormState` = `{ title: string; description: string; labels: string[]; visibility: VisibilityOption; isSubmitting: boolean; isPreviewing: boolean; errors: NoteErrors; }`.
- `NoteFieldError` = `string[]`.
- `NoteErrors` = `{ title?: NoteFieldError; description?: NoteFieldError; labels?: NoteFieldError; visibility?: NoteFieldError; _request?: NoteFieldError; }`.
- `PreviewResponse` = `{ html: string; source: NotePayload; }` (render markdown server-side if available endpoint `/api/notes/preview`; jeśli brak, można local render fallback).
- `ApiNoteResponse` (z endpointu POST) = `{ data: { id: number; owner_id: number; url_token: string; title: string; description: string; labels: string[]; visibility: VisibilityOption; created_at: string; updated_at: string; } }`.

## 6. Zarządzanie stanem
- Lokalny stan w `NoteForm` (np. użycie prostych zmiennych lub małego store’a, bez global state).
- Hooki:
  - `useNoteFormState(initialVisibility = "private")`: zarządza polami, błędami, pending; zapewnia akcje `setField`, `addLabel`, `removeLabel`, `dedupeLabels`, `setErrors`.
  - `useSubmitNote`: wykonuje POST `/api/notes`, mapuje błędy 400 na `NoteErrors`, zarządza loading.
  - `usePreviewNote`: HTMX lub fetch do `/api/notes/preview` (jeśli dostępny) z payloadem; fallback: local markdown render.
- Walidacja klienta przed wysłaniem: max length, required; w razie naruszenia blokuje submit i ustawia `errors`.

## 7. Integracja API
- POST `/api/notes`
  - Payload: `NotePayload` (visibility default `private` jeśli puste).
  - Headers: `Content-Type: application/json`, CSRF (jeśli wymagane przez backend), auth cookie/token.
  - Sukces 201: przekieruj na dashboard lub edycję (`/notes/{id}/edit` / dashboard — zgodnie z flow backendu; minimalnie dashboard).
  - 400: mapuj `errors` do pól (title/description/labels/visibility/_request).
  - 401: redirect do logowania.
  - 409 (kolizja URL): pokaż komunikat i spróbuj ponownie na kolejnym submit.
- Preview: `POST /api/notes/preview` (HTMX). Payload jak `NotePayload`; render markdown → zwrócony HTML podmienia sekcję podglądu.

## 8. Interakcje użytkownika
- Wpisywanie tytułu → licznik; >255 blokuje lub oznacza error.
- Wpisywanie opisu → licznik; >10000 blokuje/oznacza error.
- Dodawanie labeli Enter/Comma → chipy; duplikaty ignorowane; pusty ignorowany; usuwanie chipów.
- Zmiana widoczności toggle.
- Klik „Podgląd” → walidacja pól required; jeśli ok, wywołanie preview; pokazanie sekcji preview pod formularzem lub w modal/inline.
- Klik „Zapisz” → walidacja klienta; POST; na sukces redirect + flash „Notatka utworzona”; na błąd render errors.
- Blokowanie podwójnego submitu przy pending.

## 9. Warunki i walidacja
- Required: `title`, `description`.
- Max length: `title` ≤255, `description` ≤10000; opcjonalnie label ≤64.
- Visibility: tylko dozwolone wartości.
- Labels: po trim usunąć puste; deduplikacja (case-insensitive zalecana).
- Autoryzacja: sprawdzenie stanu logowania (guard).
- CSRF: dołączyć token w formularzu (hidden input).

## 10. Obsługa błędów
- 400 validation: mapować na pola, wyświetlać pod inputem i w `ValidationAlertList`.
- 401: redirect do logowania lub overlay z info.
- 409 kolizja URL: pokaż alert i umożliw ponowny zapis (retry).
- Network/5xx: toast/alert „Błąd serwera, spróbuj ponownie”.
- Brak preview endpointu: fallback do lokalnego renderera markdown z ostrzeżeniem.

## 11. Kroki implementacji
1. Dodać trasę `/notes/new` w Symfony + widok Twig z kontenerem formularza i CSRF.
2. Zaimplementować `NewNotePage` layout z gridem/kartą, nagłówkiem i miejscem na alerty.
3. Stworzyć `NoteForm` w Twig (HTMX-friendly) lub komponentach dzielonych; ustawić aria/labels i maxLength na polach.
4. Dodać `TitleField`, `MarkdownTextarea` (z toolbar), `VisibilityToggle`, `TagInput` z deduplikacją, `ValidationAlertList`.
5. Wprowadzić `StickyActionBar` z przyciskami Podgląd/Zapisz; disabled gdy pending lub niepoprawne pola.
6. Dodać walidację klienta (maxlength, required) + mapowanie błędów serwera; aria-describedby dla komunikatów.
7. Podłączyć HTMX/fetch do `POST /api/notes` na submit; na sukces redirect na dashboard lub edycję; na błędy renderuj komunikaty.
8. Podłączyć HTMX/fetch do `/api/notes/preview`; renderować HTML w sekcji podglądu; fallback lokalny jeśli endpoint niedostępny.
9. Dodać minimalne style Tailwind: spacing, sticky bar, focus states, error states; upewnić się w dostępności (aria, role, focus outline).


