<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Journal;

use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Exception\InvalidBatch;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Nutrition\Domain\Journal\LoggedFood;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Identifier\UuidGenerator;

final readonly class LogFoodsHandler implements CommandHandler
{
    public function __construct(private FoodRepository $foods, private JournalWriter $journal, private UuidGenerator $uuids)
    {
    }

    public function __invoke(LogFoods $command): void
    {
        if ([] === $command->items || \count($command->items) > LogFoods::MAX_ITEMS) {
            throw InvalidBatch::because(\sprintf('1 to %d foods at once', LogFoods::MAX_ITEMS));
        }

        $foods = [];
        foreach ($this->foods->visibleAmong($command->userId, array_map(static fn (LogItem $item) => $item->foodId, $command->items)) as $food) {
            $foods[$food->id()->value] = $food;
        }

        foreach ($command->items as $item) {
            $food = $foods[$item->foodId->value] ?? throw FoodNotFound::withId($item->foodId->value);
            $this->journal->log(new JournalEntryId($this->uuids->generate()), $command->userId, $command->day, $command->slot, LoggedFood::snapshotOf($food), $item->grams);
        }
    }
}
