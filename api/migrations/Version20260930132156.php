<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260930132156 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Export: export requests (one queued or being made at a time per user); media files are photos or archives (kind; an archive has no thumbnail nor dimensions).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE export_requests (owner_id UUID NOT NULL, from_day DATE DEFAULT NULL, to_day DATE DEFAULT NULL, domains JSON NOT NULL, status VARCHAR(10) NOT NULL, archive_id UUID DEFAULT NULL, requested_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, ready_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, expires_at TIMESTAMP(0) WITH TIME ZONE DEFAULT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX export_requests_owner_requested ON export_requests (owner_id, requested_at)');
        $this->addSql('CREATE UNIQUE INDEX export_requests_one_open ON export_requests (owner_id) WHERE ((status)::text = ANY (ARRAY[(\'queued\'::character varying)::text, (\'processing\'::character varying)::text]))');
        $this->addSql('ALTER TABLE media_files ADD kind VARCHAR(10) DEFAULT \'photo\' NOT NULL');
        $this->addSql('ALTER TABLE media_files ALTER kind DROP DEFAULT');
        $this->addSql('ALTER TABLE media_files ALTER thumbnail_key DROP NOT NULL');
        $this->addSql('ALTER TABLE media_files ALTER content_type TYPE VARCHAR(64)');
        $this->addSql('ALTER TABLE media_files ALTER width DROP NOT NULL');
        $this->addSql('ALTER TABLE media_files ALTER height DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE export_requests');
        $this->addSql('ALTER TABLE media_files DROP kind');
        $this->addSql('ALTER TABLE media_files ALTER thumbnail_key SET NOT NULL');
        $this->addSql('ALTER TABLE media_files ALTER content_type TYPE VARCHAR(32)');
        $this->addSql('ALTER TABLE media_files ALTER width SET NOT NULL');
        $this->addSql('ALTER TABLE media_files ALTER height SET NOT NULL');
    }
}
