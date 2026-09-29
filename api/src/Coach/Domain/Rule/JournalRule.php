<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * Too few days logged for the Coach to judge the calories.
 */
final readonly class JournalRule implements Rule
{
    public function evaluate(Facts $facts): ?Finding
    {
        if (null === $facts->target || $facts->intake->logged() >= CalorieRule::MIN_LOGGED_DAYS) {
            return null;
        }

        return new Finding(Kind::IncompleteJournal, Level::Info, [
            'days' => $facts->intake->windowDays,
            'loggedDays' => $facts->intake->logged(),
            'neededDays' => CalorieRule::MIN_LOGGED_DAYS,
        ]);
    }
}
