<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260928155212 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Nutrition: barcodes on foods (scanned Open Food Facts products, custom packaged foods).';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE foods ADD barcode VARCHAR(14) DEFAULT NULL');
        $this->addSql('CREATE INDEX foods_barcode_idx ON foods (barcode)');
        // One own food per barcode (deleted ones free it); shared products are unique by source reference.
        $this->addSql('CREATE UNIQUE INDEX foods_owner_barcode_unique ON foods (owner_id, barcode) WHERE ((owner_id IS NOT NULL) AND (barcode IS NOT NULL) AND (deleted_at IS NULL))');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX foods_owner_barcode_unique');
        $this->addSql('DROP INDEX foods_barcode_idx');
        $this->addSql('ALTER TABLE foods DROP barcode');
    }
}
