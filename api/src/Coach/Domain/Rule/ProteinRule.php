<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;
use App\Coach\Domain\Analysis\IntakeFacts;

/**
 * Protein clearly short of the target over the week (spec §26).
 */
final readonly class ProteinRule implements Rule
{
    public const int MIN_LOGGED_DAYS = 4;

    public function evaluate(Facts $facts): ?Finding
    {
        $average = $facts->week->averageProtein();
        if (null === $facts->target || 0 === $facts->target->proteinGrams || null === $average || $facts->week->logged() < self::MIN_LOGGED_DAYS) {
            return null;
        }
        if ($average >= $facts->target->proteinGrams * IntakeFacts::PROTEIN_SHARE) {
            return null;
        }

        return new Finding(Kind::LowProtein, Level::Watch, [
            'loggedDays' => $facts->week->logged(),
            'averageProtein' => $average,
            'targetProtein' => $facts->target->proteinGrams,
            'daysOnProtein' => $facts->week->daysOnProtein($facts->target->proteinGrams),
        ]);
    }
}
