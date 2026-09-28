<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Target;

use App\Nutrition\Domain\Exception\InvalidEnergyInputs;

/**
 * Everything the initial estimate is computed from (spec §24), kept with the result to explain it.
 */
final readonly class EnergyInputs
{
    public const int MIN_AGE = 13;
    public const int MAX_AGE = 120;
    public const int MIN_HEIGHT_CM = 100;
    public const int MAX_HEIGHT_CM = 250;
    public const float MIN_WEIGHT_KG = 25.0;
    public const float MAX_WEIGHT_KG = 350.0;
    public const int MAX_SESSIONS_PER_WEEK = 14;
    /** Fastest sensible weight change, kg per week, either way. */
    public const float MAX_WEEKLY_CHANGE_KG = 1.0;
    public const float MIN_WEEKLY_CHANGE_KG = 0.1;

    public function __construct(
        public Sex $sex,
        public int $age,
        public int $heightCm,
        public float $weightKg,
        public Lifestyle $lifestyle,
        public int $trainingSessionsPerWeek,
        public Goal $goal,
        /** Signed: positive to gain, negative to lose, 0 to maintain. */
        public float $weeklyWeightChangeKg,
    ) {
        match (true) {
            $age < self::MIN_AGE || $age > self::MAX_AGE => throw InvalidEnergyInputs::because('age must be between 13 and 120'),
            $heightCm < self::MIN_HEIGHT_CM || $heightCm > self::MAX_HEIGHT_CM => throw InvalidEnergyInputs::because('height must be between 100 and 250 cm'),
            $weightKg < self::MIN_WEIGHT_KG || $weightKg > self::MAX_WEIGHT_KG => throw InvalidEnergyInputs::because('weight must be between 25 and 350 kg'),
            $trainingSessionsPerWeek < 0 || $trainingSessionsPerWeek > self::MAX_SESSIONS_PER_WEEK => throw InvalidEnergyInputs::because('training sessions per week must be between 0 and 14'),
            Goal::Bulk === $goal && ($weeklyWeightChangeKg < self::MIN_WEEKLY_CHANGE_KG || $weeklyWeightChangeKg > self::MAX_WEEKLY_CHANGE_KG) => throw InvalidEnergyInputs::because('a bulk gains between 0.1 and 1 kg per week'),
            Goal::Cut === $goal && ($weeklyWeightChangeKg > -self::MIN_WEEKLY_CHANGE_KG || $weeklyWeightChangeKg < -self::MAX_WEEKLY_CHANGE_KG) => throw InvalidEnergyInputs::because('a cut loses between 0.1 and 1 kg per week'),
            Goal::Maintain === $goal && 0.0 !== $weeklyWeightChangeKg => throw InvalidEnergyInputs::because('maintenance keeps the weight stable'),
            default => null,
        };
    }
}
