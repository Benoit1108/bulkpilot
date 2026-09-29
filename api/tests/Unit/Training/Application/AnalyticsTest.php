<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Domain\Calendar\Period;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\InMemoryWorkoutRepository;
use App\Training\Application\Progress\GetTrainingAnalytics;
use App\Training\Application\Progress\GetTrainingAnalyticsHandler;
use App\Training\Application\Progress\TrainingAnalyticsView;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string ROW = '00000000-0000-7000-8000-0000000000e1';

    private InMemoryWorkoutRepository $workouts;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->workouts = new InMemoryWorkoutRepository();
    }

    /**
     * @param list<array{0: float, 1: int, 2?: SetKind}> $sets
     */
    private function workout(string $date, array $sets, bool $finished = true, string $owner = self::ALEX): void
    {
        $at = new \DateTimeImmutable($date.' 18:00');
        $planned = new PlannedExercise(new ExerciseId(self::ROW), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
        $workout = Workout::start(new WorkoutId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), new UserId($owner), null, [new WorkoutExercise(new ExerciseId(self::ROW), 'Rowing', Measurement::Weight, 0, $planned)], $at, $at);
        foreach ($sets as $set) {
            $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-9%011d', ++$this->ids)), 0, $set[2] ?? SetKind::Normal, $set[0], $set[1], null, null, $at, $at);
        }
        if ($finished) {
            $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));
        }
        $this->workouts->save($workout);
    }

    private function analytics(Period $period): TrainingAnalyticsView
    {
        return new GetTrainingAnalyticsHandler($this->workouts)(new GetTrainingAnalytics(new UserId(self::ALEX), $period, new \DateTimeImmutable('2026-09-29')));
    }

    public function testThePeriodSumsTheWorkingSetsAndCountsTheRecordsBeaten(): void
    {
        $this->workout('2026-08-01', [[60.0, 10]]);
        $this->workout('2026-09-15', [[40.0, 12, SetKind::Warmup], [60.0, 10], [60.0, 9]]);
        $this->workout('2026-09-22', [[65.0, 8], [65.0, 8]]);
        $this->workout('2026-09-28', [[70.0, 6]], finished: false);
        $this->workout('2026-09-22', [[200.0, 5]], owner: '00000000-0000-7000-8000-000000000511');

        $month = $this->analytics(Period::Month);

        self::assertSame([2, 4, 35, 2180.0], [$month->totals->sessions, $month->totals->workingSets, $month->totals->reps, $month->totals->tonnageKg], 'the warm-up, the workout under way and another user\'s left out');
        self::assertSame(3, $month->totals->records, 'the 15th beats the session volume, the 22nd the heaviest load and the estimated 1RM');
        self::assertSame(['2026-08-31', '2026-09-07', '2026-09-14', '2026-09-21', '2026-09-28'], array_keys($month->weeks), 'every week, those without a session included');
        self::assertSame([0, 0, 1, 1, 0], array_map(static fn ($week): int => $week->sessions, array_values($month->weeks)));
        self::assertSame(0.5, $month->sessionsPerWeek, '2 sessions over 30 days');
        self::assertSame(3, $this->analytics(Period::All)->totals->sessions);
    }

    public function testNothingTrainedIsNothing(): void
    {
        $all = $this->analytics(Period::All);

        self::assertSame([0, []], [$all->totals->sessions, $all->weeks]);
        self::assertNull($all->sessionsPerWeek);
        $week = $this->analytics(Period::Week);
        self::assertCount(2, $week->weeks, 'the last 7 days span two weeks, without a session');
        self::assertSame(0.0, $week->sessionsPerWeek);
    }

    public function testFourSessionsInSevenDaysAreFourAWeek(): void
    {
        foreach (['2026-09-23', '2026-09-25', '2026-09-27', '2026-09-29'] as $day) {
            $this->workout($day, [[60.0, 10]]);
        }

        self::assertSame(4.0, $this->analytics(Period::Week)->sessionsPerWeek, 'not 2 for the two calendar weeks touched');
    }
}
