# Plan wdrożenia assetów (Symfony 8 / Twig)

## Cele i założenia
- Docelowy, wspierany przez Symfony 8 stack: AssetMapper + ImportMap + Stimulus, HTMX, Tailwind CSS. Brak Webpack/Encore.
- Zero globalnych skryptów w szablonach; kod modułowy, kontrolery Stimulus, HTMX do partiali.
- Build reproducible w Docker (builder stage), cache-busting przez fingerprinting AssetMapper, brak Node w runtime.

## Architektura docelowa
- AssetMapper jako jedyny bundler (ESM, fingerprinting, kompilacja do `public/assets`).
- ImportMap do zarządzania zewnętrznymi libami (htmx.org, opcjonalnie alpine/turbo).
- Stimulus Bundle do JS domenowego (`assets/controllers/**`).
- Tailwind CSS + PostCSS (Node tylko w builder stage) → wynik importowany do AssetMapper.

## Struktura katalogów
- `assets/app.js` – punkt wejścia (import Tailwind CSS, rejestracja Stimulus, konfiguracja HTMX).
- `assets/styles/app.css` – `@tailwind base; @tailwind components; @tailwind utilities;` + własne warstwy.
- `assets/styles/dist/tailwind.css` – wynik builda (commitowany/packowany).
- `assets/controllers/` – kontrolery Stimulus (np. `note_form_controller.js`, `edit_note_controller.js`, `flash_controller.js`).
- `assets/vendor/` – ewentualne shimy lokalne (jeśli nie w ImportMap).

## Konfiguracja do wykonania
1) Composer:
   - `composer require symfony/asset-mapper symfony/stimulus-bundle symfony/ux-turbo` (Turbo opcjonalne).
2) ImportMap:
   - `php bin/console importmap:require htmx.org`
   - Dodać inne liby używane w JS (np. `sortablejs`, `@hotwired/turbo` jeśli używany).
3) AssetMapper:
   - `config/packages/asset_mapper.yaml`: `paths: ['assets/']`, `public_prefix: /assets`, fingerprinting domyślne.
4) Tailwind/PostCSS:
   - `tailwind.config.js` content: `['./templates/**/*.html.twig', './assets/**/*.{js,ts}']`.
   - `postcss.config.js`: `module.exports = { plugins: { tailwindcss: {}, autoprefixer: {} } };`
   - Script: `npx tailwindcss -i ./assets/styles/app.css -o ./assets/styles/dist/tailwind.css --minify`
5) Twig:
   - W layout bazowy dodać:  
     - `{{ asset('app.css') }}` (AssetMapper wygeneruje link)  
     - `{{ asset('app.js') | importmap_script }}`  
   - Usunąć ręczne `<script src="/assets/...">` i inline init.

## Migracja istniejącego JS (assets/note_form.js, edit_note.js)
- Audyt funkcji: eventy formularzy, walidacje, modale, autosave itp.
- Podzielić na kontrolery Stimulus (1 odpowiedzialność per kontroler), np.:
  - `note-form` – obsługa walidacji/submit, integracja z HTMX.
  - `edit-note` – logika edycji, podgląd, autosave.
  - Wspólne utilsy jako moduły ES importowane przez kontrolery.
- Usunąć globalne `DOMContentLoaded` i rejestracje na `document`; używać `data-controller`, `data-action`, `data-target`.
- Zapewnić idempotentność (wielokrotne mounty HTMX).

## CSS / Tailwind
- Zastąpić ręczne style wspólną bazą Tailwind + ewentualne `@layer components` na własne utility.
- Jeżeli istnieją style z `assets/styles/` lub `public/assets/`, przenieść do warstw Tailwind lub do osobnych plików importowanych w `app.css`.
- Build wynikowy commitowany/pakowany w obrazie produkcyjnym.

## Integracja HTMX
- Import przez ImportMap (`htmx.org`), inicjalizacja w `assets/app.js` (globalne nagłówki, spinnery, kolejka).
- Szablony Twig odpowiadają fragmentami (partial responses), bez inline skryptów; Stimulus + `hx-` atrybuty.

## Prace w Twig
- Layout bazowy: sekcja `<head>` z assetami AssetMapper; `<body>` z `{{ stimulus_controller(...) }}` tam gdzie wymagane.
- Komponenty/fragmenty Twig nie powinny ładować skryptów; tylko znaczniki `data-controller`/`hx-*`.

## Build i uruchamianie
- Dev: `symfony server:start` + `php bin/console asset-map:compile --watch` (+ opcjonalnie `npx tailwindcss --watch`).
- Prod: `npx tailwindcss ... --minify` → `php bin/console asset-map:compile --env=prod`.

## Docker/CI (ważne dla prod VPS)
- Builder stage (Dockerfile.prod):
  - Doinstalować Node tylko w builderze (np. `curl -fsSL https://deb.nodesource.com/setup_20.x | bash -` + `apt-get install -y nodejs`).
  - `npm ci` (z `package-lock.json`), `npm run tailwind:build` (tworzy `assets/styles/dist/tailwind.css`).
  - `php bin/console importmap:install --no-interaction --env=prod`
  - `php bin/console asset-map:compile --env=prod`
  - Usunąć `node_modules` przed kopiowaniem do runtime.
- Runtime stage bez Node; serwowanie `public/assets` przez Apache.
- CI: job budujący obraz powinien uruchomić powyższe kroki; w GitHub Actions dopisać step `npm ci && npm run tailwind:build` przed `asset-map:compile`.

## Testy i kontrola jakości
- `php bin/console asset-map:compile` w CI (wykryje brakujące importy).
- Smoke E2E (Playwright) dla krytycznych flow: tworzenie/edycja notki (HTMX + Stimulus).
- Sprawdzić CSP nagłówki po stronie Traefika/Apache, uwzględniając `script-src 'self'` + importmap.

## Plan wdrożenia (kolejność)
1) Dodać zależności Composer + konfigurację AssetMapper/ImportMap/Stimulus.  
2) Dodać Tailwind/PostCSS config + skrypty npm.  
3) Utworzyć `assets/app.js`, `assets/styles/app.css`, wpiąć w Twig layout.  
4) Zmigrować istniejące skrypty do kontrolerów Stimulus; usunąć inline/global init.  
5) Zbudować CSS (Tailwind) + `asset-map:compile`; potwierdzić w dev.  
6) Zaktualizować Dockerfile.prod (Node w builder, tailwind build) + workflow CI.  
7) Testy E2E i manualne smoke, potem deploy na VPS.

## Ryzyka / uwagi
- Brak Node w runtime – wymagany build w pipeline; dopilnować cache npm w builderze.
- HTMX + Stimulus: ważna idempotentność kontrolerów na fragmentach ładowanych dynamicznie.
- Cache statyczny po stronie Traefika/Apache: włączyć długie TTL na `/assets/*` (fingerprinted).

