<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Food;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class DeleteCustomFoodHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods, private ClockInterface $clock)
    {
    }

    public function __invoke(DeleteCustomFood $command): void
    {
        $food = $this->foods->getVisibleTo($command->foodId, $command->userId);
        $food->delete($this->clock->now());
        $this->foods->save($food);
    }
}
