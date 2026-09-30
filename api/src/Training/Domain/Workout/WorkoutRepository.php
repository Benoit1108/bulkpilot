<?php

declare(strict_types=1);

namespace App\Training\Domain\Workout;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Exercise\ExerciseId;

interface WorkoutRepository
{
    public function save(Workout $workout): void;

    /**
     * Also someone else's or a discarded one: an id sent twice must be recognised (idempotent start).
     */
    public function find(WorkoutId $id): ?Workout;

    /**
     * @throws WorkoutNotFound when it does not exist, is discarded or is someone else's
     */
    public function getVisibleTo(WorkoutId $id, UserId $userId): Workout;

    public function inProgressOf(UserId $userId): ?Workout;

    /**
     * @return list<Workout> finished workouts, the latest first, started before the given time if any
     */
    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array;

    /** How far back records and "last time" look: a few years of training. */
    public const int HISTORY_LIMIT = 300;

    /**
     * The finished workouts that had any of these exercises (the latest {@see HISTORY_LIMIT}),
     * started before the given time if any, the oldest first.
     *
     * @param list<ExerciseId> $exerciseIds
     *
     * @return list<Workout>
     */
    public function historyWith(UserId $userId, array $exerciseIds, ?\DateTimeImmutable $before = null): array;

    /**
     * @return list<Workout> the finished workouts finished since that time, the oldest first
     */
    public function finishedSince(UserId $userId, \DateTimeImmutable $since): array;

    /**
     * @return list<Workout> the finished workouts started since that time, the oldest first
     */
    public function startedSince(UserId $userId, \DateTimeImmutable $since): array;
}
