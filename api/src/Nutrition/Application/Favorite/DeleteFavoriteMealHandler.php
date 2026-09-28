<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Favorite;

use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class DeleteFavoriteMealHandler implements CommandHandler
{
    public function __construct(private FavoriteMealRepository $favorites)
    {
    }

    public function __invoke(DeleteFavoriteMeal $command): void
    {
        $this->favorites->remove($this->favorites->getOwnedBy($command->favoriteId, $command->userId));
    }
}
