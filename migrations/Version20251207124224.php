<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration: Version20251205CreateCoreSchema
 *
 * Cel:
 *  - Utworzyć podstawowy schemat dla aplikacji Notes:
 *    * EXTENSION pgcrypto (jeśli dostępne) - dla gen_random_uuid()
 *    * TYPE note_visibility (ENUM)
 *    * TABLES: users, notes, note_collaborators
 *    * INDEXES: unikalne i wydajnościowe (GIN, BTREE, UNIQUE)
 *    * FUNCTION & TRIGGER: utrzymanie kolumny search_vector_simple (tsvector)
 *    * TRIGGER: automatyczna aktualizacja updated_at przy UPDATE (update_timestamp_updated_at)
 *
 * Dotknięte obiekty DB:
 *  - EXTENSION: pgcrypto
 *  - TYPE: note_visibility
 *  - TABLES: users, notes, note_collaborators
 *  - INDEXES: ux_users_email_lower, ux_users_uuid, ix_notes_*, ux_note_collaborators_*
 *  - FUNCTIONS: notes_search_vector_update(), update_timestamp_updated_at()
 *  - TRIGGERS: trg_notes_search_vector, trg_users_set_updated_at, trg_notes_set_updated_at
 *
 * Uwaga operacyjna:
 *  - Ta migracja zakłada PostgreSQL. Zanim uruchomisz, upewnij się, że masz backupy — operacje DOWN są destrukcyjne.
 *  - CREATE INDEX CONCURRENTLY nie jest użyte, ponieważ Doctrine uruchamia migracje w transakcji. Jeśli chcesz indeksy
 *    tworzone bez blokowania tabel, trzeba oddzielić te polecenia poza transakcję migracji.
 */
