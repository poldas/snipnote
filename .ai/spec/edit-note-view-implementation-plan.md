# Plan implementacji widoku Edycja notatki

## 1. Przegląd
Widok pełnoekranowy `/notes/{id}/edit` dla właściciela lub współedytora, oparty na istniejącym widoku tworzenia notatki. Umożliwia edycję pól, zmianę widoczności, podgląd markdown, zarządzanie współedytorami, usuwanie notatki (owner only) i obsługę publicznego linku. Reuse istniejących komponentów (note_form, sticky_action_bar, visibility_toggle itd.) z prefillami; brak duplikacji UI.

## 2. Routing widoku
- Ścieżka: `/notes/{id}/edit` (GET) — tylko zalogowani z uprawnieniem owner/collaborator; w razie braku dostępu 403/redirect na login.
- Akcje w widoku:
  - PATCH `/api/notes/{id}` (zapis)
  - GET `/api/notes/{id}` (prefill serwerowy przed renderem)
  - DELETE `/api/notes/{id}` (owner-only)
  - POST `/api/notes/preview` (podgląd)
  - POST `/api/notes/{id}/collaborators` (dodaj)
  - DELETE `/api/notes/{id}/collaborators/{collabId|email}` (usuń/self-remove)
  - (Opcjonalnie) POST `/api/notes/{id}/url/regenerate` (regeneracja linku)

## 3. Struktura komponentów
- EditNotePage (layout, extends base)
  - Header (powrót do listy, user badge, meta tytuł/ID)
  - GlobalAlerts placeholder
  - NoteForm (reused) z prefillami i visibility toggle
  - PublicLinkInfo (karta z URL i kopiowaniem, widoczna gdy visibility=public)
  - CollaboratorsPanel
  - DangerZone (owner-only: delete, regenerate URL)
  - StickyActionBar (preview + save, reuse)
  - ConfirmModal (reusable, dla delete / regenerate / self-remove)

## 4. Szczegóły komponentów
### EditNotePage
- Opis: Shell strony, przekazuje dane inicjalne do form i paneli, renderuje toasty/alerts.
- Główne elementy: nagłówek z linkiem „← Powrót”, karta z sekcjami, toasty `#toast-stack`, aria-live.
- Obsługiwane interakcje: brak własnych; deleguje do dzieci.
- Obsługiwana walidacja: wstępne sprawdzenie uprawnień (server); brak w JS.
- Typy: `EditNoteViewModel`.
- Propsy: `note`, `csrfToken`, `isOwner`, `canEdit`, `collaborators`, `publicUrl`, `dashboardUrl`.

### NoteForm (reuse)
- Opis: istniejący formularz (title, description, labels, visibility, preview). Prefilluje wartości i działa w trybie „edit”.
- Główne elementy: TitleField, VisibilityToggle, MarkdownTextarea, TagInput, PreviewSection.
- Obsługiwane interakcje: input change, markdown toolbar, preview click, save click.
- Obsługiwana walidacja: tytuł wymagany <=255; opis wymagany <=10000; visibility ∈ {private, public, draft}; labels opcjonalne dedupe.
- Typy: `NoteFormInitialData`, `PatchNotePayload`.
- Propsy: `csrfToken`, `initialTitle`, `initialDescription`, `initialLabels`, `initialVisibility`, `noteId`, `mode: 'edit'`, `submitUrl`, `redirectUrl`, `previewUrl`.

### StickyActionBar (reuse)
- Opis: dolny pasek z przyciskami Podgląd/Zapisz, wskaźnikiem stanu.
- Główne elementy: status text, spinner, buttons preview/save.
- Obsługiwane interakcje: `click` preview/save (obsługa w JS).
- Walidacja: zależna od NoteForm.
- Typy: korzysta z tego samego stanu formy.
- Propsy: brak nowych (sterowanie w JS przez data-atrybuty).

### PublicLinkInfo
- Opis: karta pokazująca aktualny publiczny URL (`/n/{url_token}`) przy visibility=public, z przyciskiem kopiuj.
- Główne elementy: tekstowy link, przycisk „Kopiuj link”, badge visibility.
- Obsługiwane interakcje: `click` kopiuj → toast/aria-live.
- Walidacja: widoczność publiczna i istniejący `urlToken`.
- Typy: `PublicLinkViewModel`.
- Propsy: `urlToken`, `visibility`.

