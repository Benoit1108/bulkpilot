<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928133018 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nutrition: foods (CIQUAL and custom, trigram search), journal entries, versioned targets, meal split.';
    }

    public function up(Schema $schema): void
    {
        // Trusted extension since PostgreSQL 13: the database owner may create it.
        $this->addSql('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        $this->addSql('CREATE TABLE foods (version INT DEFAULT 1 NOT NULL, source VARCHAR(10) NOT NULL, source_reference VARCHAR(20) DEFAULT NULL, owner_id UUID DEFAULT NULL, name VARCHAR(200) NOT NULL, brand VARCHAR(100) DEFAULT NULL, category VARCHAR(150) DEFAULT NULL, state VARCHAR(20) NOT NULL, portions JSON NOT NULL, search_key TEXT NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, per100g_energy_kcal DOUBLE PRECISION NOT NULL, per100g_protein DOUBLE PRECISION NOT NULL, per100g_carbohydrate DOUBLE PRECISION NOT NULL, per100g_fat DOUBLE PRECISION NOT NULL, per100g_fibre DOUBLE PRECISION DEFAULT NULL, per100g_sugars DOUBLE PRECISION DEFAULT NULL, per100g_saturated_fat DOUBLE PRECISION DEFAULT NULL, per100g_salt DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX foods_search_key_trgm ON foods USING gin (search_key gin_trgm_ops)');
        $this->addSql('CREATE INDEX foods_owner_idx ON foods (owner_id)');
        $this->addSql('CREATE UNIQUE INDEX foods_source_reference_unique ON foods (source, source_reference)');
        $this->addSql('CREATE TABLE journal_entries (owner_id UUID NOT NULL, day DATE NOT NULL, slot VARCHAR(10) NOT NULL, grams DOUBLE PRECISION NOT NULL, logged_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, food_id UUID DEFAULT NULL, food_name VARCHAR(310) NOT NULL, food_state VARCHAR(20) NOT NULL, food_per100g_energy_kcal DOUBLE PRECISION NOT NULL, food_per100g_protein DOUBLE PRECISION NOT NULL, food_per100g_carbohydrate DOUBLE PRECISION NOT NULL, food_per100g_fat DOUBLE PRECISION NOT NULL, food_per100g_fibre DOUBLE PRECISION DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX journal_entries_owner_day_idx ON journal_entries (owner_id, day)');
        $this->addSql('CREATE INDEX journal_entries_owner_logged_idx ON journal_entries (owner_id, logged_at)');
        $this->addSql('CREATE TABLE meal_splits (version INT DEFAULT 1 NOT NULL, shares JSON NOT NULL, owner_id UUID NOT NULL, PRIMARY KEY (owner_id))');
        $this->addSql('CREATE TABLE nutrition_targets (owner_id UUID NOT NULL, effective_from DATE NOT NULL, source VARCHAR(10) NOT NULL, estimate JSON DEFAULT NULL, set_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, energy_kcal SMALLINT NOT NULL, protein_grams SMALLINT NOT NULL, carbohydrate_grams SMALLINT NOT NULL, fat_grams SMALLINT NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX nutrition_targets_owner_effective_idx ON nutrition_targets (owner_id, effective_from)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE foods');
        $this->addSql('DROP TABLE journal_entries');
        $this->addSql('DROP TABLE meal_splits');
        $this->addSql('DROP TABLE nutrition_targets');
    }
}
