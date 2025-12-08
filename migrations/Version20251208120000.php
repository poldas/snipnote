<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_verified flag to users for email verification flow.
 */
final class Version20251208120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add users.is_verified flag (default true) to support email verification state.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users
            ADD COLUMN IF NOT EXISTS is_verified BOOLEAN NOT NULL DEFAULT TRUE;
        SQL
        );
    }

    public function down(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            ALTER TABLE users
            DROP COLUMN IF EXISTS is_verified;
        SQL
        );
    }
}
