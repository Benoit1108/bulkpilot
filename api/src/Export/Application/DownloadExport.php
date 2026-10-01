<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user downloads a ready export (traced, spec §105); the link is asked for after.
 */
final readonly class DownloadExport implements Command
{
    public function __construct(public ExportId $exportId, public UserId $userId)
    {
    }
}
