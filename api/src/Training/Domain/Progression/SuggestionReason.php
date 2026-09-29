<?php

declare(strict_types=1);

namespace App\Training\Domain\Progression;

enum SuggestionReason: string
{
    /** Every set reached its target: a little more load. */
    case IncreaseLoad = 'increase_load';
    /** Same load, one more rep (or a few more seconds). */
    case AddReps = 'add_reps';
    /** Same as last time: the target was not reached yet. */
    case Repeat = 'repeat';
    /** The sets were harder than planned (RIR below target): a little less. */
    case DecreaseLoad = 'decrease_load';
}
