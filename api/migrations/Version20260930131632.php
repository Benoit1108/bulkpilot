<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930131632 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: account deletion asked (when) and due (erased after the 7-day delay unless the user signs in again).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD deletion_requested_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD deletion_due_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP deletion_requested_at');
        $this->addSql('ALTER TABLE users DROP deletion_due_at');
    }
}
