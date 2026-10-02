<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Progression\PersonalRecord;
use App\Training\Domain\Progression\ProgressionAdvisor;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Progression\RecordKind;
use App\Training\Domain\Progression\StallDetector;
use App\Training\Domain\Progression\SuggestionReason;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutSet;
use PHPUnit\Framework\TestCase;

/**
 * The edges of suggestions, records and stalls, and of a workout's own rules.
 */
final class ProgressionEdgesTest extends TestCase
{
    private const string ROW = '00000000-0000-7000-8000-00000000b001';
    private const string SQUAT = '00000000-0000-7000-8000-00000000b002';
    private const string OWNER = '00000000-0000-7000-8000-00000000a1e7';

    private int $ids = 0;

    public function testFixedRepsNeedsTheSetsTheRepsAndAnEasyEffort(): void
    {
        $planned = self::planned(ProgressionStrategy::FixedReps, targetRir: 0);

        self::assertSame(SuggestionReason::IncreaseLoad, ProgressionAdvisor::suggest($planned, Measurement::Weight, [$this->set(60, 12, 1), $this->set(60, 12, 1), $this->set(60, 12, 1)])?->reason, 'RIR 1 above a target of 0');
        self::assertSame(SuggestionReason::Repeat, ProgressionAdvisor::suggest($planned, Measurement::Weight, [$this->set(60, 12, null), $this->set(60, 12, null)])?->reason, 'two sets of three');
    }

