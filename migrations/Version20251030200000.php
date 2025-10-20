<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251020194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create or replace vibes table with vector column, link to user, and updated_at trigger';
    }

    public function up(Schema $schema): void
    {
        // Ensure pgvector extension exists
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        // Drop existing vibes table if it exists
        $this->addSql('DROP TABLE IF EXISTS vibes CASCADE');

        // Create vibes table
        $this->addSql('CREATE TABLE vibes (
            uid TEXT PRIMARY KEY,
            user_id INT UNIQUE,
            original_vibe TEXT NOT NULL,
            vibe TEXT NOT NULL,
            embedding VECTOR(1536) NOT NULL,
            embedding_model TEXT NOT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            CONSTRAINT fk_vibes_user FOREIGN KEY(user_id) REFERENCES "user"(id) ON DELETE CASCADE
        )');

        // Index on embedding for vector search
        $this->addSql('CREATE INDEX vibes_embedding_idx ON vibes USING ivfflat (embedding vector_cosine_ops) WITH (lists = 100)');

        // Function to update updated_at
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        // Drop existing trigger if any
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');

        // Create trigger
        $this->addSql('CREATE TRIGGER vibes_set_updated_at
            BEFORE UPDATE ON vibes
            FOR EACH ROW
            EXECUTE PROCEDURE set_updated_at()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');
        $this->addSql('DROP FUNCTION IF EXISTS set_updated_at()');
        $this->addSql('DROP INDEX IF EXISTS vibes_embedding_idx');
        $this->addSql('DROP TABLE IF EXISTS vibes');
        // Optionally drop extension if desired (be careful)
        // $this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
