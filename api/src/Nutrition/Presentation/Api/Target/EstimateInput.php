<?php

declare(strict_types=1);

namespace App\Nutrition\Presentation\Api\Target;

use App\Nutrition\Application\Target\EstimateRequest;
use App\Nutrition\Domain\Exception\InvalidEnergyInputs;
use App\Nutrition\Domain\Target\EnergyInputs;
use App\Nutrition\Domain\Target\Goal;
use App\Nutrition\Domain\Target\Lifestyle;
use App\Nutrition\Domain\Target\Sex;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Prefilled by the PWA from the profile; the weight is typed until Progression records it.
 */
final class EstimateInput
{
    #[Assert\Choice(choices: ['female', 'male'])]
    public string $sex = 'male';

    #[Assert\NotBlank]
    #[Assert\Date]
    public string $birthDate = '';

    #[Assert\NotNull]
    #[Assert\Range(min: EnergyInputs::MIN_HEIGHT_CM, max: EnergyInputs::MAX_HEIGHT_CM)]
    public ?int $heightCm = null;

    #[Assert\NotNull]
    #[Assert\Range(min: EnergyInputs::MIN_WEIGHT_KG, max: EnergyInputs::MAX_WEIGHT_KG)]
    public ?float $weightKg = null;

    #[Assert\Choice(choices: ['sedentary', 'light', 'active', 'very_active'])]
    public string $lifestyle = 'sedentary';

    #[Assert\Range(min: 0, max: EnergyInputs::MAX_SESSIONS_PER_WEEK)]
    public int $trainingSessionsPerWeek = 0;

    #[Assert\Choice(choices: ['bulk', 'maintain', 'cut'])]
    public string $goal = 'maintain';

    /** Signed kg per week: positive for a bulk, negative for a cut, 0 to maintain. */
    #[Assert\Range(min: -EnergyInputs::MAX_WEEKLY_CHANGE_KG, max: EnergyInputs::MAX_WEEKLY_CHANGE_KG)]
    public float $weeklyWeightChangeKg = 0;

    public function request(): EstimateRequest
    {
        $birthDate = \DateTimeImmutable::createFromFormat('!Y-m-d', $this->birthDate);
        if (false === $birthDate) {
            throw InvalidEnergyInputs::because('the birth date is written YYYY-MM-DD');
        }

        return new EstimateRequest(
            Sex::from($this->sex),
            $birthDate,
            (int) $this->heightCm,
            (float) $this->weightKg,
            Lifestyle::from($this->lifestyle),
            $this->trainingSessionsPerWeek,
            Goal::from($this->goal),
            $this->weeklyWeightChangeKg,
        );
    }
}
