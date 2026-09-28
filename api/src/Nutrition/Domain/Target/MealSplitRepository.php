<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Shared\Domain\Identifier\UserId;

interface MealSplitRepository
{
    public function save(MealSplit $split): void;

    public function find(UserId $userId): ?MealSplit;

    /**
     * The user's split, or the default one when they never changed it.
     */
    public function splitOf(UserId $userId): MealSplit;
}
