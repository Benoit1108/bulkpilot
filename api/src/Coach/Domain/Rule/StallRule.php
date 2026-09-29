<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\Stall;

/**
 * Exercises that stall (spec §67). What the data says of eating comes with it, to look at first;
 * the Coach never raises the volume.
 */
final readonly class StallRule implements Rule
{
    public const int MAX_EXERCISES = 3;

    public function evaluate(Facts $facts): ?Finding
    {
        if ([] === $facts->training->stalls) {
            return null;
        }
        $ratio = $facts->kcalRatio();

        return new Finding(Kind::Stall, Level::Watch, [
            'exercises' => array_map(
                static fn (Stall $stall): array => ['exercise' => $stall->exercise, 'sessions' => $stall->sessions, 'since' => $stall->since->format('Y-m-d')],
                \array_slice($facts->training->stalls, 0, self::MAX_EXERCISES),
            ),
            'loggedDays' => $facts->intake->logged(),
            'percentOfTarget' => null === $ratio ? null : CalorieRule::percent($ratio),
        ]);
    }
}
