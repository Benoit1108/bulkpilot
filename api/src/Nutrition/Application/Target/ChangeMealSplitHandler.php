<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class ChangeMealSplitHandler implements CommandHandler
{
    public function __construct(private MealSplitRepository $mealSplits)
    {
    }

    public function __invoke(ChangeMealSplit $command): void
    {
        $split = $this->mealSplits->find($command->userId);
        if (null === $split) {
            $split = MealSplit::of($command->userId, $command->shares);
        } else {
            $split->change($command->shares);
        }
        $this->mealSplits->save($split);
    }
}
