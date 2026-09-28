<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<TargetView>>
 */
final readonly class ListTargetHistory implements Query
{
    public const int LIMIT = 50;

    public function __construct(public UserId $userId)
    {
    }
}
