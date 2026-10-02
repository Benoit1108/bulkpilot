<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidExercise;
use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteId;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Exercise\SearchKey;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Rir;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Program\TrainingDay;
use App\Training\Domain\Progression\PersonalRecord;
use App\Training\Domain\Progression\RecordBook;
use App\Training\Domain\Progression\RecordKind;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutSet;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The exact edges of Training's rules: a limit is accepted, one beyond it is not, lengths count
 * characters.
 */
final class BoundariesTest extends TestCase
{
    private const string EXERCISE = '00000000-0000-7000-8000-00000000a001';
    private const string OTHER_EXERCISE = '00000000-0000-7000-8000-00000000a002';
    private const string OWNER = '0199a0b1-0000-7000-8000-000000000001';

    public function testSetsAtTheirLimits(): void
    {
        self::assertSame(WorkoutSet::MAX_LOAD_KG, self::set(load: WorkoutSet::MAX_LOAD_KG)->loadKg);
        self::assertSame(0.0, self::set(load: 0.0)->loadKg, 'an empty bar');
        self::assertSame(WorkoutSet::MAX_REPS, self::set(reps: WorkoutSet::MAX_REPS)->reps);
        self::assertSame(0, self::set(reps: 0)->reps, 'a failed attempt');
        self::assertSame(1, self::set(Measurement::Duration, null, null, 1)->seconds);
        self::assertSame(WorkoutSet::MAX_SECONDS, self::set(Measurement::Duration, null, null, WorkoutSet::MAX_SECONDS)->seconds);
    }

    public function testTheVolumeIsLoadTimesRepsToATenth(): void
    {
        self::assertSame(0.0, self::set(Measurement::Bodyweight, null, 12)->volume(), 'no added load');
        self::assertSame(0.0, self::set(Measurement::Duration, 20.0, null, 60)->volume(), 'no reps');
        self::assertSame(1021.3, WorkoutSet::totalVolume([self::set(load: 101.12, reps: 10), self::set(load: 10.11, reps: 1)]), '1 011,2 + 10,11 = 1 021,31 → 1 021,3');
        self::assertSame(1021.4, WorkoutSet::totalVolume([self::set(load: 101.12, reps: 10), self::set(load: 10.16, reps: 1)]), '1 021,36 → 1 021,4');
    }

