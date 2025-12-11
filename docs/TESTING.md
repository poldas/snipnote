# Snipnote testing setup

This repo is wired for PHPUnit (backend) and Playwright (E2E). Follow the steps below to bring up a reproducible test environment that matches the tech stack and the test plan.

## Prerequisites
- Docker + Docker Compose
- Node.js 18+ with npm
- PHP CLI available locally (only if you want to run Symfony commands outside the container)

## Environment files
- Copy `env.example` to `.env`.
- Copy `env.test.example` to `.env.test` (Doctrine will append `_test` automatically).
- Adjust `DATABASE_URL` host to `localhost` if you run tests outside Docker; keep `database` when running inside the `app` container.
- Set `JWT_SECRET` consistently across app and tests.

## Bring up services
```bash
docker compose up -d --build
```
- Exposes web app on `http://localhost:8080`, Postgres on `http://localhost:5432`, Mailpit on `http://localhost:8025`.

## Database for dev/test
```bash
# inside the container
docker compose exec app bash -lc "php bin/console doctrine:migrations:migrate --no-interaction"
docker compose exec app bash -lc "APP_ENV=test php bin/console doctrine:database:create --if-not-exists"
docker compose exec app bash -lc "APP_ENV=test php bin/console doctrine:migrations:migrate --no-interaction"
```

## Unit tests (PHPUnit)
```bash
./test.sh              # runs phpunit inside the app container
docker compose exec app ./bin/phpunit  # alternative
```
- `APP_ENV=test` is forced by `phpunit.dist.xml`; database name will be suffixed with `_test`.

## E2E tests (Playwright)
```bash
npm install
npm run e2e:install:browsers
E2E_BASE_URL=http://localhost:8080 npm run e2e          # headless
HEADLESS=false E2E_BASE_URL=http://localhost:8080 npm run e2e  # headed
npm run e2e:ui        # explore tests in UI mode
```
- Ensure the app server is running (`docker compose up -d`). Optional: set `E2E_WEB_SERVER_CMD` to let Playwright start/await your server.
- Reports: `playwright-report/`; artifacts: `e2e/artifacts/test-results/`.

## Playwright conventions
- Only Chromium/Desktop Chrome is configured (see `playwright.config.ts`).
- Tests live under `e2e/specs/`; page objects under `e2e/page-objects/`.
- Prefer `data-testid` selectors for new UI elements to keep tests stable.

