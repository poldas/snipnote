# Lokalny start (Docker Compose)

1) Skonfiguruj zmienne:
   - `cp local.env.example .env` (plik jest konsumpowany przez `docker compose`).  
   - W razie potrzeby podmień sekrety (`APP_SECRET`, `JWT_SECRET`, `VERIFY_EMAIL_SECRET`).

2) Uruchom stack:
   - `docker compose up -d`  
   - Kontenery: app (apache+php), database (postgres), mailer (mailpit).

3) Zainstaluj front dev deps i zbuduj Tailwind:
   - `npm install`
   - `npm run tailwind:build` (lub `npm run tailwind:watch` w trakcie dev).

4) Zbuduj assety Symfony:
   - `docker compose exec app php bin/console importmap:install`
   - `docker compose exec app php bin/console asset-map:compile`

5) Aplikacja dev dostępna pod `http://localhost:8080`.

Przydatne:
- Jeśli zmienisz CSS/JS, odpal `tailwind:watch` oraz `asset-map:compile --watch` (w kontenerze).
- W środowisku debug po zmianach usuń stare artefakty: `rm -rf public/assets`.

