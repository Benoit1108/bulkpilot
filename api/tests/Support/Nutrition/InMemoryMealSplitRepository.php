<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryMealSplitRepository implements MealSplitRepository
{
    /** @var array<string, MealSplit> */
    public array $splits = [];

    public function save(MealSplit $split): void
    {
        $this->splits[$split->ownerId()->value] = $split;
    }

    public function find(UserId $userId): ?MealSplit
    {
        return $this->splits[$userId->value] ?? null;
    }

    public function splitOf(UserId $userId): MealSplit
    {
        return $this->find($userId) ?? MealSplit::defaultFor($userId);
    }
}
