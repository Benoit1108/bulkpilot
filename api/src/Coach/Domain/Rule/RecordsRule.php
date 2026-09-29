<?php

declare(strict_types=1);

namespace App\Coach\Domain\Rule;

use App\Coach\Domain\Analysis\Facts;

/**
 * Records beaten over the week (spec §67: PR).
 */
final readonly class RecordsRule implements Rule
{
    public function evaluate(Facts $facts): ?Finding
    {
        if (0 === $facts->training->records) {
            return null;
        }

        return new Finding(Kind::Records, Level::Info, [
            'days' => Facts::WEEK_DAYS,
            'records' => $facts->training->records,
            'sessions' => $facts->training->sessions,
        ]);
    }
}
