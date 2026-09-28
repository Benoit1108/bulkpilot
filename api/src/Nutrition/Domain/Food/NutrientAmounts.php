<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

/**
 * Absolute nutrient amounts (kcal, grams) of a quantity eaten, or of a reference quantity
 * (100 g of a food, one serving of a recipe).
 *
 * Calories are the reference (spec §25); macros are what the calories are made of.
 */
final readonly class NutrientAmounts
{
    /** Energy of macronutrients (EU Regulation 1169/2011, annex XIV), kcal per gram. */
    public const float KCAL_PER_GRAM_PROTEIN = 4.0;
    public const float KCAL_PER_GRAM_CARBOHYDRATE = 4.0;
    public const float KCAL_PER_GRAM_FAT = 9.0;
    public const float KCAL_PER_GRAM_FIBRE = 2.0;

    public function __construct(
        public float $energyKcal,
        public float $protein,
        public float $carbohydrate,
        public float $fat,
        public ?float $fibre = null,
    ) {
    }

    /** Nothing eaten; fibre stays unknown until a food gives it. */
    public static function zero(): self
    {
        return new self(0.0, 0.0, 0.0, 0.0, null);
    }

    public static function energyOfMacros(float $protein, float $carbohydrate, float $fat): float
    {
        return self::KCAL_PER_GRAM_PROTEIN * $protein + self::KCAL_PER_GRAM_CARBOHYDRATE * $carbohydrate + self::KCAL_PER_GRAM_FAT * $fat;
    }

    public function scale(float $factor): self
    {
        return new self(
            $this->energyKcal * $factor,
            $this->protein * $factor,
            $this->carbohydrate * $factor,
            $this->fat * $factor,
            null === $this->fibre ? null : $this->fibre * $factor,
        );
    }

    public function add(self $other): self
    {
        return new self(
            $this->energyKcal + $other->energyKcal,
            $this->protein + $other->protein,
            $this->carbohydrate + $other->carbohydrate,
            $this->fat + $other->fat,
            null === $this->fibre && null === $other->fibre ? null : ($this->fibre ?? 0.0) + ($other->fibre ?? 0.0),
        );
    }

    /**
     * @param iterable<self> $amounts
     */
    public static function sum(iterable $amounts): self
    {
        $total = self::zero();
        foreach ($amounts as $amount) {
            $total = $total->add($amount);
        }

        return $total;
    }
}
