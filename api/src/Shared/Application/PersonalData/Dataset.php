<?php

declare(strict_types=1);

namespace App\Shared\Application\PersonalData;

/**
 * One table of an export: a CSV file, an Excel sheet, a section of the PDF, a key of the JSON.
 * A moment is given as a date-time (the archive writes it in the user's time zone); a day as
 * `YYYY-MM-DD`; words the user reads in French.
 *
 * @phpstan-type Cell string|int|float|bool|\DateTimeImmutable|null
 */
final readonly class Dataset
{
    /**
     * @param string           $name    file-safe (`pesees`)
     * @param string           $title   shown to the user (« Pesées »)
     * @param list<string>     $columns
     * @param list<list<Cell>> $rows    one value per column
     */
    public function __construct(public string $name, public string $title, public array $columns, public array $rows)
    {
    }

    public function isEmpty(): bool
    {
        return [] === $this->rows;
    }
}
