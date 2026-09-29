<?php

declare(strict_types=1);

namespace App\Training\Domain\Prescription;

/**
 * How the next load is suggested for an exercise (spec §37). A suggestion is only ever shown:
 * the user decides.
 */
enum ProgressionStrategy: string
{
    /** Reps climb within the range; once every set reaches its top, the load goes up. */
    case DoubleProgression = 'double_progression';
    /** The load goes up whenever every set reaches the bottom of the range. */
    case Linear = 'linear';
    /** Same load each time: only reps are suggested. */
    case FixedLoad = 'fixed_load';
    /** Same reps each time: the load goes up once they all feel easy (RIR above target). */
    case FixedReps = 'fixed_reps';
    /** The load follows the effort: up when sets end further from failure than planned, down when closer. */
    case TargetRir = 'target_rir';
}
