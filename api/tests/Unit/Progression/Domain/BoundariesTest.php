<?php

declare(strict_types=1);

namespace App\Tests\Unit\Progression\Domain;

use App\Progression\Domain\DayWindow;
use App\Progression\Domain\Exception\InvalidMeasurement;
use App\Progression\Domain\Exception\InvalidWeighIn;
use App\Progression\Domain\Exception\InvalidWeightGoal;
use App\Progression\Domain\Goal\Estimate;
use App\Progression\Domain\Goal\EstimateStatus;
use App\Progression\Domain\Goal\GoalProgress;
use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalId;
use App\Progression\Domain\Measurement\CustomMeasure;
use App\Progression\Domain\Measurement\CustomMeasureId;
use App\Progression\Domain\Measurement\Length;
use App\Progression\Domain\Measurement\MeasurementDay;
use App\Progression\Domain\Measurement\MeasurementDayId;
use App\Progression\Domain\Photo\Pose;
use App\Progression\Domain\Photo\ProgressPhoto;
use App\Progression\Domain\Photo\ProgressPhotoId;
use App\Progression\Domain\Weight\DayWeight;
use App\Progression\Domain\Weight\TrendDirection;
use App\Progression\Domain\Weight\Weight;
use App\Progression\Domain\Weight\WeightTrend;
use App\Shared\Domain\Identifier\MediaId;
use App\Shared\Domain\Identifier\UserId;
use PHPUnit\Framework\TestCase;

/**
 * The exact edges of Progression's rules.
 */
final class BoundariesTest extends TestCase
{
    private const string ID = '00000000-0000-7000-8000-00000000c001';
    private const string OWNER = '0199a0b1-0000-7000-8000-000000000001';

    public function testTheWindowOfDaysIsInclusiveWhateverTheHour(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 15:00');

        self::assertTrue(DayWindow::allows(new \DateTimeImmutable('2000-01-01 10:00'), $now), 'the earliest day, at any hour');
        self::assertTrue(DayWindow::allows(new \DateTimeImmutable('2026-10-01 23:00'), $now), 'tomorrow');
        self::assertFalse(DayWindow::allows(new \DateTimeImmutable('2026-10-02 00:00'), $now));
        self::assertFalse(DayWindow::allows(new \DateTimeImmutable('1999-12-31 23:00'), $now));
        self::assertTrue(DayWindow::allows(new \DateTimeImmutable('2026-10-01 18:00'), new \DateTimeImmutable('2026-09-30 00:30')), 'tomorrow, just after midnight');
        self::assertTrue(DayWindow::allows(new \DateTimeImmutable('2026-10-01'), new \DateTimeImmutable('2026-09-30 00:00')), 'tomorrow, at midnight');
    }

    public function testRecordsAreForTheDayNotTheHour(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 15:00');
        $measurements = MeasurementDay::record(new MeasurementDayId(self::ID), new UserId(self::OWNER), new \DateTimeImmutable('2026-09-29 15:30'), ['waist' => new Length(80)], $now);
        $photo = ProgressPhoto::add(new ProgressPhotoId(self::ID), new UserId(self::OWNER), new MediaId(self::ID), new \DateTimeImmutable('2026-09-29 15:30'), Pose::Front, 1200, 1600, $now);

        self::assertSame('2026-09-29 00:00', $measurements->day()->format('Y-m-d H:i'));
        self::assertSame('2026-09-29 00:00', $photo->day()->format('Y-m-d H:i'));
    }

    public function testWeightsAndLengthsAtTheirLimits(): void
    {
        self::assertSame([Weight::MIN_KG, Weight::MAX_KG], [new Weight(Weight::MIN_KG)->kg, new Weight(Weight::MAX_KG)->kg]);
        self::assertSame([Length::MIN_CM, Length::MAX_CM], [new Length(Length::MIN_CM)->cm, new Length(Length::MAX_CM)->cm]);
        self::assertSame(10.0, new Length(10.04)->cm, 'to the millimetre');
        self::assertRefused(static fn () => new Weight(Weight::MAX_KG + 0.1), InvalidWeighIn::class);
        self::assertRefused(static fn () => new Length(Length::MAX_CM + 0.1), InvalidMeasurement::class);
        self::assertRefused(static fn () => new Length(Length::MIN_CM - 0.1), InvalidMeasurement::class);
    }

    public function testACustomMeasureNameCountsCharacters(): void
    {
        $measure = CustomMeasure::add(new CustomMeasureId(self::ID), new UserId(self::OWNER), str_repeat('é', CustomMeasure::MAX_NAME), new \DateTimeImmutable());
        self::assertSame(CustomMeasure::MAX_NAME, mb_strlen($measure->name()));

        foreach (['', str_repeat('a', CustomMeasure::MAX_NAME + 1)] as $name) {
            self::assertRefused(static fn () => CustomMeasure::add(new CustomMeasureId(self::ID), new UserId(self::OWNER), $name, new \DateTimeImmutable()), InvalidMeasurement::class);
        }
    }

