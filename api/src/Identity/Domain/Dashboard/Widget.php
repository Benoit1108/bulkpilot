<?php

declare(strict_types=1);

namespace App\Identity\Domain\Dashboard;

/**
 * The widgets of the home dashboard (spec §13), in their default order.
 */
enum Widget: string
{
    case Weight = 'weight';
    case Nutrition = 'nutrition';
    case Meals = 'meals';
    case Workout = 'workout';
    case Coach = 'coach';
    case Goal = 'goal';
    case Records = 'records';
    case Report = 'report';
}
