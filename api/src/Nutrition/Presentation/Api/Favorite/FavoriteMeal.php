<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Favorite;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post;
use App\Nutrition\Application\Favorite\FavoriteMealView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

/**
 * A meal eaten often, logged again in one go (POST /nutrition/favorite-meals/{id}/log).
 */
#[ApiResource(
    shortName: 'FavoriteMeal',
    operations: [
        new GetCollection(uriTemplate: '/nutrition/favorite-meals', description: 'Favourite meals, by name, with their current values.', paginationEnabled: false, provider: FavoriteMealState::class),
        new Post(uriTemplate: '/nutrition/favorite-meals', description: 'Keeps what was logged in a meal of a day as a favourite.', input: SaveFavoriteInput::class, processor: FavoriteMealState::class),
        new Delete(uriTemplate: '/nutrition/favorite-meals/{id}', requirements: ['id' => '[0-9a-f-]{36}'], description: 'Deletes a favourite meal (the journal is untouched).', read: false, processor: FavoriteMealState::class),
    ],
)]
final readonly class FavoriteMeal
{
    /**
     * @param list<FavoriteMealItem> $items
     */
    public function __construct(
        #[ApiProperty(identifier: true, required: true)]
        public string $id,
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public array $items,
        #[ApiProperty(required: true)]
        public Nutrients $totals,
    ) {
    }

    public static function fromView(FavoriteMealView $view): self
    {
        return new self($view->id, $view->name, array_map(FavoriteMealItem::fromView(...), $view->items), Nutrients::of($view->totals));
    }
}
