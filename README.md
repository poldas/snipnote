# Snipnote – Notes Sharing MVP

## Table of Contents
- [1. Project name](#1-project-name)
- [2. Project description and catalog tree](#2-project-description-and-catalog-tree)
- [3. Tech stack](#3-tech-stack)
- [4. Getting started locally](#4-getting-started-locally)
- [5. Available scripts](#5-available-scripts)
- [6. Project scope](#6-project-scope)
- [7. Project status](#7-project-status)
- [8. Main project modules](#8-main-project-modules)

## 1. Project name
Snipnote – Notes Sharing MVP

## 2. Project description and catalog tree
Snipnote is a lightweight web app for creating, sharing, and co-editing notes. It supports markdown content, per-note labels, configurable visibility (private/public), collaborator access by email, unique shareable URLs, and a public catalog of a user’s public notes. The product targets fast, minimal setup with a clean UI and straightforward sharing flows.

Project layout (selected top-level items):
```
.
├─ compose.yaml            # Docker Compose for app + PostgreSQL
├─ Dockerfile              # PHP 8.4 Apache image
├─ src/                    # Symfony 8 backend (controllers, services, entities)
├─ templates/              # Twig views (auth, notes, public note)
├─ assets/                 # Frontend assets (Tailwind, HTMX/stimulus controllers)
├─ migrations/             # Doctrine migrations
├─ tests/                  # PHPUnit test suite
├─ e2e/                    # Playwright tests (TS)
├─ public/                 # Public web root
├─ package.json            # Node deps for Playwright
├─ composer.json           # PHP deps (Symfony + Doctrine)
└─ README.md
```

## 3. Tech stack
- Backend: PHP 8.4, Symfony 8 (attributes), Doctrine ORM 3.5, Doctrine Migrations, Symfony Security + JWT (lexik/jwt-authentication-bundle), refresh tokens (gesdinet/jwt-refresh-token-bundle).
- Frontend: Twig templating, HTMX 2+, Tailwind CSS, minimal vanilla JS/Stimulus controllers.
- Database: PostgreSQL (Dockerized).
- Tooling/quality: PHPUnit, PHP-CS-Fixer (PSR-12), PHPStan lvl 5, Symfony Profiler/Monolog.
- E2E: Playwright (TypeScript, Chromium).

## 4. Getting started locally
Prerequisites: Docker & Docker Compose, Node 18+ (for Playwright tools), Make sure `.env` values for database and JWT are set (see `env.example` / `env.test.example`).

Steps:
1) Install dependencies (in containers):  
   ```bash
   docker compose up -d
   docker compose exec app composer install
   ```
2) Prepare environment: copy `env.example` → `.env` and fill `DATABASE_URL`, `POSTGRES_*`, `JWT_SECRET`.
3) Run database migrations:  
   ```bash
   docker compose exec app php bin/console doctrine:migrations:migrate
   ```
4) (Optional) Install Playwright browsers for E2E:  
   ```bash
   npm install
   npm run e2e:install:browsers
   ```
5) Access the app at `http://localhost:8080` (container exposes port 80 → host 8080 via `compose.yaml`).

## 5. Available scripts
- `docker compose up -d` / `docker compose down` – start/stop services.
- `docker compose exec app composer install` – install PHP dependencies.
- `docker compose exec app php bin/console doctrine:migrations:migrate` – apply DB migrations.
- `docker compose exec app ./bin/phpunit` – run backend test suite.
- `npm run e2e` – headless Playwright tests.
- `npm run e2e:headed` – headed Playwright run.
- `npm run e2e:ui` – Playwright UI mode.
- `npm run e2e:codegen` – Playwright code generator (uses `E2E_BASE_URL`).
- `npm run e2e:report` – open the Playwright HTML report.

## 6. Project scope
Key capabilities:
- Create and edit notes with title (≤255 chars), markdown body (≈10k chars), and per-note labels (deduplicated).
- Explicit save (no autosave) with initial unique shareable URL; manual URL regeneration invalidates prior links.
- Visibility toggle per note: private (owner + collaborators) or public (readable via URL; edits only for owner/collaborators).
- Collaborator management by email; collaborators share owner privileges except deletion and can remove their own access.
- Search in dashboard and public catalog by title/description plus `label:` filter (OR across provided labels); paginated.
- Public note view (rendered markdown, labels, created date) with copy-friendly content and edit shortcuts for authorized users.
- Public catalog per user UUID with pagination and search; friendly empty/error states.
- Auth flows: email/password registration with verification notice, login, logout, access/refresh tokens with rotation, password reset (request + token-based reset).
- Deletion by owner removes note and all associations; confirmed via prompt.

Out of scope for MVP: version history/undo, rich profiles, global user search, social integrations, imports/exports, attachments, advanced conflict resolution, admin panel, detailed telemetry.

## 7. Project status
- MVP implementation in progress with Dockerized Symfony 8 + PostgreSQL backend, Twig/HTMX frontend, and initial Doctrine migrations.
- Automated coverage includes PHPUnit suites and Playwright E2E specs scaffolded for auth and landing flows.

## 8. Main project modules
- Notes domain (`src/Entity/Note*`, `src/Service/NoteService`, `src/Service/NotesQueryService`, `src/Service/NotesSearchParser`, `src/Repository/NoteRepository`): note lifecycle, visibility, search, and URL regeneration.
- Collaborators (`src/Service/NoteCollaboratorService`, `src/Repository/NoteCollaboratorRepository`, collaborator commands/controllers): add/remove collaborators, collaborator self-removal.
- Public access (`src/Controller/PublicNotePageController.php`, `src/Service/PublicNotesCatalogService`, `src/Mapper/PublicNoteJsonMapper`): public note rendering and public catalog.
- Auth & security (`src/Service/AuthService`, `src/Service/EmailVerificationService`, `src/Security/JwtAuthenticator`, `src/Entity/User`, `src/Entity/RefreshToken`): login/registration, email verification notice, JWT/refresh handling.
- Markdown & presentation (`src/Service/MarkdownPreviewService`, Twig templates in `templates/`, frontend assets in `assets/` with Tailwind/HTMX/Stimulus controllers).
- Testing (`tests/` for PHPUnit, `e2e/` for Playwright specs and page objects).

