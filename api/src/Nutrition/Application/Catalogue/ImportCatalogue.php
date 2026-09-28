<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Catalogue;

use App\Shared\Application\Bus\Command\Command;

/**
 * Creates or refreshes the catalogue foods; running it twice changes nothing. Foods already
 * logged keep their journal snapshot.
 *
 * Commands return nothing on this bus: the handler fills the report in, which the CLI reads once
 * the transaction is committed (a failed import throws, so a report is never read half-done).
 */
final readonly class ImportCatalogue implements Command
{
    public function __construct(public ?string $location = null, public ImportReport $report = new ImportReport())
    {
    }
}
