<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

enum Goal: string
{
    case Bulk = 'bulk';
    case Maintain = 'maintain';
    case Cut = 'cut';

    /**
     * Protein per kg of body weight (spec §26: set automatically from the goal, editable).
     */
    public function proteinPerKg(): float
    {
        return match ($this) {
            self::Bulk => 1.8,
            self::Maintain => 1.6,
            self::Cut => 2.2,
        };
    }
}
