<?php

declare(strict_types=1);

namespace App\Tests\Unit\Export;

use App\Export\Application\ExportContents;
use App\Export\Infrastructure\Archive\SpreadsheetWriter;
use App\Export\Infrastructure\Archive\SummaryWriter;
use App\Export\Infrastructure\Archive\ZipExportArchive;
use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Application\PersonalData\ExportFile;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Progression\FakePhotos;
use OpenSpout\Reader\XLSX\Reader;
use PHPUnit\Framework\TestCase;

/**
 * The archive's files (ADR-0030): the same tables as JSON, CSV, Excel and PDF, and the photos.
 */
final class ZipExportArchiveTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string PHOTO = '00000000-0000-7000-8000-00000000f001';
    private const string GONE = '00000000-0000-7000-8000-00000000f002';

    private string $path;

    protected function setUp(): void
    {
        $this->path = (string) tempnam(sys_get_temp_dir(), 'archive-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->path);
    }

    private function write(): \ZipArchive
    {
        $photos = new FakePhotos();
        $photos->stored[self::PHOTO] = self::ALEX;
        $datasets = [
            'nutrition' => [
                new Dataset('journal', 'Journal', ['Jour', 'Aliment', 'Quantité (g)', 'Favori'], [
                    ['2026-09-29', 'Riz basmati', 150.5, true],
                    ['2026-09-30', '=HYPERLINK("http://evil")', 80, null],
                ]),
            ],
            'progression' => [
                new Dataset('pesees', 'Pesées', ['Jour', 'Poids (kg)'], [['2026-09-29', 80.5]]),
                new Dataset('photos', 'Photos', ['Jour', 'Fichier'], [['2026-09-29', 'photos/2026-09-29-face-1.webp']]),
            ],
        ];
        $contents = new ExportContents(new \DateTimeImmutable('2026-09-30 10:00:00'), new \DateTimeImmutable('2026-09-01'), null, ['nutrition' => 'Nutrition', 'progression' => 'Progression']);
        $files = [new ExportFile(new MediaId(self::PHOTO), 'photos/2026-09-29-face-1.webp'), new ExportFile(new MediaId(self::GONE), 'photos/gone.webp')];

        new ZipExportArchive($photos, new SpreadsheetWriter(), new SummaryWriter())->write($this->path, $contents, $datasets, new UserId(self::ALEX), $files);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->path));

        return $zip;
    }

    public function testTheArchiveHoldsEachFormatAndThePhotosThatStillExist(): void
    {
        $zip = $this->write();
        $names = [];
        for ($i = 0; $i < $zip->numFiles; ++$i) {
            $names[] = $zip->getNameIndex($i);
        }

        self::assertSame(['donnees.json', 'csv/nutrition-journal.csv', 'csv/progression-pesees.csv', 'csv/progression-photos.csv', 'bulkpilot.xlsx', 'synthese.pdf', 'photos/2026-09-29-face-1.webp'], $names);
        self::assertSame('photo '.self::PHOTO, $zip->getFromName('photos/2026-09-29-face-1.webp'));
        self::assertStringStartsWith('%PDF-', (string) $zip->getFromName('synthese.pdf'));
    }

    public function testMomentsAreTheUsersAndEmptyTablesHaveNoCsv(): void
    {
        $datasets = [
            'training' => [
                new Dataset('seances', 'Séances', ['Début', 'Tonnage (kg)'], [[new \DateTimeImmutable('2026-09-28 22:30:00 UTC'), 1234.5]]),
                new Dataset('notes', 'Notes', ['Note'], []),
                new Dataset('reglages', 'Réglages', ['Ton'], [['Direct']]),
            ],
            'notification' => [new Dataset('reglages', 'Réglages', ['De'], [['23:00']])],
        ];
        $contents = new ExportContents(new \DateTimeImmutable('2026-09-30 10:00:00 UTC'), null, null, ['training' => 'Entraînement', 'notification' => 'Notifications'], 'Europe/Paris');
        new ZipExportArchive(new FakePhotos(), new SpreadsheetWriter(), new SummaryWriter())->write($this->path, $contents, $datasets, new UserId(self::ALEX), []);
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->path));

        self::assertSame("\u{FEFF}Début;\"Tonnage (kg)\"\n\"2026-09-29 00:30\";1234,5\n", $zip->getFromName('csv/entrainement-seances.csv'), 'Paris time, a French decimal');
        self::assertFalse($zip->getFromName('csv/entrainement-notes.csv'));
        $json = json_decode((string) $zip->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('2026-09-29T00:30:00+02:00', self::at($json, 'data.training.seances.0.Début'));

        $workbook = (string) tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($workbook, (string) $zip->getFromName('bulkpilot.xlsx'));
        $reader = new Reader();
        $reader->open($workbook);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $sheets[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($workbook);

        self::assertSame(['Séances', 'Entraînement - Réglages', 'Notifications - Réglages'], array_keys($sheets), 'named after the table, its domain in front only when two share a title');
        $start = $sheets['Séances'][1][0];
        self::assertInstanceOf(\DateTimeInterface::class, $start, 'a real date cell');
        self::assertSame('2026-09-29 00:30', $start->format('Y-m-d H:i'));
    }

    public function testTheJsonKeepsTheValuesAsTheyAre(): void
    {
        $json = json_decode((string) $this->write()->getFromName('donnees.json'), true, flags: \JSON_THROW_ON_ERROR);

        self::assertSame(['from' => '2026-09-01', 'to' => null], self::at($json, 'period'));
        self::assertSame(['Jour' => '2026-09-29', 'Aliment' => 'Riz basmati', 'Quantité (g)' => 150.5, 'Favori' => true], self::at($json, 'data.nutrition.journal.0'));
        self::assertSame('=HYPERLINK("http://evil")', self::at($json, 'data.nutrition.journal.1.Aliment'));
        self::assertSame(80.5, self::at($json, 'data.progression.pesees.0.Poids (kg)'));
    }

    public function testTheCsvIsWhatFrenchExcelOpens(): void
    {
        $csv = (string) $this->write()->getFromName('csv/nutrition-journal.csv');

        self::assertSame(
            "\u{FEFF}Jour;Aliment;\"Quantité (g)\";Favori\n2026-09-29;\"Riz basmati\";150,5;oui\n2026-09-30;\"'=HYPERLINK(\"\"http://evil\"\")\";80;\n",
            $csv,
            'a BOM, `;`, decimal commas, and text that would run as a formula made harmless',
        );
    }

    public function testTheWorkbookHasASheetPerTable(): void
    {
        $zip = $this->write();
        $workbook = (string) tempnam(sys_get_temp_dir(), 'xlsx-');
        file_put_contents($workbook, (string) $zip->getFromName('bulkpilot.xlsx'));
        $reader = new Reader();
        $reader->open($workbook);
        $sheets = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $rows[] = $row->toArray();
            }
            $sheets[$sheet->getName()] = $rows;
        }
        $reader->close();
        unlink($workbook);

        self::assertSame(['Journal', 'Pesées', 'Photos'], array_keys($sheets));
        self::assertSame(['Jour', 'Aliment', 'Quantité (g)', 'Favori'], $sheets['Journal'][0]);
        self::assertSame(['2026-09-29', 'Riz basmati', 150.5, 'oui'], $sheets['Journal'][1]);
        self::assertSame("'=HYPERLINK(\"http://evil\")", $sheets['Journal'][2][1], 'never a formula');
    }

    private static function at(mixed $json, string $path): mixed
    {
        foreach (explode('.', $path) as $key) {
            self::assertIsArray($json);
            self::assertArrayHasKey($key, $json);
            $json = $json[$key];
        }

        return $json;
    }
}