### CollaboratorsPanel
- Opis: sekcja listy współedytorów oraz formularz dodawania emaila; wspiera self-remove.
- Główne elementy: lista wierszy (email, rola, akcje usuń/self-remove), input email + button Dodaj, hinty.
- Obsługiwane interakcje: dodaj (POST), usuń (DELETE), self-remove (DELETE + redirect), odświeżenie listy.
- Walidacja: email format, brak duplikatów lokalnych, komunikaty 400/409.
- Typy: `CollaboratorViewModel`, `AddCollaboratorPayload`.
- Propsy: `noteId`, `items`, `isOwner`, `currentUserEmail`, `endpoints {addUrl, deleteUrl(email/id)}`, `redirectUrlOnSelfRemove`.

### DangerZone
- Opis: sekcja działań krytycznych (owner-only): Delete note, Regenerate URL.
- Główne elementy: dwa przyciski z opisem skutków.
- Obsługiwane interakcje: `click` → ConfirmModal → DELETE/POST.
- Walidacja: tylko `isOwner=true`.
- Typy: używa `DeleteNoteAction`, `RegenerateUrlAction`.
- Propsy: `deleteUrl`, `regenerateUrl?`, `redirectUrl`.

### ConfirmModal (reusable)
- Opis: dostępny modal potwierdzeń z focus management.
- Główne elementy: tytuł, opis konsekwencji, przyciski Confirm/Cancel.
- Obsługiwane interakcje: confirm/cancel; zamknięcie na Esc/overlay.
- Walidacja: brak, tylko sterowanie stanem modalu.
- Typy: `ConfirmModalState`.
- Propsy: `title`, `description`, `confirmLabel`, `variant`, `onConfirm`.

## 5. Typy
- `EditNoteViewModel`: `{ noteId:int, csrfToken:string, initialTitle:string, initialDescription:string, initialLabels:string[], initialVisibility:'private'|'public'|'draft', urlToken?:string, isOwner:bool, canEdit:bool, deleteUrl:string, patchUrl:string, previewUrl:string, regenerateUrl?:string, dashboardUrl:string }`
- `NoteFormInitialData`: `{ mode:'edit', submitUrl:string, redirectUrl:string, previewUrl:string }` + pola formularza.
- `PatchNotePayload`: partial `{ title?:string, description?:string, labels?:string[], visibility?:'private'|'public'|'draft' }`
- `PublicLinkViewModel`: `{ urlToken:string, visibility:string }`
- `CollaboratorViewModel`: `{ id?:number, email:string, isOwner:bool, isSelf:bool, userId?:number, removeUrl:string }`
- `AddCollaboratorPayload`: `{ email:string }`
- `ConfirmModalState`: `{ open:boolean, type:'delete'|'regenerate'|'self-remove', targetId?:number|string }`

## 6. Zarządzanie stanem
- Rozszerzyć istniejący `note_form.js` na tryb edit: wczytanie wartości początkowych z `data-initial` (JSON) lub hidden inputs; ustawienie `submitUrl`, `mode`.
- `formState`: tytuł/opis/labels/visibility + `noteId`, `mode`, `isSubmitting`, `isPreviewing`, `errors`.
- `collabState`: `{ items, isLoading, error, pendingId }`, obsługiwany w osobnym module `collaborators.js` korzystającym z toast/announce helperów.
- `uiState`: modale (`delete`, `regen`, `selfRemove`), `showPreview`.
- Brak dedykowanych hooków; moduły JS inicjalizowane po DOMContentLoaded.

## 7. Integracja API
- Prefill: serwer wywołuje GET `/api/notes/{id}` i przekazuje dane do Twig (JSON dla JS).
- Save: PATCH `/api/notes/{id}` z payloadem (tylko zmienione lub pełne pola); nagłówki `Content-Type: application/json`, `X-CSRF-Token`.
- Preview: POST `/api/notes/preview` z aktualnym stanem formy; wstawia HTML do `data-preview-content`.
- Delete: DELETE `/api/notes/{id}` → na 204 redirect `/notes`.
- Collaborators:
  - POST `/api/notes/{id}/collaborators` body `{ email }`
  - DELETE `/api/notes/{id}/collaborators/{collabId|email}`
  - Po self-remove → natychmiastowy redirect `/notes`.
