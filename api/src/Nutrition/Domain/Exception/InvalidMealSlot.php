<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Exception;

use App\Shared\Domain\Exception\DomainException;

final class InvalidMealSlot extends DomainException
{
    public static function named(string $slot): self
    {
        return new self(\sprintf('Unknown meal "%s".', $slot));
    }

    public function errorCode(): string
    {
        return 'nutrition.invalid_meal_slot';
    }
}