    /**
     * @return iterable<string, array{\Closure(): mixed, class-string<\Throwable>}>
     */
    public static function oneBeyond(): iterable
    {
        yield 'a load over 1 000 kg' => [static fn () => self::set(load: WorkoutSet::MAX_LOAD_KG + 0.01), InvalidWorkout::class];
        yield 'too many reps' => [static fn () => self::set(reps: WorkoutSet::MAX_REPS + 1), InvalidWorkout::class];
        yield 'negative reps' => [static fn () => self::set(reps: -1), InvalidWorkout::class];
        yield 'zero seconds' => [static fn () => self::set(Measurement::Duration, null, null, 0), InvalidWorkout::class];
        yield 'more than an hour' => [static fn () => self::set(Measurement::Duration, null, null, WorkoutSet::MAX_SECONDS + 1), InvalidWorkout::class];
        yield 'too many sets' => [static fn () => self::planned(sets: PlannedExercise::MAX_SETS + 1), InvalidPrescription::class];
        yield 'too many warm-up sets' => [static fn () => self::planned(warmup: PlannedExercise::MAX_WARMUP_SETS + 1), InvalidPrescription::class];
        yield 'too small an increment' => [static fn () => self::planned(increment: 0.24), InvalidPrescription::class];
        yield 'too big an increment' => [static fn () => self::planned(increment: 20.01), InvalidPrescription::class];
        yield 'notes too long' => [static fn () => self::planned(notes: str_repeat('a', PlannedExercise::MAX_NOTES_LENGTH + 1)), InvalidPrescription::class];
        yield 'a rep range over 300' => [static fn () => new RepRange(1, RepRange::MAX + 1), InvalidPrescription::class];
        yield 'a rep range from 0' => [static fn () => new RepRange(0, 5), InvalidPrescription::class];
        yield 'a RIR over 10' => [static fn () => Rir::valid(Rir::MAX + 1), InvalidPrescription::class];
        yield 'a five-digit tempo' => [static fn () => new Tempo('31100'), InvalidPrescription::class];
        yield 'a tempo with a prefix' => [static fn () => new Tempo('a3110'), InvalidPrescription::class];
        yield 'too big a superset' => [static fn () => new ExerciseBlock(array_fill(0, ExerciseBlock::MAX_EXERCISES + 1, self::planned())), InvalidProgram::class];
        yield 'a day name too long' => [static fn () => new TrainingDay(str_repeat('a', TrainingDay::MAX_NAME_LENGTH + 1), [self::block()]), InvalidProgram::class];
        yield 'too many blocks' => [static fn () => new TrainingDay('Push', array_fill(0, TrainingDay::MAX_BLOCKS + 1, self::block())), InvalidProgram::class];
        yield 'too many days' => [static fn () => self::version(ProgramVersion::MAX_DAYS + 1), InvalidProgram::class];
        yield 'two days named alike' => [static fn () => ProgramVersion::first(new ProgramVersionId(self::EXERCISE), new ProgramId(self::EXERCISE), [new TrainingDay('épaules', [self::block()]), new TrainingDay('ÉPAULES', [self::block()])], new \DateTimeImmutable()), InvalidProgram::class];
        yield 'a program name too long' => [static fn () => Program::create(new ProgramId(self::EXERCISE), new UserId(self::OWNER), str_repeat('a', Program::MAX_NAME_LENGTH + 1), self::version(1), new \DateTimeImmutable()), InvalidProgram::class];
        yield 'an exercise name too long' => [static fn () => self::spec(str_repeat('a', ExerciseSpec::MAX_NAME_LENGTH + 1)), InvalidExercise::class];
        yield 'too many secondary muscles' => [static fn () => self::spec(secondary: [Muscle::Lats, Muscle::Traps, Muscle::Biceps, Muscle::Triceps, Muscle::Forearms, Muscle::Abs, Muscle::UpperBack]), InvalidExercise::class];
        yield 'a description too long' => [static fn () => self::spec(description: str_repeat('a', ExerciseSpec::MAX_DESCRIPTION_LENGTH + 1)), InvalidExercise::class];
        yield 'a media link too long' => [static fn () => self::spec(mediaUrl: 'https://example.org/'.str_repeat('a', ExerciseSpec::MAX_MEDIA_URL_LENGTH)), InvalidExercise::class];
        yield 'a media link that is not one' => [static fn () => self::spec(mediaUrl: 'https://'), InvalidExercise::class];
        yield 'a note too long' => [static fn () => ExerciseNote::write(new ExerciseNoteId(self::EXERCISE), new UserId(self::OWNER), new ExerciseId(self::EXERCISE), str_repeat('a', ExerciseNote::MAX_LENGTH + 1), new \DateTimeImmutable()), InvalidExercise::class];
    }

    /**
     * @param \Closure(): mixed        $make
     * @param class-string<\Throwable> $error
     */
    #[DataProvider('oneBeyond')]
    public function testOneBeyondTheLimitIsRefused(\Closure $make, string $error): void
    {
        $this->expectException($error);
        $make();
    }

