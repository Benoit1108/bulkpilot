<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<TargetView>
 */
final readonly class GetTarget implements Query
{
    public function __construct(public UserId $userId, public NutritionTargetId $targetId)
    {
    }
}
