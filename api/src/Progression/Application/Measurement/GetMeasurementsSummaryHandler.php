<?php

declare(strict_types=1);

namespace App\Progression\Application\Measurement;

use App\Progression\Domain\Measurement\BodyPart;
use App\Progression\Domain\Measurement\CustomMeasureRepository;
use App\Progression\Domain\Measurement\DayLength;
use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetMeasurementsSummaryHandler implements QueryHandler
{
    public function __construct(private MeasurementDayRepository $days, private CustomMeasureRepository $measures)
    {
    }

    /**
     * @return list<MeasureSeries> the body parts then one's own; an archived measure only while it has values
     */
    public function __invoke(GetMeasurementsSummary $query): array
    {
        $from = $query->period->firstDay($query->today->setTime(0, 0));
        $days = $this->days->ofUser($query->userId);
        $measures = [
            ...array_map(MeasureView::part(...), BodyPart::cases()),
            ...array_map(MeasureView::custom(...), $this->measures->ofUser($query->userId, withArchived: true)),
        ];

        $series = [];
        foreach ($measures as $measure) {
            $all = self::lengths($days, $measure->key);
            if ($measure->archived && [] === $all) {
                continue;
            }
            $points = array_values(array_filter($all, static fn (DayLength $point): bool => null === $from || $point->day >= $from));
            $latest = $all[\count($all) - 1] ?? null;
            $first = $points[0] ?? null;
            $change = null === $first || null === $latest || $first === $latest ? null : round($latest->cm - $first->cm, 1);
            $series[] = new MeasureSeries($measure, $latest, $change, $points);
        }

        return $series;
    }

    /**
     * @param list<MeasurementDay> $days
     *
     * @return list<DayLength>
     */
    private static function lengths(array $days, string $key): array
    {
        $lengths = [];
        foreach ($days as $day) {
            $cm = $day->valueOf($key);
            if (null !== $cm) {
                $lengths[] = new DayLength($day->day(), $cm);
            }
        }

        return $lengths;
    }
}
