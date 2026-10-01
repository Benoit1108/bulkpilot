<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Export\Domain\ExportId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * A short-lived signed link to a ready export's archive.
 *
 * @implements Query<string>
 */
final readonly class GetExportLink implements Query
{
    /** The link is followed at once. */
    public const int LINK_SECONDS = 300;

    public function __construct(public ExportId $exportId, public UserId $userId)
    {
    }
}
