<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Application\Coaching\TrainingReading;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\InMemoryProgramRepository;
use App\Tests\Support\Training\InMemoryProgramVersionRepository;
use App\Tests\Support\Training\InMemoryWorkoutRepository;
use App\Training\Application\Coaching\WorkoutReadings;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Program\TrainingDay;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use PHPUnit\Framework\TestCase;

final class CoachingTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string BENCH = '00000000-0000-7000-8000-0000000000e1';
    private const string ROW = '00000000-0000-7000-8000-0000000000e2';
    private const string SQUAT = '00000000-0000-7000-8000-0000000000e3';
    private const string CURL = '00000000-0000-7000-8000-0000000000e4';

    private InMemoryWorkoutRepository $workouts;
    private InMemoryProgramRepository $programs;
    private InMemoryProgramVersionRepository $versions;
    private int $ids = 0;

    protected function setUp(): void
    {
        $this->workouts = new InMemoryWorkoutRepository();
        $this->programs = new InMemoryProgramRepository();
        $this->versions = new InMemoryProgramVersionRepository();
    }

    private static function planned(string $exercise): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId($exercise), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
    }

    private function workout(string $date, string $exercise, string $name, float $load, bool $finished = true): void
    {
        $at = new \DateTimeImmutable($date.' 18:00');
        $workout = Workout::start(new WorkoutId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), new UserId(self::ALEX), null, [new WorkoutExercise(new ExerciseId($exercise), $name, Measurement::Weight, 0, self::planned($exercise))], $at, $at);
        $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-9%011d', ++$this->ids)), 0, SetKind::Normal, $load, 10, null, null, $at, $at);
        if ($finished) {
            $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));
        }
        $this->workouts->save($workout);
    }

    /**
     * @param array<string, float> $loads by day
     */
    private function sessions(string $exercise, string $name, array $loads): void
    {
        foreach ($loads as $day => $load) {
            $this->workout($day, $exercise, $name, $load);
        }
    }

    private function reading(): TrainingReading
    {
        return new WorkoutReadings($this->workouts, $this->programs, $this->versions)->between(new UserId(self::ALEX), new \DateTimeImmutable('2026-09-23'), new \DateTimeImmutable('2026-09-29'));
    }

    public function testTheWeekCountsTheSessionsFinishedInItAndTheRecordsTheyBeat(): void
    {
        $this->sessions(self::ROW, 'Rowing', ['2026-09-01' => 50.0, '2026-09-28' => 55.0]);
        $this->sessions(self::CURL, 'Curl', ['2026-09-24' => 12.0]);
        $this->workout('2026-09-27', self::ROW, 'Rowing', 90.0, finished: false);
        $this->workout('2026-09-30', self::ROW, 'Rowing', 90.0);

        $reading = $this->reading();

        self::assertSame(2, $reading->sessions, 'the workout under way and the one after the week left out');
        self::assertSame(3, $reading->records, 'the 28th beats the load, the estimated 1RM and the volume');
        self::assertNull($reading->plannedPerWeek, 'no active program');
    }

    public function testTheActiveProgramsDaysAreTheSessionsPlannedAWeek(): void
    {
        $now = new \DateTimeImmutable('2026-09-01');
        $programId = new ProgramId('00000000-0000-7000-8000-00000000b001');
        $days = array_map(static fn (string $name): TrainingDay => new TrainingDay($name, [new ExerciseBlock([self::planned(self::BENCH)])]), ['Push', 'Pull', 'Legs']);
        $version = ProgramVersion::first(new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), $programId, $days, $now);
        $program = Program::create($programId, new UserId(self::ALEX), 'PPL', $version, $now);
        $this->versions->save($version);
        $this->programs->save($program);

        self::assertNull($this->reading()->plannedPerWeek, 'not active');

        $program->activate();
        self::assertSame(3, $this->reading()->plannedPerWeek);
    }

    public function testAnExerciseStallsAfterFourSessionsAndThreeWeeksWithoutARecord(): void
    {
        $this->sessions(self::BENCH, 'Développé couché', ['2026-09-01' => 60.0, '2026-09-03' => 62.0, '2026-09-08' => 60.0, '2026-09-15' => 60.0, '2026-09-22' => 60.0, '2026-09-26' => 60.0]);
        // A record lately: progressing.
        $this->sessions(self::ROW, 'Rowing', ['2026-08-01' => 50.0, '2026-08-08' => 50.0, '2026-08-15' => 50.0, '2026-08-22' => 50.0, '2026-08-29' => 50.0, '2026-09-28' => 55.0]);
        // Stalled but no longer trained.
        $this->sessions(self::SQUAT, 'Squat', ['2026-08-01' => 100.0, '2026-08-03' => 100.0, '2026-08-05' => 100.0, '2026-08-07' => 100.0, '2026-08-09' => 100.0]);
        // Three sessions since the first: too few to tell.
        $this->sessions(self::CURL, 'Curl', ['2026-08-20' => 12.0, '2026-08-27' => 12.0, '2026-09-10' => 12.0, '2026-09-24' => 12.0]);

        $stalls = $this->reading()->stalls;

        self::assertSame([['Développé couché', 4, '2026-09-03']], array_map(static fn ($stall): array => [$stall->exerciseName, $stall->sessions, $stall->since->format('Y-m-d')], $stalls));
    }

    public function testAStallIsCountedFromTheFirstSessionWithoutAnyRecord(): void
    {
        $this->sessions(self::BENCH, 'Développé couché', ['2026-09-01' => 60.0, '2026-09-08' => 60.0, '2026-09-15' => 60.0, '2026-09-22' => 60.0, '2026-09-29' => 60.0]);
        $this->sessions(self::CURL, 'Curl', ['2026-09-10' => 12.0, '2026-09-12' => 12.0, '2026-09-14' => 12.0, '2026-09-16' => 12.0, '2026-09-18' => 12.0]);

        self::assertSame([['Développé couché', 4, '2026-09-01']], array_map(static fn ($stall): array => [$stall->exerciseName, $stall->sessions, $stall->since->format('Y-m-d')], $this->reading()->stalls), 'the curls started 19 days ago: not yet three weeks');
    }

    public function testTheMostStalledComeFirstThenTheOldest(): void
    {
        $this->sessions(self::BENCH, 'Développé couché', ['2026-08-01' => 60.0, '2026-08-08' => 60.0, '2026-08-15' => 60.0, '2026-08-22' => 60.0, '2026-08-29' => 60.0, '2026-09-26' => 60.0]);
        $this->sessions(self::SQUAT, 'Squat', ['2026-08-02' => 100.0, '2026-08-09' => 100.0, '2026-08-16' => 100.0, '2026-08-23' => 100.0, '2026-09-27' => 100.0]);
        $this->sessions(self::ROW, 'Rowing', ['2026-08-03' => 50.0, '2026-08-10' => 50.0, '2026-08-17' => 50.0, '2026-08-24' => 50.0, '2026-09-28' => 50.0]);

        $stalls = array_map(static fn ($stall): array => [$stall->exerciseName, $stall->sessions], $this->reading()->stalls);

        self::assertSame([['Développé couché', 5], ['Squat', 4], ['Rowing', 4]], $stalls, 'by sessions, then since the oldest');
    }
}
