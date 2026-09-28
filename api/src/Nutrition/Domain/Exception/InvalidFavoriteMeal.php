<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidFavoriteMeal extends DomainException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public function errorCode(): string
    {
        return 'nutrition.invalid_favorite_meal';
    }
}