    public function testTheTargetRirOfTheProgramIsTheOneJudged(): void
    {
        $suggestion = ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::TargetRir, targetRir: 4), Measurement::Weight, [$this->set(60, 10, 4), $this->set(60, 10, 4), $this->set(60, 10, 4)]);

        self::assertSame(SuggestionReason::Repeat, $suggestion?->reason, 'on the target of 4, not the default 2');
    }

    public function testTheBestRepsAtTheTopLoadAreRepeated(): void
    {
        $suggestion = ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::Linear), Measurement::Weight, [$this->set(60, 9, null), $this->set(60, 7, null)]);

        self::assertSame([SuggestionReason::Repeat, 60.0, 9], [$suggestion?->reason, $suggestion?->loadKg, $suggestion?->reps]);

        $later = ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::Linear), Measurement::Weight, [$this->set(60, 7, null), $this->set(60, 9, null)]);
        self::assertSame(9, $later?->reps, 'the best set, wherever it came');
    }

    public function testRecordsAWorkoutBeatAreAListInTheirOrder(): void
    {
        $before = $this->workout('2026-09-01', [[100.0, 5]]);
        $now = $this->workout('2026-09-08', [[90.0, 12]]);

        $beaten = RecordBook::beatenBy($now, [$before], new ExerciseId(self::ROW));

        self::assertSame([RecordKind::EstimatedOneRepMax, RecordKind::Volume], array_map(static fn (PersonalRecord $record): RecordKind => $record->kind, $beaten));
    }

    public function testATieKeepsTheFirstRecordAndTimedOrBodyweightSetsHaveTheirOwn(): void
    {
        $first = $this->workout('2026-09-01', [[100.0, 5]]);
        $tie = $this->workout('2026-09-08', [[100.0, 5]]);
        $bests = RecordBook::bests([$first, $tie], new ExerciseId(self::ROW));
        self::assertSame($first->id()->value, $bests['load']->workoutId->value);

        self::assertArrayNotHasKey('load', RecordBook::bests([$this->workout('2026-09-01', [[100.0, 0]])], new ExerciseId(self::ROW)), 'a failed attempt is no load record');
        self::assertSame(90.0, RecordBook::bests([$this->workout('2026-09-01', [[null, null, 60], [null, null, 90]], Measurement::Duration)], new ExerciseId(self::ROW))['duration']->value, 'every timed set counts');
        self::assertArrayNotHasKey('estimated_one_rep_max', RecordBook::bests([$this->workout('2026-09-01', [[100.0, 15]])], new ExerciseId(self::ROW)), 'no estimate beyond 12 reps');
        self::assertSame(['reps'], array_keys(RecordBook::bests([$this->workout('2026-09-01', [[null, 12]], Measurement::Bodyweight)], new ExerciseId(self::ROW))), 'without load: reps only');
        self::assertSame(['duration'], array_keys(RecordBook::bests([$this->workout('2026-09-01', [[null, null, 60]], Measurement::Duration)], new ExerciseId(self::ROW))));
    }

    public function testAStallIsCountedFromItsLastRecordToTheDay(): void
    {
        // The record on the 8th, then four sessions without one, the last within two weeks.
        $end = new \DateTimeImmutable('2026-09-30 00:00');
        $history = $this->stalledFrom('2026-09-08', '2026-09-16');
        $stalls = StallDetector::stalls($history, StallDetector::recordsByExercise($history), [self::ROW => 'Rowing'], $end);
        self::assertCount(1, $stalls);
        self::assertSame(['Rowing', 4, '2026-09-08 00:00'], [$stalls[0]->exerciseName, $stalls[0]->sessions, $stalls[0]->since->format('Y-m-d H:i')]);

        $recent = $this->stalledFrom('2026-09-09', '2026-09-16');
        self::assertSame([], StallDetector::stalls($recent, StallDetector::recordsByExercise($recent), [self::ROW => 'Rowing'], $end), 'a record 21 days ago is not old enough');
        $lastAtTheEdge = $this->stalledFrom('2026-09-01', '2026-09-04');
        self::assertCount(1, StallDetector::stalls($lastAtTheEdge, StallDetector::recordsByExercise($lastAtTheEdge), [self::ROW => 'Rowing'], new \DateTimeImmutable('2026-09-30 18:00')), 'the last session exactly 14 days before');
    }

    public function testTheMostStalledComeFirstThenTheOldest(): void
    {
        $end = new \DateTimeImmutable('2026-09-30');
        $workouts = [];
        // Rowing: no record after its first session (6 since); Squat: a record on the 3rd (5 since).
        foreach (['2026-09-01' => [100, 90], '2026-09-03' => [90, 100], '2026-09-10' => [90, 90], '2026-09-14' => [90, 90], '2026-09-18' => [90, 90], '2026-09-22' => [90, 90], '2026-09-26' => [90, 80]] as $day => [$row, $squat]) {
            $workouts[] = $this->twoExercises($day, (float) $row, (float) $squat);
        }
        $beaten = StallDetector::recordsByExercise($workouts);
        $stalls = StallDetector::stalls($workouts, $beaten, [self::ROW => 'Rowing', self::SQUAT => 'Squat'], $end);

        self::assertSame(['Rowing', 'Squat'], array_map(static fn ($stall): string => $stall->exerciseName, $stalls));
        self::assertSame(0, $beaten[self::ROW][$workouts[1]->id()->value], 'no rowing record on the 3rd');
    }

    public function testRecordsOfAWorkoutAddUpOverItsExercises(): void
    {
        $records = StallDetector::recordsByWorkout([self::ROW => ['w1' => 2, 'w2' => 0], self::SQUAT => ['w1' => 3]]);

        self::assertSame(['w1' => 5, 'w2' => 0], $records);
    }

    public function testAWorkoutEndsWithinADayOfItsStartAndNotInTheFuture(): void
    {
        $start = new \DateTimeImmutable('2026-09-29 18:00');
        $workout = $this->started($start);
        $workout->finish($start->modify('+1 day'), $start->modify('+1 day'));
        self::assertSame('2026-09-30 18:00', $workout->finishedAt()?->format('Y-m-d H:i'));

        $this->expectException(InvalidWorkout::class);
        $this->started($start)->finish($start->modify('+2 hours'), $start->modify('+1 hour'));
    }

    public function testRemovingASetKeepsTheOthersInAList(): void
    {
        $start = new \DateTimeImmutable('2026-09-29 18:00');
        $workout = $this->started($start);
        $workout->recordSet(new SetId('00000000-0000-7000-8000-000000000001'), 0, SetKind::Normal, 60.0, 8, null, null, $start, $start);
        $workout->recordSet(new SetId('00000000-0000-7000-8000-000000000002'), 0, SetKind::Normal, 62.5, 8, null, null, $start->modify('+3 minutes'), $start->modify('+3 minutes'));
        $workout->removeSet(new SetId('00000000-0000-7000-8000-000000000001'));

        self::assertSame(62.5, $workout->sets()[0]->loadKg);
        self::assertSame(62.5, $workout->setsOf(0)[0]->loadKg);
        self::assertFalse($workout->exercises()[0]->added, 'planned from the start, not added during the session');
    }

    private static function planned(ProgressionStrategy $strategy, ?int $targetRir = 2): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId(self::ROW), 3, new RepRange(8, 12), $targetRir, 120, $strategy);
    }

    private function set(float $load, int $reps, ?int $rir): WorkoutSet
    {
        return new WorkoutSet(new SetId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), 0, SetKind::Normal, Measurement::Weight, $load, $reps, null, $rir, new \DateTimeImmutable());
    }

    private function started(\DateTimeImmutable $at, Measurement $measurement = Measurement::Weight): Workout
    {
        return Workout::start(new WorkoutId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), new UserId(self::OWNER), null, [new WorkoutExercise(new ExerciseId(self::ROW), 'Rowing', $measurement, 0, self::planned(ProgressionStrategy::DoubleProgression))], $at, $at);
    }

    /**
     * @param list<array{0: ?float, 1: ?int, 2?: int}> $sets load, reps, seconds
     */
    private function workout(string $day, array $sets, Measurement $measurement = Measurement::Weight): Workout
    {
        $at = new \DateTimeImmutable($day.' 18:00');
        $workout = $this->started($at, $measurement);
        foreach ($sets as $set) {
            $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), 0, SetKind::Normal, $set[0], $set[1], $set[2] ?? null, null, $at, $at);
        }
        $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));

        return $workout;
    }

    /**
     * A first session, a record on `$record`, then four sessions without one, every four days from `$from`.
     *
     * @return list<Workout>
     */
    private function stalledFrom(string $record, string $from): array
    {
        $history = [$this->workout('2026-08-25', [[80.0, 8]]), $this->workout($record, [[100.0, 8]])];
        foreach (range(0, 3) as $n) {
            $history[] = $this->workout(new \DateTimeImmutable($from)->modify(\sprintf('+%d days', 4 * $n))->format('Y-m-d'), [[90.0, 8]]);
        }

        return $history;
    }

    private function twoExercises(string $day, float $row, float $squat): Workout
    {
        $at = new \DateTimeImmutable($day.' 18:00');
        $workout = Workout::start(new WorkoutId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), new UserId(self::OWNER), null, [
            new WorkoutExercise(new ExerciseId(self::ROW), 'Rowing', Measurement::Weight, 0, self::planned(ProgressionStrategy::DoubleProgression)),
            new WorkoutExercise(new ExerciseId(self::SQUAT), 'Squat', Measurement::Weight, 1, self::planned(ProgressionStrategy::DoubleProgression)),
        ], $at, $at);
        $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), 0, SetKind::Normal, $row, 8, null, null, $at, $at);
        $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-%012d', ++$this->ids)), 1, SetKind::Normal, $squat, 8, null, null, $at, $at);
        $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));

        return $workout;
    }
}
