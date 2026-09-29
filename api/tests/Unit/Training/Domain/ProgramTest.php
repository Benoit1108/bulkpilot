<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Domain;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Program\TrainingDay;
use PHPUnit\Framework\TestCase;

final class ProgramTest extends TestCase
{
    private const string BENCH = '00000000-0000-7000-8000-0000000000e1';
    private const string ROW = '00000000-0000-7000-8000-0000000000e2';
    private const string RAISE = '00000000-0000-7000-8000-0000000000e3';

    private ProgramId $programId;
    private UserId $alex;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->programId = new ProgramId('00000000-0000-7000-8000-00000000b001');
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testAProgramStartsWithItsFirstVersion(): void
    {
        [$program, $first] = $this->ppl();

        self::assertSame('PPL Masse', $program->name());
        self::assertSame(1, $program->currentVersionNumber());
        self::assertTrue($program->currentVersionId()->equals($first->id()));
        self::assertFalse($program->isActive());
        self::assertTrue($program->belongsTo($this->alex));
        self::assertSame($this->now, $program->createdAt());
        self::assertSame([self::BENCH, self::RAISE, self::ROW], array_map(static fn (ExerciseId $id): string => $id->value, $first->exerciseIds()));
        self::assertTrue($first->day(0)->blocks[1]->isSuperset());
        self::assertFalse($first->day(0)->blocks[0]->isSuperset());
    }

    public function testAProgramCannotStartFromAnotherOnesVersion(): void
    {
        $other = ProgramVersion::first(new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), new ProgramId('00000000-0000-7000-8000-00000000b002'), [$this->day('Push')], $this->now);

