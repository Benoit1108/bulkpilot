<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

enum FoodSource: string
{
    /** ANSES CIQUAL generic foods, shared by everyone. */
    case Ciqual = 'ciqual';
    /** Created by a user, visible to them only. */
    case Custom = 'custom';
}
