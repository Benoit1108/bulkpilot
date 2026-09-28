<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928121348 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: external identities (Google…), accounts without password.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE user_external_identities (user_id UUID NOT NULL, provider VARCHAR(20) NOT NULL, subject VARCHAR(255) NOT NULL, linked_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX user_external_identities_user ON user_external_identities (user_id)');
        $this->addSql('CREATE UNIQUE INDEX user_external_identities_subject ON user_external_identities (provider, subject)');
        $this->addSql('ALTER TABLE users ALTER password_hash DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_external_identities');
        $this->addSql('ALTER TABLE users ALTER password_hash SET NOT NULL');
    }
}