    public function testPrescriptionsAtTheirLimits(): void
    {
        $planned = self::planned(sets: PlannedExercise::MAX_SETS, warmup: PlannedExercise::MAX_WARMUP_SETS, increment: PlannedExercise::MIN_INCREMENT_KG, notes: str_repeat('é', PlannedExercise::MAX_NOTES_LENGTH));
        self::assertSame([PlannedExercise::MAX_SETS, PlannedExercise::MAX_WARMUP_SETS, PlannedExercise::MIN_INCREMENT_KG], [$planned->sets, $planned->warmupSets, $planned->incrementKg]);
        self::assertSame(PlannedExercise::MAX_INCREMENT_KG, self::planned(increment: PlannedExercise::MAX_INCREMENT_KG)->incrementKg);
        self::assertSame(0, new PlannedExercise(new ExerciseId(self::EXERCISE), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression)->warmupSets, 'no warm-up unless asked');
        self::assertSame([1, RepRange::MAX], [new RepRange(1, RepRange::MAX)->min, new RepRange(1, RepRange::MAX)->max]);
        self::assertSame(Rir::MAX, Rir::valid(Rir::MAX));
        self::assertSame('3-1-1-0', new Tempo('3110')->value);
        self::assertCount(ExerciseBlock::MAX_EXERCISES, new ExerciseBlock(array_fill(0, ExerciseBlock::MAX_EXERCISES, self::planned()))->exercises);
    }

    public function testProgramsAtTheirLimits(): void
    {
        $day = new TrainingDay('  '.str_repeat('é', TrainingDay::MAX_NAME_LENGTH).'  ', array_fill(0, TrainingDay::MAX_BLOCKS, self::block()));
        self::assertSame(str_repeat('é', TrainingDay::MAX_NAME_LENGTH), $day->name, 'trimmed, then counted in characters');
        self::assertCount(TrainingDay::MAX_BLOCKS, $day->blocks);
        self::assertCount(ProgramVersion::MAX_DAYS, self::version(ProgramVersion::MAX_DAYS)->days());
        $program = Program::create(new ProgramId(self::EXERCISE), new UserId(self::OWNER), str_repeat('é', Program::MAX_NAME_LENGTH), self::version(1), new \DateTimeImmutable());
        self::assertSame(Program::MAX_NAME_LENGTH, mb_strlen($program->name()));
    }

    public function testAMissingDaySaysWhichOne(): void
    {
        try {
            self::version(2)->day(4);
            self::fail('no fifth day');
        } catch (InvalidProgram $error) {
            self::assertStringContainsString('this version has no day 5', $error->getMessage());
        }
    }

    public function testExercisesAtTheirLimits(): void
    {
        $spec = self::spec(str_repeat('é', ExerciseSpec::MAX_NAME_LENGTH), [Muscle::Lats, Muscle::Traps, Muscle::Biceps, Muscle::Triceps, Muscle::Forearms, Muscle::Abs], str_repeat('é', ExerciseSpec::MAX_DESCRIPTION_LENGTH), 'https://example.org/'.str_repeat('a', ExerciseSpec::MAX_MEDIA_URL_LENGTH - 20));
        self::assertSame(ExerciseSpec::MAX_NAME_LENGTH, mb_strlen($spec->name));
        self::assertCount(ExerciseSpec::MAX_SECONDARY_MUSCLES, $spec->secondaryMuscles);
        self::assertSame(ExerciseSpec::MAX_DESCRIPTION_LENGTH, mb_strlen((string) $spec->description));
        self::assertSame(ExerciseSpec::MAX_MEDIA_URL_LENGTH, mb_strlen((string) $spec->mediaUrl));
        $note = ExerciseNote::write(new ExerciseNoteId(self::EXERCISE), new UserId(self::OWNER), new ExerciseId(self::EXERCISE), str_repeat('é', ExerciseNote::MAX_LENGTH), new \DateTimeImmutable());
        self::assertSame(ExerciseNote::MAX_LENGTH, mb_strlen($note->text()));
    }

    public function testSearchKeysAreAsciiWordsAtMostSix(): void
    {
        self::assertSame('developpe couche oeuf aeble', SearchKey::of('  Développé-couché, Œuf, Æble  '));
        self::assertSame(['un', 'deux', 'trois', 'quatre', 'cinq', 'six'], SearchKey::words('un deux trois quatre cinq six sept'));
        self::assertSame(['rowing', 'barre'], SearchKey::words('Rowing  barre rowing'));
    }

