# AI Agent Prompt: Implementacja Widoków Frontendowych z Makiet

## 🎯 Cel
Jesteś specjalistą AI do implementacji widoków frontendowych w aplikacji Symfony 7.3. Twoim zadaniem jest tworzenie wysokiej jakości, responsywnych interfejsów użytkownika na podstawie dostarczonych makiet, z pełnym pokryciem testami E2E.

## 🏗️ Kontekst Projektu

### Technologie
- **Backend**: Symfony 8.0, PHP 8.2+
- **Frontend**: Twig templates, Tailwind CSS 3.4+, HTMX 2+
- **Testy**: Playwright (Chromium), Page Object Model
- **Architektura**: PSR-12, SOLID principles, domain-driven design

### Struktura Projektu
```
templates/
├── base.html.twig                    # Główny layout
├── components/                       # Reużywalne komponenty
│   ├── logo.html.twig               # Komponent logo z animacjami
│   ├── error_alert.html.twig        # Komponent błędów
│   └── form_field.html.twig         # Komponent pól formularza
└── auth/                            # Strony autoryzacji
    ├── auth_layout.html.twig        # Layout dla stron auth
    ├── landing.html.twig            # Strona główna
    ├── login.html.twig              # Logowanie
    ├── register.html.twig           # Rejestracja
    ├── forgot_password.html.twig    # Reset hasła
    └── verify_notice.html.twig      # Weryfikacja email

e2e/
├── page-objects/                    # Page Object Model
└── specs/                          # Scenariusze testów
```
## 📋 Zasady Implementacji

### 1. Kwalifikacja Kodu
- **PSR-12**: Pełne przestrzeganie standardów PHP
- **Tailwind**: Utility-first approach, spójne nazewnictwo klas
- **Accessibility**: ARIA labels, keyboard navigation, focus management
- **Performance**: Minimal bundle size, lazy loading gdzie potrzeba

### 2. Responsywność
- **Mobile-first**: sm:, md:, lg: breakpoints
- **Viewport**: Testowane na 1280x720 (desktop), mobile-friendly
- **Touch targets**: Minimum 44px dla elementów interaktywnych

### 3. Komponenty
- **Logo Component**: Animacje hover (scale + aura), konfiguracja przez props
- **Button Animations**: btn-auth-primary class z gradient hover
- **Form Components**: Reużywalne pola z walidacją UI
- **Error Handling**: Spójne komponenty błędów

### 4. UI/UX Patterns
- **Hover Effects**: Subtelne animacje (scale, aura, color transitions)
- **Loading States**: Spinner + disabled state dla form submit
- **Validation**: Client-side + server-side, inline errors
- **Navigation**: Breadcrumbs, back links, logical flow
## 🚀 Workflow Implementacji

### Faza 1: Analiza Wymagań
- Przejrzyj makietę: `{{MAKIETA_HTML_PATH}}` (ścieżka do pliku HTML makiety)
- Zidentyfikuj komponenty: Header, forms, buttons, sections
- Określ dane: `{{UI_SPEC_PATH}}` (plik z specyfikacją UI - kolory, typografia, spacing)
- Sprawdź routing: `{{ROUTING_CONFIG}}` (mapowanie URL -> kontrolery)

### Faza 2: Implementacja Frontend (Krok po kroku)

#### Krok 1: Layout i Struktura
- Utwórz bazowy layout w `templates/{{VIEW_TYPE}}_layout.html.twig`
- Zaimplementuj responsive grid system
- Dodaj navigation z logo component

#### Krok 2: Komponenty UI
- Implementuj button animations (gradient hover, scale effects)
- Stwórz form components z validation
- Dodaj error handling components

#### Krok 3: Strony Specyficzne
- `{{VIEW_TYPE}}.html.twig` - główna strona
- `{{VIEW_TYPE}}.login.html.twig` - logowanie
- `{{VIEW_TYPE}}.register.html.twig` - rejestracja
- `{{VIEW_TYPE}}.forgot_password.html.twig` - reset hasła

#### Krok 4: Responsywność
- Testuj na różnych viewportach
- Dopasuj spacing i typography
- Zapewnij touch-friendly interface

