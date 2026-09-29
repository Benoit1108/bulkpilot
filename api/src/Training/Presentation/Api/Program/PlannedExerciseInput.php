<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use App\Training\Application\Program\ProgramDetails;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rest;
use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Presentation\Api\Exercise\ExerciseChoices;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-import-type Planned from ProgramDetails
 */
final class PlannedExerciseInput
{
    #[Assert\NotBlank]
    #[Assert\Uuid]
    public string $exerciseId = '';

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: PlannedExercise::MAX_SETS)]
    public ?int $sets = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: RepRange::MAX)]
    public ?int $repMin = null;

    #[Assert\NotNull]
    #[Assert\Range(min: 1, max: RepRange::MAX)]
    #[Assert\GreaterThanOrEqual(propertyPath: 'repMin')]
    public ?int $repMax = null;

    #[Assert\Range(min: 0, max: Rir::MAX)]
    public ?int $targetRir = null;

    #[Assert\Range(min: 0, max: Rest::MAX_SECONDS)]
    public int $restSeconds = 120;

    #[Assert\Choice(choices: ExerciseChoices::STRATEGIES)]
    public string $strategy = 'double_progression';

    #[Assert\Range(min: PlannedExercise::MIN_INCREMENT_KG, max: PlannedExercise::MAX_INCREMENT_KG)]
    public float $incrementKg = 2.5;

    #[Assert\Range(min: 0, max: PlannedExercise::MAX_WARMUP_SETS)]
    public int $warmupSets = 0;

    public bool $dropSet = false;

    #[Assert\Length(max: 15)]
    public ?string $tempo = null;

    #[Assert\Length(max: PlannedExercise::MAX_NOTES_LENGTH, normalizer: 'trim')]
    public ?string $notes = null;

    /**
     * @return Planned
     */
    public function details(): array
    {
        return [
            'exerciseId' => $this->exerciseId,
            'sets' => (int) $this->sets,
            'repMin' => (int) $this->repMin,
            'repMax' => (int) $this->repMax,
            'targetRir' => $this->targetRir,
            'restSeconds' => $this->restSeconds,
            'strategy' => ProgressionStrategy::from($this->strategy),
            'incrementKg' => $this->incrementKg,
            'warmupSets' => $this->warmupSets,
            'dropSet' => $this->dropSet,
            'tempo' => $this->tempo,
            'notes' => $this->notes,
        ];
    }
}
