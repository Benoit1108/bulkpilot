<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928222725 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Training: workouts (planned exercises and sets as JSON, one in progress per user).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE workouts (version INT DEFAULT 1 NOT NULL, owner_id UUID NOT NULL, program_day JSON DEFAULT NULL, exercises JSON NOT NULL, sets JSON NOT NULL, exercise_key TEXT NOT NULL, started_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, finished_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX workouts_owner_started_idx ON workouts (owner_id, started_at)');
        $this->addSql('CREATE UNIQUE INDEX workouts_one_in_progress ON workouts (owner_id) WHERE ((finished_at IS NULL) AND (deleted_at IS NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE workouts');
    }
}