    public function testRecordKeysTellLoadsApart(): void
    {
        $workout = new WorkoutId(self::EXERCISE);

        self::assertSame('load', new PersonalRecord(RecordKind::Load, 100.0, new \DateTimeImmutable(), $workout)->key());
        self::assertSame('reps_at_load@62.5', new PersonalRecord(RecordKind::RepsAtLoad, 8.0, new \DateTimeImmutable(), $workout, 62.5)->key());
    }

    public function testTheEstimateStopsAtTwelveReps(): void
    {
        self::assertSame(140.0, RecordBook::estimatedOneRepMax(100.0, 12));
        self::assertNull(RecordBook::estimatedOneRepMax(100.0, 13));
        self::assertSame(100.0, RecordBook::estimatedOneRepMax(100.0, 1));
        self::assertNull(RecordBook::estimatedOneRepMax(0.0, 5));
    }

    public function testAWorkoutHoldsUpToEightyExercisesAndNamesTheOneThatDiffers(): void
    {
        $now = new \DateTimeImmutable('2026-09-30 18:00');
        $exercises = array_map(static fn (int $i): WorkoutExercise => new WorkoutExercise(new ExerciseId(self::EXERCISE), 'Squat', Measurement::Weight, $i, self::planned()), range(0, Workout::MAX_EXERCISES - 1));
        $workout = Workout::start(new WorkoutId(self::EXERCISE), new UserId(self::OWNER), null, $exercises, $now, $now);
        self::assertCount(Workout::MAX_EXERCISES, $workout->exercises());

        try {
            $workout->addExercise(Workout::MAX_EXERCISES, new ExerciseId(self::EXERCISE), 'Squat', Measurement::Weight, self::planned());
            self::fail('an 81st exercise');
        } catch (InvalidWorkout $error) {
            self::assertStringContainsString('after the last one', $error->getMessage());
        }
        try {
            $workout->addExercise(2, new ExerciseId(self::OTHER_EXERCISE), 'Rowing', Measurement::Weight, self::planned());
            self::fail('another exercise in place of the third');
        } catch (InvalidWorkout $error) {
            self::assertStringContainsString('exercise 3 of this workout is another one', $error->getMessage());
        }

        $this->expectException(InvalidWorkout::class);
        Workout::start(new WorkoutId(self::EXERCISE), new UserId(self::OWNER), null, [...$exercises, $exercises[0]], $now, $now);
    }

    private static function set(Measurement $measurement = Measurement::Weight, ?float $load = 80.0, ?int $reps = 8, ?int $seconds = null): WorkoutSet
    {
        return new WorkoutSet(new SetId(self::EXERCISE), 0, SetKind::Normal, $measurement, $load, $reps, $seconds, null, new \DateTimeImmutable());
    }

    private static function planned(int $sets = 3, int $warmup = 0, float $increment = 2.5, ?string $notes = null): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId(self::EXERCISE), $sets, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, $increment, $warmup, notes: $notes);
    }

    private static function block(): ExerciseBlock
    {
        return new ExerciseBlock([self::planned()]);
    }

    private static function version(int $days): ProgramVersion
    {
        return ProgramVersion::first(new ProgramVersionId(self::EXERCISE), new ProgramId(self::EXERCISE), array_map(static fn (int $n): TrainingDay => new TrainingDay('Jour '.$n, [self::block()]), range(1, $days)), new \DateTimeImmutable());
    }

    /**
     * @param list<Muscle> $secondary
     */
    private static function spec(string $name = 'Squat', array $secondary = [], ?string $description = null, ?string $mediaUrl = null): ExerciseSpec
    {
        return new ExerciseSpec($name, Muscle::Quads, $secondary, ExerciseType::Compound, Equipment::Barbell, Measurement::Weight, new ExerciseDefaults(new RepRange(6, 10), 2, 180), $description, $mediaUrl);
    }
}
