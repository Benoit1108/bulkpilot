<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Exercise;

use App\Training\Application\Exercise\ExerciseDetails;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rest;
use App\Training\Domain\Prescription\Rir;
use Symfony\Component\Validator\Constraints as Assert;

final class ExerciseInput
{
    #[Assert\NotBlank(normalizer: 'trim')]
    #[Assert\Length(max: ExerciseSpec::MAX_NAME_LENGTH, normalizer: 'trim')]
    public string $name = '';

    #[Assert\NotBlank]
    #[Assert\Choice(choices: ExerciseChoices::MUSCLES)]
    public string $primaryMuscle = '';

    /** @var list<string> */
    #[Assert\Count(max: ExerciseSpec::MAX_SECONDARY_MUSCLES)]
    #[Assert\All([new Assert\Choice(choices: ExerciseChoices::MUSCLES)])]
    public array $secondaryMuscles = [];

    #[Assert\Choice(choices: ExerciseChoices::TYPES)]
    public string $type = 'compound';

    #[Assert\Choice(choices: ExerciseChoices::EQUIPMENT)]
    public string $equipment = 'other';

    #[Assert\Choice(choices: ExerciseChoices::MEASUREMENTS)]
    public string $measurement = 'weight';

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

    #[Assert\Length(max: 15)]
    public ?string $tempo = null;

    #[Assert\Length(max: ExerciseSpec::MAX_DESCRIPTION_LENGTH)]
    public ?string $description = null;

    #[Assert\Length(max: ExerciseSpec::MAX_MEDIA_URL_LENGTH)]
    #[Assert\Url(protocols: ['https'], requireTld: true)]
    public ?string $mediaUrl = null;

    public function details(): ExerciseDetails
    {
        return new ExerciseDetails(
            $this->name,
            Muscle::from($this->primaryMuscle),
            array_map(Muscle::from(...), $this->secondaryMuscles),
            ExerciseType::from($this->type),
            Equipment::from($this->equipment),
            Measurement::from($this->measurement),
            (int) $this->repMin,
            (int) $this->repMax,
            $this->targetRir,
            $this->restSeconds,
            $this->tempo,
            $this->description,
            $this->mediaUrl,
        );
    }
}
