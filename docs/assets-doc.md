# CSS i JS – jak działają i jak je budować

## Stos i przepływ
- Używamy Symfony Asset Mapper + ImportMap (bez bundlera) oraz Tailwind CSS.
- Node jest potrzebny tylko do kompilacji Tailwinda; w runtime (prod) działa czyste PHP/Apache.
- ImportMap wskazuje zewnętrzne moduły (`htmx.org`, `@hotwired/stimulus`, `@hotwired/turbo`) w `importmap.php`.
- Asset Mapper publikuje zasoby do `public/assets` z fingerprintem (`asset-map:compile`).

## Gdzie co jest
- CSS źródło: `tailwind/app.css` (dyrektywy Tailwind + custom style).  
- CSS wynikowy: `assets/styles/dist/tailwind.css` (generowany przez Tailwind; nie edytuj ręcznie). 
- CSS entrypoint: `assets/app.css` importuje wynikowy Tailwind (ładowany w Twig przez `asset('app.css')`).
- JS entrypoint: `assets/app.js` (importuje CSS, HTMX oraz `stimulus_bootstrap.js`).
- Stimulus: kontrolery w `assets/controllers/*.js`, rejestrowane w `assets/stimulus_bootstrap.js`.
- ImportMap/Asset Mapper: `importmap.php`, `config/packages/asset_mapper.yaml`.
- Szablony: `templates/base.html.twig` dołącza `<link rel="stylesheet" href="{{ asset('app.css') }}">` oraz `{{ importmap('app') }}`. `templates/public_note.html.twig` ma dodatkowy inline CSS (legacy).

## Jak to działa w szablonach
1) `base.html.twig` ładuje `app.css` (fingerprinted plik z Asset Mapper) oraz skrypt `app` z ImportMap.
2) `app.js` ustawia globalny nagłówek dla HTMX i startuje Stimulus, który montuje kontrolery na elementach z `data-controller`.
3) Tailwind generuje gotowy CSS w `assets/styles/dist/tailwind.css`, który jest dołączany przez `assets/app.css`.

### Lżejsze strony (np. login/rejestracja) bez pełnego JS
Jeśli strona nie potrzebuje wszystkich kontrolerów:
- Utwórz drugi entrypoint, np. `assets/app-public.js` (tylko to, co potrzebne; może nie importować HTMX/Stimulus albo importować minimalny zestaw).
- Dodaj go do importmap (np. `php bin/console importmap:require ./assets/app-public.js --entrypoint` lub ręcznie w `importmap.php`).
- W szablonie, który ma być “lekki”, nadpisz blok `javascripts`:
  ```twig
  {% block javascripts %}
      {{ importmap('app-public') }}
  {% endblock %}
  ```
- Jeśli chcesz osobny, lżejszy CSS, dodaj `assets/app-public.css` i analogicznie `{{ asset('app-public.css') }}` w bloku `stylesheets`.

## Praca deweloperska
- Edytuj style w `tailwind/app.css` (nie w `assets/styles/dist/tailwind.css`).
- Edytuj JS w kontrolerach Stimulus (`assets/controllers/**`) lub w `assets/app.js` jeśli to globalny glue code.
- Tailwind watch (na hosta):
  ```bash
  npm install        # lub npm ci
  npm run tailwind:watch
  ```  
  Wejście: `tailwind/app.css`; wyjście: `assets/styles/dist/tailwind.css`.
- Asset Mapper w dev: Symfony może serwować zasoby bez kompilacji, ale jeśli chcesz mieć fingerprinty na bieżąco:  
  ```bash
  php bin/console asset-map:compile --watch
  ```
- Start całego stacka w Dockerze (buduje CSS i asset mapę jednokrotnie): `./localbin/start.sh`.

## Build / minifikacja
- CSS: `npm run tailwind:build` (używa `tailwindcss -i ./tailwind/app.css -o ./assets/styles/dist/tailwind.css --minify`).
- JS: `npm run js:minify` (esbuild, bez bundlowania, na `assets/*.js` + `assets/controllers/*.js`, format ESM, `--minify --target=es2020 --keep-names --allow-overwrite`). Używane w buildzie prod; w dev zwykle pomijamy.
- ImportMap: `php bin/console importmap:install --no-interaction --env=prod` (pobiera zdefiniowane paczki).
- Asset Mapper: `php bin/console asset-map:compile --env=prod` (kopiuje/fingerprintuje do `public/assets`).

- ### Ścieżki automatyczne
- `localbin/assets.sh` – dev: buduje Tailwind w kontenerze Node (bez minifikacji JS), uruchamia `importmap:install` i `asset-map:compile` w działającym kontenerze `app`.  
- `localbin/start.sh` – najpierw wywołuje `localbin/assets.sh`, potem uruchamia `docker compose up` (logi na konsoli).  
- `Dockerfile.prod` (stage `builder`) – prod: `npm ci`, `npm run tailwind:build`, `npm run js:minify`, `importmap:install --env=prod`, `asset-map:compile --env=prod`, potem usuwa `node_modules`.

## Szybki cheat sheet
- Dev CSS watch: `npm run tailwind:watch`
- Ręczny build CSS (minify): `npm run tailwind:build`
- Ręczna minifikacja JS (ESM, bez bundla): `npm run js:minify`
- Odbudowa mapy assetów: `php bin/console asset-map:compile --env=prod`
- ImportMap (gdy dodasz nową bibliotekę): `php bin/console importmap:install`
- Pełny build w Dockerze: `./localbin/assets.sh`