final class Version20251207124224 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create core schema: users, notes, note_collaborators, indexes, tsvector function + triggers (including updated_at trigger)';
    }

    public function up(Schema $schema): void
    {
        // -------------------------
        // 1) EXTENSION pgcrypto
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- WŁĄCZENIE ROZSZERZENIA pgcrypto (gen_random_uuid()).
            CREATE EXTENSION IF NOT EXISTS pgcrypto;
        SQL
        );

        // -------------------------
        // 2) TYPE note_visibility
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Tworzenie typu ENUM note_visibility tylko jeśli nie istnieje.
            DO $$
            BEGIN
              IF NOT EXISTS (SELECT 1 FROM pg_type WHERE typname = 'note_visibility') THEN
                CREATE TYPE note_visibility AS ENUM ('public','private','draft');
              END IF;
            END
            $$;
        SQL
        );

        // -------------------------
        // 3) TABLE users
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Tabela users: id (PK), uuid (publiczny identyfikator), email, password_hash, created_at, updated_at
            CREATE TABLE users (
              id BIGSERIAL PRIMARY KEY,
              uuid UUID NOT NULL DEFAULT gen_random_uuid(),
              email TEXT NOT NULL,
              password_hash TEXT NOT NULL,
              created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
              updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        SQL
        );

        // Indexes on users
        $this->addSql(
            <<<'SQL'
            -- Unikalność e-mail case-insensitive przez wyrażenie LOWER(email).
            CREATE UNIQUE INDEX ux_users_email_lower ON users (LOWER(email));
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Indeks dla uuid.
            CREATE UNIQUE INDEX ux_users_uuid ON users (uuid);
        SQL
        );

        // -------------------------
        // 4) TABLE notes
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Tabela notes: owner_id -> users(id) ON DELETE CASCADE, url_token (UUID publiczny), title, description, labels, visibility, search_vector_simple, timestamps
            CREATE TABLE notes (
              id BIGSERIAL PRIMARY KEY,
              owner_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              url_token UUID NOT NULL UNIQUE,
              title TEXT NOT NULL,
              description TEXT NOT NULL,
              labels TEXT[] NOT NULL DEFAULT '{}',
              visibility note_visibility NOT NULL DEFAULT 'private',
              search_vector_simple TSVECTOR,
              created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
              updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        SQL
        );

        // Indexes on notes
        $this->addSql(
            <<<'SQL'
            -- GIN index dla labels (text[]).
            CREATE INDEX ix_notes_labels_gin ON notes USING GIN (labels);
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- GIN index dla search_vector_simple (full-text).
            CREATE INDEX ix_notes_search_vector_gin ON notes USING GIN (search_vector_simple);
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- BTREE na (visibility, url_token).
            CREATE INDEX ix_notes_visibility_urltoken ON notes (visibility, url_token);
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Indeks pomocniczy dla listowania dashboardu właściciela.
            CREATE INDEX ix_notes_owner_createdat_desc ON notes (owner_id, created_at DESC);
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Opcjonalny indeks dla katalogu publicznych notatek właściciela.
            CREATE INDEX ix_notes_owner_visibility_createdat ON notes (owner_id, visibility, created_at DESC);
        SQL
        );

        // -------------------------
        // 5) TABLE note_collaborators
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Tabela note_collaborators: note_id -> notes(id) ON DELETE CASCADE, email, user_id -> users(id) ON DELETE SET NULL
            CREATE TABLE note_collaborators (
              id BIGSERIAL PRIMARY KEY,
              note_id BIGINT NOT NULL REFERENCES notes(id) ON DELETE CASCADE,
              email TEXT NOT NULL,
              user_id BIGINT NULL REFERENCES users(id) ON DELETE SET NULL,
              created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        SQL
        );

        // Indexes on note_collaborators
        $this->addSql(
            <<<'SQL'
            -- Unikalność współedytora per notatka (case-insensitive).
            CREATE UNIQUE INDEX ux_note_collaborators_noteid_email_lower ON note_collaborators (note_id, LOWER(email));
        SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE INDEX ix_note_collaborators_userid ON note_collaborators (user_id);
        SQL
        );

        $this->addSql(
            <<<'SQL'
            CREATE INDEX ix_note_collaborators_email_lower ON note_collaborators (LOWER(email));
        SQL
        );

        // -------------------------
        // 6) FUNCTION notes_search_vector_update + TRIGGER trg_notes_search_vector
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Funkcja aktualizująca search_vector_simple używając konfiguracji 'simple'.
            CREATE OR REPLACE FUNCTION notes_search_vector_update() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
              NEW.search_vector_simple := TO_TSVECTOR('simple', COALESCE(NEW.title, '') || ' ' || COALESCE(NEW.description, ''));
              RETURN NEW;
            END;
            $$;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Trigger utrzymujący kolumnę search_vector_simple przy INSERT/UPDATE.
            CREATE TRIGGER trg_notes_search_vector
            BEFORE INSERT OR UPDATE ON notes
            FOR EACH ROW EXECUTE FUNCTION notes_search_vector_update();
        SQL
        );

        // -------------------------
        // 7) FUNCTION + TRIGGERS updated_at (automatic updated_at refresh on UPDATE)
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- Funkcja ustawiająca NEW.updated_at = NOW() przed UPDATE.
            CREATE OR REPLACE FUNCTION update_timestamp_updated_at() RETURNS trigger
            LANGUAGE plpgsql AS $$
            BEGIN
              NEW.updated_at := NOW();
              RETURN NEW;
            END;
            $$;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Trigger dla tabeli users — automatyczne ustawienie updated_at na NOW() przy UPDATE.
            CREATE TRIGGER trg_users_set_updated_at
            BEFORE UPDATE ON users
            FOR EACH ROW EXECUTE FUNCTION update_timestamp_updated_at();
        SQL
        );

        $this->addSql(
            <<<'SQL'
            -- Trigger dla tabeli notes — automatyczne ustawienie updated_at na NOW() przy UPDATE.
            CREATE TRIGGER trg_notes_set_updated_at
            BEFORE UPDATE ON notes
            FOR EACH ROW EXECUTE FUNCTION update_timestamp_updated_at();
        SQL
        );

        // Koniec UP
    }

    public function down(Schema $schema): void
    {
        // WARNING: operacje poniżej są DESTRUKCYJNE — usuwają tabele i dane. Wykonaj backup przed rollbackem.

        // -------------------------
        // 1) Remove updated_at triggers + function
        // -------------------------
        $this->addSql(
            <<<'SQL'
            DROP TRIGGER IF EXISTS trg_users_set_updated_at ON users;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP TRIGGER IF EXISTS trg_notes_set_updated_at ON notes;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP FUNCTION IF EXISTS update_timestamp_updated_at();
        SQL
        );

        // -------------------------
        // 2) Remove search_vector trigger + function
        // -------------------------
        $this->addSql(
            <<<'SQL'
            DROP TRIGGER IF EXISTS trg_notes_search_vector ON notes;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP FUNCTION IF EXISTS notes_search_vector_update();
        SQL
        );

        // -------------------------
        // 3) Drop indexes (IF EXISTS)
        // -------------------------
        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_note_collaborators_email_lower;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_note_collaborators_userid;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ux_note_collaborators_noteid_email_lower;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_notes_owner_visibility_createdat;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_notes_owner_createdat_desc;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_notes_visibility_urltoken;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_notes_search_vector_gin;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ix_notes_labels_gin;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ux_users_uuid;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP INDEX IF EXISTS ux_users_email_lower;
        SQL
        );

        // -------------------------
        // 4) Drop tables (DESTRUCTIVE)
        // -------------------------
        $this->addSql(
            <<<'SQL'
            -- DESTRUKCYJNE: usunięcie tabel usuwa WSZYSTKIE dane — wykonaj backup przed uruchomieniem.
            DROP TABLE IF EXISTS note_collaborators;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP TABLE IF EXISTS notes;
        SQL
        );

        $this->addSql(
            <<<'SQL'
            DROP TABLE IF EXISTS users;
        SQL
        );

        // -------------------------
        // 5) Drop enum type (if exists)
        // -------------------------
        $this->addSql(
            <<<'SQL'
            DO $$
            BEGIN
              IF EXISTS (SELECT 1 FROM pg_type WHERE typname = 'note_visibility') THEN
                DROP TYPE IF EXISTS note_visibility;
              END IF;
            END
            $$;
        SQL
        );

        // NOTE: pgcrypto extension is not dropped by this migration (may be used by other objects).
    }
}
