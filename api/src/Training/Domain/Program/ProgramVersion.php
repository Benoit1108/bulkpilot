<?php

declare(strict_types=1);

namespace App\Training\Domain\Program;

use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exercise\ExerciseId;

/**
 * The structure of a program at one point in time (spec §49). Never changed once written:
 * changing the structure makes the next version, and workouts stay tied to the one they
 * followed, so that history is never rewritten.
 */
class ProgramVersion extends AggregateRoot
{
    public const int MAX_DAYS = 7;

    /** @var list<TrainingDay> */
    private readonly array $days;

    /**
     * @param list<TrainingDay> $days
     */
    private function __construct(
        private readonly ProgramVersionId $id,
        private readonly ProgramId $programId,
        private readonly int $number,
        array $days,
        private readonly \DateTimeImmutable $createdAt,
    ) {
        $this->days = self::validDays($days);
    }

    /**
     * @param list<TrainingDay> $days
     */
    public static function first(ProgramVersionId $id, ProgramId $programId, array $days, \DateTimeImmutable $now): self
    {
        return new self($id, $programId, 1, $days, $now);
    }

    /**
     * @param list<TrainingDay> $days
     */
    public function next(ProgramVersionId $id, array $days, \DateTimeImmutable $now): self
    {
        return new self($id, $this->programId, $this->number + 1, $days, $now);
    }

    /**
     * @param list<TrainingDay> $days
     */
    public function hasDays(array $days): bool
    {
        return \count($days) === \count($this->days)
            && array_all($this->days, static fn (TrainingDay $day, int $i): bool => $day->equals($days[$i]));
    }

    public function day(int $index): TrainingDay
    {
        return $this->days[$index] ?? throw InvalidProgram::because(\sprintf('this version has no day %d', $index + 1));
    }

    /**
     * @return list<ExerciseId> every exercise the version uses, once
     */
    public function exerciseIds(): array
    {
        $ids = [];
        foreach ($this->days as $day) {
            foreach ($day->exerciseIds() as $id) {
                $ids[$id->value] = $id;
            }
        }

        return array_values($ids);
    }

    public function id(): ProgramVersionId
    {
        return $this->id;
    }

    public function programId(): ProgramId
    {
        return $this->programId;
    }

    public function number(): int
    {
        return $this->number;
    }

    /**
     * @return list<TrainingDay>
     */
    public function days(): array
    {
        return $this->days;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param list<TrainingDay> $days
     *
     * @return list<TrainingDay>
     */
    private static function validDays(array $days): array
    {
        if ([] === $days || \count($days) > self::MAX_DAYS) {
            throw InvalidProgram::because(\sprintf('a program has 1 to %d days', self::MAX_DAYS));
        }
        $names = array_map(static fn (TrainingDay $day): string => mb_strtolower($day->name), $days);
        if (\count(array_unique($names)) !== \count($names)) {
            throw InvalidProgram::because('two days of a program cannot have the same name');
        }

        return $days;
    }
}
