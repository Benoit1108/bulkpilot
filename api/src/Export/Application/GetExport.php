<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<ExportView>
 */
final readonly class GetExport implements Query
{
    public function __construct(public ExportId $exportId, public UserId $userId)
    {
    }
}