- Regenerate URL (jeśli dostępne): POST `/api/notes/{id}/url/regenerate` → reload strony po sukcesie.

## 8. Interakcje użytkownika
- Edycja pól: aktualizuje licznik, czyści błędy.
- Podgląd: walidacja minimalna (tytuł/opis), request preview, pokazuje sekcję preview.
- Zapis: pełna walidacja; PATCH; toast sukcesu; redirect dashboard.
- Zmiana widoczności: aktualizacja opisów; zapisuje się dopiero po kliknięciu „Zapisz”.
- Dodanie współedytora: wpis email → POST → aktualizacja listy, toast sukcesu.
- Usunięcie współedytora: klik usuń → DELETE → odświeżenie listy; przy self-remove modal ostrzeżenia → DELETE → redirect.
- Usunięcie notatki: owner klika usuń → modal → DELETE → redirect dashboard.
- Kopiowanie publicznego linku: przycisk „Kopiuj” → clipboard + aria-live/ toast.

## 9. Warunki i walidacja
- Client-side: tytuł wymagany, <=255; opis wymagany, <=10000; visibility w dozwolonych wartościach; labele dedupe, max długość pojedynczej etykiety 64 (jak w tag_input).
- Access UI: sekcje edycji i sticky bar tylko gdy `canEdit`; DangerZone tylko gdy `isOwner`.
- Visibility public: pokazuje PublicLinkInfo tylko przy `visibility === 'public'` i `urlToken`.
- Collaborators: nie dodawać emaila gdy pusty lub niepoprawny; nie duplikować istniejących wpisów (case-insensitive).

## 10. Obsługa błędów
- 400 z PATCH/POST: mapowanie na pola + lista globalna (reuse ValidationAlertList).
- 401/403: toast + redirect na login lub komunikat „Brak dostępu” z linkiem powrotu.
- 404: renderować uprzejmy komunikat „Notatka niedostępna” (fallback view) i link do dashboardu.
- 409 (np. duplikat collaborator/url collision): global alert/toast.
- 5xx / network: toast „Spróbuj ponownie”; brak zmian stanu formy.
- Preview fail: fallback lokalny render jak w note_form.js.

## 11. Kroki implementacji
1. Dodaj trasę kontrolera strony `/notes/{id}/edit` (jeśli brak) z autoryzacją; pobierz note via API service i przekaż `EditNoteViewModel` + csrf do Twig.
2. Stwórz szablon `templates/notes/edit.html.twig`, rozszerzający bazę i layout jak `notes/new.html.twig`, podłączając istniejące komponenty (note_form, sticky_action_bar), wstrzyknij dane inicjalne (np. `data-note-initial` w skrypcie JSON).
3. W NoteForm include ustaw `initial*` wartości z modelu; zapewnij, że labels/visibility prefillowane; previewUrl i submitUrl dla trybu edit.
4. Rozszerz `note_form.js` o tryb `mode: 'edit'` i konfigurację endpointów: wczytaj initial state, ustaw counters, PATCH na `submitUrl`, po sukcesie redirect `/notes`.
5. Dodaj moduł JS `collaborators.js` dla panelu: render listy z danych serwera, obsłuż POST/DELETE/self-remove, wykorzystaj toasty/aria-live.
6. Dodaj komponenty Twig: `public_link_info`, `collaborators_panel`, `danger_zone`, `confirm_modal` (lub reuse istniejącego modal infra jeśli jest); podłącz do edit page.
7. W StickyActionBar zachowaj przyciski preview/save; ewentualny przycisk delete pozostaw w DangerZone (nie duplikuj).
8. Zapewnij kopiowanie linku (navigator.clipboard) i komunikaty aria-live/toast.
9. Obsłuż stany błędów i brak dostępu (render fallback lub redirect) zgodnie z PRD.

