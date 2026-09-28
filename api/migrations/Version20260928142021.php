<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928142021 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nutrition: recipes, favourite meals, journal entries logged from a recipe.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE favorite_meals (owner_id UUID NOT NULL, name VARCHAR(100) NOT NULL, items JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX favorite_meals_owner_idx ON favorite_meals (owner_id)');
        $this->addSql('CREATE TABLE recipes (version INT DEFAULT 1 NOT NULL, owner_id UUID NOT NULL, name VARCHAR(200) NOT NULL, servings SMALLINT NOT NULL, cooked_weight_grams DOUBLE PRECISION DEFAULT NULL, ingredients JSON NOT NULL, created_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, deleted_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX recipes_owner_idx ON recipes (owner_id)');
        $this->addSql('ALTER TABLE journal_entries ADD food_recipe_id UUID DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE favorite_meals');
        $this->addSql('DROP TABLE recipes');
        $this->addSql('ALTER TABLE journal_entries DROP food_recipe_id');
    }
}
