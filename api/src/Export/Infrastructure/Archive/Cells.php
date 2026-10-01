<?php

declare(strict_types=1);

namespace App\Export\Infrastructure\Archive;

use App\Shared\Application\PersonalData\Dataset;

/**
 * Values as the archive writes them, in French and in the user's time zone: « 2026-09-30 15:53 »
 * in a CSV (French Excel reads it as a date), a real date cell in the workbook, « 30/09/2026 15:53 »
 * in the PDF; decimals with a comma, booleans in words. Text a spreadsheet would run as a formula
 * (`=`, `+`, `-`, `@`, tab, carriage return first: a food or exercise name the user typed) is
 * prefixed with `'`.
 *
 * @phpstan-import-type Cell from Dataset
 */
final readonly class Cells
{
    private const string BOM = "\u{FEFF}";

    public function __construct(private \DateTimeZone $zone)
    {
    }

    public static function in(string $timeZone): self
    {
        return new self(new \DateTimeZone($timeZone));
    }

    /**
     * Writes the dataset as a CSV file (UTF-8 with a BOM, `;`), streamed.
     */
    public function csv(Dataset $dataset, string $path): void
    {
        $stream = fopen($path, 'w');
        if (false === $stream) {
            throw new \RuntimeException('Cannot write a CSV.');
        }
        try {
            fwrite($stream, self::BOM);
            fputcsv($stream, $dataset->columns, ';', '"', '');
            foreach ($dataset->rows as $row) {
                fputcsv($stream, array_map($this->text(...), $row), ';', '"', '');
            }
        } finally {
            fclose($stream);
        }
    }

    /**
     * What a workbook cell holds: moments as dates in the user's zone, numbers as they are.
     *
     * @param Cell $value
     */
    public function value(string|int|float|bool|\DateTimeImmutable|null $value): string|int|float|\DateTimeImmutable|null
    {
        return match (true) {
            $value instanceof \DateTimeImmutable => $value->setTimezone($this->zone),
            \is_bool($value) => self::boolean($value),
            \is_string($value) => self::safe($value),
            default => $value,
        };
    }

    /**
     * A cell of the PDF: « — » when empty, « 2 350 », « 72,5 », « 30/09/2026 15:53 ».
     *
     * @param Cell $value
     */
    public function shown(string|int|float|bool|\DateTimeImmutable|null $value): string
    {
        return match (true) {
            null === $value => '—',
            $value instanceof \DateTimeImmutable => $value->setTimezone($this->zone)->format('d/m/Y H:i'),
            \is_bool($value) => self::boolean($value),
            \is_int($value) => number_format($value, 0, ',', "\u{202F}"),
            \is_float($value) => self::decimal($value, "\u{202F}"),
            1 === preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) => implode('/', array_reverse(explode('-', $value))),
            default => mb_strimwidth($value, 0, 80, '…'),
        };
    }

    /** « le 30/09/2026 à 15:53 » in the user's zone. */
    public function moment(\DateTimeImmutable $moment): string
    {
        return $moment->setTimezone($this->zone)->format('d/m/Y à H:i');
    }

    public static function boolean(bool $value): string
    {
        return $value ? 'oui' : 'non';
    }

    /** « 72,5 », « 1 234,5 » with a thousands separator. */
    public static function decimal(float $value, string $thousands = ''): string
    {
        $text = rtrim(rtrim(number_format($value, 3, ',', $thousands), '0'), ',');

        return '' === $text || '-' === $text ? '0' : $text;
    }

    /**
     * @param Cell $value
     */
    private function text(string|int|float|bool|\DateTimeImmutable|null $value): string
    {
        return match (true) {
            null === $value => '',
            $value instanceof \DateTimeImmutable => $value->setTimezone($this->zone)->format('Y-m-d H:i'),
            \is_bool($value) => self::boolean($value),
            \is_float($value) => self::decimal($value),
            \is_int($value) => (string) $value,
            default => self::safe($value),
        };
    }

    private static function safe(string $text): string
    {
        return '' !== $text && str_contains("=+-@\t\r", $text[0]) ? "'".$text : $text;
    }
}
