<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928115334 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: two-factor authentication (encrypted TOTP seed, replay guard, recovery code hashes).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD two_factor_pending_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD two_factor_secret TEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD two_factor_enabled_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD two_factor_last_step BIGINT DEFAULT NULL');
        // Existing accounts start without recovery codes; the default only fills them.
        $this->addSql("ALTER TABLE users ADD two_factor_recovery_codes JSON DEFAULT '[]' NOT NULL");
        $this->addSql('ALTER TABLE users ALTER two_factor_recovery_codes DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP two_factor_pending_secret');
        $this->addSql('ALTER TABLE users DROP two_factor_secret');
        $this->addSql('ALTER TABLE users DROP two_factor_enabled_at');
        $this->addSql('ALTER TABLE users DROP two_factor_last_step');
        $this->addSql('ALTER TABLE users DROP two_factor_recovery_codes');
    }
}
