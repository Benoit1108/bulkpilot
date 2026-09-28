<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Food\NutrientAmounts;

/**
 * Initial daily targets from the user's profile and goal. A starting point only: the Coach adjusts
 * it later from the real weight trend, always with the user's approval.
 *
 *  1. BMR, Mifflin-St Jeor: 10·kg + 6.25·cm − 5·age + (5 men | −161 women)
 *  2. Expenditure: BMR × (lifestyle level + 0.05 per weekly training session, at most +0.3)
 *  3. Adjustment: weekly weight change × 7700 kcal/kg ÷ 7
 *  4. Target: expenditure + adjustment, rounded to 10 kcal
 *  5. Protein: g/kg for the goal; fat: 25 % of calories (at least 0.6 g/kg); carbohydrate: the rest
 */
final class EnergyCalculator
{
    public const float KCAL_PER_KG = 7700.0;
    public const float TRAINING_ALLOWANCE_PER_SESSION = 0.05;
    public const float MAX_TRAINING_ALLOWANCE = 0.3;
    public const float FAT_SHARE = 0.25;
    public const float MIN_FAT_PER_KG = 0.6;
    /** Below this, a diet needs medical follow-up: the estimate never suggests less. */
    public const int MIN_DAILY_KCAL = 1200;

    public function estimate(EnergyInputs $inputs): EnergyEstimate
    {
        $bmr = 10 * $inputs->weightKg + 6.25 * $inputs->heightCm - 5 * $inputs->age + (Sex::Male === $inputs->sex ? 5 : -161);
        $training = min(self::MAX_TRAINING_ALLOWANCE, $inputs->trainingSessionsPerWeek * self::TRAINING_ALLOWANCE_PER_SESSION);
        $level = $inputs->lifestyle->activityLevel();
        $expenditure = $bmr * ($level + $training);
        $adjustment = $inputs->weeklyWeightChangeKg * self::KCAL_PER_KG / 7;

        $energy = (int) (round(($expenditure + $adjustment) / 10) * 10);
        $energy = max(self::MIN_DAILY_KCAL, min(MacroTargets::MAX_ENERGY_KCAL, $energy));

        return new EnergyEstimate($inputs, $bmr, $level, $training, $expenditure, $adjustment, self::split($energy, $inputs));
    }

    /**
     * Protein and a minimum of fat first, carbohydrate with what is left. For a heavy person on a
     * cut, g/kg of body weight can exceed the calories: protein is then capped to what fits.
     */
    private static function split(int $energy, EnergyInputs $inputs): MacroTargets
    {
        $fat = (int) round(max($energy * self::FAT_SHARE / NutrientAmounts::KCAL_PER_GRAM_FAT, self::MIN_FAT_PER_KG * $inputs->weightKg));
        $fat = min($fat, (int) floor($energy * 0.5 / NutrientAmounts::KCAL_PER_GRAM_FAT));
        $protein = (int) round($inputs->goal->proteinPerKg() * $inputs->weightKg);
        $protein = min($protein, (int) floor(($energy - NutrientAmounts::KCAL_PER_GRAM_FAT * $fat) / NutrientAmounts::KCAL_PER_GRAM_PROTEIN));
        $carbohydrate = max(0, (int) round(($energy - NutrientAmounts::KCAL_PER_GRAM_PROTEIN * $protein - NutrientAmounts::KCAL_PER_GRAM_FAT * $fat) / NutrientAmounts::KCAL_PER_GRAM_CARBOHYDRATE));

        return new MacroTargets($energy, $protein, $carbohydrate, $fat);
    }
}
