<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Journal\MealSlot;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class ChangeMealSplit implements Command
{
    /**
     * @param array<string, int> $shares by MealSlot value
     */
    public function __construct(public UserId $userId, public array $shares)
    {
    }
}
