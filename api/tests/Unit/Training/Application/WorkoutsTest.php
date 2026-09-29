<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\InMemoryWorkoutRepository;
use App\Tests\Support\Training\TrainingTestCase;
use App\Training\Application\Program\CreateProgram;
use App\Training\Application\Program\CreateProgramHandler;
use App\Training\Application\Program\DeleteProgram;
use App\Training\Application\Program\DeleteProgramHandler;
use App\Training\Application\Program\EditProgram;
use App\Training\Application\Program\EditProgramHandler;
use App\Training\Application\Program\ProgramDetails;
use App\Training\Application\Program\ProgramExercises;
use App\Training\Application\Progress\GetExerciseProgress;
use App\Training\Application\Progress\GetExerciseProgressHandler;
use App\Training\Application\Workout\AddWorkoutExercise;
use App\Training\Application\Workout\AddWorkoutExerciseHandler;
use App\Training\Application\Workout\DiscardWorkout;
use App\Training\Application\Workout\DiscardWorkoutHandler;
use App\Training\Application\Workout\FinishWorkout;
use App\Training\Application\Workout\FinishWorkoutHandler;
use App\Training\Application\Workout\GetWorkout;
use App\Training\Application\Workout\GetWorkoutHandler;
use App\Training\Application\Workout\GetWorkoutInProgress;
use App\Training\Application\Workout\GetWorkoutInProgressHandler;
use App\Training\Application\Workout\ListWorkouts;
use App\Training\Application\Workout\ListWorkoutsHandler;
use App\Training\Application\Workout\PreviewWorkout;
use App\Training\Application\Workout\PreviewWorkoutHandler;
use App\Training\Application\Workout\RecordSet;
use App\Training\Application\Workout\RecordSetHandler;
use App\Training\Application\Workout\RemoveSet;
use App\Training\Application\Workout\RemoveSetHandler;
use App\Training\Application\Workout\StartWorkout;
use App\Training\Application\Workout\StartWorkoutHandler;
use App\Training\Application\Workout\WorkoutPlanner;
use App\Training\Application\Workout\WorkoutView;
use App\Training\Application\Workout\WorkoutViews;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exception\InvalidWorkout;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Exception\WorkoutInProgress;
use App\Training\Domain\Exception\WorkoutNotFound;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Program\PlannedExercise;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Progression\RecordKind;
use App\Training\Domain\Progression\SuggestionReason;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\WorkoutId;

/**
 * @phpstan-import-type Day from ProgramDetails
 */
final class WorkoutsTest extends TrainingTestCase
{
    private const string PPL = '00000000-0000-7000-8000-00000000b001';
    private const string MONDAY = '00000000-0000-7000-8000-00000000d001';
    private const string THURSDAY = '00000000-0000-7000-8000-00000000d002';

    private InMemoryWorkoutRepository $workouts;
    private Exercise $bench;
    private Exercise $raise;
    private Exercise $plank;

