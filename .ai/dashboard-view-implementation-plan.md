# Plan implementacji widoku Dashboard — lista notatek

## 1. Przegląd
Widok `/notes` to główny hub użytkownika do przeglądania, filtrowania i zarządzania własnymi notatkami. Dostarcza listę notatek z paginacją, wyszukiwarkę (pełnotekst + `label:`), akcje przejścia do edycji oraz usuwania, a także pusty stan z CTA „Dodaj notatkę”.

## 2. Routing widoku
Ścieżka: `/notes` (dostępna wyłącznie dla zalogowanego użytkownika; w przypadku 401 przekierowanie do logowania).

## 3. Struktura komponentów
- LayoutPage
  - TopbarSearch (pole q)
  - MainSplit
    - SidebarFilters (opcjonalne „label:” hint/help)
    - NotesPanel
      - NotesHeader (liczba wyników, CTA „Dodaj notatkę”)
      - NotesList
        - NoteRow (klik całości -> edycja; ikonki edit/delete)
      - EmptyState (gdy brak danych)
      - PaginationControls
      - DeleteConfirmModal (portalled)
      - Toasts (global, jeśli już istnieją w app)

## 4. Szczegóły komponentów
### LayoutPage
- Opis: kontener widoku z siatką topbar + sidebar + content.
- Główne elementy: `header` (TopbarSearch), `aside` (SidebarFilters), `section` (NotesPanel).
- Obsługiwane interakcje: brak własnych; przekazuje callbacki do dzieci.
- Obsługiwana walidacja: brak.
- Typy: `DashboardViewModel` (props).
- Propsy: `model: DashboardViewModel`, `onSearch`, `onDelete`, `onPaginate`, `onAddNote`.

### TopbarSearch
- Opis: pojedyncze pole wyszukiwania q z hintem o `label:`.
- Główne elementy: `form`, `input[type=text]`, `button[type=submit]`, opcjonalny reset.
- Obsługiwane interakcje: submit (wywołuje `onSearch(q)`), enter, clear.
- Obsługiwana walidacja: długość q (np. trim, maks. ~200 znaków klientowsko, opcjonalnie).
- Typy: `SearchParamsViewModel`.
- Propsy: `value: string`, `onChange(q)`, `onSubmit(q)`, `isLoading: boolean`.

### SidebarFilters (opcjonalne)
- Opis: pomoc kontekstowa do składni `label:` lub szybkie chipy ostatnich labeli (jeśli dostępne w stanie).
- Główne elementy: lista tipów/chipów.
- Obsługiwane interakcje: kliknięcie chipu dodaje `label:...` do pola q.
- Obsługiwana walidacja: brak.
- Typy: `LabelSuggestion`.
- Propsy: `suggestions?: LabelSuggestion[]`, `onInsertLabel(label: string)`.

### NotesPanel
- Opis: sekcja listy z nagłówkiem, listą, paginacją i pustym stanem.
- Główne elementy: `NotesHeader`, `NotesList` lub `EmptyState`, `PaginationControls`.
- Obsługiwane interakcje: deleguje do dzieci; handle delete, navigate, paginate.
- Obsługiwana walidacja: brak.
- Typy: `NoteListViewModel`, `PaginationMeta`.
- Propsy: `notes`, `meta`, `isLoading`, `error`, `onEdit(id)`, `onDelete(id)`, `onPaginate(page)`, `onAddNote`.

### NotesHeader
- Opis: pokazuje tytuł sekcji i CTA „Dodaj notatkę”, ewentualnie licznik wyników.
- Główne elementy: `h1/h2`, `button`.
- Obsługiwane interakcje: klik CTA -> `onAddNote()`.
- Obsługiwana walidacja: brak.
- Typy: none poza prostymi prymitywami.
- Propsy: `count?: number`, `onAddNote`.

