<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Journal\MealSlot;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetMealSplitHandler implements QueryHandler
{
    public function __construct(private MealSplitRepository $mealSplits)
    {
    }

    /**
     * @return array<value-of<MealSlot>, int>
     */
    public function __invoke(GetMealSplit $query): array
    {
        return $this->mealSplits->splitOf($query->userId)->shares();
    }
}