    protected function setUp(): void
    {
        parent::setUp();
        $this->workouts = new InMemoryWorkoutRepository();
        $this->bench = $this->libraryExercise('bench', 'Développé couché');
        $this->raise = $this->libraryExercise('raise', 'Élévations latérales', Muscle::SideDelts);
        $this->plank = Exercise::fromLibrary(new ExerciseId('00000000-0000-7000-8000-0000000000a5'), 'plank', self::spec('Planche', Muscle::Abs, Measurement::Duration), $this->clock->now());
        $this->exercises->save($this->plank);
        new CreateProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises), $this->clock)(new CreateProgram(
            new ProgramId(self::PPL),
            new ProgramVersionId('00000000-0000-7000-8000-00000000c001'),
            $this->alex,
            new ProgramDetails('PPL', [$this->day('Push', [[$this->bench], [$this->raise, $this->plank]]), $this->day('Pull', [[$this->raise]])]),
        ));
    }

    public function testAWorkoutCopiesTheProgramDayAndIsStartedOnce(): void
    {
        $this->start(self::MONDAY);
        $this->start(self::MONDAY, at: '+5 minutes');

        $workout = $this->get(self::MONDAY);
        self::assertSame(['Développé couché', 'Élévations latérales', 'Planche'], array_map(static fn ($exercise): string => $exercise->exercise->name, $workout->exercises));
        self::assertSame([0, 1, 1], array_map(static fn ($exercise): int => $exercise->exercise->block, $workout->exercises), 'the superset shares a block');
        self::assertSame(['Push', 1, 'PPL'], [$workout->programDay?->dayName, $workout->programDay?->versionNumber, $workout->programDay?->programName]);
        self::assertSame('10:00', $workout->startedAt?->format('H:i'), 'sent again: nothing changes');
        self::assertCount(1, $this->workouts->workouts);
        self::assertSame(self::MONDAY, $this->inProgress()?->id);

        $this->expectException(WorkoutInProgress::class);
        $this->start(self::THURSDAY);
    }

    public function testTheWorkoutFollowsTheVersionTheDeviceSaw(): void
    {
        new EditProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises), $this->clock)(new EditProgram(
            new ProgramId(self::PPL),
            new ProgramVersionId('00000000-0000-7000-8000-00000000c002'),
            $this->alex,
            new ProgramDetails('PPL', [$this->day('Push', [[$this->bench]]), $this->day('Pull', [[$this->raise]])]),
        ));

        $this->start(self::MONDAY, version: 1);
        self::assertCount(3, $this->get(self::MONDAY)->exercises, 'as in version 1');
        self::assertSame(1, $this->get(self::MONDAY)->programDay?->versionNumber);

        new DiscardWorkoutHandler($this->workouts, $this->clock)(new DiscardWorkout(new WorkoutId(self::MONDAY), $this->alex));
        $this->start(self::THURSDAY);
        self::assertCount(1, $this->get(self::THURSDAY)->exercises, 'the current version by default');
        new DiscardWorkoutHandler($this->workouts, $this->clock)(new DiscardWorkout(new WorkoutId(self::THURSDAY), $this->alex));

        $this->expectException(ProgramNotFound::class);
        $this->start('00000000-0000-7000-8000-00000000d003', version: 7);
    }

    public function testAWorkoutIdIsTheUsersOwn(): void
    {
        $this->start(self::MONDAY);

        $this->expectException(InvalidWorkout::class);
        $this->start(self::MONDAY, user: $this->sam);
    }

    public function testADayOrAProgramThatDoesNotExistIsRefused(): void
    {
        try {
            $this->start(self::MONDAY, day: 5);
            self::fail('day 6');
        } catch (InvalidProgram) {
            self::addToAssertionCount(1);
        }
        new DeleteProgramHandler($this->programs, $this->clock)(new DeleteProgram(new ProgramId(self::PPL), $this->alex));

        $this->expectException(ProgramNotFound::class);
        $this->start(self::MONDAY);
    }

    public function testAFreeWorkoutGetsItsExercisesOnTheGo(): void
    {
        $this->start(self::MONDAY, program: false);
        $add = new AddWorkoutExerciseHandler($this->workouts, $this->planner());
        $add(new AddWorkoutExercise(new WorkoutId(self::MONDAY), $this->alex, 0, $this->bench->id()));
        $add(new AddWorkoutExercise(new WorkoutId(self::MONDAY), $this->alex, 1, $this->plank->id()));
        $add(new AddWorkoutExercise(new WorkoutId(self::MONDAY), $this->alex, 1, $this->plank->id()));

        $workout = $this->get(self::MONDAY);
        self::assertNull($workout->programDay);
        self::assertSame([0, 1], array_map(static fn ($exercise): int => $exercise->exercise->block, $workout->exercises));
        self::assertTrue($workout->exercises[0]->exercise->added);
        self::assertSame([PlannedExercise::USUAL_SETS, 6, 10], [$workout->exercises[0]->exercise->planned->sets, $workout->exercises[0]->exercise->planned->repRange->min, $workout->exercises[0]->exercise->planned->repRange->max], 'its usual values');

        $this->expectException(ExerciseNotFound::class);
        $add(new AddWorkoutExercise(new WorkoutId(self::MONDAY), $this->alex, 2, new ExerciseId('00000000-0000-7000-8000-0000000000ff')));
    }

    public function testSetsAreRecordedCorrectedRemovedThenTheWorkoutFinished(): void
    {
        $this->start(self::MONDAY);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000501', 0, 80, 8);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000501', 0, 82.5, 8);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000502', 2, seconds: 45);
        self::assertCount(2, $this->get(self::MONDAY)->sets);
        self::assertSame(82.5, $this->get(self::MONDAY)->sets[0]->loadKg);

        new RemoveSetHandler($this->workouts)(new RemoveSet(new WorkoutId(self::MONDAY), $this->alex, new SetId('00000000-0000-7000-8000-000000000502')));
        self::assertCount(1, $this->get(self::MONDAY)->sets);

        $this->finish(self::MONDAY);
        self::assertNull($this->inProgress());
        self::assertNotNull($this->get(self::MONDAY)->finishedAt);

        try {
            $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000503', 2, 80, 8);
            self::fail('a load on a timed exercise');
        } catch (InvalidWorkout) {
            self::addToAssertionCount(1);
        }
        $this->expectException(InvalidWorkout::class);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000503', 9, 80, 8);
    }

    public function testEachExerciseShowsLastTimeWhatToAimForAndItsRecords(): void
    {
        $this->notes->save(ExerciseNote::write(new ExerciseNoteId('00000000-0000-7000-8000-0000000000a1'), $this->alex, $this->bench->id(), 'Omoplates serrées', $this->clock->now()));
        $this->start(self::MONDAY);
        foreach ([[80, 12], [80, 12], [80, 12]] as $i => [$load, $reps]) {
            $this->record(self::MONDAY, \sprintf('00000000-0000-7000-8000-%012d', 100 + $i), 0, $load, $reps);
        }
        $this->finish(self::MONDAY);

        $preview = new PreviewWorkoutHandler($this->planner(), $this->views())(new PreviewWorkout($this->alex, new ProgramId(self::PPL), 0));
        self::assertNull($preview->id);
        $bench = $preview->exercises[0];
        self::assertSame('Omoplates serrées', $bench->note);
        self::assertSame([80.0, 80.0, 80.0], array_map(static fn ($set): ?float => $set->loadKg, $bench->previous->sets ?? []));
        self::assertSame([SuggestionReason::IncreaseLoad, 82.5], [$bench->suggestion?->reason, $bench->suggestion?->loadKg]);
        self::assertSame(80.0, $bench->records['load']->value);
        self::assertNull($preview->exercises[1]->previous, 'never done');
        self::assertNull($preview->exercises[1]->suggestion);

        $this->clock->modify('+3 days');
        $this->start(self::THURSDAY);
        $this->record(self::THURSDAY, '00000000-0000-7000-8000-000000000201', 0, 82.5, 9);
        $today = $this->get(self::THURSDAY)->exercises[0];
        self::assertSame('2026-09-28', $today->previous?->date->format('Y-m-d'));
        self::assertSame([RecordKind::Load], array_map(static fn ($record): RecordKind => $record->kind, $today->newRecords), 'beaten while training (80 × 12 still estimates higher than 82.5 × 9)');

        $monday = $this->get(self::MONDAY)->exercises[0];
        self::assertNull($monday->previous, 'viewed later, a workout still shows what came before it');
        self::assertSame([], $monday->newRecords);
    }

    public function testHistoryAndProgressAreFinishedWorkoutsOnly(): void
    {
        $this->start(self::MONDAY);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000501', 0, 80, 10);
        $this->record(self::MONDAY, '00000000-0000-7000-8000-000000000502', 0, 60, 12, kind: SetKind::Warmup);
        $this->finish(self::MONDAY);
        $this->clock->modify('+3 days');
        $this->start(self::THURSDAY);
        $this->record(self::THURSDAY, '00000000-0000-7000-8000-000000000601', 0, 85, 8);

        $history = new ListWorkoutsHandler($this->workouts)(new ListWorkouts($this->alex));
        self::assertSame([self::MONDAY], array_map(static fn ($summary): string => $summary->id, $history), 'in progress is not history');
        self::assertSame([1, 1, 800.0, 'Push'], [$history[0]->exercises, $history[0]->workingSets, $history[0]->volumeKg, $history[0]->dayName]);
        self::assertSame([], new ListWorkoutsHandler($this->workouts)(new ListWorkouts($this->alex, 20, new \DateTimeImmutable('2026-09-01'))));

        $progress = new GetExerciseProgressHandler($this->exercises, $this->workouts)(new GetExerciseProgress($this->alex, $this->bench->id()));
        self::assertCount(1, $progress->sessions);
        self::assertSame([80.0, 106.7, 800.0, 10], [$progress->sessions[0]->topLoadKg, $progress->sessions[0]->estimatedOneRepMax, $progress->sessions[0]->volumeKg, $progress->sessions[0]->bestReps]);
        self::assertContains(RecordKind::Load, array_map(static fn ($record): RecordKind => $record->kind, $progress->records));
    }

    public function testDiscardingIsIdempotentAndTheIdCannotStartAgain(): void
    {
        $this->start(self::MONDAY);
        $discard = new DiscardWorkoutHandler($this->workouts, $this->clock);
        $discard(new DiscardWorkout(new WorkoutId(self::MONDAY), $this->alex));
        $discard(new DiscardWorkout(new WorkoutId(self::MONDAY), $this->alex));
        self::assertNull($this->inProgress());

        try {
            $discard(new DiscardWorkout(new WorkoutId(self::MONDAY), $this->sam));
            self::fail('someone else\'s');
        } catch (WorkoutNotFound) {
            self::addToAssertionCount(1);
        }
        $this->expectException(InvalidWorkout::class);
        $this->start(self::MONDAY);
    }

    public function testAWorkoutKeepsTheNamesItStartedWith(): void
    {
        $this->start(self::MONDAY);
        $this->programs->programs[self::PPL]->rename('PPL Force');

        self::assertSame('PPL', $this->get(self::MONDAY)->programDay?->programName);
    }

    public function testSomeoneElsesWorkoutIsNotFound(): void
    {
        $this->start(self::MONDAY);

        $this->expectException(WorkoutNotFound::class);
        new GetWorkoutHandler($this->workouts, $this->views())(new GetWorkout($this->sam, new WorkoutId(self::MONDAY)));
    }

    private function start(string $id, ?UserId $user = null, int $day = 0, ?int $version = null, bool $program = true, string $at = 'now'): void
    {
        new StartWorkoutHandler($this->workouts, $this->planner(), $this->clock)(new StartWorkout(new WorkoutId($id), $user ?? $this->alex, $this->clock->now()->modify($at), $program ? new ProgramId(self::PPL) : null, $version, $day));
    }

    private function record(string $workout, string $set, int $exercise, ?float $load = null, ?int $reps = null, ?int $seconds = null, SetKind $kind = SetKind::Normal): void
    {
        new RecordSetHandler($this->workouts, $this->clock)(new RecordSet(new WorkoutId($workout), $this->alex, new SetId($set), $exercise, $kind, $load, $reps, $seconds, null, $this->clock->now()));
    }

    private function finish(string $workout): void
    {
        new FinishWorkoutHandler($this->workouts, $this->clock)(new FinishWorkout(new WorkoutId($workout), $this->alex, $this->clock->now()));
    }

    private function get(string $workout): WorkoutView
    {
        return new GetWorkoutHandler($this->workouts, $this->views())(new GetWorkout($this->alex, new WorkoutId($workout)));
    }

    private function inProgress(): ?WorkoutView
    {
        return new GetWorkoutInProgressHandler($this->workouts, $this->views())(new GetWorkoutInProgress($this->alex));
    }

    private function planner(): WorkoutPlanner
    {
        return new WorkoutPlanner($this->programs, $this->versions, $this->exercises);
    }

    private function views(): WorkoutViews
    {
        return new WorkoutViews($this->workouts, $this->notes);
    }

    /**
     * @param list<list<Exercise>> $blocks
     *
     * @return Day
     */
    private function day(string $name, array $blocks): array
    {
        return ['name' => $name, 'blocks' => array_map(static fn (array $block): array => array_map(static fn (Exercise $exercise): array => [
            'exerciseId' => $exercise->id()->value,
            'sets' => 3,
            'repMin' => 8,
            'repMax' => 12,
            'targetRir' => 2,
            'restSeconds' => 120,
            'strategy' => ProgressionStrategy::DoubleProgression,
            'incrementKg' => 2.5,
            'warmupSets' => 0,
            'dropSet' => false,
            'tempo' => null,
            'notes' => null,
        ], $block), $blocks)];
    }
}
