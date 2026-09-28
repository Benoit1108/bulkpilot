<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Nutrition\Domain\Journal\JournalEntryRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class SaveFavoriteMealHandler implements CommandHandler
{
    public function __construct(private JournalEntryRepository $entries, private FavoriteMealRepository $favorites, private ClockInterface $clock)
    {
    }

    public function __invoke(SaveFavoriteMeal $command): void
    {
        $entries = array_values(array_filter(
            $this->entries->ofDay($command->userId, $command->day),
            static fn (JournalEntry $entry): bool => $entry->slot() === $command->slot,
        ));

        $this->favorites->save(FavoriteMeal::fromEntries($command->favoriteId, $command->userId, $command->name, $entries, $this->clock->now()));
    }
}
