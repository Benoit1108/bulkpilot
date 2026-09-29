<?php

declare(strict_types=1);

namespace App\Training\Application\Progress;

use App\Training\Domain\Workout\WorkoutSet;

/**
 * Sessions, working sets, their reps and tonnage (load × reps), and records beaten.
 */
final readonly class TrainingTotals
{
    public function __construct(
        public int $sessions = 0,
        public int $workingSets = 0,
        public int $reps = 0,
        public float $tonnageKg = 0.0,
        public int $records = 0,
    ) {
    }

    /**
     * @param list<WorkoutSet> $workingSets
     */
    public function withSession(array $workingSets, int $records): self
    {
        return new self(
            $this->sessions + 1,
            $this->workingSets + \count($workingSets),
            $this->reps + array_sum(array_map(static fn (WorkoutSet $set): int => $set->reps ?? 0, $workingSets)),
            round($this->tonnageKg + WorkoutSet::totalVolume($workingSets), 1),
            $this->records + $records,
        );
    }
}
