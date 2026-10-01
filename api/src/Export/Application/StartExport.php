<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\Command;

/**
 * The worker takes the export (queued → processing); a message redelivered after a crash finds
 * it taken and stops (ExportTaken).
 */
final readonly class StartExport implements Command
{
    public function __construct(public ExportId $exportId)
    {
    }
}
