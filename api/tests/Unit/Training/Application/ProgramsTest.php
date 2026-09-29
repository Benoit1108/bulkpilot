<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\TrainingTestCase;
use App\Training\Application\Program\ActivateProgram;
use App\Training\Application\Program\ActivateProgramHandler;
use App\Training\Application\Program\CreateProgram;
use App\Training\Application\Program\CreateProgramHandler;
use App\Training\Application\Program\DeactivateProgram;
use App\Training\Application\Program\DeactivateProgramHandler;
use App\Training\Application\Program\DeleteProgram;
use App\Training\Application\Program\DeleteProgramHandler;
use App\Training\Application\Program\EditProgram;
use App\Training\Application\Program\EditProgramHandler;
use App\Training\Application\Program\GetProgram;
use App\Training\Application\Program\GetProgramHandler;
use App\Training\Application\Program\ListPrograms;
use App\Training\Application\Program\ListProgramsHandler;
use App\Training\Application\Program\ProgramDetails;
use App\Training\Application\Program\ProgramExercises;
use App\Training\Application\Program\ProgramView;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exception\InvalidProgram;
use App\Training\Domain\Exception\ProgramNotFound;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\ProgressionStrategy;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;

/**
 * @phpstan-import-type Day from ProgramDetails
 */
final class ProgramsTest extends TrainingTestCase
{
    private const string PPL = '00000000-0000-7000-8000-00000000b001';
    private const string FULL_BODY = '00000000-0000-7000-8000-00000000b002';

    private Exercise $bench;
    private Exercise $row;
    private Exercise $raise;

    protected function setUp(): void
    {
        parent::setUp();
        $this->bench = $this->libraryExercise('bench', 'Développé couché');
        $this->row = $this->libraryExercise('row', 'Rowing barre', Muscle::UpperBack);
        $this->raise = $this->libraryExercise('raise', 'Élévations latérales', Muscle::SideDelts);
    }

    public function testTheFirstProgramBecomesActiveAndTheNextOnesDoNot(): void
    {
        $this->create(self::PPL, 'PPL Masse', [$this->day('Push', [[$this->bench], [$this->raise, $this->bench]]), $this->day('Pull', [[$this->row]])]);
        $this->create(self::FULL_BODY, 'Full Body', [$this->day('Full', [[$this->bench], [$this->row]])]);

        $programs = $this->list();
        self::assertSame(['PPL Masse', 'Full Body'], array_map(static fn (ProgramView $view): string => $view->name, $programs), 'the active one first');
        self::assertSame([true, false], array_map(static fn (ProgramView $view): bool => $view->active, $programs));

        $ppl = $programs[0];
        self::assertSame([1, 1], [$ppl->currentVersion, $ppl->versionNumber]);
        self::assertSame(['Push', 'Pull'], array_column($ppl->days, 'name'));
        self::assertSame(['Élévations latérales', 'Développé couché'], array_map(static fn ($exercise): string => $exercise->name, $ppl->days[0]['blocks'][1]), 'a superset, in order');
        self::assertSame([], $ppl->versions, 'no history in the list');
    }

    public function testRenamingKeepsTheVersionChangingTheDaysMakesTheNextOne(): void
    {
        $days = [$this->day('Push', [[$this->bench]])];
        $this->create(self::PPL, 'PPL', $days);

        $this->edit(self::PPL, 'PPL Masse', $days);
        self::assertSame(['PPL Masse', 1], [$this->get()->name, $this->get()->currentVersion]);

        $this->clock->modify('+2 weeks');
        $this->edit(self::PPL, 'PPL Masse', [$this->day('Push', [[$this->bench], [$this->raise]])]);
        $program = $this->get();
        self::assertSame([2, 2], [$program->currentVersion, $program->versionNumber]);
        self::assertSame([2, 1], array_column($program->versions, 'number'), 'latest first');
        self::assertSame('2026-10-12 10:00:00', $program->versionCreatedAt->format('Y-m-d H:i:s'));

        $first = $this->get(1);
        self::assertSame([2, 1], [$first->currentVersion, $first->versionNumber]);
        self::assertCount(1, $first->days[0]['blocks'], 'the first version is as it was');

        $this->expectException(ProgramNotFound::class);
        $this->get(3);
    }

    public function testAProgramUsesExercisesTheUserCanSee(): void
    {
        $theirs = Exercise::createCustom(new ExerciseId('00000000-0000-7000-8000-0000000005a1'), $this->sam, self::spec('Curl de Sam', Muscle::Biceps), $this->clock->now());
        $this->exercises->save($theirs);

        $this->expectException(ExerciseNotFound::class);
        $this->create(self::PPL, 'PPL', [$this->day('Pull', [[$this->row], [$theirs]])]);
    }

