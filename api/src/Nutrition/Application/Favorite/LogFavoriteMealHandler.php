<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Application\Journal\JournalWriter;
use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Nutrition\Domain\Journal\JournalEntryId;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Identifier\UuidGenerator;

final readonly class LogFavoriteMealHandler implements CommandHandler
{
    public function __construct(
        private FavoriteMealRepository $favorites,
        private FavoriteItems $items,
        private JournalWriter $journal,
        private UuidGenerator $uuids,
    ) {
    }

    public function __invoke(LogFavoriteMeal $command): void
    {
        $favorite = $this->favorites->getOwnedBy($command->favoriteId, $command->userId);

        foreach ($favorite->items() as $item) {
            $this->journal->log(new JournalEntryId($this->uuids->generate()), $command->userId, $command->day, $command->slot, $this->items->snapshot($command->userId, $item), $item->grams);
        }
    }
}
