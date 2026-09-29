<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Domain;

use App\Progression\Domain\Exception\InvalidWeighIn;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInId;
use App\Progression\Domain\Weight\WeighInRecorded;
use App\Progression\Domain\Weight\Weight;
use App\Progression\Domain\Weight\WeightTrend;
use App\Tests\Support\Progression\ProgressionTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class WeightTrendTest extends ProgressionTestCase
{
    public function testAWeightIsReadToTheTenthWithinBounds(): void
    {
        self::assertSame(62.4, new Weight(62.44)->kg);

        foreach ([19.9, 400.1, \NAN] as $kg) {
            try {
                new Weight($kg);
                self::fail(\sprintf('%s kg accepted', $kg));
            } catch (InvalidWeighIn $refused) {
                self::assertSame('progression.invalid_weigh_in', $refused->errorCode());
            }
        }
    }

    public function testAWeighInIsForADayThatHasComeAndCorrectingItAnnouncesTheChange(): void
    {
        $weighIn = WeighIn::record(new WeighInId($this->uuids->generate()), $this->alex, self::day('2026-09-30'), new Weight(62.0), $this->clock->now());
        self::assertSame([WeighInRecorded::class], array_map(static fn (object $event): string => $event::class, $weighIn->pullDomainEvents()), 'tomorrow for a device ahead of the server');

        $weighIn->correct(new Weight(62.0), $this->clock->now());
        self::assertSame([], $weighIn->pullDomainEvents(), 'the same weight is no change');
        $weighIn->correct(new Weight(61.8), $this->clock->now());
        self::assertSame(61.8, $weighIn->weight()->kg);
        self::assertCount(1, $weighIn->pullDomainEvents());

        $this->expectException(InvalidWeighIn::class);
        WeighIn::record(new WeighInId($this->uuids->generate()), $this->alex, self::day('2026-10-01'), new Weight(62.0), $this->clock->now());
    }

    public function testAveragesReadTheDaysEndingTodayOnly(): void
    {
        $weighIns = self::series(['2026-08-20' => 58.0, '2026-09-22' => 60.0, '2026-09-24' => 61.0, '2026-09-29' => 62.0, '2026-09-30' => 70.0]);
        $today = self::day('2026-09-29');

        self::assertSame(61.5, WeightTrend::average($weighIns, $today, 7), 'the 23rd to the 29th, tomorrow left out');
        self::assertSame(61.0, WeightTrend::average($weighIns, $today, 30));
        self::assertNull(WeightTrend::average($weighIns, self::day('2026-09-10'), 7));
    }

    /**
     * @param array<string, float> $weights
     */
    #[DataProvider('speeds')]
    public function testTheSpeedIsTheSlopeOfTheLastFourWeeks(array $weights, ?float $kgPerWeek, ?TrendDirection $direction): void
    {
        $speed = WeightTrend::kgPerWeek(self::series($weights), self::day('2026-09-29'));

        self::assertSame($kgPerWeek, $speed);
        self::assertSame($direction, WeightTrend::direction($speed));
    }

    /**
     * @return iterable<string, array{array<string, float>, ?float, ?TrendDirection}>
     */
    public static function speeds(): iterable
    {
        yield 'half a kilo a week' => [['2026-09-08' => 60.0, '2026-09-15' => 60.5, '2026-09-22' => 61.0, '2026-09-29' => 61.5], 0.5, TrendDirection::Gaining];
        yield 'losing' => [['2026-09-08' => 70.0, '2026-09-15' => 69.7, '2026-09-22' => 69.4, '2026-09-29' => 69.1], -0.3, TrendDirection::Losing];
        yield 'within 50 g a week' => [['2026-09-08' => 60.0, '2026-09-15' => 60.1, '2026-09-22' => 59.9, '2026-09-29' => 60.0], -0.02, TrendDirection::Stable];
        yield 'one odd day moves it little' => [['2026-09-08' => 60.0, '2026-09-15' => 60.5, '2026-09-18' => 63.0, '2026-09-22' => 61.0, '2026-09-29' => 61.5], 0.47, TrendDirection::Gaining];
        yield 'too few weigh-ins' => [['2026-09-15' => 60.0, '2026-09-22' => 61.0, '2026-09-29' => 62.0], null, null];
        yield 'over too few days' => [['2026-09-25' => 60.0, '2026-09-26' => 60.2, '2026-09-27' => 60.4, '2026-09-28' => 60.6], null, null];
        yield 'older than four weeks is not read' => [['2026-08-01' => 50.0, '2026-08-08' => 50.0, '2026-08-15' => 50.0, '2026-08-22' => 50.0, '2026-09-29' => 60.0], null, null];
    }

    public function testTheChartsAveragesAreThoseOfEachDayOverYearsOfWeighIns(): void
    {
        $weights = [];
        for ($day = 0; $day < 1500; ++$day) {
            if (0 !== $day % 3) {
                $weights[new \DateTimeImmutable('2022-01-01')->modify(\sprintf('+%d days', $day))->format('Y-m-d')] = 60 + ($day % 11) / 10;
            }
        }
        $weighIns = self::series($weights);

        $points = WeightTrend::series($weighIns, null);

        self::assertCount(1000, $points);
        foreach ([0, 1, 499, 999] as $i) {
            self::assertSame(WeightTrend::average($weighIns, $points[$i]->day, 7), $points[$i]->average7);
            self::assertSame(WeightTrend::average($weighIns, $points[$i]->day, 30), $points[$i]->average30);
        }
    }

    public function testTheChartKeepsThePeriodAndReadsTheMonthBeforeForItsAverages(): void
    {
        $points = WeightTrend::series(self::series(['2026-09-01' => 60.0, '2026-09-27' => 62.0, '2026-09-29' => 64.0]), self::day('2026-09-23'));

        self::assertSame(['2026-09-27', '2026-09-29'], array_map(static fn ($point): string => $point->day->format('Y-m-d'), $points));
        self::assertSame([62.0, 63.0], array_map(static fn ($point): float => $point->average7, $points));
        self::assertSame([61.0, 62.0], array_map(static fn ($point): float => $point->average30, $points));
    }
}
