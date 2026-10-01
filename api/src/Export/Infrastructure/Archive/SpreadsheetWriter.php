<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Archive;

use App\Export\Application\ExportContents;
use App\Shared\Application\PersonalData\Dataset;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The Excel workbook (OpenSpout, streamed): one sheet per table named after it (the domain's name
 * in front only when two tables share a title), its header row in bold and frozen, moments as real
 * dates (jj/mm/aaaa hh:mm) in the user's time zone. Empty tables are left out.
 */
final readonly class SpreadsheetWriter
{
    /** Excel's limit on a sheet name. */
    private const int NAME_LENGTH = 31;
    private const float COLUMN_WIDTH = 18.0;

    /**
     * @param array<string, list<Dataset>> $datasets
     */
    public function write(string $path, ExportContents $contents, array $datasets): void
    {
        $cells = Cells::in($contents->timeZone);
        $writer = new Writer();
        $writer->openToFile($path);
        $bold = new Style()->withFontBold(true);
        $date = new Style()->withFormat('dd/mm/yyyy hh:mm');
        $titles = self::titleCounts($datasets);
        $names = [];
        $first = true;
        foreach ($datasets as $domain => $tables) {
            foreach ($tables as $dataset) {
                if ($dataset->isEmpty()) {
                    continue;
                }
                $sheet = $first ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
                $first = false;
                $title = ($titles[$dataset->title] ?? 0) > 1 ? ($contents->titles[$domain] ?? $domain).' - '.$dataset->title : $dataset->title;
                $sheet->setName(self::name($title, $names));
                $sheet->setSheetView(new SheetView(freezeRow: 2));
                $sheet->setColumnWidthForRange(self::COLUMN_WIDTH, 1, max(1, \count($dataset->columns)));
                $writer->addRow(Row::fromValuesWithStyle($dataset->columns, $bold));
                foreach ($dataset->rows as $row) {
                    $writer->addRow(new Row(array_map(static function (mixed $value) use ($cells, $date): Cell {
                        $shown = $cells->value($value);

                        return Cell::fromValue($shown, $shown instanceof \DateTimeImmutable ? $date : null);
                    }, $row)));
                }
            }
        }
        if ($first) {
            $writer->getCurrentSheet()->setName('Export');
            $writer->addRow(Row::fromValues(['Aucune donnée pour cette période.']));
        }
        $writer->close();
    }

    /**
     * @param array<string, list<Dataset>> $datasets
     *
     * @return array<string, int> how many non-empty tables carry each title
     */
    private static function titleCounts(array $datasets): array
    {
        $counts = [];
        foreach ($datasets as $tables) {
            foreach ($tables as $dataset) {
                if (!$dataset->isEmpty()) {
                    $counts[$dataset->title] = ($counts[$dataset->title] ?? 0) + 1;
                }
            }
        }

        return $counts;
    }

    /**
     * A unique sheet name within Excel's rules (31 characters, none of []:*?/\).
     *
     * @param list<string> $taken
     */
    private static function name(string $title, array &$taken): string
    {
        $base = mb_substr(str_replace(['[', ']', ':', '*', '?', '/', '\\'], ' ', $title), 0, self::NAME_LENGTH);
        $name = $base;
        for ($n = 2; \in_array(mb_strtolower($name), $taken, true); ++$n) {
            $suffix = ' ('.$n.')';
            $name = mb_substr($base, 0, self::NAME_LENGTH - mb_strlen($suffix)).$suffix;
        }
        $taken[] = mb_strtolower($name);

        return $name;
    }
}
