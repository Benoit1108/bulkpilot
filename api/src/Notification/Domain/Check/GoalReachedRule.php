<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;
use App\Shared\Domain\Text\French;

/**
 * A milestone or the target of the weight goal just reached (spec §75: « objectif atteint »).
 * Only lately: a step reached long ago is old news.
 */
final readonly class GoalReachedRule implements Rule
{
    /** A step reached today or yesterday is news. */
    public const int RECENT_DAYS = 1;

    public function evaluate(Situation $situation): array
    {
        $since = $situation->now->setTime(0, 0)->modify(\sprintf('-%d day', self::RECENT_DAYS))->format('Y-m-d');
        $drafts = [];
        foreach ($situation->reachedSteps as $step) {
            if ($step->reachedOn->format('Y-m-d') < $since) {
                continue;
            }
            $drafts[] = new Draft(
                Category::GoalReached,
                \sprintf('goal_reached:%s:%s', $step->goalId, number_format($step->weightKg, 1, '.', '')),
                French::typeset($step->target ? \sprintf('Objectif atteint : %s !', French::kg($step->weightKg)) : \sprintf('Jalon atteint : %s', French::kg($step->weightKg))),
                French::typeset($step->target ? 'Bravo : tu peux fixer un nouvel objectif.' : 'Une étape de plus vers ton objectif.'),
                '/progression/objectif',
            );
        }

        return $drafts;
    }
}
