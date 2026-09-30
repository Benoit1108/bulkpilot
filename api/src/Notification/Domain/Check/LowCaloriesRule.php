<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;
use App\Shared\Domain\Text\French;

/**
 * Very little eaten in the evening (spec §75, §130: « Tu es à 1 540 / 2 350 kcal »). Not right
 * after a meal was logged (spec §77: the user is on it), nor on a day nothing was logged at all
 * (the journal was not kept, which is not eating too little).
 */
final readonly class LowCaloriesRule implements Rule
{
    public const string FROM = '20:00';
    public const float SHARE = 0.70;
    public const int QUIET_AFTER_LOGGING_MINUTES = 60;

    public function evaluate(Situation $situation): array
    {
        $target = $situation->targetKcal;
        if (!$situation->isFrom(self::FROM) || null === $target || $situation->eatenKcal <= 0 || $situation->eatenKcal >= $target * self::SHARE) {
            return [];
        }
        if (null !== $situation->lastLoggedAt && $situation->lastLoggedAt > $situation->now->modify(\sprintf('-%d minutes', self::QUIET_AFTER_LOGGING_MINUTES))) {
            return [];
        }
        $left = round(($target - $situation->eatenKcal) / 10) * 10;

        return [new Draft(
            Category::LowCalories,
            'low_calories:'.$situation->today(),
            'Calories très en dessous de la cible',
            French::typeset(\sprintf('Tu es à %s / %s : il reste environ %s.', French::number($situation->eatenKcal), French::kcal($target), French::kcal($left))),
            '/nutrition?ajouter=aliment',
        )];
    }
}
