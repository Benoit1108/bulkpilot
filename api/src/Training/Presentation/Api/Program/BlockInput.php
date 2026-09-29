<?php

declare(strict_types=1);

namespace App\Training\Presentation\Api\Program;

use App\Training\Application\Program\ProgramDetails;
use App\Training\Domain\Program\ExerciseBlock;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @phpstan-import-type Planned from ProgramDetails
 */
final class BlockInput
{
    /** @var list<PlannedExerciseInput> one exercise, or several for a superset */
    #[Assert\Count(min: 1, max: ExerciseBlock::MAX_EXERCISES)]
    #[Assert\Valid]
    public array $exercises = [];

    /**
     * @return list<Planned>
     */
    public function details(): array
    {
        return array_map(static fn (PlannedExerciseInput $exercise): array => $exercise->details(), $this->exercises);
    }
}
