<?php

declare(strict_types=1);

namespace App\Export\Application;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<ExportView>>
 */
final readonly class ListExports implements Query
{
    public const int LIMIT = 10;

    public function __construct(public UserId $userId)
    {
    }
}