    public function testEstimatesAtTheirEdges(): void
    {
        self::assertSame(EstimateStatus::NotMoving, Estimate::toward(2.0, WeightTrend::STABLE_KG_PER_WEEK, TrendDirection::Gaining)->status, 'exactly stable');
        self::assertSame(4, Estimate::toward(1.0, 0.3, TrendDirection::Gaining)->weeks, '3.3 weeks, rounded up');
        self::assertSame(TrendDirection::Stable, WeightTrend::direction(WeightTrend::STABLE_KG_PER_WEEK));
        self::assertSame(TrendDirection::Stable, WeightTrend::direction(-WeightTrend::STABLE_KG_PER_WEEK));
    }

    public function testASpeedNeedsAWeekBetweenTheFirstAndLastWeighIns(): void
    {
        $end = new \DateTimeImmutable('2026-09-30');
        $weighIns = self::weighIns(['2026-09-23' => 60.0, '2026-09-24' => 60.1, '2026-09-25' => 60.3, '2026-09-30' => 60.9]);

        self::assertSame(0.91, WeightTrend::kgPerWeek($weighIns, $end), 'seven days from first to last: 3,75 / 29 kg a day');
        self::assertNull(WeightTrend::kgPerWeek(\array_slice($weighIns, 1), $end));
    }

    public function testTheChartStartsOnItsFirstDayAndMeansCoverThirtyDays(): void
    {
        $points = WeightTrend::series(self::weighIns(['2026-09-01' => 60.0, '2026-09-30' => 62.0]), new \DateTimeImmutable('2026-09-30'));

        self::assertCount(1, $points, 'the first day of the chart is in it');
        self::assertSame(61.0, $points[0]->average30, 'a weigh-in 29 days before is within the 30 days');
        self::assertSame(62.0, $points[0]->average7);
    }

    public function testGoalBoundsAndTheProgressOnIt(): void
    {
        $start = new DayWeight(new \DateTimeImmutable('2026-09-01'), 60.0);
        foreach ([[60.4, []], [61.0, [new Weight(60.0)]], [61.0, [new Weight(61.0)]], [59.0, [new Weight(59.0)]], [59.0, [new Weight(60.0)]], [62.0, array_fill(0, WeightGoal::MAX_MILESTONES + 1, new Weight(61.0))]] as [$target, $milestones]) {
            self::assertRefused(static fn () => WeightGoal::set(new WeightGoalId(self::ID), new UserId(self::OWNER), $start, new Weight($target), $milestones, new \DateTimeImmutable()), InvalidWeightGoal::class);
        }
        $losing = WeightGoal::set(new WeightGoalId(self::ID), new UserId(self::OWNER), $start, new Weight(55.0), [new Weight(59.5)], new \DateTimeImmutable());
        self::assertTrue($losing->reaches(59.5, 59.5), 'a step is reached at its weight');
        self::assertSame(TrendDirection::Losing, $losing->direction());
        $ten = array_map(static fn (int $n): Weight => new Weight(60.0 + $n / 10), range(1, WeightGoal::MAX_MILESTONES));
        self::assertCount(WeightGoal::MAX_MILESTONES, WeightGoal::set(new WeightGoalId(self::ID), new UserId(self::OWNER), $start, new Weight(62.0), $ten, new \DateTimeImmutable())->milestones());
        $nearest = WeightGoal::set(new WeightGoalId(self::ID), new UserId(self::OWNER), $start, new Weight(60.5), [], new \DateTimeImmutable());
        self::assertSame(60.5, $nearest->targetKg(), 'half a kilo away');
        $gaining = WeightGoal::set(new WeightGoalId(self::ID), new UserId(self::OWNER), $start, new Weight(62.0), [new Weight(61.0)], new \DateTimeImmutable());
        self::assertTrue($gaining->reaches(61.0, 61.0));

        $progress = GoalProgress::of($gaining, [new DayWeight(new \DateTimeImmutable('2026-09-01'), 61.04), new DayWeight(new \DateTimeImmutable('2026-09-10'), 61.0)], new DayWeight(new \DateTimeImmutable('2026-09-10'), 61.04), null);
        self::assertSame('2026-09-01', $progress->steps[0]->reachedOn?->format('Y-m-d'), 'reached on the start day');
        self::assertSame([0.0, 1.0, 1.0], [$progress->sinceLastStepKg, $progress->toTargetKg, $progress->next?->remainingKg], 'to the tenth: 0,04 is 0,0 and 0,96 is 1,0');
    }

    /**
     * @param \Closure(): mixed        $make
     * @param class-string<\Throwable> $error
     */
    private static function assertRefused(\Closure $make, string $error): void
    {
        try {
            $make();
        } catch (\Throwable $refused) {
            self::assertInstanceOf($error, $refused);

            return;
        }
        self::fail('expected '.$error);
    }

    /**
     * @param array<string, float> $weights by day
     *
     * @return list<DayWeight>
     */
    private static function weighIns(array $weights): array
    {
        return array_map(static fn (string $day, float $kg): DayWeight => new DayWeight(new \DateTimeImmutable($day), $kg), array_keys($weights), array_values($weights));
    }
}
