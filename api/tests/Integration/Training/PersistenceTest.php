<?php

declare(strict_types=1);

namespace App\Tests\Integration\Training;

use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Domain\Identifier\UserId;
use App\Training\Application\Library\ImportExerciseLibrary;
use App\Training\Application\Program\ActivateProgram;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteId;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use App\Training\Domain\Exercise\ExerciseRepository;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\Program;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramRepository;
use App\Training\Domain\Program\ProgramVersion;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Program\ProgramVersionRepository;
use App\Training\Domain\Program\TrainingDay;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class PersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ExerciseRepository $exercises;
    private UserId $alex;
    private UserId $sam;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->exercises = self::getContainer()->get(ExerciseRepository::class);
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->sam = new UserId('00000000-0000-7000-8000-000000000511');
        $this->now = new \DateTimeImmutable('2026-09-28 10:00:00');
    }

    public function testTheShippedLibraryImportsAndIsIdempotent(): void
    {
        $bus = self::getContainer()->get(CommandBus::class);
        $bus->dispatch($first = new ImportExerciseLibrary());
        $bus->dispatch($second = new ImportExerciseLibrary());

        self::assertGreaterThanOrEqual(80, $first->report->created);
        self::assertSame([0, $first->report->created], [$second->report->created, $second->report->updated]);
        $this->entityManager->clear();
        $library = $this->exercises->library();
        self::assertSame('Développé couché à la barre', $library['barbell_bench_press']->name());
        self::assertSame(Measurement::Duration, $library['plank']->measurement());
        self::assertSame([Muscle::Triceps, Muscle::FrontDelts], $library['barbell_bench_press']->secondaryMuscles());
    }

    public function testExercisesAreSearchedByWordsAndMuscleOwnOnesFirst(): void
    {
        $this->exercises->save(Exercise::fromLibrary(new ExerciseId('00000000-0000-7000-8000-0000000000e1'), 'bench', self::spec('Développé couché à la barre', Muscle::Chest, [Muscle::Triceps]), $this->now));
        $this->exercises->save(Exercise::fromLibrary(new ExerciseId('00000000-0000-7000-8000-0000000000e2'), 'row', self::spec('Rowing barre', Muscle::UpperBack, [Muscle::Biceps, Muscle::Lats]), $this->now));
        $this->exercises->save($mine = Exercise::createCustom(new ExerciseId('00000000-0000-7000-8000-0000000000e3'), $this->alex, self::spec('Rowing 100% Yates', Muscle::Lats, []), $this->now));
        $this->exercises->save(Exercise::createCustom(new ExerciseId('00000000-0000-7000-8000-0000000000e4'), $this->sam, self::spec('Rowing de Sam', Muscle::Lats, []), $this->now));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $names = fn (string $query, ?Muscle $muscle = null): array => array_map(static fn (Exercise $exercise): string => $exercise->name(), $this->exercises->search($this->alex, $query, $muscle, 50));
        self::assertSame(['Rowing 100% Yates', 'Développé couché à la barre', 'Rowing barre'], $names(''));
        self::assertSame(['Développé couché à la barre'], $names('DEVELOPPE barre'));
        self::assertSame(['Rowing 100% Yates'], $names('100%'), 'punctuation is ignored, never a wildcard');
        self::assertCount(3, $names('%%'), 'only punctuation: no filter');
        self::assertSame(['Rowing 100% Yates', 'Rowing barre'], $names('', Muscle::Lats), 'primary or secondary');
        self::assertSame([], $names('', Muscle::Abs));
        self::assertCount(1, $this->exercises->search($this->alex, '', null, 1));

        $usable = $this->exercises->usableAmong($this->sam, [new ExerciseId('00000000-0000-7000-8000-0000000000e3'), new ExerciseId('00000000-0000-7000-8000-0000000000e2')]);
        self::assertSame(['00000000-0000-7000-8000-0000000000e2'], array_keys($usable), 'someone else\'s is left out');
        self::assertSame([], $this->exercises->usableAmong($this->alex, []));
        self::assertSame('Rowing 100% Yates', $this->exercises->getVisibleTo($mine->id(), $this->alex)->name());
    }

    public function testNotesAreOnePerUserAndExercise(): void
    {
        $notes = self::getContainer()->get(ExerciseNoteRepository::class);
        $bench = new ExerciseId('00000000-0000-7000-8000-0000000000e1');
        $notes->save(ExerciseNote::write(new ExerciseNoteId('00000000-0000-7000-8000-0000000000a1'), $this->alex, $bench, 'Omoplates serrées', $this->now));
        $notes->save(ExerciseNote::write(new ExerciseNoteId('00000000-0000-7000-8000-0000000000a2'), $this->sam, $bench, 'Pieds au sol', $this->now));
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::assertSame('Omoplates serrées', $notes->of($this->alex, $bench)?->text());
        self::assertSame(['00000000-0000-7000-8000-0000000000e1'], array_keys($notes->forExercises($this->sam, [$bench])));
        self::assertSame([], $notes->forExercises($this->sam, []));
        $note = $notes->of($this->sam, $bench);
        self::assertNotNull($note);
        $notes->remove($note);
        $this->entityManager->flush();
        self::assertNull($notes->of($this->sam, $bench));

        $notes->save(ExerciseNote::write(new ExerciseNoteId('00000000-0000-7000-8000-0000000000a3'), $this->alex, $bench, 'Doublon', $this->now));
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testAVersionIsStoredWholeAndProgramsAreListedActiveFirst(): void
    {
        $programs = self::getContainer()->get(ProgramRepository::class);
        $versions = self::getContainer()->get(ProgramVersionRepository::class);
        $planned = new PlannedExercise(new ExerciseId('00000000-0000-7000-8000-0000000000e1'), 4, new RepRange(6, 10), 2, 180, ProgressionStrategy::Linear, 1.25, 2, true, new Tempo('3-1-1-0'), 'Pause en bas');
        $days = [new TrainingDay('Push', [new ExerciseBlock([$planned]), new ExerciseBlock([$planned, $planned])])];
        [$ppl, $first] = $this->program('00000000-0000-7000-8000-00000000b001', 'PPL', $days);
        [$full] = $this->program('00000000-0000-7000-8000-00000000b002', 'full body', $days);
        $ppl->activate();
        $programs->save($ppl);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $versions->currentOf($ppl);
        self::assertTrue($read->hasDays($days), 'every field survives the JSON round trip');
        self::assertSame('Pause en bas', $read->day(0)->blocks[0]->exercises[0]->notes);
        self::assertSame(['PPL', 'full body'], array_map(static fn (Program $program): string => $program->name(), $programs->programsOf($this->alex)));
        self::assertSame('PPL', $programs->activeOf($this->alex)?->name());
        self::assertSame([], $programs->programsOf($this->sam));
        self::assertSame([$ppl->id()->value], array_keys($versions->currentOfEach([$ppl])));
        self::assertSame([], $versions->currentOfEach([]));
        self::assertSame([1], array_column($versions->historyOf($ppl), 'number'));
        self::assertSame($first->id()->value, $versions->numbered($ppl, 1)->id()->value);
        try {
            $versions->numbered($ppl, 2);
            self::fail('no version 2');
        } catch (ProgramNotFound) {
            self::addToAssertionCount(1);
        }
        self::assertSame('full body', $programs->getVisibleTo($full->id(), $this->alex)->name());
    }

    public function testActivatingAnotherProgramPassesTheOneActiveIndex(): void
    {
        $days = [new TrainingDay('Push', [new ExerciseBlock([new PlannedExercise(new ExerciseId('00000000-0000-7000-8000-0000000000e1'), 3, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression)])])];
        [$ppl] = $this->program('00000000-0000-7000-8000-00000000b001', 'PPL', $days);
        [$full] = $this->program('00000000-0000-7000-8000-00000000b002', 'Full Body', $days);
        $ppl->activate();
        $this->entityManager->flush();
        $this->entityManager->clear();

        self::getContainer()->get(CommandBus::class)->dispatch(new ActivateProgram($full->id(), $this->alex));
        $this->entityManager->clear();
        self::assertSame('Full Body', self::getContainer()->get(ProgramRepository::class)->activeOf($this->alex)?->name());

        $programs = self::getContainer()->get(ProgramRepository::class);
        $ppl = $programs->getVisibleTo($ppl->id(), $this->alex);
        $ppl->activate();
        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    /**
     * @param list<TrainingDay> $days
     *
     * @return array{Program, ProgramVersion}
     */
    private function program(string $id, string $name, array $days): array
    {
        $programId = new ProgramId($id);
        $version = ProgramVersion::first(new ProgramVersionId(substr($id, 0, -4).'c'.substr($id, -3)), $programId, $days, $this->now);
        $program = Program::create($programId, $this->alex, $name, $version, $this->now);
        self::getContainer()->get(ProgramVersionRepository::class)->save($version);
        self::getContainer()->get(ProgramRepository::class)->save($program);

        return [$program, $version];
    }

    /**
     * @param list<Muscle> $secondary
     */
    private static function spec(string $name, Muscle $primary, array $secondary): ExerciseSpec
    {
        return new ExerciseSpec($name, $primary, $secondary, ExerciseType::Compound, Equipment::Barbell, Measurement::Weight, new ExerciseDefaults(new RepRange(8, 12), 2, 120));
    }
}
