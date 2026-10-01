<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\MediaId;

/**
 * Keeps the written archive privately (Media) and makes the export ready, in one short transaction.
 */
final readonly class CompleteExport implements Command
{
    public function __construct(public ExportId $exportId, public MediaId $archiveId, public string $path)
    {
    }
}
