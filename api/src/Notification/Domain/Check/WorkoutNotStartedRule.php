<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;
use App\Shared\Domain\Text\French;

/**
 * A session the week needs today (spec §75: « séance prévue non commencée »): the sessions still
 * to do this week take every day left, today included, and none was started today.
 */
final readonly class WorkoutNotStartedRule implements Rule
{
    public const string FROM = '18:00';

    public function evaluate(Situation $situation): array
    {
        $planned = $situation->plannedPerWeek;
        if (!$situation->isFrom(self::FROM) || null === $planned || $situation->startedToday) {
            return [];
        }
        $toDo = $planned - $situation->sessionsThisWeek;
        // Days left in the week, today included (Monday is 1, Sunday 7).
        $daysLeft = 8 - (int) $situation->now->format('N');
        if ($toDo <= 0 || $toDo < $daysLeft) {
            return [];
        }

        return [new Draft(
            Category::WorkoutNotStarted,
            'workout_not_started:'.$situation->today(),
            'Séance prévue aujourd’hui',
            French::typeset(\sprintf(
                'Il te reste %s cette semaine : c’est le moment de t’entraîner.',
                French::count($toDo, 'séance', 'séances'),
            )),
            '/training',
        )];
    }
}
