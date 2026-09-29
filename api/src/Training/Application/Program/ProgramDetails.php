<?php

declare(strict_types=1);

namespace App\Training\Application\Program;

use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\TrainingDay;

/**
 * A program as the user describes it: days of blocks of exercises (a block of several is a superset).
 *
 * @phpstan-type Planned array{exerciseId: string, sets: int, repMin: int, repMax: int, targetRir: ?int, restSeconds: int, strategy: ProgressionStrategy, incrementKg: float, warmupSets: int, dropSet: bool, tempo: ?string, notes: ?string}
 * @phpstan-type Day array{name: string, blocks: list<list<Planned>>}
 */
final readonly class ProgramDetails
{
    /**
     * @param list<Day> $days
     */
    public function __construct(public string $name, public array $days)
    {
    }

    /**
     * @return list<TrainingDay>
     */
    public function days(): array
    {
        return array_map(
            static fn (array $day): TrainingDay => new TrainingDay($day['name'], array_map(
                static fn (array $block): ExerciseBlock => new ExerciseBlock(array_map(self::planned(...), $block)),
                $day['blocks'],
            )),
            $this->days,
        );
    }

    /**
     * @param Planned $planned
     */
    private static function planned(array $planned): PlannedExercise
    {
        $tempo = null === $planned['tempo'] || '' === trim($planned['tempo']) ? null : new Tempo($planned['tempo']);

        return new PlannedExercise(
            new ExerciseId($planned['exerciseId']),
            $planned['sets'],
            new RepRange($planned['repMin'], $planned['repMax']),
            $planned['targetRir'],
            $planned['restSeconds'],
            $planned['strategy'],
            $planned['incrementKg'],
            $planned['warmupSets'],
            $planned['dropSet'],
            $tempo,
            $planned['notes'],
        );
    }
}
