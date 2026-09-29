<?php

declare(strict_types=1);

namespace App\Progression\Application\Coaching;

use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Progression\Domain\Weight\WeightTrend;
use App\Shared\Application\Coaching\BodyWeight;
use App\Shared\Application\Coaching\WeightReading;
use App\Shared\Domain\Identifier\UserId;

/**
 * The weight trend as the Coach reads it (ADR-0028), from the last four weeks of weigh-ins.
 */
final readonly class WeighInReadings implements BodyWeight
{
    public function __construct(private WeighInRepository $weighIns)
    {
    }

    public function reading(UserId $userId, \DateTimeImmutable $day): WeightReading
    {
        $end = $day->setTime(0, 0);
        $window = array_values(array_map(
            DayWeight::of(...),
            array_filter(
                $this->weighIns->since($userId, $end->modify(\sprintf('-%d days', WeightTrend::SPEED_DAYS - 1))),
                static fn (WeighIn $weighIn): bool => $weighIn->day() <= $end,
            ),
        ));
        $lastWeek = $end->modify('-6 days');

        return new WeightReading(
            WeightTrend::average($window, $end, 7),
            WeightTrend::kgPerWeek($window, $end),
            \count(array_filter($window, static fn (DayWeight $weighIn): bool => $weighIn->day >= $lastWeek)),
            \count($window),
        );
    }
}
