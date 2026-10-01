<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\Command;

final readonly class FailExport implements Command
{
    public function __construct(public ExportId $exportId)
    {
    }
}
