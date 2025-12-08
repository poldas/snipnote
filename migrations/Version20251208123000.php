<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create refresh_tokens table for API refresh flow.
 */
final class Version20251208123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add refresh_tokens table (token, user_id, expires_at, revoked_at, created_at)';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(
            <<<'SQL'
            CREATE TABLE refresh_tokens (
              id BIGSERIAL PRIMARY KEY,
              token VARCHAR(255) NOT NULL UNIQUE,
              user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
              expires_at TIMESTAMPTZ NOT NULL,
              revoked_at TIMESTAMPTZ NULL,
              created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            );
        SQL
        );

        $this->addSql('CREATE INDEX ix_refresh_tokens_user_id ON refresh_tokens (user_id);');
        $this->addSql('CREATE INDEX ix_refresh_tokens_expires_at ON refresh_tokens (expires_at);');
        $this->addSql('CREATE INDEX ix_refresh_tokens_revoked_at ON refresh_tokens (revoked_at);');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS refresh_tokens;');
    }
}
