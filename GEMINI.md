# Snipnote - Project Context for Gemini

## Project Overview
**Snipnote** is a Notes Sharing MVP web application. It allows users to create, edit, share, and co-edit notes with markdown support. It features per-note visibility settings (private/public), collaborator management, and a public catalog.

### Tech Stack
*   **Backend:** PHP 8.4, Symfony 8.0 (Attributes, DI), Doctrine ORM 3.5.
*   **Frontend:** Twig (templating), HTMX 2+ (interactivity), Tailwind CSS (styling), Stimulus (minimal JS).
*   **Database:** PostgreSQL (Dockerized).
*   **Authentication:** Symfony Security, JWT (Lexik bundle), Refresh Tokens (Gesdinet bundle).
*   **Testing:** PHPUnit (Backend/Integration), Playwright (E2E).
*   **Infrastructure:** Docker Compose (Apache/PHP, PostgreSQL, Mailpit).

## Building and Running

The project is fully Dockerized. All backend commands should generally be run inside the `app` container.

### Prerequisites
*   Docker & Docker Compose
*   Node.js 18+ (for frontend assets & E2E tests)

### Quick Start
1.  **Configure Environment:**
    ```bash
    # Adjust DATABASE_URL, JWT_SECRET, etc. if needed
    ```
2.  **Start Services:**
    ```bash
    docker compose up -d
    ```
3.  **Install Dependencies:**
    ```bash
    docker compose exec app composer install
    npm install
    ```
4.  **Setup Database:**
    ```bash
    docker compose exec app php bin/console doctrine:migrations:migrate
    ```
5.  **Build Frontend:**
    ```bash
    ./localbin/assets.sh   # Comprehensive asset build and map compilation
    ```
6.  **Access App:** `http://localhost:8080`

### Useful Development Commands
*   **Watch Tailwind:** `npm run tailwind:watch`
*   **Watch Symfony Assets:** `docker compose exec app php bin/console asset-map:compile --watch`
*   **Run PHPUnit Tests:** `docker compose exec app ./bin/phpunit`
*   **Run E2E Tests:** `./localbin/test_e2e.sh` (Headless), `npm run e2e:ui` (UI Mode)
*   **Access Shell:** `docker compose exec app bash`

## Project Structure & Key Files

*   **`src/`**: Symfony backend source code.
    *   `Entity/`: Doctrine entities (Note, User, etc.).
    *   `Controller/`: Request handlers (HTML & API).
    *   `Service/`: Business logic.
    *   `Repository/`: Database queries.
*   **`templates/`**: Twig templates for views.
    *   `components/`: Reusable global components (`alert.html.twig`, `logo.html.twig`).
    *   `notes/components/`: Notes-specific components (`notes_nav.html.twig`, `public_link.html.twig`, `note_form.html.twig`).
*   **`assets/`**: Frontend source (CSS, JS, Controllers).
    *   `controllers/`: Stimulus controllers (`notes_dashboard_controller.js`, `note_form_controller.js`, etc.).
    *   `styles/src/`: Source CSS files (organized by `base`, `components`, and `pages`).
*   **`migrations/`**: Database schema changes.
*   **`tests/`**: PHPUnit backend/integration tests.
*   **`e2e/`**: Playwright End-to-End tests.
    *   `specs/`: Detailed test suites including `landing.comprehensive.spec.ts`.
    *   `page-objects/`: Page Object Model implementation.

## Development Conventions
*   **Code Style:** PSR-12 (PHP-CS-Fixer).
*   **Static Analysis:** PHPStan (Level 5).
*   **Architecture:**
    *   Uses Symfony Attributes for routing and ORM mapping.
    *   HTMX is preferred over heavy JS frameworks for interactivity.
    *   Tailwind CSS for all styling.
*   **UI/UX:**
    *   **Shared Components:** Use `alert` for all notifications and `notes_nav` for post-auth navigation.
    *   **Standard Inputs:** Use `.input-modern` class for consistent hover/focus shadow animations.
    *   **Responsiveness:** Always ensure views are mobile-friendly (e.g., date labels, feature cards).
*   **Testing:**
    *   **E2E Selectors:** Use `data-testid` attribute and Playwright's `getByTestId()` locator. This ensures:
        *   **Separation of Concerns:** Changes in CSS classes (styling) or JS logic won't break tests.
        *   **Robustness:** Tests remain stable even after rebrandings or changes in text (i18n).
        *   **Accessibility:** Whenever possible, combine `data-testid` with proper ARIA labels to support both automated testing and screen readers.
    *   **Visual Regression:** Use `expect(page).toHaveScreenshot()` for UI stability verification.
    *   **Page Object Model:** Always update Page Objects when changing DOM structure to keep tests resilient.