### NotesList
- Opis: renderuje listę NoteRow lub EmptyState.
- Główne elementy: `ul/li` lub `div` grid/lista.
- Obsługiwane interakcje: przekazuje zdarzenia z NoteRow.
- Obsługiwana walidacja: brak.
- Typy: `NoteSummaryVM[]`.
- Propsy: `notes`, `onEdit`, `onDelete`.

### NoteRow
- Opis: pojedyncza notatka; cały wiersz klikalny do edycji, z ikonami edit/delete.
- Główne elementy: `article/button role`, `title`, `excerpt ≤255`, `labels badges`, `created_at`, ikony akcji.
- Obsługiwane interakcje: click row / przycisk edit -> `onEdit(id)`; przycisk delete -> otwiera modal via `onDeleteRequest(id)`.
- Obsługiwana walidacja: brak (dane tylko do prezentacji).
- Typy: `NoteSummaryVM`.
- Propsy: `note: NoteSummaryVM`, `onEdit(id)`, `onDeleteRequest(id)`.

### EmptyState
- Opis: ekran „Nie ma jeszcze notatek” z CTA.
- Główne elementy: ilustracja/ikonka, tekst, `button`.
- Obsługiwane interakcje: klik CTA -> `onAddNote()`.
- Obsługiwana walidacja: brak.
- Typy: none.
- Propsy: `onAddNote`.

### PaginationControls
- Opis: paginacja (10/strona), next/prev, numery.
- Główne elementy: `nav`, `buttons/links`.
- Obsługiwane interakcje: klik strony -> `onPaginate(page)`.
- Obsługiwana walidacja: page w zakresie [1, totalPages].
- Typy: `PaginationMeta`.
- Propsy: `page`, `perPage`, `total`, `onChange`.

### DeleteConfirmModal
- Opis: modal potwierdzenia usuwania; pokazuje tytuł notatki jeśli dostępny.
- Główne elementy: `dialog`, `text`, `cancel`, `confirm`.
- Obsługiwane interakcje: confirm -> `onConfirm(id)`, cancel -> `onCancel()`, esc/overlay close.
- Obsługiwana walidacja: brak.
- Typy: `DeleteRequestState`.
- Propsy: `noteTitle?`, `isOpen`, `onConfirm`, `onCancel`, `isSubmitting`.

## 5. Typy
- API DTO (z `/api/notes`):
  - `ApiNoteSummary`: `{ id: number; url_token: string; title: string; description: string; labels: string[]; visibility: "private"|"public"; created_at: string; updated_at: string; }`
  - `ApiNotesResponse`: `{ data: ApiNoteSummary[]; meta: { page: number; per_page: number; total: number } }`
- ViewModel:
  - `NoteSummaryVM`: `{ id: number; title: string; excerpt: string; labels: string[]; visibility: "private"|"public"; createdAt: Date; updatedAt: Date; urlToken: string }`
  - `PaginationMeta`: `{ page: number; perPage: number; total: number; totalPages: number }`
  - `DashboardViewModel`: `{ notes: NoteSummaryVM[]; meta: PaginationMeta; isLoading: boolean; error?: string; q: string }`
  - `DeleteRequestState`: `{ id: number|null; title?: string; isOpen: boolean; isSubmitting: boolean }`
  - `SearchParamsViewModel`: `{ q: string; labelParams: string[] }`
  - `LabelSuggestion`: `{ label: string; count?: number }`

## 6. Zarządzanie stanem
- Lokalny stan w widoku: `q`, `notes`, `meta`, `isLoading`, `error`, `deleteRequest`, `toasts`.
- Parsowanie q do `labelParams` (prefiks `label:`) i części tekstowej; wysyłamy oba: `q` (pełne) i `label` (array) do API.
- Hook customowy (opcjonalnie) `useNotesSearch`:
  - wejście: `{ initialQ }`
  - zwraca: `{ q, setQ, notes, meta, isLoading, error, search(q), paginate(page), remove(id), reload() }`
  - obsługuje abort controller dla wyścigu zapytań.

