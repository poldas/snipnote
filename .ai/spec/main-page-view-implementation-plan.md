# Plan implementacji widoku Landing / Login / Register

## 1. Przegląd
Widoki landing, logowania i rejestracji mają umożliwić szybkie wejście do aplikacji: prezentują krótką informację o Snipnote, prosty formularz email/hasło z walidacją i wyraźnymi CTA do logowania lub rejestracji. Na tym etapie implementujemy wyłącznie warstwę frontową (Twig + Tailwind + Fluent UI 2, opcjonalnie HTMX) bez realnej logiki autoryzacji.

## 2. Routing widoku
- `/` – landing z CTA do logowania/rejestracji i osadzonym formularzem logowania (opcjonalnie skróconym).
- `/login` – pełny widok logowania.
- `/register` – pełny widok rejestracji.

## 3. Struktura komponentów
- `LayoutAuth` (szkielet, wspólne tło i responsywna kolumna)
  - `NavLinksAuth` (link przełączający login/register)
  - `HeroIntro` (tytuł + krótki opis aplikacji)
  - `AuthCard`
    - `LoginForm` albo `RegisterForm`
      - `FormField` (Label + Input + `InlineError`)
      - `GlobalErrorBanner`
      - `SubmitButton`
  - `FeatureBullets` (opcjonalny krótki opis korzyści)

## 4. Szczegóły komponentów
### LayoutAuth
- Opis: wspólny layout dla landing/login/register; kolumnowy układ center; ogranicza max szerokość, ustawia tło.
- Główne elementy: `main`, `section` z grid flex, header z logo/brand.
- Obsługiwane interakcje: brak logiki poza nawigacją.
- Obsługiwana walidacja: n/d.
- Typy: `LandingContent`.
- Propsy: `title?: string`, `children`, `showNavSwitch?: bool`.

### NavLinksAuth
- Opis: link tekstowy/przycisk do alternatywnego widoku (login <-> register) z małym CTA.
- Główne elementy: `a` z klasami Tailwind.
- Interakcje: klik → nawigacja.
- Walidacja: n/d.
- Typy: brak specjalnych (statyczny).
- Propsy: `variant: 'login'|'register'`, `href: string`, `label: string`.

### HeroIntro
- Opis: krótki nagłówek i podtytuł objaśniający Snipnote, opcjonalne CTA do rejestracji.
- Główne elementy: `h1`, `p`, `a` CTA.
- Interakcje: klik CTA → nawigacja.
- Walidacja: n/d.
- Typy: `LandingContent`.
- Propsy: `headline: string`, `subcopy: string`, `ctaPrimary`, `ctaSecondary?`.

### AuthCard
- Opis: kontener z tłem/obramowaniem na formularz; wspólne marginesy/paddingi.
- Główne elementy: `div` z Tailwind shadow, slot na form.
- Interakcje: brak.
- Walidacja: n/d.
- Typy: brak specjalnych.
- Propsy: `title: string`, `children`.

### LoginForm
- Opis: formularz logowania email/hasło z inline errorami i globalnym komunikatem; przygotowany pod POST `/login`.
- Główne elementy: `form[action="/login"][method="post"]`, hidden CSRF (`{{ csrf_token('authenticate') }}`), `FormField email`, `FormField password`, `GlobalErrorBanner`, `SubmitButton`, link do rejestracji.
- Interakcje: submit (Enter/klik), focus-first na email, opcjonalnie `hx-post` do późniejszej integracji.
- Walidacja: wymagane email (format), hasło wymagane; CSRF obecny.
- Typy: `AuthFormModel`, `FieldConfig`, `ErrorBag`.
- Propsy: `action: string`, `csrfToken: string`, `errors?: ErrorBag`, `prefill?: {email?: string}`, `pending?: bool`.

### RegisterForm
- Opis: formularz rejestracji email/hasło (min 8 znaków) z inline errorami; przygotowany pod POST `/register`.
- Główne elementy: `form[action="/register"][method="post"]`, CSRF (`{{ csrf_token('register') }}`), `FormField email`, `FormField password`, opcjonalnie `FormField passwordConfirm` (jeśli chcemy prostą weryfikację kliencką), `GlobalErrorBanner`, `SubmitButton`, link do logowania.
- Interakcje: submit, focus-first na email.
- Walidacja: email wymagany + format; hasło wymagane min 8 znaków; (opcjonalnie) dopasowanie z confirm; CSRF obecny.
- Typy: `AuthFormModel`, `FieldConfig`, `ErrorBag`.
- Propsy: `action: string`, `csrfToken: string`, `errors?: ErrorBag`, `prefill?: {email?: string}`, `pending?: bool`.

### FormField
- Opis: para label + input + inline error dla pól email/hasło.
- Główne elementy: `label[for]`, `input`, `InlineError`.
- Interakcje: onChange/onBlur (do walidacji klienta lub do HTMX target); Enter submit.
- Walidacja: zależna od typu (email format, min length).
- Typy: `FieldConfig`, `FieldError`.
- Propsy: `config: FieldConfig`, `value?: string`, `error?: string`, `required?: bool`, `autoFocus?: bool`.

