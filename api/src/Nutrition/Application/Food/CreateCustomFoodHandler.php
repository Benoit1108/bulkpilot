<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class CreateCustomFoodHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods)
    {
    }

    public function __invoke(CreateCustomFood $command): void
    {
        $details = $command->details;
        $this->foods->save(Food::createCustom(
            $command->foodId,
            $command->userId,
            $details->name,
            $details->brand,
            $details->state,
            $details->facts(),
            $details->portions(),
        ));
    }
}
