<?php

declare(strict_types=1);

namespace App\Tests\Integration\Training;

use App\Shared\Domain\Identifier\UserId;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Prescription\RepRange;
use App\Training\Domain\Prescription\Tempo;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Workout\ProgramDayRef;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\Workout;
use App\Training\Domain\Workout\WorkoutExercise;
use App\Training\Domain\Workout\WorkoutId;
use App\Training\Domain\Workout\WorkoutRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class WorkoutPersistenceTest extends KernelTestCase
{
    private const string BENCH = '00000000-0000-7000-8000-0000000000e1';
    private const string PLANK = '00000000-0000-7000-8000-0000000000e2';

    private EntityManagerInterface $entityManager;
    private WorkoutRepository $workouts;
    private UserId $alex;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->workouts = self::getContainer()->get(WorkoutRepository::class);
        $this->alex = new UserId('00000000-0000-7000-8000-00000000a1e7');
        $this->now = new \DateTimeImmutable();
    }

    public function testAWorkoutIsStoredWhole(): void
    {
        $day = new ProgramDayRef(new ProgramId('00000000-0000-7000-8000-00000000b001'), 'PPL', new ProgramVersionId('00000000-0000-7000-8000-00000000c001'), 3, 1, 'Pull');
        $workout = $this->workout('00000000-0000-7000-8000-00000000d001', '-2 hours', $day);
        $workout->recordSet(new SetId('00000000-0000-7000-8000-000000000501'), 0, SetKind::Effective, 82.5, 8, null, 1, $this->now->modify('-100 minutes'), $this->now);
        $workout->recordSet(new SetId('00000000-0000-7000-8000-000000000502'), 1, SetKind::Normal, null, null, 45, null, $this->now->modify('-90 minutes'), $this->now);
        $this->workouts->save($workout);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->workouts->getVisibleTo($workout->id(), $this->alex);
        self::assertSame(['Pull', 3, 1], [$read->programDay()?->dayName, $read->programDay()?->versionNumber, $read->programDay()?->dayIndex]);
        self::assertSame(['Développé couché', Measurement::Duration, 1, true], [$read->exercises()[0]->name, $read->exercises()[1]->measurement, $read->exercises()[1]->block, $read->exercises()[1]->added]);
        self::assertSame('2-0-1-0', $read->exercises()[0]->planned->tempo?->value);
        self::assertSame([SetKind::Effective, 82.5, 8, 1], [$read->sets()[0]->kind, $read->sets()[0]->loadKg, $read->sets()[0]->reps, $read->sets()[0]->rir]);
        self::assertSame(45, $read->sets()[1]->seconds);
        self::assertSame($this->now->modify('-100 minutes')->getTimestamp(), $read->sets()[0]->doneAt->getTimestamp());
        self::assertSame($read, $this->workouts->inProgressOf($this->alex));
        self::assertNull($this->workouts->find(new WorkoutId('00000000-0000-7000-8000-00000000d0ff')));
    }

    public function testHistoryHasFinishedWorkoutsWithTheExercise(): void
    {
        foreach (['00000000-0000-7000-8000-00000000d001' => '-3 days', '00000000-0000-7000-8000-00000000d002' => '-1 day'] as $id => $when) {
            $workout = $this->workout($id, $when);
            $workout->finish($this->now->modify($when)->modify('+1 hour'), $this->now);
            $this->workouts->save($workout);
        }
        $discarded = $this->workout('00000000-0000-7000-8000-00000000d003', '-2 days');
        $discarded->finish($this->now->modify('-2 days +1 hour'), $this->now);
        $discarded->discard($this->now);
        $this->workouts->save($discarded);
        $this->workouts->save($this->workout('00000000-0000-7000-8000-00000000d004', '-10 minutes'));
        $this->entityManager->flush();
        $this->entityManager->clear();

        $ids = static fn (array $workouts): array => array_map(static fn (mixed $workout): string => $workout instanceof Workout ? substr($workout->id()->value, -4) : '?', $workouts);
        self::assertSame(['d002', 'd001'], $ids($this->workouts->historyOf($this->alex, 10)));
        self::assertSame(['d001'], $ids($this->workouts->historyOf($this->alex, 10, $this->now->modify('-2 days'))));
        self::assertSame(['d002'], $ids($this->workouts->historyOf($this->alex, 1)));
        self::assertSame(['d001', 'd002'], $ids($this->workouts->historyWith($this->alex, [new ExerciseId(self::PLANK)])), 'the oldest first');
        self::assertSame(['d001', 'd002'], $ids($this->workouts->historyWith($this->alex, [new ExerciseId('00000000-0000-7000-8000-0000000000e9'), new ExerciseId(self::BENCH)])), 'any of them');
        self::assertSame(['d001'], $ids($this->workouts->historyWith($this->alex, [new ExerciseId(self::PLANK)], $this->now->modify('-2 days'))));
        self::assertSame([], $this->workouts->historyWith($this->alex, [new ExerciseId('00000000-0000-7000-8000-0000000000e9')]));
        self::assertSame([], $this->workouts->historyWith($this->alex, []));
        self::assertSame([], $this->workouts->historyOf(new UserId('00000000-0000-7000-8000-000000000511'), 10));
    }

    public function testChangesAfterLoadArePersisted(): void
    {
        $workout = $this->workout('00000000-0000-7000-8000-00000000d001', '-1 hour');
        $this->workouts->save($workout);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $loaded = $this->workouts->getVisibleTo($workout->id(), $this->alex);
        $loaded->addExercise(2, new ExerciseId('00000000-0000-7000-8000-0000000000e9'), 'Curl', Measurement::Weight, new PlannedExercise(new ExerciseId('00000000-0000-7000-8000-0000000000e9'), 3, new RepRange(10, 12), null, 60, ProgressionStrategy::FixedLoad));
        $loaded->recordSet(new SetId('00000000-0000-7000-8000-000000000501'), 2, SetKind::Normal, 12.5, 12, null, null, $this->now->modify('-30 minutes'), $this->now);
        $loaded->recordSet(new SetId('00000000-0000-7000-8000-000000000502'), 0, SetKind::Normal, 80, 8, null, null, $this->now->modify('-40 minutes'), $this->now);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $again = $this->workouts->getVisibleTo($workout->id(), $this->alex);
        $again->recordSet(new SetId('00000000-0000-7000-8000-000000000501'), 2, SetKind::Normal, 15, 10, null, null, $this->now->modify('-30 minutes'), $this->now);
        $again->removeSet(new SetId('00000000-0000-7000-8000-000000000502'));
        $again->finish($this->now, $this->now);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $read = $this->workouts->getVisibleTo($workout->id(), $this->alex);
        self::assertSame([[15.0, 10]], array_map(static fn ($set): array => [$set->loadKg, $set->reps], $read->sets()));
        self::assertSame(['d001'], array_map(static fn (Workout $found): string => substr($found->id()->value, -4), $this->workouts->historyWith($this->alex, [new ExerciseId('00000000-0000-7000-8000-0000000000e9')])), 'found by an exercise added on the go');
    }

    public function testOneWorkoutAtATimeEvenWhenRacing(): void
    {
        $this->workouts->save($this->workout('00000000-0000-7000-8000-00000000d001', '-5 minutes'));
        $this->entityManager->flush();
        $this->workouts->save($this->workout('00000000-0000-7000-8000-00000000d002', 'now'));

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    private function workout(string $id, string $when, ?ProgramDayRef $day = null): Workout
    {
        $planned = static fn (string $exercise, int $sets): PlannedExercise => new PlannedExercise(new ExerciseId($exercise), $sets, new RepRange(8, 12), 2, 120, ProgressionStrategy::DoubleProgression, tempo: new Tempo('2010'));

        return Workout::start(new WorkoutId($id), $this->alex, $day, [
            new WorkoutExercise(new ExerciseId(self::BENCH), 'Développé couché', Measurement::Weight, 0, $planned(self::BENCH, 4)),
            new WorkoutExercise(new ExerciseId(self::PLANK), 'Planche', Measurement::Duration, 1, $planned(self::PLANK, 3), true),
        ], $this->now->modify($when), $this->now);
    }
}
