<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Builder;

use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * What a meal should bring: what is left of a target, never negative.
 */
final readonly class MacroGoal
{
    public float $energyKcal;
    public float $protein;
    public float $carbohydrate;
    public float $fat;

    public function __construct(float $energyKcal, float $protein, float $carbohydrate, float $fat)
    {
        $this->energyKcal = max(0.0, $energyKcal);
        $this->protein = max(0.0, $protein);
        $this->carbohydrate = max(0.0, $carbohydrate);
        $this->fat = max(0.0, $fat);
    }

    /**
     * The tighter of two goals, value by value: a meal never gets more than what is left of the day.
     */
    public function within(self $other): self
    {
        return new self(
            min($this->energyKcal, $other->energyKcal),
            min($this->protein, $other->protein),
            min($this->carbohydrate, $other->carbohydrate),
            min($this->fat, $other->fat),
        );
    }

    public static function remainingOf(NutrientAmounts $target, NutrientAmounts $eaten): self
    {
        return new self(
            $target->energyKcal - $eaten->energyKcal,
            $target->protein - $eaten->protein,
            $target->carbohydrate - $eaten->carbohydrate,
            $target->fat - $eaten->fat,
        );
    }
}
