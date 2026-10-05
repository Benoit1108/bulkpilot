<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261002143553 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'System: the first and last beats of the worker\'s schedules and of the backups, read by the health report (docs/MONITORING.md).';
    }

    public function up(Schema $schema): void
    {
        // Written by plain SQL (DoctrineBeats, outside the ORM): the first beat starts the grace period
        // of what runs rarely (the weekly restore check after the first backups).
        $this->addSql('CREATE TABLE system_heartbeats (beat VARCHAR(20) NOT NULL, beat_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, first_beat_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (beat))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_heartbeats');
    }
}
