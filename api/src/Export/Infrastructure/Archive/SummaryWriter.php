<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Archive;

use App\Export\Application\ExportContents;
use App\Shared\Application\PersonalData\Dataset;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * The PDF summary, in French: what the export holds, and the first rows of each table (the whole
 * of them is in the CSV, Excel and JSON files).
 */
final readonly class SummaryWriter
{
    /** Rows of each table shown in the PDF. */
    public const int ROWS = 20;

    /**
     * @param array<string, list<Dataset>> $datasets
     */
    public function render(ExportContents $contents, array $datasets): string
    {
        $options = new Options();
        $options->setIsRemoteEnabled(false);
        $options->setIsPhpEnabled(false);
        $options->setDefaultFont('DejaVu Sans');
        $cache = sys_get_temp_dir().'/dompdf';
        if (!is_dir($cache)) {
            @mkdir($cache, 0o770, true);
        }
        $options->setFontCache($cache);
        $options->setTempDir($cache);
        $options->setChroot($cache);

        $pdf = new Dompdf($options);
        $pdf->loadHtml($this->html($contents, $datasets), 'UTF-8');
        $pdf->setPaper('A4', 'landscape');
        $pdf->render();

        return $pdf->output();
    }

    /**
     * @param array<string, list<Dataset>> $datasets
     */
    private function html(ExportContents $contents, array $datasets): string
    {
        $cells = Cells::in($contents->timeZone);
        $e = static fn (string $text): string => htmlspecialchars($text, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $period = match (true) {
            null === $contents->from && null === $contents->to => 'toutes les données',
            null === $contents->from => 'jusqu’au '.$contents->to->format('d/m/Y'),
            null === $contents->to => 'depuis le '.$contents->from->format('d/m/Y'),
            default => \sprintf('du %s au %s', $contents->from->format('d/m/Y'), $contents->to->format('d/m/Y')),
        };
        $html = '<html lang="fr"><head><meta charset="utf-8"><title>BulkPilot — export de tes données</title><style>'
            .'body{font-family:"DejaVu Sans",sans-serif;font-size:9pt;color:#222}h1{font-size:16pt}h2{font-size:12pt;margin-top:14pt}'
            .'h3{font-size:10pt;margin:8pt 0 2pt}table{border-collapse:collapse;width:100%}th,td{border-bottom:1px solid #ccc;padding:2pt 3pt;text-align:left}'
            .'th{background:#eee}.muted{color:#666}</style></head><body>'
            .'<h1>BulkPilot — export de tes données</h1>'
            .'<p>Fait le '.$e($cells->moment($contents->madeAt)).', '.$e($period).'.</p>'
            .'<p class="muted">Ce PDF résume l’export ; toutes les lignes sont dans les fichiers CSV, Excel (bulkpilot.xlsx) et JSON (donnees.json).</p>';
        foreach ($datasets as $domain => $tables) {
            $html .= '<h2>'.$e($contents->titles[$domain] ?? $domain).'</h2>';
            $empty = [];
            foreach ($tables as $dataset) {
                $count = \count($dataset->rows);
                if (0 === $count) {
                    $empty[] = $dataset->title;
                    continue;
                }
                $html .= '<h3>'.$e($dataset->title).' <span class="muted">('.number_format($count, 0, ',', "\u{202F}").' ligne'.($count > 1 ? 's' : '').')</span></h3>';
                $html .= '<table><tr>'.implode('', array_map(static fn (string $column): string => '<th>'.$e($column).'</th>', $dataset->columns)).'</tr>';
                foreach (\array_slice($dataset->rows, 0, self::ROWS) as $row) {
                    $html .= '<tr>'.implode('', array_map(static fn (mixed $value): string => '<td>'.$e($cells->shown($value)).'</td>', $row)).'</tr>';
                }
                $html .= '</table>';
                if ($count > self::ROWS) {
                    $html .= '<p class="muted">… et '.number_format($count - self::ROWS, 0, ',', "\u{202F}").' de plus dans les autres fichiers.</p>';
                }
            }
            if ([] !== $empty) {
                $html .= '<p class="muted">Rien : '.$e(implode(', ', $empty)).'.</p>';
            }
        }

        return $html.'</body></html>';
    }
}
