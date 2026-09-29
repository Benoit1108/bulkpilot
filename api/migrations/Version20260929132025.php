<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929132025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Coach: recommendations and their decisions (versioned), settings (tone, pace, last analysis and its adherence; versioned), weekly reports (one a week per user).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE coach_recommendations (version INT DEFAULT 1 NOT NULL, owner_id UUID NOT NULL, kind VARCHAR(40) NOT NULL, level VARCHAR(10) NOT NULL, figures JSON NOT NULL, proposal_from_kcal INT DEFAULT NULL, proposal_by_kcal INT DEFAULT NULL, title VARCHAR(200) NOT NULL, body TEXT NOT NULL, why JSON NOT NULL, action VARCHAR(300) NOT NULL, provider VARCHAR(40) NOT NULL, status VARCHAR(10) NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, postponed_until TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, decided_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX coach_recommendations_owner_created ON coach_recommendations (owner_id, created_at)');
        $this->addSql('CREATE TABLE coach_settings (version INT DEFAULT 1 NOT NULL, personality VARCHAR(20) NOT NULL, pace_kg_per_week DOUBLE PRECISION DEFAULT NULL, analysed_on DATE DEFAULT NULL, adherence JSON DEFAULT NULL, updated_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (owner_id))');
        $this->addSql('CREATE TABLE coach_weekly_reports (owner_id UUID NOT NULL, week_start DATE NOT NULL, figures JSON NOT NULL, adherence JSON NOT NULL, verdict VARCHAR(10) NOT NULL, action VARCHAR(300) DEFAULT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX coach_weekly_reports_owner_week ON coach_weekly_reports (owner_id, week_start)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE coach_recommendations');
        $this->addSql('DROP TABLE coach_settings');
        $this->addSql('DROP TABLE coach_weekly_reports');
    }
}
