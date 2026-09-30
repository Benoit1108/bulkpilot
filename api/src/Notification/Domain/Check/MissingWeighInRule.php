<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;

/**
 * No weigh-in yet today for someone who weighs themselves most days (spec §75: « pesée manquante »).
 */
final readonly class MissingWeighInRule implements Rule
{
    public const string FROM = '11:00';
    /** Weigh-ins over the 7 days before that make a habit. */
    public const int HABIT = 3;

    public function evaluate(Situation $situation): array
    {
        if (!$situation->isFrom(self::FROM) || $situation->weighedToday || $situation->weighInsLastWeek < self::HABIT) {
            return [];
        }

        return [new Draft(
            Category::MissingWeighIn,
            'missing_weigh_in:'.$situation->today(),
            'Pas encore de pesée aujourd’hui',
            'Ajoute celle du jour pour garder une tendance fiable.',
            '/progression?ajouter=pesee',
        )];
    }
}
