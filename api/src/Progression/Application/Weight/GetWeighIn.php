<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<WeighInView>
 */
final readonly class GetWeighIn implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day)
    {
    }
}
