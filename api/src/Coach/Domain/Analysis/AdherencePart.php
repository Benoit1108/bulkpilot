<?php

declare(strict_types=1);

namespace App\Coach\Domain\Analysis;

enum AdherencePart: string
{
    /** Days within 10 % of the calorie target. */
    case Calories = 'calories';
    /** Days at 90 % of the protein target or more. */
    case Protein = 'protein';
    /** Sessions done of those the program plans. */
    case Training = 'training';
    /** Days weighed, of those a trend needs. */
    case WeighIns = 'weigh_ins';
}
