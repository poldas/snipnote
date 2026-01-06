# Snipnote UI/UX Specification

Dokumentacja systemu projektowego Snipnote. Zawiera definicje kolorów, gradientów, cieni oraz stanów interaktywnych (hover/focus), zapewniając spójność wizualną całej aplikacji.

## 1. Primary Brand Colors (Kolory Marki)

Główna tożsamość wizualna oparta na odcieniach indygo i purpury.

### Gradients (Grandiety)
- **Primary Gradient**: `from-indigo-500 to-purple-600` (używany w logo, głównych przyciskach).
- **Secondary Gradient**: `from-indigo-600 to-purple-600` (używany w akcentach tekstowych).
- **CTA Section Background**: `from-indigo-500 via-purple-600 to-cyan-500` (szeroki gradient dla sekcji zachęty).

### Backgrounds (Tła)
- **Main App Background**: `from-indigo-50 via-purple-50 to-cyan-50` (subtelny gradient dla całej strony).
- **Glass Effect (Szkło)**: `bg-white/90 backdrop-blur-lg` (używany w kartach, nagłówkach i modalach dla efektu głębi).
- **Overlay**: `bg-white/40 backdrop-blur-sm` (lekkie przesłony sekcji).

## 2. Typography (Typografia)

Hierarchia tekstowa zapewniająca czytelność i profesjonalny wygląd.

### Text Palette
- **Headings (Nagłówki)**: `text-indigo-950` (najciemniejszy odcień dla H1, H2).
- **Subheadings**: `text-indigo-900` (dla H3 i mniejszych nagłówków).
- **Body Text**: `text-slate-700` (główny tekst treści).
- **Secondary/Description**: `text-slate-600` (teksty pomocnicze).
- **Muted/Metadata**: `text-indigo-700/80` (najlżejszy tekst informacyjny).

### Links (Linki)
- **Standard Links**: `text-indigo-600 hover:text-indigo-800`.
- **Nav/CTA Links**: `text-indigo-700 hover:text-indigo-900`.

## 3. Feature Icons & Accents (Kafle Funkcji)

Każda karta funkcji na stronie głównej posiada unikalny gradient dla ikony:
1. **Speed (Lightning)**: `from-yellow-400 to-orange-500`.
2. **Security (Shield)**: `from-green-400 to-emerald-500`.
3. **Sharing (Network)**: `from-indigo-400 to-purple-500`.
4. **Search (Magnifying Glass)**: `from-blue-400 to-cyan-500`.
5. **History (Clock)**: `from-purple-400 to-pink-500`.
6. **Formatting (Star)**: `from-pink-400 to-rose-500`.

## 4. Modern Input System (System Pól Formularzy)

Ujednolicony styl dla wszystkich pól tekstowych (`input-modern`).

- **Default State**: Klasa `shadow-md`, obramowanie `border-slate-300`.
- **Hover State**: Płynne przejście do `shadow-xl`.
- **Focus State**: `transform: translateY(-2px)`, `shadow-xl` z poświatą indygo (`rgba(102, 126, 234, 0.3)`), `ring-2 ring-indigo-200`.
- **Transition**: Zawsze stosuj `transition-all duration-300` dla płynności animacji.

## 5. Unified Alert System (System Powiadomień)

Spójna kolorystyka dla komponentu `alert` oraz komunikatów `toast`.

- **Error (Błąd)**: `bg-red-50`, `border-red-200`, `text-red-700` (ikony: `text-red-500`).
- **Success (Sukces)**: `bg-emerald-50`, `border-emerald-200`, `text-emerald-700` (ikony: `text-emerald-500`).
- **Warning (Ostrzeżenie)**: `bg-amber-50`, `border-amber-200`, `text-amber-900` (ikony: `text-amber-500`).
- **Info (Informacja)**: `bg-indigo-50`, `border-indigo-100`, `text-indigo-900` (ikony: `text-indigo-500`).

## 6. Interactive States & Animations (Stany Interaktywne)

### Buttons (Przyciski)
- **Primary (btn-auth-primary)**: Gradient marki, `hover:shadow-xl hover:-translate-y-1`.
- **Secondary (Białe/Glass)**: `bg-white/60`, `border-indigo-200`, `hover:bg-white`, `hover:shadow-md`, `hover:-translate-y-0.5`.
- **Special (White Info)**: `bg-white/80`, `hover:scale-105` z aurą gradientową `opacity-40`.

### Smart Behaviors (Inteligentne Zachowania)
- **Smart Autofocus**: Automatyczny fokus na polach wejściowych jest aktywny tylko na Desktopach (>= 1024px), aby zapobiec niechcianemu przewijaniu strony na urządzeniach mobilnych.
- **Logo Hover**: Efekt skalowania `scale-110` oraz zwiększenie widoczności aury tła z `0.6` do `1`.

## 7. Implementation Guidelines

- **Tailwind-First**: Wszystkie style implementujemy za pomocą klas narzędziowych Tailwind CSS.
- **Responsiveness**: Każdy element musi posiadać zdefiniowane zachowanie na mobile (np. kafle funkcji przechodzą z `grid-cols-3` do `flex row`).
- **Transitions**: Zmiany stanów interaktywnych (hover/focus) muszą być płynne (minimum 150ms-300ms).
