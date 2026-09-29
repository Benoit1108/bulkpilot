<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;

/**
 * Calories and proteins day by day and week by week over a period (spec §55–56): the complete
 * days only (today is under way), the days without any entry left out ("not logged", not zero).
 *
 * @implements Query<NutritionHistoryView>
 */
final readonly class GetNutritionHistory implements Query
{
    public function __construct(public UserId $userId, public Period $period, public \DateTimeImmutable $today)
    {
    }
}
