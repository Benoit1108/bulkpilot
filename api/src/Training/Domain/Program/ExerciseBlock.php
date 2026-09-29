<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Training\Domain\Exception\InvalidProgram;

/**
 * One exercise, or several done back to back as a superset (A1, A2…, spec §35): the rest comes
 * after the last one of the round.
 */
final readonly class ExerciseBlock
{
    public const int MAX_EXERCISES = 4;

    /** @var list<PlannedExercise> */
    public array $exercises;

    /**
     * @param list<PlannedExercise> $exercises
     */
    public function __construct(array $exercises)
    {
        if ([] === $exercises || \count($exercises) > self::MAX_EXERCISES) {
            throw InvalidProgram::because(\sprintf('a block has 1 to %d exercises', self::MAX_EXERCISES));
        }
        $this->exercises = $exercises;
    }

    public function isSuperset(): bool
    {
        return \count($this->exercises) > 1;
    }

    public function equals(self $other): bool
    {
        return \count($this->exercises) === \count($other->exercises)
            && array_all($this->exercises, static fn (PlannedExercise $exercise, int $i): bool => $exercise->equals($other->exercises[$i]));
    }
}
