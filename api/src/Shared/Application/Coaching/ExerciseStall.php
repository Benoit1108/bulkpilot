<?php

declare(strict_types=1);

namespace App\Shared\Application\Coaching;

/**
 * An exercise still trained that has not beaten a record for a while (spec §67: stagnation).
 */
final readonly class ExerciseStall
{
    public function __construct(
        public string $exerciseName,
        /** Sessions of it since its last record. */
        public int $sessions,
        /** Its last record (or its first session, without one). */
        public \DateTimeImmutable $since,
    ) {
    }
}
