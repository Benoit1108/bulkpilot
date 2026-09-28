<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

enum FoodSource: string
{
    /** ANSES CIQUAL generic foods, shared by everyone. */
    case Ciqual = 'ciqual';
    /** Created by a user, visible to them only. */
    case Custom = 'custom';
    /**
     * A packaged product from Open Food Facts (ODbL), kept once a user confirmed it: shared, keyed
     * by its barcode, refreshed from the source.
     */
    case OpenFoodFacts = 'off';
}
