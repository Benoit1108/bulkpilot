<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928215846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Training: exercises (shared library and own ones), personal exercise notes, versioned programs (one active per user).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE exercise_notes (owner_id UUID NOT NULL, exercise_id UUID NOT NULL, text VARCHAR(500) NOT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX exercise_notes_owner_exercise_unique ON exercise_notes (owner_id, exercise_id)');
        $this->addSql('CREATE TABLE exercises (version INT DEFAULT 1 NOT NULL, source VARCHAR(10) NOT NULL, library_key VARCHAR(60) DEFAULT NULL, owner_id UUID DEFAULT NULL, name VARCHAR(100) NOT NULL, primary_muscle VARCHAR(20) NOT NULL, secondary_muscles JSON NOT NULL, type VARCHAR(10) NOT NULL, equipment VARCHAR(20) NOT NULL, measurement VARCHAR(10) NOT NULL, description TEXT DEFAULT NULL, media_url VARCHAR(500) DEFAULT NULL, target_rir SMALLINT DEFAULT NULL, rest_seconds SMALLINT NOT NULL, tempo VARCHAR(15) DEFAULT NULL, search_key TEXT NOT NULL, muscle_key VARCHAR(200) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, rep_min SMALLINT NOT NULL, rep_max SMALLINT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX exercises_owner_idx ON exercises (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX exercises_library_key_unique ON exercises (library_key)');
        $this->addSql('CREATE TABLE training_program_versions (program_id UUID NOT NULL, number SMALLINT NOT NULL, days JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX training_program_versions_number_unique ON training_program_versions (program_id, number)');
        $this->addSql('CREATE TABLE training_programs (version INT DEFAULT 1 NOT NULL, owner_id UUID NOT NULL, name VARCHAR(100) NOT NULL, active BOOLEAN NOT NULL, current_version_id UUID NOT NULL, current_version_number SMALLINT NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX training_programs_owner_idx ON training_programs (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX training_programs_one_active ON training_programs (owner_id) WHERE (active AND (deleted_at IS NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE exercise_notes');
        $this->addSql('DROP TABLE exercises');
        $this->addSql('DROP TABLE training_program_versions');
        $this->addSql('DROP TABLE training_programs');
    }
}
