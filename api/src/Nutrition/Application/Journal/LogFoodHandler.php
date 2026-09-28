<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class LogFoodHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods, private JournalWriter $journal)
    {
    }

    public function __invoke(LogFood $command): void
    {
        $food = $this->foods->getVisibleTo($command->foodId, $command->userId);

        $this->journal->log($command->entryId, $command->userId, $command->day, $command->slot, LoggedFood::snapshotOf($food), $command->grams);
    }
}
