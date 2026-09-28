<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class FavoriteMealNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Favourite meal "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'nutrition.favorite_meal_not_found';
    }
}
