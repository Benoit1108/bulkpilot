<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Job\Job;

/**
 * Builds an export's archive, in the worker (routed to the async transport), outside any
 * transaction: it reads every context, then records the result through short commands.
 */
final readonly class GenerateExport implements Job
{
    public function __construct(public ExportId $exportId)
    {
    }
}