    public function testADeletedOwnExerciseStaysWhereItWasButIsNotAddedAnew(): void
    {
        $mine = Exercise::createCustom(new ExerciseId('00000000-0000-7000-8000-0000000000a9'), $this->alex, self::spec('Curl araignée', Muscle::Biceps), $this->clock->now());
        $this->exercises->save($mine);
        $this->create(self::PPL, 'PPL', [$this->day('Pull', [[$this->row], [$mine]])]);
        $mine->delete($this->clock->now());

        $this->edit(self::PPL, 'PPL', [$this->day('Pull', [[$this->row], [$mine]]), $this->day('Push', [[$this->bench]])]);
        $program = $this->get();
        self::assertSame(2, $program->currentVersion);
        self::assertFalse($program->days[0]['blocks'][1][0]->available, 'shown as deleted');
        self::assertTrue($program->days[0]['blocks'][0][0]->available);

        $this->expectException(ExerciseNotFound::class);
        $this->create(self::FULL_BODY, 'Full', [$this->day('Full', [[$mine]])]);
    }

    public function testOneProgramIsActiveAtATime(): void
    {
        $this->create(self::PPL, 'PPL', [$this->day('Push', [[$this->bench]])]);
        $this->create(self::FULL_BODY, 'Full Body', [$this->day('Full', [[$this->row]])]);

        $activate = new ActivateProgramHandler($this->programs);
        $activate(new ActivateProgram(new ProgramId(self::FULL_BODY), $this->alex));
        self::assertSame(['Full Body' => true, 'PPL' => false], $this->activity());
        $activate(new ActivateProgram(new ProgramId(self::FULL_BODY), $this->alex));
        self::assertSame(['Full Body' => true, 'PPL' => false], $this->activity(), 'activating the active one changes nothing');

        new DeactivateProgramHandler($this->programs)(new DeactivateProgram(new ProgramId(self::FULL_BODY), $this->alex));
        self::assertSame(['Full Body' => false, 'PPL' => false], $this->activity());
        $this->create('00000000-0000-7000-8000-00000000b003', 'Vacances', [$this->day('Hôtel', [[$this->bench]])]);
        self::assertTrue($this->activity()['Vacances'], 'with none active, a new program is');
    }

    public function testAnArchivedProgramIsGone(): void
    {
        $this->create(self::PPL, 'PPL', [$this->day('Push', [[$this->bench]])]);
        new DeleteProgramHandler($this->programs, $this->clock)(new DeleteProgram(new ProgramId(self::PPL), $this->alex));

        self::assertSame([], $this->list());
        self::assertCount(1, $this->versions->versions, 'its versions stay for past workouts');

        $this->expectException(ProgramNotFound::class);
        new ActivateProgramHandler($this->programs)(new ActivateProgram(new ProgramId(self::PPL), $this->alex));
    }

    public function testAUserHasAtMostSoManyPrograms(): void
    {
        for ($i = 1; $i <= CreateProgramHandler::MAX_PER_USER; ++$i) {
            $this->create(\sprintf('00000000-0000-7000-8000-%012d', $i), 'Programme '.$i, [$this->day('Full', [[$this->bench]])]);
        }

        $this->expectException(InvalidProgram::class);
        $this->create('00000000-0000-7000-8000-00000000ffff', 'De trop', [$this->day('Full', [[$this->bench]])]);
    }

    public function testSomeoneElsesProgramIsNotFound(): void
    {
        $this->create(self::PPL, 'PPL', [$this->day('Push', [[$this->bench]])]);

        $this->expectException(ProgramNotFound::class);
        new GetProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises))(new GetProgram($this->sam, new ProgramId(self::PPL)));
    }

    /**
     * @param list<Day> $days
     */
    private function create(string $id, string $name, array $days, ?UserId $user = null): void
    {
        new CreateProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises), $this->clock)(
            new CreateProgram(new ProgramId($id), new ProgramVersionId($this->uuids->generate()), $user ?? $this->alex, new ProgramDetails($name, $days)),
        );
    }

    /**
     * @param list<Day> $days
     */
    private function edit(string $id, string $name, array $days): void
    {
        new EditProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises), $this->clock)(
            new EditProgram(new ProgramId($id), new ProgramVersionId($this->uuids->generate()), $this->alex, new ProgramDetails($name, $days)),
        );
    }

    private function get(?int $version = null): ProgramView
    {
        return new GetProgramHandler($this->programs, $this->versions, new ProgramExercises($this->exercises))(new GetProgram($this->alex, new ProgramId(self::PPL), $version));
    }

    /**
     * @return list<ProgramView>
     */
    private function list(): array
    {
        return new ListProgramsHandler($this->programs, $this->versions, new ProgramExercises($this->exercises))(new ListPrograms($this->alex));
    }

    /**
     * @return array<string, bool>
     */
    private function activity(): array
    {
        $activity = [];
        foreach ($this->list() as $program) {
            $activity[$program->name] = $program->active;
        }
        ksort($activity);

        return $activity;
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
