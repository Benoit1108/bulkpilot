<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930093444 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Notification: notifications (one per subject and user, ever), settings (categories turned off, quiet hours if any; versioned), and when each account was last checked.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE notification_settings (version INT DEFAULT 1 NOT NULL, disabled JSON NOT NULL, quiet_from VARCHAR(5) DEFAULT NULL, quiet_to VARCHAR(5) DEFAULT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (owner_id))');
        $this->addSql('CREATE TABLE notifications (owner_id UUID NOT NULL, category VARCHAR(30) NOT NULL, dedup_key VARCHAR(120) NOT NULL, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, link VARCHAR(200) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, read_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX notifications_owner_created ON notifications (owner_id, created_at)');
        $this->addSql('CREATE UNIQUE INDEX notifications_owner_key ON notifications (owner_id, dedup_key)');
        $this->addSql('CREATE TABLE notification_checks (checked_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (owner_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notification_settings');
        $this->addSql('DROP TABLE notifications');
        $this->addSql('DROP TABLE notification_checks');
    }
}
