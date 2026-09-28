<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928123555 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: optimistic locking on users and one-time tokens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE one_time_tokens ADD version INT DEFAULT 1 NOT NULL');
        $this->addSql('ALTER TABLE users ADD version INT DEFAULT 1 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE one_time_tokens DROP version');
        $this->addSql('ALTER TABLE users DROP version');
    }
}
