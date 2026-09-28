<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Journal;

/**
 * Moments of the day, in chronological order. The gainer is a meal of its own for mass gain.
 */
enum MealSlot: string
{
    case Breakfast = 'breakfast';
    case Lunch = 'lunch';
    case Snack = 'snack';
    case Gainer = 'gainer';
    case Dinner = 'dinner';
}
