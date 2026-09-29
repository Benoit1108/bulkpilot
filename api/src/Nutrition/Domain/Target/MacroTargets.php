<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Exception\InconsistentTarget;
use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * Daily targets. Calories are the reference; the macros must add up to them
 * (4 kcal/g protein and carbohydrate, 9 kcal/g fat) within a tolerance (spec §25).
 */
final readonly class MacroTargets
{
    /** Allowed gap between the calories and what the macros add up to. */
    public const float TOLERANCE = 0.05;
    public const int MIN_ENERGY_KCAL = 1000;
    public const int MAX_ENERGY_KCAL = 7000;

    public function __construct(
        public int $energyKcal,
        public int $proteinGrams,
        public int $carbohydrateGrams,
        public int $fatGrams,
    ) {
        if ($energyKcal < self::MIN_ENERGY_KCAL || $energyKcal > self::MAX_ENERGY_KCAL) {
            throw InconsistentTarget::because(\sprintf('a daily target is between %d and %d kcal', self::MIN_ENERGY_KCAL, self::MAX_ENERGY_KCAL));
        }
        if ($proteinGrams < 0 || $carbohydrateGrams < 0 || $fatGrams < 0) {
            throw InconsistentTarget::because('macros cannot be negative');
        }
        $fromMacros = self::energyOf($proteinGrams, $carbohydrateGrams, $fatGrams);
        if (abs($fromMacros - $energyKcal) > $energyKcal * self::TOLERANCE) {
            throw InconsistentTarget::because(\sprintf('the macros add up to %d kcal, not %d', $fromMacros, $energyKcal));
        }
    }

    public function equals(self $other): bool
    {
        return $this->energyKcal === $other->energyKcal
            && $this->proteinGrams === $other->proteinGrams
            && $this->carbohydrateGrams === $other->carbohydrateGrams
            && $this->fatGrams === $other->fatGrams;
    }

    /**
     * The same protein and fat at other calories, the carbohydrates making up the rest (an accepted
     * Coach proposal, ADR-0028).
     *
     * @throws InconsistentTarget the calories are out of bounds, or below what the protein and fat already give
     */
    public function withEnergy(int $energyKcal): self
    {
        $left = $energyKcal - NutrientAmounts::KCAL_PER_GRAM_PROTEIN * $this->proteinGrams - NutrientAmounts::KCAL_PER_GRAM_FAT * $this->fatGrams;
        if ($left < 0) {
            throw InconsistentTarget::because(\sprintf('the protein and fat alone give more than %d kcal', $energyKcal));
        }

        return new self($energyKcal, $this->proteinGrams, (int) round($left / NutrientAmounts::KCAL_PER_GRAM_CARBOHYDRATE), $this->fatGrams);
    }

    public static function energyOf(int $protein, int $carbohydrate, int $fat): int
    {
        return (int) NutrientAmounts::energyOfMacros($protein, $carbohydrate, $fat);
    }

    public function amounts(): NutrientAmounts
    {
        return new NutrientAmounts($this->energyKcal, $this->proteinGrams, $this->carbohydrateGrams, $this->fatGrams);
    }
}
