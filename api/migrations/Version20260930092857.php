<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930092857 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: preferences apart from the account — the time zone of the devices (scheduled work follows it) and the dashboard layout.';
    }

    public function up(Schema $schema): void
    {
        // No row until a device sends its zone or the user arranges the dashboard: the defaults apply.
        $this->addSql('CREATE TABLE user_preferences (owner_id UUID NOT NULL, time_zone VARCHAR(64) NOT NULL, dashboard_layout JSON DEFAULT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, PRIMARY KEY (owner_id))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_preferences');
    }
}
