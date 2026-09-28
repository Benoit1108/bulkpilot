<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

/**
 * Past journal entries keep the values they were logged with.
 */
final readonly class EditCustomFood implements Command
{
    public function __construct(public FoodId $foodId, public UserId $userId, public CustomFoodDetails $details)
    {
    }
}
