<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20251020171547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create user table with created_at and updated_at, plus messenger_messages';
    }

    public function up(Schema $schema): void
    {
        // Create user table with created_at and updated_at
        $this->addSql('CREATE TABLE "user" (
            id SERIAL NOT NULL,
            email VARCHAR(255) NOT NULL,
            roles JSON NOT NULL,
            intra_login VARCHAR(255) DEFAULT NULL,
            usual_full_name VARCHAR(255) DEFAULT NULL,
            display_name VARCHAR(255) DEFAULT NULL,
            kind VARCHAR(255) DEFAULT NULL,
            image VARCHAR(255) DEFAULT NULL,
            location VARCHAR(255) DEFAULT NULL,
            projects JSON DEFAULT NULL,
            campus JSON DEFAULT NULL,
            created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
            ready_to_help BOOLEAN DEFAULT FALSE NOT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D649E7927C74 ON "user" (email)');

        // Trigger function to update updated_at automatically
        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION user_set_updated_at()
            RETURNS TRIGGER AS $$
            BEGIN
                NEW.updated_at = NOW();
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $this->addSql('DROP TRIGGER IF EXISTS user_set_updated_at_trigger ON "user"');
        $this->addSql('CREATE TRIGGER user_set_updated_at_trigger
            BEFORE UPDATE ON "user"
            FOR EACH ROW
            EXECUTE PROCEDURE user_set_updated_at()');

        // Messenger messages table
        $this->addSql('CREATE TABLE messenger_messages (
            id BIGSERIAL NOT NULL,
            body TEXT NOT NULL,
            headers TEXT NOT NULL,
            queue_name VARCHAR(190) NOT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            available_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            delivered_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            PRIMARY KEY(id)
        )');

        $this->addSql('CREATE INDEX IDX_75EA56E0FB7336F0 ON messenger_messages (queue_name)');
        $this->addSql('CREATE INDEX IDX_75EA56E0E3BD61CE ON messenger_messages (available_at)');
        $this->addSql('CREATE INDEX IDX_75EA56E016BA31DB ON messenger_messages (delivered_at)');

        $this->addSql('COMMENT ON COLUMN messenger_messages.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.available_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN messenger_messages.delivered_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql(<<<'SQL'
            CREATE OR REPLACE FUNCTION notify_messenger_messages() RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify('messenger_messages', NEW.queue_name::text);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        SQL);

        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages');
        $this->addSql('CREATE TRIGGER notify_trigger AFTER INSERT OR UPDATE ON messenger_messages FOR EACH ROW EXECUTE PROCEDURE notify_messenger_messages()');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TRIGGER IF EXISTS user_set_updated_at_trigger ON "user"');
        $this->addSql('DROP FUNCTION IF EXISTS user_set_updated_at()');
        $this->addSql('DROP TABLE "user"');

        $this->addSql('DROP TRIGGER IF EXISTS notify_trigger ON messenger_messages');
        $this->addSql('DROP FUNCTION IF EXISTS notify_messenger_messages()');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
