<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260929102406 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Media: stored photo files (opaque keys, owner). Progression: progress photos (a day, a pose, the media id).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE media_files (owner_id UUID NOT NULL, storage_key VARCHAR(64) NOT NULL, thumbnail_key VARCHAR(64) NOT NULL, content_type VARCHAR(32) NOT NULL, width INT NOT NULL, height INT NOT NULL, bytes INT NOT NULL, stored_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX media_files_owner_idx ON media_files (owner_id)');
        $this->addSql('CREATE TABLE progression_photos (owner_id UUID NOT NULL, media_id UUID NOT NULL, day DATE NOT NULL, pose VARCHAR(10) NOT NULL, width INT NOT NULL, height INT NOT NULL, added_at TIMESTAMP(0) WITH TIME ZONE NOT NULL, id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX progression_photos_owner_day_idx ON progression_photos (owner_id, day)');
        $this->addSql('CREATE UNIQUE INDEX progression_photos_media_key ON progression_photos (media_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE media_files');
        $this->addSql('DROP TABLE progression_photos');
    }
}
