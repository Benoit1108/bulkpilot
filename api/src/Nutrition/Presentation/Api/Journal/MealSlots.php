<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Journal;

final class MealSlots
{
    /** Chronological, as App\Nutrition\Domain\Journal\MealSlot. */
    public const array VALUES = ['breakfast', 'lunch', 'snack', 'gainer', 'dinner'];
}
