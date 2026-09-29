<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Progression\PersonalRecord;
use App\Training\Domain\Progression\ProgressionAdvisor;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Progression\RecordKind;
use App\Training\Domain\Progression\SuggestionReason;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProgressionTest extends TestCase
{
    private const string ROW = '00000000-0000-7000-8000-0000000000e1';

    /**
     * @return iterable<string, array{ProgressionStrategy, list<array{float, int, ?int}>, SuggestionReason, ?float, ?int}>
     */
    public static function suggestions(): iterable
    {
        // Spec §38: 8-12 reps, 70 × 12, 12, 11 → not yet; all at 12 → 72.5 kg.
        yield 'double: not every set at the top' => [ProgressionStrategy::DoubleProgression, [[70, 12, 2], [70, 12, 1], [70, 11, 0]], SuggestionReason::AddReps, 70.0, 12];
        yield 'double: every set at the top' => [ProgressionStrategy::DoubleProgression, [[70, 12, 2], [70, 12, 1], [70, 12, 0]], SuggestionReason::IncreaseLoad, 72.5, 8];
        yield 'double: only the top load counts' => [ProgressionStrategy::DoubleProgression, [[72.5, 9, 1], [70, 12, 2], [70, 12, 2]], SuggestionReason::AddReps, 72.5, 10];
        yield 'linear: the bottom is reached' => [ProgressionStrategy::Linear, [[100, 8, 2], [100, 8, 1], [100, 8, 1]], SuggestionReason::IncreaseLoad, 102.5, 8];
        yield 'linear: missed' => [ProgressionStrategy::Linear, [[100, 8, 2], [100, 7, 0], [100, 6, 0]], SuggestionReason::Repeat, 100.0, 8];
        yield 'fixed load: more reps' => [ProgressionStrategy::FixedLoad, [[20, 10, 2], [20, 9, 1], [20, 9, 1]], SuggestionReason::AddReps, 20.0, 10];
        yield 'fixed load: at the top, keep going' => [ProgressionStrategy::FixedLoad, [[20, 12, 2], [20, 12, 1], [20, 12, 1]], SuggestionReason::Repeat, 20.0, 12];
        yield 'fixed reps: easy' => [ProgressionStrategy::FixedReps, [[60, 12, 3], [60, 12, 3], [60, 12, 4]], SuggestionReason::IncreaseLoad, 62.5, 12];
        yield 'fixed reps: hard' => [ProgressionStrategy::FixedReps, [[60, 12, 2], [60, 12, 1], [60, 12, 0]], SuggestionReason::Repeat, 60.0, 12];
        yield 'fixed reps: no RIR noted, reps reached' => [ProgressionStrategy::FixedReps, [[60, 12, null], [60, 12, null], [60, 12, null]], SuggestionReason::IncreaseLoad, 62.5, 12];
        yield 'target RIR: far from failure' => [ProgressionStrategy::TargetRir, [[80, 10, 4], [80, 10, 4], [80, 10, 5]], SuggestionReason::IncreaseLoad, 82.5, 10];
        yield 'target RIR: too close to failure' => [ProgressionStrategy::TargetRir, [[80, 10, 0], [80, 8, 0], [80, 7, 0]], SuggestionReason::DecreaseLoad, 77.5, 10];
        yield 'target RIR: as planned' => [ProgressionStrategy::TargetRir, [[80, 10, 2], [80, 10, 2], [80, 9, 1]], SuggestionReason::Repeat, 80.0, 10];
        yield 'target RIR: not noted' => [ProgressionStrategy::TargetRir, [[80, 10, null]], SuggestionReason::Repeat, 80.0, 10];
        yield 'target RIR: exactly two above' => [ProgressionStrategy::TargetRir, [[80, 10, 4], [80, 10, 4], [80, 10, 4]], SuggestionReason::IncreaseLoad, 82.5, 10];
        yield 'double: a single set at the top load is not enough' => [ProgressionStrategy::DoubleProgression, [[72.5, 12, 1], [70, 12, 2], [70, 12, 2]], SuggestionReason::Repeat, 72.5, 12];
        yield 'double: an extra set does not spoil the planned ones' => [ProgressionStrategy::DoubleProgression, [[100, 12, 2], [100, 12, 1], [100, 12, 1], [100, 6, 0]], SuggestionReason::IncreaseLoad, 102.5, 8];
        yield 'double: at the top but a set missing' => [ProgressionStrategy::DoubleProgression, [[70, 12, 2], [70, 12, 1]], SuggestionReason::Repeat, 70.0, 12];
        yield 'fixed reps: RIR at target is not easy' => [ProgressionStrategy::FixedReps, [[60, 12, 2], [60, 12, 2], [60, 12, 2]], SuggestionReason::Repeat, 60.0, 12];
        yield 'fixed load: sets missing still add reps' => [ProgressionStrategy::FixedLoad, [[20, 9, 1]], SuggestionReason::AddReps, 20.0, 10];
    }

    /**
     * @param list<array{float, int, ?int}> $sets
     */
    #[DataProvider('suggestions')]
    public function testTheNextTargetFollowsTheStrategy(ProgressionStrategy $strategy, array $sets, SuggestionReason $reason, ?float $load, ?int $reps): void
    {
        $suggestion = ProgressionAdvisor::suggest(self::planned($strategy), Measurement::Weight, array_map(fn (array $set): WorkoutSet => $this->set($set[0], $set[1], $set[2]), $sets));

        self::assertNotNull($suggestion);
        self::assertSame([$reason, $load, $reps, null], [$suggestion->reason, $suggestion->loadKg, $suggestion->reps, $suggestion->seconds]);
    }

    public function testWarmUpsDoNotCountAndTheFirstTimeHasNoSuggestion(): void
    {
        self::assertNull(ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::DoubleProgression), Measurement::Weight, [$this->set(40, 12, null, SetKind::Warmup)]));
        self::assertNull(ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::DoubleProgression), Measurement::Weight, []));
    }

    public function testABodyweightExerciseGainsLoadOnceTheRepsAreThere(): void
    {
        $sets = [$this->set(null, 12, 2, measurement: Measurement::Bodyweight), $this->set(null, 12, 2, measurement: Measurement::Bodyweight), $this->set(null, 12, 1, measurement: Measurement::Bodyweight)];

        $suggestion = ProgressionAdvisor::suggest(self::planned(ProgressionStrategy::DoubleProgression), Measurement::Bodyweight, $sets);

        self::assertSame([SuggestionReason::IncreaseLoad, 2.5, 8], [$suggestion?->reason, $suggestion?->loadKg, $suggestion?->reps]);
    }

    public function testATimedExerciseGainsSeconds(): void
    {
        $plank = new PlannedExercise(new ExerciseId(self::ROW), 3, new RepRange(30, 60), null, 60, ProgressionStrategy::DoubleProgression);
        $timed = static fn (int $seconds): WorkoutSet => new WorkoutSet(new SetId('00000000-0000-7000-8000-000000000501'), 0, SetKind::Normal, Measurement::Duration, null, null, $seconds, null, new \DateTimeImmutable());

        $more = ProgressionAdvisor::suggest($plank, Measurement::Duration, [$timed(40), $timed(45)]);
        self::assertSame([SuggestionReason::AddReps, null, null, 50], [$more?->reason, $more?->loadKg, $more?->reps, $more?->seconds]);
        self::assertSame(60, ProgressionAdvisor::suggest($plank, Measurement::Duration, [$timed(58)])?->seconds, 'up to the top of the range');
        self::assertSame(SuggestionReason::Repeat, ProgressionAdvisor::suggest($plank, Measurement::Duration, [$timed(60)])?->reason);
    }

    public function testRecordsAreTheBestsOfWorkingSets(): void
    {
        $history = [
            $this->workout('00000000-0000-7000-8000-00000000d001', '2026-09-01', [[100, 5], [100, 5], [90, 8]]),
            $this->workout('00000000-0000-7000-8000-00000000d002', '2026-09-08', [[105, 3], [95, 6], [60, 20, SetKind::Warmup]]),
        ];

        $bests = RecordBook::bests($history, new ExerciseId(self::ROW));

        self::assertSame(105.0, $bests['load']->value);
        self::assertSame('2026-09-08', $bests['load']->date->format('Y-m-d'));
        self::assertSame(116.7, $bests['estimated_one_rep_max']->value, '100 × 5 → 116.7, better than 105 × 3 → 115.5');
        self::assertSame(1720.0, $bests['volume']->value, 'the first workout, warm-ups aside');
        self::assertArrayNotHasKey('reps_at_load', $bests);
        self::assertSame('00000000-0000-7000-8000-00000000d001', $bests['volume']->workoutId->value);
    }

    public function testAWorkoutSetsTheRecordsItBeats(): void
    {
        $before = [$this->workout('00000000-0000-7000-8000-00000000d001', '2026-09-01', [[100, 5], [100, 5], [90, 8]])];
        $today = $this->workout('00000000-0000-7000-8000-00000000d002', '2026-09-08', [[100, 6], [102.5, 2], [80, 8]]);

        $kinds = array_map(static fn (PersonalRecord $record): string => $record->kind->value.(null === $record->loadKg ? '' : '@'.$record->loadKg), RecordBook::beatenBy($today, $before, new ExerciseId(self::ROW)));

        sort($kinds);
        self::assertSame(['estimated_one_rep_max', 'load', 'reps_at_load@100'], $kinds, 'more reps at 100 kg; 80 kg was never done, it is no record');
        self::assertSame([], RecordBook::beatenBy($today, [], new ExerciseId(self::ROW)), 'the first time is not a record');
    }

    public function testBodyweightAndTimedRecords(): void
    {
        $pullUps = fn (string $id, string $date, int $reps): Workout => $this->bodyweight($id, $date, $reps);
        $before = [$pullUps('00000000-0000-7000-8000-00000000d001', '2026-09-01', 8)];
        $today = $pullUps('00000000-0000-7000-8000-00000000d002', '2026-09-08', 10);

        self::assertSame([RecordKind::Reps], array_map(static fn (PersonalRecord $record): RecordKind => $record->kind, RecordBook::beatenBy($today, $before, new ExerciseId(self::ROW))));
        self::assertNull(RecordBook::estimatedOneRepMax(100, 13), 'too many reps to estimate');
        self::assertNull(RecordBook::estimatedOneRepMax(0, 5));
        self::assertSame(100.0, RecordBook::estimatedOneRepMax(100, 1));
    }

    public function testATieIsNoRecordNorAreWarmUps(): void
    {
        $before = [$this->workout('00000000-0000-7000-8000-00000000d001', '2026-09-01', [[100, 5], [100, 5]])];
        $tie = $this->workout('00000000-0000-7000-8000-00000000d002', '2026-09-08', [[100, 5], [100, 5]]);
        $warmUpsOnly = [$this->workout('00000000-0000-7000-8000-00000000d003', '2026-08-25', [[60, 10, SetKind::Warmup]])];

        self::assertSame([], RecordBook::beatenBy($tie, $before, new ExerciseId(self::ROW)));
        self::assertSame([], RecordBook::beatenBy($tie, $warmUpsOnly, new ExerciseId(self::ROW)), 'nothing done before to beat');
    }

    public function testTheTargetRirDefaultsAndTheLoadNeverGoesNegative(): void
    {
        $noTarget = new PlannedExercise(new ExerciseId(self::ROW), 1, new RepRange(8, 12), null, 120, ProgressionStrategy::TargetRir, 2.5);
        self::assertSame(SuggestionReason::IncreaseLoad, ProgressionAdvisor::suggest($noTarget, Measurement::Weight, [$this->set(80, 10, 4)])?->reason, 'RIR 2 assumed');
        self::assertSame(0.0, ProgressionAdvisor::suggest($noTarget, Measurement::Weight, [$this->set(1, 10, 0)])?->loadKg);
        self::assertSame(SuggestionReason::Repeat, ProgressionAdvisor::suggest($noTarget, Measurement::Bodyweight, [$this->set(null, 10, 0, measurement: Measurement::Bodyweight)])?->reason, 'no load to take off');
    }

    public function testATimedExerciseIgnoresWarmUpsAndFixedRepsKeepsTheTime(): void
    {
        $plank = new PlannedExercise(new ExerciseId(self::ROW), 2, new RepRange(30, 60), null, 60, ProgressionStrategy::FixedReps);
        $timed = static fn (int $seconds, SetKind $kind = SetKind::Normal): WorkoutSet => new WorkoutSet(new SetId('00000000-0000-7000-8000-000000000501'), 0, $kind, Measurement::Duration, null, null, $seconds, null, new \DateTimeImmutable());

        self::assertSame([SuggestionReason::Repeat, 40], [ProgressionAdvisor::suggest($plank, Measurement::Duration, [$timed(90, SetKind::Warmup), $timed(40)])?->reason, ProgressionAdvisor::suggest($plank, Measurement::Duration, [$timed(90, SetKind::Warmup), $timed(40)])?->seconds]);
    }

    private static function planned(ProgressionStrategy $strategy): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId(self::ROW), 3, new RepRange(8, 12), 2, 120, $strategy);
    }

    private function set(?float $load, int $reps, ?int $rir, SetKind $kind = SetKind::Normal, Measurement $measurement = Measurement::Weight): WorkoutSet
    {
        return new WorkoutSet(new SetId('00000000-0000-7000-8000-000000000501'), 0, $kind, $measurement, $load, $reps, null, $rir, new \DateTimeImmutable());
    }

    /**
     * @param list<array{0: float, 1: int, 2?: SetKind}> $sets
     */
    private function workout(string $id, string $date, array $sets): Workout
    {
        $at = new \DateTimeImmutable($date.' 18:00');
        $workout = Workout::start(new WorkoutId($id), new UserId('00000000-0000-7000-8000-00000000a1e7'), null, [new WorkoutExercise(new ExerciseId(self::ROW), 'Rowing', Measurement::Weight, 0, self::planned(ProgressionStrategy::DoubleProgression))], $at, $at);
        foreach ($sets as $i => $set) {
            $workout->recordSet(new SetId(\sprintf('00000000-0000-7000-8000-%012d', $i + 1)), 0, $set[2] ?? SetKind::Normal, $set[0], $set[1], null, null, $at, $at);
        }
        $workout->finish($at->modify('+1 hour'), $at->modify('+1 hour'));

        return $workout;
    }

    private function bodyweight(string $id, string $date, int $reps): Workout
    {
        $at = new \DateTimeImmutable($date.' 18:00');
        $workout = Workout::start(new WorkoutId($id), new UserId('00000000-0000-7000-8000-00000000a1e7'), null, [new WorkoutExercise(new ExerciseId(self::ROW), 'Tractions', Measurement::Bodyweight, 0, self::planned(ProgressionStrategy::DoubleProgression))], $at, $at);
        $workout->recordSet(new SetId('00000000-0000-7000-8000-000000000001'), 0, SetKind::Normal, null, $reps, null, null, $at, $at);

        return $workout;
    }
}
