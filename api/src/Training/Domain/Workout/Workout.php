<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Program\PlannedExercise;

/**
 * A training session: the exercises it planned (copied from a program day, or added on the go)
 * and the sets actually done. Times come from the device (it may be offline), within bounds.
 * It is only finished when the user says so (spec §46); sets can still be corrected afterwards.
 */
class Workout extends AggregateRoot
{
    /** A full program day (15 blocks of 4) and some added on the go. */
    public const int MAX_EXERCISES = 80;
    public const int MAX_SETS = 300;
    /** Offline for weeks at most; a clock a few minutes ahead is tolerated. */
    public const string OLDEST = '-30 days';
    public const string LATEST = '+10 minutes';
    /** Sets are done after the start (a device clock a little off aside), within a day. */
    public const string EARLIEST_SET = '-5 minutes';
    public const string LONGEST = '+1 day';

    private int $version = 1;
    private ?\DateTimeImmutable $finishedAt = null;
    private ?\DateTimeImmutable $deletedAt = null;
    /** @var list<WorkoutSet> */
    private array $sets = [];
    /** Every exercise id, space-separated and padded: what "the last time I did X" matches. */
    private string $exerciseKey;

    /**
     * @param list<WorkoutExercise> $exercises
     */
    private function __construct(
        private readonly WorkoutId $id,
        private readonly UserId $ownerId,
        private readonly ?ProgramDayRef $programDay,
        private array $exercises,
        private readonly \DateTimeImmutable $startedAt,
    ) {
        $this->exerciseKey = self::keyOf($exercises);
    }

    /**
     * @param list<WorkoutExercise> $exercises
     */
    public static function start(WorkoutId $id, UserId $owner, ?ProgramDayRef $programDay, array $exercises, \DateTimeImmutable $startedAt, \DateTimeImmutable $now): self
    {
        self::assertPlausible($startedAt, $now);
        if (\count($exercises) > self::MAX_EXERCISES) {
            throw InvalidWorkout::because(\sprintf('a workout has at most %d exercises', self::MAX_EXERCISES));
        }

        return new self($id, $owner, $programDay, $exercises, $startedAt);
    }

    /**
     * Appends an exercise at the given position, in a block of its own; sending the same one
     * again changes nothing.
     */
    public function addExercise(int $index, ExerciseId $exerciseId, string $name, Measurement $measurement, PlannedExercise $planned): void
    {
        $existing = $this->exercises[$index] ?? null;
        if (null !== $existing) {
            if (!$existing->exerciseId->equals($exerciseId)) {
                throw InvalidWorkout::because(\sprintf('exercise %d of this workout is another one', $index + 1));
            }

            return;
        }
        if (null !== $this->finishedAt) {
            throw InvalidWorkout::because('exercises are added while the workout is in progress');
        }
        if ($index !== \count($this->exercises) || $index >= self::MAX_EXERCISES) {
            throw InvalidWorkout::because('an exercise is added after the last one');
        }
        $blocks = array_map(static fn (WorkoutExercise $exercise): int => $exercise->block, $this->exercises);
        $this->exercises[] = new WorkoutExercise($exerciseId, $name, $measurement, [] === $blocks ? 0 : max($blocks) + 1, $planned, true);
        $this->exerciseKey = self::keyOf($this->exercises);
    }

    /**
     * Records a set, or corrects it when its id is known (sending it twice records it once).
     * The set is measured like its exercise, and stays with it.
     */
    public function recordSet(SetId $id, int $exerciseIndex, SetKind $kind, ?float $loadKg, ?int $reps, ?int $seconds, ?int $rir, \DateTimeImmutable $doneAt, \DateTimeImmutable $now): void
    {
        $exercise = $this->exercises[$exerciseIndex] ?? throw InvalidWorkout::because('a set belongs to an exercise of the workout');
        self::assertPlausible($doneAt, $now);
        if ($doneAt < $this->startedAt->modify(self::EARLIEST_SET) || $doneAt > ($this->finishedAt ?? $this->startedAt->modify(self::LONGEST))) {
            throw InvalidWorkout::because('a set is done during its workout');
        }
        $set = new WorkoutSet($id, $exerciseIndex, $kind, $exercise->measurement, $loadKg, $reps, $seconds, $rir, $doneAt);
        foreach ($this->sets as $i => $recorded) {
            if ($recorded->id->equals($id)) {
                if ($recorded->exerciseIndex !== $exerciseIndex) {
                    throw InvalidWorkout::because('a set cannot move to another exercise');
                }
                $this->sets[$i] = $set;

                return;
            }
        }
        if (\count($this->sets) >= self::MAX_SETS) {
            throw InvalidWorkout::because(\sprintf('a workout has at most %d sets', self::MAX_SETS));
        }
        $this->sets[] = $set;
    }

