<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The weigh-ins, the most recent first: a page of them, before a day when given.
 *
 * @implements Query<list<WeighInView>>
 */
final readonly class ListWeighIns implements Query
{
    public const int MAX_LIMIT = 100;

    public function __construct(public UserId $userId, public int $limit, public ?\DateTimeImmutable $before)
    {
    }
}