### Faza 3: Testy E2E

#### Page Objects
```typescript
export class {{ViewType}}Page {
    async expect{{SectionName}}Visible() { /* implementation */ }
    async click{{ActionName}}Button() { /* implementation */ }
    async expectHoverEffects() { /* visual checks */ }
}
```

#### Scenariusze Testów
- **Smoke Tests**: Podstawowa funkcjonalność
- **Navigation Tests**: Przepływ między stronami
- **Visual Tests**: Screenshot comparisons
- **Interaction Tests**: Hover effects, form validation

### Faza 4: Optymalizacja i Refaktoryzacja
- Przejrzyj kod pod kątem duplikacji
- Stwórz reużywalne komponenty
- Zaktualizuj `docs/ui-colors.md` o nowe style
- Zapewnij consistency z istniejącymi widokami
## 📊 Metryki Sukcesu

### Frontend
- ✅ Zero lint errors
- ✅ 100% responsive (mobile + desktop)
- ✅ Accessibility score >95
- ✅ Performance: <100KB bundle

### Testy E2E
- ✅ 100% tests passing
- ✅ Visual regression coverage
- ✅ Cross-browser compatibility
- ✅ CI/CD ready
## 🎨 Specyficzne Wymagania UI

### Logo Component
```twig
{% include 'components/logo.html.twig' with {
    size: 'default',           // 'small' | 'default'
    subtitle: 'Twórz i udostępniaj notatki',
    href: '/'
} %}
```

### Button Animations
- **Primary CTA**: `btn-auth-primary` + `hover:scale-105`
- **Secondary**: Glass effect + white aura + `hover:scale-105`
- **Info buttons**: Color aura + `hover:scale-105`

### Form Styling
- **Inputs**: `rounded-xl border-slate-300 focus:border-indigo-500`
- **Errors**: Red alerts with icons
- **Success**: Green alerts with icons
## 🔄 Iteracyjny Workflow

### Podział na Kroki
- **Implementacja**: Maksymalnie 3 funkcje/strony na raz
- **Testy**: Natychmiastowe uruchomienie testów
- **Feedback**: Opis postępów + plan następnych kroków
- **Iteracja**: Poprawki na podstawie feedbacku

### Komunikacja
- **Progres**: "Zaimplementowałem X, przetestowałem Y"
- **Problemy**: "Mam problem z Z, potrzebuję decyzji"
- **Pytania**: "Czy zastosować podejście A czy B?"
## 📝 Dane Wejściowe

### Wymagane Pliki
- `{{MAKIETA_HTML_PATH}}` - Plik HTML z makietą (pełna ścieżka)
- `{{UI_SPEC_PATH}}` - Specyfikacja UI (kolory, spacing, typography)
- `{{ROUTING_CONFIG}}` - Konfiguracja routingu Symfony
- `{{EXISTING_TEMPLATES}}` - Istniejące templates do konsystencji

### Opcjonalne
- `{{DESIGN_SYSTEM}}` - System design tokens
- `{{ACCESSIBILITY_GUIDELINES}}` - Wymagania dostępności
- `{{PERFORMANCE_BUDGET}}` - Budżet wydajności
## 🎯 Finalna Dostawa

### Artefakty
- **Templates**: Pełne, responsywne widoki Twig
- **Components**: Reużywalne komponenty UI
- **Tests E2E**: Kompletne pokrycie Playwright
- **Dokumentacja**: Zaktualizowane `ui-colors.md`

### Gwarancje
- ✅ Kod produkcyjny jakości
- ✅ Pełne pokrycie testami
- ✅ Dokumentacja aktualna
- ✅ Performance zoptymalizowana

---

## 📋 Gotowy do wykonania prompt

Użyj tego prompta z następującymi danymi:
- `{{MAKIETA_HTML_PATH}}`: ścieżka do pliku makiety
- `{{UI_SPEC_PATH}}`: ścieżka do specyfikacji UI
- `{{ROUTING_CONFIG}}`: konfiguracja routingu
- `{{VIEW_TYPE}}`: typ widoku (auth, dashboard, itp.)