    public function removeSet(SetId $id): void
    {
        $this->sets = array_values(array_filter($this->sets, static fn (WorkoutSet $set): bool => !$set->id->equals($id)));
    }

    /**
     * Idempotent: finishing again keeps the first time.
     */
    public function finish(\DateTimeImmutable $finishedAt, \DateTimeImmutable $now): void
    {
        if (null !== $this->finishedAt) {
            return;
        }
        self::assertPlausible($finishedAt, $now);
        if ($finishedAt < $this->startedAt || $finishedAt > $this->startedAt->modify(self::LONGEST)) {
            throw InvalidWorkout::because('a workout finishes after it started, within a day');
        }
        $this->finishedAt = $finishedAt;
    }

    /**
     * Discarded (started by mistake): gone from history and records.
     */
    public function discard(\DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    public function isDiscarded(): bool
    {
        return null !== $this->deletedAt;
    }

    public function isVisibleTo(UserId $userId): bool
    {
        return null === $this->deletedAt && $this->ownerId->equals($userId);
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function isInProgress(): bool
    {
        return null === $this->finishedAt && null === $this->deletedAt;
    }

    /**
     * @return list<WorkoutSet> the sets of one exercise of the workout, in the order they were done
     */
    public function setsOf(int $exerciseIndex): array
    {
        $sets = array_filter($this->sets, static fn (WorkoutSet $set): bool => $set->exerciseIndex === $exerciseIndex);
        usort($sets, static fn (WorkoutSet $a, WorkoutSet $b): int => $a->doneAt <=> $b->doneAt);

        return $sets;
    }

    /**
     * @return list<WorkoutSet> the sets of that exercise that count (normal and effective)
     */
    public function workingSetsOf(ExerciseId $exerciseId): array
    {
        return array_values(array_filter($this->setsOfExercise($exerciseId), static fn (WorkoutSet $set): bool => $set->kind->isWorking()));
    }

    /**
     * How that exercise was measured in this workout (null when it was not in it).
     */
    public function measurementOf(ExerciseId $exerciseId): ?Measurement
    {
        return array_find($this->exercises, static fn (WorkoutExercise $exercise): bool => $exercise->exerciseId->equals($exerciseId))?->measurement;
    }

    /**
     * @return list<WorkoutSet> every set of that exercise, wherever it appears in the workout
     */
    public function setsOfExercise(ExerciseId $exerciseId): array
    {
        $sets = [];
        foreach ($this->exercises as $index => $exercise) {
            if ($exercise->exerciseId->equals($exerciseId)) {
                array_push($sets, ...$this->setsOf($index));
            }
        }

        return $sets;
    }

    public function id(): WorkoutId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function programDay(): ?ProgramDayRef
    {
        return $this->programDay;
    }

    /**
     * @return list<WorkoutExercise>
     */
    public function exercises(): array
    {
        return $this->exercises;
    }

    /**
     * @return list<WorkoutSet>
     */
    public function sets(): array
    {
        return $this->sets;
    }

    public function startedAt(): \DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function finishedAt(): ?\DateTimeImmutable
    {
        return $this->finishedAt;
    }

    private static function assertPlausible(\DateTimeImmutable $at, \DateTimeImmutable $now): void
    {
        if ($at < $now->modify(self::OLDEST) || $at > $now->modify(self::LATEST)) {
            throw InvalidWorkout::because('a workout time is within the last 30 days');
        }
    }

    /**
     * @param list<WorkoutExercise> $exercises
     */
    private static function keyOf(array $exercises): string
    {
        return ' '.implode(' ', array_unique(array_map(static fn (WorkoutExercise $exercise): string => $exercise->exerciseId->value, $exercises))).' ';
    }
}
