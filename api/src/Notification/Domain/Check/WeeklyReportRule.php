<?php

declare(strict_types=1);

namespace App\Notification\Domain\Check;

use App\Notification\Domain\Notification\Category;
use App\Shared\Domain\Calendar\Days;
use App\Shared\Domain\Text\French;

/**
 * The Coach's report of the week that just ended is ready (spec §59, §75).
 */
final readonly class WeeklyReportRule implements Rule
{
    public function evaluate(Situation $situation): array
    {
        $lastWeek = Days::weekOf($situation->now)->modify('-7 days')->format('Y-m-d');
        if ($situation->latestReportWeek?->format('Y-m-d') !== $lastWeek) {
            return [];
        }

        return [new Draft(
            Category::WeeklyReport,
            'weekly_report:'.$lastWeek,
            'Ton rapport de la semaine est prêt',
            French::typeset('Poids, alimentation, entraînement : le bilan du Coach sur la semaine passée.'),
            '/coach/rapports',
        )];
    }
}
