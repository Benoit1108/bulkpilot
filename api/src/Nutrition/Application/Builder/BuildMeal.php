<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Builder;

use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * Meals that fill what is left of the day, or of one meal when a slot is given.
 *
 * @implements Query<MealBuilderView>
 */
final readonly class BuildMeal implements Query
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $day, public ?MealSlot $slot = null)
    {
    }
}
