<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class DeleteCustomFood implements Command
{
    public function __construct(public FoodId $foodId, public UserId $userId)
    {
    }
}
