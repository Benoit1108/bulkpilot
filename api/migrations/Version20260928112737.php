<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928112737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identity: users, device sessions, one-time tokens; HTTP session storage (PdoSessionHandler).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE one_time_tokens (purpose VARCHAR(30) NOT NULL, user_id UUID NOT NULL, secret_hash VARCHAR(64) NOT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, consumed_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX one_time_tokens_user_purpose ON one_time_tokens (user_id, purpose)');
        $this->addSql('CREATE TABLE user_sessions (user_id UUID NOT NULL, device_label VARCHAR(100) NOT NULL, opened_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, last_seen_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, revoked_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX user_sessions_user_active ON user_sessions (user_id, revoked_at)');
        $this->addSql('CREATE TABLE users (email VARCHAR(254) NOT NULL, password_hash VARCHAR(255) NOT NULL, email_verified_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, registered_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, profile_first_name VARCHAR(50) NOT NULL, profile_birth_date DATE DEFAULT NULL, profile_sex VARCHAR(10) DEFAULT NULL, profile_height_cm SMALLINT DEFAULT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX users_email_unique ON users (email)');

        // Owned by PdoSessionHandler (excluded from the ORM schema by doctrine.dbal.schema_filter).
        $this->addSql('CREATE TABLE sessions (sess_id VARCHAR(128) NOT NULL PRIMARY KEY, sess_data BYTEA NOT NULL, sess_lifetime INTEGER NOT NULL, sess_time INTEGER NOT NULL)');
        $this->addSql('CREATE INDEX sessions_sess_lifetime_idx ON sessions (sess_lifetime)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE sessions');
        $this->addSql('DROP TABLE one_time_tokens');
        $this->addSql('DROP TABLE user_sessions');
        $this->addSql('DROP TABLE users');
    }
}
