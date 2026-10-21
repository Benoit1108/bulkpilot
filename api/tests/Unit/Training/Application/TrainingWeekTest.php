<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\InMemoryExerciseRepository;
use App\Tests\Support\Training\InMemoryWorkoutRepository;
use App\Training\Application\Progress\GetTrainingWeek;
use App\Training\Application\Progress\GetTrainingWeekHandler;
use App\Training\Application\Progress\TrainingWeekView;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use PHPUnit\Framework\TestCase;

final class TrainingWeekTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string SAM = '00000000-0000-7000-8000-000000000511';
    private const string ROW = '00000000-0000-7000-8000-0000000000e1';
    private const string BENCH = '00000000-0000-7000-8000-0000000000e2';
    private const string OWN = '00000000-0000-7000-8000-0000000000e3';
    private const string UNKNOWN = '00000000-0000-7000-8000-0000000000e4';

    private InMemoryWorkoutRepository $workouts;
    private InMemoryExerciseRepository $exercises;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->workouts = new InMemoryWorkoutRepository();
        $this->exercises = new InMemoryExerciseRepository();
        $now = new \DateTimeImmutable('2026-01-01');
        $this->exercises->save(Exercise::fromLibrary(new ExerciseId(self::ROW), 'barbell_row', self::spec('Rowing', Muscle::UpperBack), $now));
        $this->exercises->save(Exercise::fromLibrary(new ExerciseId(self::BENCH), 'bench_press', self::spec('Développé couché', Muscle::Chest), $now));
        $own = Exercise::createCustom(new ExerciseId(self::OWN), new UserId(self::ALEX), self::spec('Mollets à la presse', Muscle::Calves), $now);
        $own->delete($now);
        $this->exercises->save($own);
    }

    private static function spec(string $name, Muscle $muscle): ExerciseSpec
    {
        return new ExerciseSpec($name, $muscle, [], ExerciseType::Compound, Equipment::Barbell, Measurement::Weight, new ExerciseDefaults(new RepRange(8, 12), 2, 120));
    }

    /**
     * @param list<array{0: float, 1: int, 2?: SetKind}> $sets
     */
    private function workout(string $date, array $sets, string $exercise = self::ROW, bool $finished = true, string $owner = self::ALEX): void
    {
        $at = new \DateTimeImmutable($date.' 18:00');
        $planned = new PlannedExercise(new ExerciseId($exercise), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
        $workout = Workout::start(new WorkoutId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), new UserId($owner), null, [new WorkoutExercise(new ExerciseId($exercise), 'Exercice', Measurement::Weight, 0, $planned)], $at, $at);
        foreach ($sets as $set) {
            $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-9%011d', ++$this->ids)), 0, $set[2] ?? SetKind::Normal, $set[0], $set[1], null, null, $at, $at);
        }
        if ($finished) {
            $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));
        }
        $this->workouts->save($workout);
    }

    private function week(): TrainingWeekView
    {
        // A Tuesday: the week under way started the day before.
        return new GetTrainingWeekHandler($this->workouts, $this->exercises)(new GetTrainingWeek(new UserId(self::ALEX), new \DateTimeImmutable('2026-09-29')));
    }

    public function testTheWeekUnderWayCountsWorkingSetsByMainMuscle(): void
    {
        $this->workout('2026-09-27', [[60.0, 10], [60.0, 10], [60.0, 10]]); // last week (Sunday)
        $this->workout('2026-09-28', [[40.0, 12, SetKind::Warmup], [60.0, 10], [60.0, 9]]);
        $this->workout('2026-09-29', [[80.0, 8], [80.0, 8]], self::BENCH);
        $this->workout('2026-09-29', [[90.0, 5]], self::BENCH, finished: false);
        $this->workout('2026-09-29', [[100.0, 5]], self::BENCH, owner: self::SAM);
        $this->workout('2026-09-29', [[100.0, 5]], self::UNKNOWN);

        $week = $this->week();

        self::assertSame('2026-09-28', $week->week->format('Y-m-d'));
        self::assertSame(['chest' => 2, 'upper_back' => 2], $week->muscles, 'equals in the body\'s order; warm-ups, unfinished, unknown and other people\'s left out');

        $this->workout('2026-09-29', [[60.0, 10]]);
        $this->workout('2026-09-29', [[0.0, 20]], self::OWN);
        self::assertSame(['upper_back' => 3, 'chest' => 2, 'calves' => 1], $this->week()->muscles, 'the most worked first; one\'s own deleted exercise still counts');
    }

    public function testTheLastFullWeekSaysWhatItLifted(): void
    {
        $this->workout('2026-09-20', [[100.0, 10]]); // the week before (Sunday)
        $this->workout('2026-09-21', [[40.0, 10, SetKind::Warmup], [60.0, 10], [60.0, 9]]);
        $this->workout('2026-09-27', [[80.0, 5]], self::BENCH);
        $this->workout('2026-09-28', [[200.0, 5]]); // this week
        $this->workout('2026-09-24', [[300.0, 5]], owner: self::SAM);

        $week = $this->week();

        self::assertSame('2026-09-21', $week->lastWeek->format('Y-m-d'));
        self::assertSame(2, $week->lastWeekTotals->sessions);
        self::assertSame(3, $week->lastWeekTotals->workingSets);
        self::assertSame(600.0 + 540.0 + 400.0, $week->lastWeekTotals->tonnageKg);
    }

    public function testAWeekWithoutWorkoutsIsEmpty(): void
    {
        $week = $this->week();

        self::assertSame([], $week->muscles);
        self::assertSame(0, $week->lastWeekTotals->sessions);
        self::assertSame(0.0, $week->lastWeekTotals->tonnageKg);
    }
}
