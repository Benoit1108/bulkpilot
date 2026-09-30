<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Application\Notifying\DayTraining;
use App\Shared\Application\Notifying\RecordWorkout;
use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\InMemoryProgramRepository;
use App\Tests\Support\Training\InMemoryProgramVersionRepository;
use App\Tests\Support\Training\InMemoryWorkoutRepository;
use App\Training\Application\Notifying\WorkoutActivity;
use App\Training\Application\Program\ActiveProgram;
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

/**
 * The training as the notifications read it, in Paris: Wednesday 2026-09-30.
 */
final class NotifyingTest extends TestCase
{
    private const string ALEX = '00000000-0000-7000-8000-00000000a1e7';
    private const string BENCH = '00000000-0000-7000-8000-0000000000e1';

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

    private static function planned(): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
    }

    private function workout(string $utc, float $load, bool $finished = true): string
    {
        $at = new \DateTimeImmutable($utc.' UTC');
        $id = \sprintf('00000000-0000-7000-8000-%012d', ++$this->ids);
        $workout = Workout::start(new WorkoutId($id), new UserId(self::ALEX), null, [new WorkoutExercise(new ExerciseId(self::BENCH), 'Développé couché', Measurement::Weight, 0, self::planned())], $at, $at);
        $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-9%011d', ++$this->ids)), 0, SetKind::Normal, $load, 10, null, null, $at, $at);
        if ($finished) {
            $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));
        }
        $this->workouts->save($workout);

        return $id;
    }

    private function activity(): DayTraining
    {
        $paris = new \DateTimeZone('Europe/Paris');

        return new WorkoutActivity($this->workouts, new ActiveProgram($this->programs, $this->versions))->on(
            new UserId(self::ALEX),
            new \DateTimeImmutable('2026-09-30 00:00', $paris),
            new \DateTimeImmutable('2026-09-28 18:30 UTC'),
        );
    }

    public function testTheWeekSoFarTodayAndTheLatestRecords(): void
    {
        $this->workout('2026-09-26 08:00', 60.0);
        $this->workout('2026-09-28 17:00', 62.5);
        $record = $this->workout('2026-09-29 17:00', 65.0);
        $programId = new ProgramId('00000000-0000-7000-8000-00000000b001');
        $version = ProgramVersion::first(new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), $programId, [new TrainingDay('Push', [new ExerciseBlock([self::planned()])]), new TrainingDay('Pull', [new ExerciseBlock([self::planned()])])], new \DateTimeImmutable('2026-09-01'));
        $program = Program::create($programId, new UserId(self::ALEX), 'PP', $version, new \DateTimeImmutable('2026-09-01'));
        $program->activate();
        $this->versions->save($version);
        $this->programs->save($program);

        $activity = $this->activity();

        self::assertSame([2, 2, false], [$activity->plannedPerWeek, $activity->sessionsThisWeek, $activity->startedToday], 'Monday 28th and Tuesday 29th this week');
        self::assertSame([[$record, 3]], array_map(static fn (RecordWorkout $workout): array => [$workout->workoutId, $workout->records], $activity->recordWorkouts), 'load, estimated max and volume; the 28th finished before the window');
    }

    public function testAWorkoutStartedAfterLocalMidnightIsToday(): void
    {
        $this->workout('2026-09-29 22:30', 60.0, finished: false);

        self::assertTrue($this->activity()->startedToday, '00:30 in Paris, in progress');
        self::assertNull($this->activity()->plannedPerWeek);
    }

    public function testAWorkoutJustAfterMondaysMidnightInParisIsOfThisWeek(): void
    {
        $this->workout('2026-09-27 22:30', 60.0);
        $this->workout('2026-09-27 21:30', 60.0);

        self::assertSame(1, $this->activity()->sessionsThisWeek, '00:30 on Monday in Paris is this week (Sunday in UTC); 23:30 on Sunday is not');
    }

    public function testWithoutAWorkoutFinishedLatelyNoRecordIsLookedFor(): void
    {
        $this->workout('2026-09-20 08:00', 60.0);
        $this->workout('2026-09-21 08:00', 80.0);

        self::assertSame([], $this->activity()->recordWorkouts, 'records of workouts finished before the window are old news');
    }
}