        $this->expectException(InvalidProgram::class);
        Program::create($this->programId, $this->alex, 'PPL', $other, $this->now);
    }

    public function testChangingTheDaysMakesTheNextVersionAndKeepsThePreviousOne(): void
    {
        [$program, $first] = $this->ppl();
        $later = $this->now->modify('+2 weeks');

        self::assertNull($program->revise($first, new ProgramVersionId('00000000-0000-7000-8000-00000000c002'), $first->days(), $later), 'same days: nothing to record');
        self::assertSame(1, $program->currentVersionNumber());

        $next = $program->revise($first, new ProgramVersionId('00000000-0000-7000-8000-00000000c002'), [$this->day('Push', self::BENCH, 4), $this->day('Pull', self::ROW)], $later);
        self::assertNotNull($next);
        self::assertSame(2, $next->number());
        self::assertSame($later, $next->createdAt());
        self::assertTrue($program->currentVersionId()->equals($next->id()));
        self::assertSame(2, $program->currentVersionNumber());
        self::assertSame(3, $first->day(0)->blocks[0]->exercises[0]->sets, 'history is never rewritten');
        self::assertSame(4, $next->day(0)->blocks[0]->exercises[0]->sets);

        $this->expectException(InvalidProgram::class);
        $program->revise($first, new ProgramVersionId('00000000-0000-7000-8000-00000000c003'), [$this->day('Legs')], $later);
    }

    public function testEveryPrescriptionFieldCountsAsAChange(): void
    {
        $base = self::planned(self::BENCH);
        $variants = [
            new PlannedExercise(new ExerciseId(self::ROW), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(6, 12), 2, 120, ProgressionStrategy::DoubleProgression),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 1, 120, ProgressionStrategy::DoubleProgression),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 90, ProgressionStrategy::DoubleProgression),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, 1.25),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, warmupSets: 2),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, dropSet: true),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, tempo: new Tempo('3-1-1-0')),
            new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, notes: 'Pieds au sol'),
        ];

        self::assertTrue($base->equals(self::planned(self::BENCH)));
        foreach ($variants as $i => $variant) {
            self::assertFalse($base->equals($variant), 'variant '.$i);
        }
        self::assertFalse(new ExerciseBlock([$base])->equals(new ExerciseBlock([$base, $base])));
        self::assertFalse(new TrainingDay('Push', [new ExerciseBlock([$base])])->equals(new TrainingDay('Push A', [new ExerciseBlock([$base])])));
    }

    public function testAPrescriptionIsBounded(): void
    {
        $cases = [
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), 0, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear),
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), PlannedExercise::MAX_SETS + 1, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear),
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear, warmupSets: PlannedExercise::MAX_WARMUP_SETS + 1),
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear, 0.1),
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::Linear, notes: str_repeat('a', PlannedExercise::MAX_NOTES_LENGTH + 1)),
            static fn () => new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), 11, 120, ProgressionStrategy::Linear),
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                self::fail('case '.$i);
            } catch (InvalidPrescription) {
                self::addToAssertionCount(1);
            }
        }

        $planned = new PlannedExercise(new ExerciseId(self::BENCH), 3, new RepRange(8, 12), null, 120, ProgressionStrategy::Linear, 1.255, notes: '  ');
        self::assertSame(1.26, $planned->incrementKg);
        self::assertNull($planned->notes);
    }

    public function testAProgramHasOneToSevenDistinctDays(): void
    {
        $cases = [
            [],
            array_map(fn (int $i): TrainingDay => $this->day('Jour '.$i), range(1, ProgramVersion::MAX_DAYS + 1)),
            [$this->day('Push'), $this->day('push')],
        ];
        foreach ($cases as $i => $days) {
            try {
                ProgramVersion::first(new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), $this->programId, $days, $this->now);
                self::fail('case '.$i);
            } catch (InvalidProgram) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testADayAndABlockAreBounded(): void
    {
        $planned = self::planned(self::BENCH);
        $cases = [
            static fn () => new TrainingDay('', [new ExerciseBlock([$planned])]),
            static fn () => new TrainingDay(str_repeat('a', TrainingDay::MAX_NAME_LENGTH + 1), [new ExerciseBlock([$planned])]),
            static fn () => new TrainingDay('Push', []),
            static fn () => new TrainingDay('Push', array_fill(0, TrainingDay::MAX_BLOCKS + 1, new ExerciseBlock([$planned]))),
            static fn () => new ExerciseBlock([]),
            static fn () => new ExerciseBlock(array_fill(0, ExerciseBlock::MAX_EXERCISES + 1, $planned)),
        ];
        foreach ($cases as $i => $case) {
            try {
                $case();
                self::fail('case '.$i);
            } catch (InvalidProgram) {
                self::addToAssertionCount(1);
            }
        }
    }

    public function testAVersionHasTheDaysItHas(): void
    {
        [, $first] = $this->ppl();

        $this->expectException(InvalidProgram::class);
        $first->day(2);
    }

    public function testOnlyAVisibleProgramIsActiveAndArchivingDeactivatesIt(): void
    {
        [$program] = $this->ppl();
        $program->rename('  PPL Masse V2 ');
        self::assertSame('PPL Masse V2', $program->name());

        $program->activate();
        self::assertTrue($program->isActive());
        $program->deactivate();
        self::assertFalse($program->isActive());

        $program->activate();
        $program->archive($this->now);
        self::assertFalse($program->isActive());
        self::assertFalse($program->isVisibleTo($this->alex));

        $this->expectException(InvalidProgram::class);
        $program->activate();
    }

    public function testAProgramHasAtMostSoManyVersions(): void
    {
        [$program, $version] = $this->ppl();
        for ($i = 2; $i <= Program::MAX_VERSIONS; ++$i) {
            $version = $program->revise($version, new ProgramVersionId(\sprintf('00000000-0000-7000-8000-%012d', $i)), [$this->day('Push', self::BENCH, 1 + $i % 2)], $this->now);
            self::assertNotNull($version);
        }
        self::assertSame(Program::MAX_VERSIONS, $program->currentVersionNumber());

        $this->expectException(InvalidProgram::class);
        $program->revise($version, new ProgramVersionId('00000000-0000-7000-8000-00000000ffff'), [$this->day('Push', self::BENCH, 5)], $this->now);
    }

    public function testAProgramNeedsAName(): void
    {
        [$program] = $this->ppl();

        $this->expectException(InvalidProgram::class);
        $program->rename(' ');
    }

    /**
     * @return array{Program, ProgramVersion}
     */
    private function ppl(): array
    {
        $first = ProgramVersion::first(new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), $this->programId, [
            new TrainingDay('Push', [new ExerciseBlock([self::planned(self::BENCH)]), new ExerciseBlock([self::planned(self::RAISE), self::planned(self::BENCH)])]),
            $this->day('Pull', self::ROW),
        ], $this->now);
        self::assertSame(1, $first->number());
        self::assertTrue($first->programId()->equals($this->programId));

        return [Program::create($this->programId, $this->alex, 'PPL Masse', $first, $this->now), $first];
    }

    private function day(string $name, string $exercise = self::BENCH, int $sets = 3): TrainingDay
    {
        return new TrainingDay($name, [new ExerciseBlock([self::planned($exercise, $sets)])]);
    }

    private static function planned(string $exercise, int $sets = 3): PlannedExercise
    {
        return new PlannedExercise(new ExerciseId($exercise), $sets, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression);
    }
}
