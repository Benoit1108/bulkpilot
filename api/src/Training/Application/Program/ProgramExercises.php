<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\TrainingDay;

/**
 * A program uses exercises the user can see. One of their own deleted since may stay where it
 * already was (the program keeps working), but is not added anywhere new.
 */
final readonly class ProgramExercises
{
    public function __construct(private ExerciseRepository $exercises)
    {
    }

    /**
     * @param list<TrainingDay> $days
     *
     * @throws ExerciseNotFound
     */
    public function assertUsable(UserId $userId, array $days, ?ProgramVersion $current = null): void
    {
        $ids = [];
        foreach ($days as $day) {
            foreach ($day->exerciseIds() as $id) {
                $ids[$id->value] = $id;
            }
        }
        $kept = [];
        foreach ($current?->exerciseIds() ?? [] as $id) {
            $kept[$id->value] = true;
        }

        $usable = $this->exercises->usableAmong($userId, array_values($ids));
        foreach ($ids as $key => $id) {
            $exercise = $usable[$key] ?? null;
            if (null === $exercise || ($exercise->isDeleted() && !isset($kept[$key]))) {
                throw ExerciseNotFound::withId($id->value);
            }
        }
    }

    /**
     * @param list<ExerciseId> $ids
     *
     * @return array<string, \App\Training\Domain\Exercise\Exercise>
     */
    public function named(UserId $userId, array $ids): array
    {
        return $this->exercises->usableAmong($userId, $ids);
    }
}
