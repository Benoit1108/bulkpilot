<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * Fewer sessions over the week than the active program plans (spec §67: frequency).
 */
final readonly class SessionsRule implements Rule
{
    public function evaluate(Facts $facts): ?Finding
    {
        $planned = $facts->training->plannedPerWeek;
        if (null === $planned || $facts->training->sessions >= $planned) {
            return null;
        }

        return new Finding(Kind::MissedSessions, Level::Watch, [
            'days' => Facts::WEEK_DAYS,
            'sessions' => $facts->training->sessions,
            'planned' => $planned,
        ]);
    }
}
