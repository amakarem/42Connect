<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251020191500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create vibes table with vector column, ivfflat index, and trigger for updated_at';
    }

    public function up(Schema $schema): void
    {
        // Create vector extension if not exists
        $this->addSql('CREATE EXTENSION IF NOT EXISTS vector');

        // Create vibes table
        $this->addSql(<<<SQL
CREATE TABLE IF NOT EXISTS vibes (
    uid TEXT PRIMARY KEY,
    original_vibe TEXT NOT NULL,
    vibe TEXT NOT NULL,
    embedding VECTOR(1536) NOT NULL,
    embedding_model TEXT NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
)
SQL
        );

        // Create ivfflat index for vector
        $this->addSql(<<<SQL
CREATE INDEX IF NOT EXISTS vibes_embedding_idx
    ON vibes USING ivfflat (embedding vector_cosine_ops)
    WITH (lists = 100)
SQL
        );

        // Create function for updated_at trigger
        $this->addSql(<<<SQL
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL
        );

        // Drop existing trigger if exists
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');

        // Create trigger
        $this->addSql(<<<SQL
CREATE TRIGGER vibes_set_updated_at
BEFORE UPDATE ON vibes
FOR EACH ROW
EXECUTE PROCEDURE set_updated_at()
SQL
        );
    }

    public function down(Schema $schema): void
    {
        // Drop trigger and function
        $this->addSql('DROP TRIGGER IF EXISTS vibes_set_updated_at ON vibes');
        $this->addSql('DROP FUNCTION IF EXISTS set_updated_at()');

        // Drop index
        $this->addSql('DROP INDEX IF EXISTS vibes_embedding_idx');

        // Drop table
        $this->addSql('DROP TABLE IF EXISTS vibes');

        // Optionally drop vector extension (be careful if shared with other tables)
        //$this->addSql('DROP EXTENSION IF EXISTS vector');
    }
}
