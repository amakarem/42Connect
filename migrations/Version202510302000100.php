<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251020195000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add ready_to_help boolean column to user table';
    }

    public function up(Schema $schema): void
    {
        // Add the ready_to_help column with default false
        $this->addSql('ALTER TABLE "user" ADD COLUMN ready_to_help BOOLEAN DEFAULT FALSE NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Remove the column on rollback
        $this->addSql('ALTER TABLE "user" DROP COLUMN ready_to_help');
    }
}