### InlineError
- Opis: mały tekst błędu pod polem; dostępny z `aria-live="polite"`.
- Główne elementy: `p` lub `div`.
- Interakcje: brak.
- Walidacja: n/d.
- Typy: `FieldError`.
- Propsy: `message?: string`.

### GlobalErrorBanner
- Opis: wyświetla globalne błędy (np. złe hasło, duplikat email, CSRF).
- Główne elementy: `div` z rolą `alert`.
- Interakcje: brak.
- Walidacja: n/d.
- Typy: `ErrorBag`.
- Propsy: `messages: string[]`.

## 5. Typy
- `AuthFormModel`: `{ email: string; password: string; csrfToken: string; errors?: ErrorBag; status?: 'idle'|'submitting'|'error'|'success' }`
- `ErrorBag`: `Array<{ field?: 'email'|'password'|'passwordConfirm'|string; message: string }>`
- `FieldConfig`: `{ id: string; name: string; label: string; type: 'email'|'password'; placeholder?: string; autocomplete?: string; required?: bool; minLength?: number }`
- `LandingContent`: `{ headline: string; subcopy: string; ctaPrimary: { href: string; label: string }; ctaSecondary?: { href: string; label: string } }`
- `FieldError`: alias na `string | undefined`.

## 6. Zarządzanie stanem
- Twig rendery początkowe; stany `pending`/`errors` przekazywane z kontrolera po submit (po wdrożeniu backendu).
- Autofocus: mały inline script lub `autofocus` na pierwszym polu (uwaga na single-page wymogi dostępności).
- HTMX (opcjonalnie): `hx-post` na form z targetem na `AuthCard`, aby otrzymać partial z błędami; `hx-disabled-elt` na przycisku podczas submitu.

## 7. Integracja API
- Obecnie brak implementacji; formularze przygotowane pod przyszłe POST:
  - `/login`: body `email`, `password`, `_csrf_token`; oczekiwany redirect do dashboard lub błąd 401/400 z komunikatem.
  - `/register`: body `email`, `password`, `_csrf_token`; oczekiwany redirect do dashboard lub 409 (duplikat email) / 400 (walidacja).
- Dodaj atrybuty `novalidate` tylko jeśli przejmujemy walidację JS/HTMX; w innym razie zostaw HTML5.

## 8. Interakcje użytkownika
- Landing: klik CTA → nawigacja do `/login` lub `/register`.
- Form fields: wpisywanie danych, Tab focus order, Enter wysyła formularz.
- Submit: przycisk dezaktywuje się w stanie `pending`, opcjonalny spinner; błędy pojawiają się inline/global.
- Link przełączający: prowadzi do przeciwnej akcji (login ↔ register).

## 9. Warunki i walidacja
- Email: wymagany, poprawny format (`type="email"` + ewentualny regex).
- Hasło: wymagane; w rejestracji min 8 znaków; w logowaniu brak limitu poza >0.
- (Opcjonalnie) Potwierdzenie hasła: musi się zgadzać.
- CSRF: hidden input obowiązkowy w obu formularzach.
- UI: blokada przycisku gdy brak wymaganych pól lub `pending`.

## 10. Obsługa błędów
- Walidacja klienta: natychmiastowe komunikaty przy blur/submit.
- Błędy serwera: mapowanie na global (`GlobalErrorBanner`); pola specyficzne na `InlineError`.
- CSRF/expired: komunikat „Sesja wygasła, odśwież stronę”.
- Rate limit/503: neutralny komunikat „Spróbuj ponownie później”.
- Network (przy HTMX): pokaż banner z retry CTA.

## 11. Kroki implementacji
1. Dodać routing Twig/Symfony dla `/`, `/login`, `/register` (GET) kierujący na nowe szablony.
2. Utworzyć bazowy szablon `LayoutAuth` z Tailwind klasami i slotem na zawartość.
3. Zaimplementować `HeroIntro` i `NavLinksAuth` w partialach Twig.
4. Zbudować `AuthCard` partial i osadzić w nim formularze.
5. Utworzyć `LoginForm` z polami email/hasło, CSRF, `InlineError`, `GlobalErrorBanner`, przyciskiem submit, linkiem do rejestracji.
6. Utworzyć `RegisterForm` z polami email/hasło (+ opcjonalne potwierdzenie), CSRF, błędami, linkiem do logowania.
7. Dodać placeholdery/zmienne konfiguracyjne (`LandingContent`, `FieldConfig`) w kontrolerach Twig lub kontekście renderu.
8. Zapewnić autofocus na pierwszym polu (HTML5 lub mały script); ustawić aria-label/aria-describedby dla błędów.
9. Dodać klasy Tailwind dla responsywności, stanów focus/disabled i alertów.
10. (Opcjonalnie) Podpiąć HTMX atrybuty dla przyszłych async submitów; inaczej klasyczne POST + redirect.
11. Przetestować ręcznie: nawigacja między widokami, focus order, klawiatura, komunikaty błędów klienta.

