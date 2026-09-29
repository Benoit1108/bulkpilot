<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Workout\ProgramDayRef;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutSet;
use PHPUnit\Framework\TestCase;

final class WorkoutTest extends TestCase
{
    private const string BENCH = '00000000-0000-7000-8000-0000000000e1';
    private const string PLANK = '00000000-0000-7000-8000-0000000000e2';

    private UserId $alex;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->now = new \DateTimeImmutable('2026-09-28 18:00:00');
    }

    public function testAWorkoutKeepsWhatItPlannedAndTheSetsDone(): void
    {
        $day = new ProgramDayRef(new ProgramId('00000000-0000-7000-8000-00000000b001'), 'PPL', new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), 2, 0, 'Push');
        $workout = $this->workout($day);
        self::assertTrue($workout->isInProgress());
        self::assertSame(['PPL', 'Push'], [$workout->programDay()?->programName, $workout->programDay()?->dayName]);
        self::assertTrue($workout->belongsTo($this->alex));

        $this->record($workout, 501, 0, 60, 10, '18:05', SetKind::Warmup);
        $this->record($workout, 503, 0, 80, 8, '18:12');
        $this->record($workout, 502, 0, 80, 9, '18:08');
        $workout->recordSet(self::setId(504), 1, SetKind::Normal, null, null, 45, null, self::at('18:20'), self::at('18:20'));
        self::assertSame([9, 8], array_map(static fn (WorkoutSet $set): ?int => $set->reps, \array_slice($workout->setsOf(0), 1)), 'in the order they were done');
        self::assertCount(4, $workout->sets());
        self::assertSame([80.0, 80.0], array_map(static fn (WorkoutSet $set): ?float => $set->loadKg, $workout->workingSetsOf(new ExerciseId(self::BENCH))));
        self::assertSame(1360.0, WorkoutSet::totalVolume($workout->workingSetsOf(new ExerciseId(self::BENCH))));
        self::assertSame(Measurement::Duration, $workout->measurementOf(new ExerciseId(self::PLANK)));
        self::assertNull($workout->measurementOf(new ExerciseId('00000000-0000-7000-8000-0000000000ff')));

        $this->record($workout, 503, 0, 82.5, 8, '18:12');
        self::assertCount(4, $workout->sets(), 'the same set, corrected');
        self::assertSame(82.5, $workout->setsOf(0)[2]->loadKg);

        $workout->removeSet(self::setId(501));
        $workout->removeSet(self::setId(501));
        self::assertCount(3, $workout->sets());

        $workout->finish(self::at('19:00'), self::at('19:00'));
        $workout->finish(self::at('20:00'), self::at('20:00'));
        self::assertSame('19:00', $workout->finishedAt()?->format('H:i'), 'finishing again keeps the first time');
        self::assertFalse($workout->isInProgress());

        $this->record($workout, 502, 0, 80, 10, '18:08', now: '21:00');
        self::assertSame(10, $workout->setsOf(0)[0]->reps, 'corrected once finished');
    }

    public function testASetStaysWithItsExerciseAndIsMeasuredLikeIt(): void
    {
        $workout = $this->workout();
        $this->record($workout, 501, 0, 80, 8);

        foreach ([
            fn () => $this->record($workout, 501, 1, 80, 8),
            fn () => $this->record($workout, 502, 1, 80, 8),
            fn () => $this->record($workout, 502, 5, 80, 8),
        ] as $i => $case) {
            try {
                $case();
                self::fail('case '.$i);
            } catch (InvalidWorkout) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testExercisesAreAppendedOnceInABlockOfTheirOwn(): void
    {
        $workout = $this->workout();
        $this->add($workout, 2, self::BENCH);
        $this->add($workout, 2, self::BENCH);
        self::assertCount(3, $workout->exercises(), 'sent twice, added once');
        self::assertSame([2, true], [$workout->exercises()[2]->block, $workout->exercises()[2]->added]);

        $this->record($workout, 501, 0, 80, 8);
        $this->record($workout, 502, 2, 60, 12);
        self::assertCount(2, $workout->setsOfExercise(new ExerciseId(self::BENCH)), 'wherever it appears');
        self::assertSame([], $workout->setsOfExercise(new ExerciseId('00000000-0000-7000-8000-0000000000ff')));

        foreach ([[2, self::PLANK], [5, self::BENCH]] as [$index, $exercise]) {
            try {
                $this->add($workout, $index, $exercise);
                self::fail('position '.$index);
            } catch (InvalidWorkout) {
                self::addToAssertionCount(1);
            }
        }

        $workout->finish($this->now, $this->now);
        $this->expectException(InvalidWorkout::class);
        $this->add($workout, 3, self::PLANK);
    }

    public function testASetMatchesWhatItsExerciseRecords(): void
    {
        $cases = [
            [Measurement::Weight, null, 8, null],
            [Measurement::Weight, 80.0, null, null],
            [Measurement::Weight, 80.0, 8, 30],
            [Measurement::Bodyweight, 10.0, null, null],
            [Measurement::Duration, null, null, null],
            [Measurement::Duration, null, 10, 30],
            [Measurement::Weight, -1.0, 8, null],
            [Measurement::Weight, 1001.0, 8, null],
            [Measurement::Weight, 80.0, 301, null],
            [Measurement::Duration, null, null, 3601],
            [Measurement::Duration, null, null, 0],
        ];
        foreach ($cases as $i => [$measurement, $load, $reps, $seconds]) {
            try {
                new WorkoutSet(self::setId(501), 0, SetKind::Normal, $measurement, $load, $reps, $seconds, null, $this->now);
                self::fail('case '.$i);
            } catch (InvalidWorkout) {
                self::addToAssertionCount(1);
            }
        }

        $bodyweight = new WorkoutSet(self::setId(501), 0, SetKind::Effective, Measurement::Bodyweight, null, 12, null, 1, $this->now);
        self::assertSame(0.0, $bodyweight->volume());
        self::assertSame(12.35, new WorkoutSet(self::setId(501), 0, SetKind::Normal, Measurement::Weight, 12.345, 5, null, null, $this->now)->loadKg);
        self::assertSame(0.0, WorkoutSet::totalVolume([]));

        $this->expectException(InvalidPrescription::class);
        new WorkoutSet(self::setId(501), 0, SetKind::Normal, Measurement::Weight, 80, 8, null, 11, $this->now);
    }

    public function testWorkingSetsAreTheOnesThatCount(): void
    {
        self::assertSame([false, false, true, true, false], array_map(static fn (SetKind $kind): bool => $kind->isWorking(), SetKind::cases()));
    }

    public function testTimesComeFromTheDeviceWithinBounds(): void
    {
        $start = fn (string $when): Workout => Workout::start(new WorkoutId('00000000-0000-7000-8000-00000000d001'), $this->alex, null, [$this->exercise(self::BENCH)], $this->now->modify($when), $this->now);
        self::assertSame([], $start('-30 days')->sets(), 'thirty days back');
        self::assertSame([], $start('+10 minutes')->sets(), 'a clock ten minutes ahead');

        $cases = [
            static fn () => $start('-30 days -1 second'),
            static fn () => $start('+10 minutes +1 second'),
            fn () => $this->record($this->workout(), 501, 0, 80, 8, '18:21'),
            fn () => $this->record($this->workout(), 501, 0, 80, 8, '17:54'),
            fn () => $this->workout()->finish($this->now->modify('-1 second'), $this->now),
            fn () => $this->workout()->finish($this->now->modify('+1 day +1 second'), $this->now->modify('+2 days')),
            fn () => Workout::start(new WorkoutId('00000000-0000-7000-8000-00000000d001'), $this->alex, null, array_fill(0, Workout::MAX_EXERCISES + 1, $this->exercise(self::BENCH)), $this->now, $this->now),
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                self::fail('case '.$i);
            } catch (InvalidWorkout) {
                self::addToAssertionCount(1);
            }
        }

        $workout = $this->workout();
        $this->record($workout, 501, 0, 80, 8, '17:55');
        $workout->finish($this->now, $this->now);
        self::assertSame($this->now, $workout->finishedAt(), 'finished the moment it started');
        $this->expectException(InvalidWorkout::class);
        $this->record($workout, 502, 0, 80, 8, '18:01', now: '18:05');
    }

    public function testAWorkoutHasAtMostSoManySets(): void
    {
        $workout = $this->workout();
        for ($i = 1; $i <= Workout::MAX_SETS; ++$i) {
            $this->record($workout, $i, 0, 80, 8);
        }
        $this->record($workout, Workout::MAX_SETS, 0, 82.5, 8);
        self::assertCount(Workout::MAX_SETS, $workout->sets(), 'a correction is still possible');

        $this->expectException(InvalidWorkout::class);
        $this->record($workout, Workout::MAX_SETS + 1, 0, 80, 8);
    }

    public function testADiscardedWorkoutIsGone(): void
    {
        $workout = $this->workout();
        $workout->discard($this->now);

        self::assertTrue($workout->isDiscarded());
        self::assertFalse($workout->isVisibleTo($this->alex));
        self::assertFalse($workout->isInProgress());
        self::assertTrue($workout->belongsTo($this->alex));
        self::assertFalse($workout->isVisibleTo(new UserId('00000000-0000-7000-8000-000000000511')));
    }

    private function workout(?ProgramDayRef $day = null): Workout
    {
        return Workout::start(new WorkoutId('00000000-0000-7000-8000-00000000d001'), $this->alex, $day, [$this->exercise(self::BENCH), $this->exercise(self::PLANK, Measurement::Duration, 1)], $this->now, $this->now);
    }

    private function exercise(string $id, Measurement $measurement = Measurement::Weight, int $block = 0): WorkoutExercise
    {
        return new WorkoutExercise(new ExerciseId($id), 'Exercice', $measurement, $block, self::planned($id));
    }

    private function add(Workout $workout, int $index, string $exercise): void
    {
        $workout->addExercise($index, new ExerciseId($exercise), 'Exercice', Measurement::Weight, self::planned($exercise));
    }

    private function record(Workout $workout, int $id, int $exercise, float $load, int $reps, string $at = '18:00', SetKind $kind = SetKind::Normal, string $now = '18:10'): void
    {
        $workout->recordSet(self::setId($id), $exercise, $kind, $load, $reps, null, 2, self::at($at), self::at($now));
    }

    private function at(string $time): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-28 '.$time);
    }

    private static function setId(int $number): SetId
    {
        return new SetId(\sprintf('00000000-0000-7000-8000-%012d', $number));
    }

    private static function planned(string $exercise): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId($exercise), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
    }
}
