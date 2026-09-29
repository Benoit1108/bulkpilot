<?php

declare(strict_types=1);

namespace App\Coach\Domain\Report;

use App\Coach\Domain\Analysis\Adherence;

/**
 * A finished week as the report shows it: its figures and how well the plan was kept.
 */
final readonly class WeekFacts
{
    public function __construct(public WeekFigures $figures, public Adherence $adherence)
    {
    }
}
