<?php

declare(strict_types=1);

namespace App\Progression\Domain\Goal;

/**
 * Why there is an estimate of the time left, or why not.
 */
enum EstimateStatus: string
{
    /** At the current speed: a statistic, never a promise (spec §53). */
    case Estimated = 'estimated';
    case Reached = 'reached';
    /** Too few weigh-ins over the last weeks to measure a speed. */
    case NotEnoughWeighIns = 'not_enough_weigh_ins';
    /** Stable weight: at this pace, never. */
    case NotMoving = 'not_moving';
    /** The weight goes the other way. */
    case MovingAway = 'moving_away';
}
