<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929100906 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Progression: measures of the user\'s own and the measurements of each day (centimetres by measure, JSON).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE progression_custom_measures (owner_id UUID NOT NULL, name VARCHAR(40) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, archived_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX progression_custom_measures_owner_idx ON progression_custom_measures (owner_id, created_at)');
        $this->addSql('CREATE TABLE progression_measurement_days (owner_id UUID NOT NULL, day DATE NOT NULL, measurements JSON NOT NULL, recorded_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX progression_measurement_days_owner_day_key ON progression_measurement_days (owner_id, day)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE progression_custom_measures');
        $this->addSql('DROP TABLE progression_measurement_days');
    }
}
