# Gemini Project Context: Snipnote

This document provides a comprehensive overview of the Snipnote project, its architecture, and development conventions to be used as instructional context for AI-powered development.

## 1. Project Overview

Snipnote is a lightweight, full-stack web application for creating, sharing, and co-editing notes. It is built with a service-oriented architecture and is fully containerized using Docker.

-   **Purpose**: To provide a fast and minimal application for markdown-based note-taking with features like public/private visibility, collaborator access, and shareable URLs.
-   **Backend**: A **PHP (8.4+)** application built on the **Symfony (8+)** framework. It uses **Doctrine ORM** for database interaction with a **PostgreSQL** database. Authentication is handled via JWTs (Access and Refresh tokens).
-   **Frontend**: The frontend is rendered using **Twig** templates. It is progressively enhanced for dynamic interactions using **HTMX** and small **Stimulus** JavaScript controllers. Styling is done with **Tailwind CSS**.
-   **Architecture**: The application follows a "Thin Controller, Fat Service" model. Business logic is encapsulated in `Service` classes, database queries are handled by `Repository` classes, and `Controller` classes manage the HTTP request/response cycle. The entire environment is orchestrated with **Docker Compose**.

## 2. Building and Running

The project is designed to be run within Docker containers. The primary commands are executed via `docker compose`.

### First-Time Setup

1.  **Start Services**:
    ```bash
    docker compose up -d
    ```
2.  **Install Dependencies**:
    ```bash
    docker compose exec app composer install
    ```
3.  **Configure Environment**: Copy `env.example` to `.env` and fill in the required values (especially for the database and JWT secrets).
4.  **Run Database Migrations**:
    ```bash
    docker compose exec app php bin/console doctrine:migrations:migrate
    ```

### Common Commands

-   **Start/Stop Application**:
    ```bash
    docker compose up -d
    docker compose down
    ```
-   **Run Backend Tests (PHPUnit)**:
    ```bash
    docker compose exec app ./bin/phpunit
    ```
-   **Run End-to-End Tests (Playwright)**:
    ```bash
    # First, install node modules if you haven't
    # npm install
    npm run e2e
    ```
-   **Run Symfony Console Commands**:
    ```bash
    docker compose exec app php bin/console <command>
    ```

## 3. Development Conventions

-   **Coding Style**: The project adheres to the **PSR-12** standard. Code should pass checks from `PHP-CS-Fixer` and `PHPStan`.
-   **Architectural Pattern**: All new logic should follow the established **Entity -> Repository -> Service -> Controller** pattern. Business logic belongs in services, not controllers.
-   **Database Changes**: Any modification to a Doctrine entity that affects the schema **must** be accompanied by a database migration file, generated with `bin/console doctrine:migrations:diff`.
-   **Data Transfer**: Use **DTOs (Data Transfer Objects)** for handling API request and response data. Request DTOs should be validated using the Symfony Validator component.
-   **Frontend Interactions**:
    -   Use **HTMX** for simple, server-rendered partial page updates.
    -   Use **Stimulus** controllers for more complex client-side interactions that require JavaScript state.
-   **Testing**:
    -   Backend business logic in services should be covered by **PHPUnit** tests.
    -   User-facing features and core flows should be covered by **Playwright** end-to-end tests.
