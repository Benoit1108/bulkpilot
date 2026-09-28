<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Exception\InvalidEnergyInputs;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\Sex;

/**
 * The estimate inputs as the user gives them; the age is derived from the birth date on the
 * server's clock so that a stored estimate always says how old the user was.
 */
final readonly class EstimateRequest
{
    public function __construct(
        public Sex $sex,
        public \DateTimeImmutable $birthDate,
        public int $heightCm,
        public float $weightKg,
        public Lifestyle $lifestyle,
        public int $trainingSessionsPerWeek,
        public Goal $goal,
        public float $weeklyWeightChangeKg,
    ) {
    }

    public function inputs(\DateTimeImmutable $today): EnergyInputs
    {
        if ($this->birthDate > $today) {
            throw InvalidEnergyInputs::because('the birth date is in the future');
        }

        return new EnergyInputs(
            $this->sex,
            $this->birthDate->diff($today)->y,
            $this->heightCm,
            $this->weightKg,
            $this->lifestyle,
            $this->trainingSessionsPerWeek,
            $this->goal,
            $this->weeklyWeightChangeKg,
        );
    }
}