## 7. Integracja API
- GET `/api/notes` z query: `page`, `per_page=10`, `q` (cały string), `label` (array z prefiksu `label:`).
- Autoryzacja: wymaga zalogowanego użytkownika; 401 -> redirect/login toast.
- Mapowanie odpowiedzi do VM (parsing dat, przycięcie `excerpt` do 255 znaków, fallback gdy description krótszy).
- DELETE `/api/notes/{id}` dla akcji usuwania (na liście); po sukcesie: refetch listy lub lokalny optimistic remove + ewentualny refetch gdy meta się zmienia.

## 8. Interakcje użytkownika
- Wpisanie q i submit: wywołuje search, pokazuje spinner w topbarze i list loading.
- Kliknięcie w NoteRow lub ikonę edit: nawigacja do edycji (`/notes/{id}/edit` lub analogiczna ścieżka w aplikacji).
- Kliknięcie delete: otwiera DeleteConfirmModal; potwierdzenie wywołuje DELETE; sukces -> toast „Notatka usunięta”, odświeżenie listy/paginacji; błąd -> toast/inline error.
- Kliknięcie „Dodaj notatkę”: nawigacja do formularza tworzenia (`/notes/new`).
- Paginacja: zmiana page -> refetch z aktualnymi parametrami q/label.
- EmptyState CTA: nawigacja do `/notes/new`.

## 9. Warunki i walidacja
- Search input: optional; trim; długość ≤ 200 (client-only).
- Label parsing: z q wyodrębniamy `label:foo,bar` -> `label=["foo","bar"]`; walidujemy niepuste stringi.
- Paginacja: page ≥1, page ≤ totalPages; disable buttons gdy poza zakresem.
- Uprawnienia: brak opcji delete dla współedytorów (front ukrywa jeśli API nie zwróci flagi; w MVP zakładamy właściciel => lista owned notes, więc delete pokazujemy zawsze, błędy 403/401 obsługujemy).
- Delete modal wymaga id; button confirm disabled przy `isSubmitting`.

## 10. Obsługa błędów
- 401: redirect do logowania (lub globalny handler); w widoku pokazujemy komunikat i CTA „Zaloguj”.
- 403/404 przy DELETE: toast z błędem, refetch listy.
- 422/validation z GET raczej brak; dla niepoprawnych q/label lokalnie sanitizujemy.
- Network error: toast + opcja ponów.
- Empty results (total=0, q ustawione): pokazujemy „Brak wyników dla …” + CTA „Wyczyść filtr”.
- Empty state bez notatek: komunikat „Nie ma jeszcze notatek” + CTA „Dodaj notatkę”.

## 11. Kroki implementacji
1. Utwórz skeleton strony `/notes` w routingu (Twig/HTMX) z layoutem topbar + sidebar + content.
2. Zaimplementuj TopbarSearch z form submit i integracją z HTMX (swap listy) lub pełny page reload GET param; dodaj hint o `label:`.
3. Dodaj logikę parsowania q -> `label` param i wykonanie GET `/api/notes`; pokaż spinner podczas ładowania.
4. Zaimplementuj mapowanie API -> VM (excerpt 255, daty).
5. Zbuduj NotesPanel: header z CTA, lista NoteRow, paginacja, empty states.
6. Dodaj DeleteConfirmModal i akcję DELETE `/api/notes/{id}`; po sukcesie odśwież listę, pokazuj toasty.
7. Dodaj obsługę paginacji (page param w URL, utrzymanie q/label w query string).
8. Zaimplementuj stany błędów i brak wyników; CTA „Dodaj notatkę” kieruje do `/notes/new`.
9. Dopracuj dostępność: role/button na NoteRow, focus states, aria na modalu.
10. Stylizuj Tailwind (listy, badge labeli, przyciski, modale) zgodnie z design systemem.

