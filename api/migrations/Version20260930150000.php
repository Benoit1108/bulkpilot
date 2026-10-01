<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: the append-only journal of important actions (owner, or a pseudonym once the account is erased).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE audit_events (owner_id UUID DEFAULT NULL, pseudonym VARCHAR(64) DEFAULT NULL, action VARCHAR(60) NOT NULL, details JSON NOT NULL, source VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX audit_events_owner_created ON audit_events (owner_id, created_at)');
        $this->addSql('CREATE INDEX audit_events_created ON audit_events (created_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE audit_events');
    }
}
