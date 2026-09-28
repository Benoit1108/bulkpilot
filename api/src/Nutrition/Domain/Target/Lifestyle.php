<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

/**
 * Daily activity outside training, as a physical activity level (PAL) multiplier of the BMR.
 */
enum Lifestyle: string
{
    case Sedentary = 'sedentary';
    case Light = 'light';
    case Active = 'active';
    case VeryActive = 'very_active';

    public function activityLevel(): float
    {
        return match ($this) {
            self::Sedentary => 1.2,
            self::Light => 1.35,
            self::Active => 1.5,
            self::VeryActive => 1.7,
        };
    }
}
