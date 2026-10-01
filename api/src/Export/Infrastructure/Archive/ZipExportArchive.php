<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Archive;

use App\Export\Application\ExportArchive;
use App\Export\Application\ExportContents;
use App\Shared\Application\File\TemporaryFile;
use App\Shared\Application\Media\MediaNotFound;
use App\Shared\Application\Media\Photos;
use App\Shared\Application\PersonalData\Dataset;
use App\Shared\Domain\Identifier\UserId;

/**
 * The export's ZIP (ADR-0030): `donnees.json` (everything, for a machine), `csv/` (one file per
 * non-empty table, named in French, UTF-8 with a BOM and `;` as French Excel opens it),
 * `bulkpilot.xlsx` (one sheet per table), `synthese.pdf` (to read) and `photos/`. Every part is
 * written to a file first and added from the disk: an export of years is never whole in memory.
 */
final readonly class ZipExportArchive implements ExportArchive
{
    /** The file names' prefix of each domain in `csv/`. */
    private const array PREFIXES = [
        'identity' => 'compte', 'nutrition' => 'nutrition', 'training' => 'entrainement', 'progression' => 'progression',
        'coach' => 'coach', 'notification' => 'notifications', 'activity' => 'activite',
    ];
    private const string TEMPORARY = 'bulkpilot-export-part-';

    public function __construct(private Photos $photos, private SpreadsheetWriter $spreadsheet, private SummaryWriter $summary)
    {
    }

    public function write(string $zipPath, ExportContents $contents, array $datasets, UserId $owner, array $files): void
    {
        $zip = new \ZipArchive();
        if (true !== $zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE)) {
            throw new \RuntimeException('Cannot create the archive.');
        }
        $cells = Cells::in($contents->timeZone);
        $temporary = [];
        $part = static function () use (&$temporary): string {
            return $temporary[] = TemporaryFile::create(self::TEMPORARY);
        };
        try {
            $this->json($json = $part(), $contents, $datasets);
            $zip->addFile($json, 'donnees.json');
            foreach ($datasets as $domain => $tables) {
                foreach ($tables as $dataset) {
                    if ($dataset->isEmpty()) {
                        continue;
                    }
                    $cells->csv($dataset, $csv = $part());
                    $zip->addFile($csv, \sprintf('csv/%s.csv', self::fileName($domain, $dataset)));
                }
            }
            $this->spreadsheet->write($workbook = $part(), $contents, $datasets);
            $zip->addFile($workbook, 'bulkpilot.xlsx');
            if (false === file_put_contents($pdf = $part(), $this->summary->render($contents, $datasets))) {
                throw new \RuntimeException('Cannot write the summary.');
            }
            $zip->addFile($pdf, 'synthese.pdf');
            foreach ($files as $file) {
                $copy = $part();
                try {
                    $this->photos->copyTo($owner, $file->mediaId, $copy);
                } catch (MediaNotFound) {
                    continue; // deleted meanwhile
                }
                $zip->addFile($copy, $file->path);
            }
            // The ZIP reads the added files only now: they must still be there.
            if (true !== $zip->close()) {
                throw new \RuntimeException('Cannot write the archive.');
            }
        } finally {
            TemporaryFile::remove(...$temporary);
        }
    }

    /** « compte.csv », « entrainement-seances.csv », « activite.csv ». */
    private static function fileName(string $domain, Dataset $dataset): string
    {
        $prefix = self::PREFIXES[$domain] ?? $domain;

        return $prefix === $dataset->name || str_starts_with($dataset->name, $prefix) ? $dataset->name : $prefix.'-'.$dataset->name;
    }

    /**
     * Written table by table, row by row: the JSON is never built whole in memory.
     *
     * @param array<string, list<Dataset>> $datasets
     */
    private function json(string $path, ExportContents $contents, array $datasets): void
    {
        $out = fopen($path, 'w');
        if (false === $out) {
            throw new \RuntimeException('Cannot write the JSON.');
        }
        $encode = static fn (mixed $value): string => json_encode($value, \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR | \JSON_PRESERVE_ZERO_FRACTION);
        $zone = new \DateTimeZone($contents->timeZone);
        try {
            fwrite($out, '{"application":"BulkPilot","madeAt":'.$encode($contents->madeAt->setTimezone($zone)->format(\DATE_ATOM))
                .',"timeZone":'.$encode($contents->timeZone)
                .',"period":'.$encode(['from' => $contents->from?->format('Y-m-d'), 'to' => $contents->to?->format('Y-m-d')]).',"data":{');
            $firstDomain = true;
            foreach ($datasets as $domain => $tables) {
                fwrite($out, ($firstDomain ? '' : ',').$encode($domain).':{');
                $firstDomain = false;
                foreach ($tables as $t => $dataset) {
                    fwrite($out, (0 === $t ? '' : ',').$encode($dataset->name).':[');
                    foreach ($dataset->rows as $r => $row) {
                        $values = array_map(static fn (mixed $value): mixed => $value instanceof \DateTimeImmutable ? $value->setTimezone($zone)->format(\DATE_ATOM) : $value, $row);
                        fwrite($out, (0 === $r ? '' : ',').$encode(array_combine($dataset->columns, $values)));
                    }
                    fwrite($out, ']');
                }
                fwrite($out, '}');
            }
            fwrite($out, '}}');
        } finally {
            fclose($out);
        }
    }
}
