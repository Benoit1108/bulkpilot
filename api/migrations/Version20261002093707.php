<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002093707 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Audit: the activity page reads a user\'s events newest first, by time then id: the owner index covers both (no walk of the time index).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('DROP INDEX audit_events_owner_created');
        $this->addSql('CREATE INDEX audit_events_owner_created ON audit_events (owner_id, created_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX audit_events_owner_created');
        $this->addSql('CREATE INDEX audit_events_owner_created ON audit_events (owner_id, created_at)');
    }
}
