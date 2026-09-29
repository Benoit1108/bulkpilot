<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

/**
 * Spec §47: what counts as a personal record.
 */
enum RecordKind: string
{
    /** Heaviest load lifted for at least one rep. */
    case Load = 'load';
    /** Best estimated one-rep max (Epley, sets of 12 reps or fewer). */
    case EstimatedOneRepMax = 'estimated_one_rep_max';
    /** Most load × reps on the exercise in one workout. */
    case Volume = 'volume';
    /** Most reps in one set (bodyweight without added load). */
    case Reps = 'reps';
    /** Longest timed set. */
    case Duration = 'duration';
    /** More reps than ever at that load. */
    case RepsAtLoad = 'reps_at_load';
}
