<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929094441 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Progression: weigh-ins (one a day per user) and weight goals (one followed at a time).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE progression_weigh_ins (owner_id UUID NOT NULL, day DATE NOT NULL, recorded_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, weight_kg DOUBLE PRECISION NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX progression_weigh_ins_owner_day_key ON progression_weigh_ins (owner_id, day)');
        $this->addSql('CREATE TABLE progression_weight_goals (owner_id UUID NOT NULL, start_day DATE NOT NULL, start_kg DOUBLE PRECISION NOT NULL, target_kg DOUBLE PRECISION NOT NULL, milestones JSON NOT NULL, set_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ended_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX progression_weight_goals_one_current ON progression_weight_goals (owner_id) WHERE (ended_at IS NULL)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE progression_weigh_ins');
        $this->addSql('DROP TABLE progression_weight_goals');
    }
}
