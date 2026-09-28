<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Favorite;

use ApiPlatform\Metadata\ApiProperty;
use App\Nutrition\Application\Favorite\FavoriteItemView;
use App\Nutrition\Presentation\Api\Shared\Nutrients;

final readonly class FavoriteMealItem
{
    public function __construct(
        #[ApiProperty(required: true)]
        public string $name,
        #[ApiProperty(required: true)]
        public float $grams,
        #[ApiProperty(required: true)]
        public Nutrients $nutrients,
        #[ApiProperty(required: true)]
        public bool $recipe,
    ) {
    }

    public static function fromView(FavoriteItemView $view): self
    {
        return new self($view->name, $view->grams, Nutrients::of($view->amounts), $view->recipe);
    }
}
