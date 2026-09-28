<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\NotFound;

final class FoodNotFound extends NotFound
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('Food "%s" not found.', $id));
    }

    public function errorCode(): string
    {
        return 'nutrition.food_not_found';
    }
}
