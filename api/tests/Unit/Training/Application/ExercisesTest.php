<?php

declare(strict_types=1);

namespace App\Tests\Unit\Training\Application;

use App\Shared\Domain\Identifier\UserId;
use App\Tests\Support\Training\TrainingTestCase;
use App\Training\Application\Exercise\CreateExercise;
use App\Training\Application\Exercise\CreateExerciseHandler;
use App\Training\Application\Exercise\DeleteExercise;
use App\Training\Application\Exercise\DeleteExerciseHandler;
use App\Training\Application\Exercise\EditExercise;
use App\Training\Application\Exercise\EditExerciseHandler;
use App\Training\Application\Exercise\ExerciseDetails;
use App\Training\Application\Exercise\ExerciseView;
use App\Training\Application\Exercise\GetExercise;
use App\Training\Application\Exercise\GetExerciseHandler;
use App\Training\Application\Exercise\ListExercises;
use App\Training\Application\Exercise\ListExercisesHandler;
use App\Training\Application\Exercise\WriteExerciseNote;
use App\Training\Application\Exercise\WriteExerciseNoteHandler;
use App\Training\Application\Library\ImportExerciseLibrary;
use App\Training\Application\Library\ImportExerciseLibraryHandler;
use App\Training\Application\Library\LibraryExercise;
use App\Training\Application\Port\ExerciseLibrary;
use App\Training\Domain\Exception\ExerciseNotFound;
use App\Training\Domain\Exception\InvalidExercise;
use App\Training\Domain\Exception\InvalidPrescription;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseNoteId;
use App\Training\Domain\Exercise\ExerciseSource;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;

final class ExercisesTest extends TrainingTestCase
{
    private const string MINE = '00000000-0000-7000-8000-0000000000e9';

    public function testTheLibraryImportIsIdempotentAndKeepsWhatLeftIt(): void
    {
        $library = new class implements ExerciseLibrary {
            /** @var list<LibraryExercise> */
            public array $entries = [];

            public function read(): iterable
            {
                return $this->entries;
            }
        };
        $library->entries = [new LibraryExercise('bench', self::spec()), new LibraryExercise('row', self::spec('Rowing barre', Muscle::UpperBack))];
        $import = new ImportExerciseLibraryHandler($library, $this->exercises, $this->uuids, $this->clock);

        $import($first = new ImportExerciseLibrary());
        $library->entries = [new LibraryExercise('bench', self::spec('Développé couché à la barre'))];
        $import($second = new ImportExerciseLibrary());

        self::assertSame([2, 0], [$first->report->created, $first->report->updated]);
        self::assertSame([0, 1], [$second->report->created, $second->report->updated]);
        self::assertCount(2, $this->exercises->exercises, 'an exercise that left the library stays');
        self::assertSame('Développé couché à la barre', $this->exercises->library()['bench']->name());
    }

    public function testAUserHasTheLibraryAndTheirOwnExercisesWithTheirNotes(): void
    {
        $this->libraryExercise('bench', 'Développé couché');
        $row = $this->libraryExercise('row', 'Rowing barre', Muscle::UpperBack);
        $this->create($this->alex, self::MINE, self::details('Rowing Yates'));
        $this->create($this->sam, '00000000-0000-7000-8000-0000000000ea', self::details('Rowing de Sam'));
        $this->note($this->alex, $row->id(), 'Dos plat');

        $all = $this->list($this->alex);
        self::assertSame(['Rowing Yates', 'Développé couché', 'Rowing barre'], array_map(static fn (ExerciseView $view): string => $view->name, $all), 'own ones first');
        self::assertSame([true, false, false], array_map(static fn (ExerciseView $view): bool => $view->editable, $all));
        self::assertSame('Dos plat', $all[2]->note);
        self::assertSame(ExerciseSource::Custom, $all[0]->source);

        self::assertSame(['Rowing Yates', 'Rowing barre'], array_map(static fn (ExerciseView $view): string => $view->name, $this->list($this->alex, 'ROWING')));
        self::assertSame(['Développé couché', 'Rowing barre'], array_map(static fn (ExerciseView $view): string => $view->name, $this->list($this->alex, '', Muscle::Triceps)), 'secondary muscles count');
        self::assertSame(['Rowing Yates', 'Rowing barre'], array_map(static fn (ExerciseView $view): string => $view->name, $this->list($this->alex, '', Muscle::UpperBack)));
        self::assertSame([], $this->list($this->alex, 'rowing', Muscle::Quads));
        self::assertNull($this->list($this->sam, 'rowing barre')[0]->note, 'notes are personal');
    }

    public function testAnOwnExerciseIsEditedThenSoftDeleted(): void
    {
        $this->create($this->alex, self::MINE, self::details('Rowing Yates'));
        $id = new ExerciseId(self::MINE);

        new EditExerciseHandler($this->exercises)(new EditExercise($id, $this->alex, self::details('Rowing Yates prise inversée', tempo: '2-0-1-1')));
        $view = $this->get($this->alex, $id);
        self::assertSame('Rowing Yates prise inversée', $view->name);
        self::assertSame('2-0-1-1', $view->defaults->tempo?->value);

        new DeleteExerciseHandler($this->exercises, $this->clock)(new DeleteExercise($id, $this->alex));
        self::assertSame([], $this->list($this->alex));

        $this->expectException(ExerciseNotFound::class);
        $this->get($this->alex, $id);
    }

    public function testSomeoneElsesExerciseIsNotFound(): void
    {
        $this->create($this->alex, self::MINE, self::details('Rowing Yates'));

        $this->expectException(ExerciseNotFound::class);
        new EditExerciseHandler($this->exercises)(new EditExercise(new ExerciseId(self::MINE), $this->sam, self::details('Volé')));
    }

    public function testALibraryExerciseIsReadOnly(): void
    {
        $bench = $this->libraryExercise('bench', 'Développé couché');

        $this->expectException(InvalidExercise::class);
        new EditExerciseHandler($this->exercises)(new EditExercise($bench->id(), $this->alex, self::details('Mon développé')));
    }

    public function testDetailsAreChecked(): void
    {
        $this->expectException(InvalidPrescription::class);

        $this->create($this->alex, self::MINE, self::details('Rowing', tempo: 'vite'));
    }

    public function testANoteIsWrittenRewrittenAndRemoved(): void
    {
        $bench = $this->libraryExercise('bench', 'Développé couché');

        $this->note($this->alex, $bench->id(), 'Omoplates serrées');
        $this->note($this->alex, $bench->id(), 'Omoplates serrées, pieds ancrés');
        self::assertCount(1, $this->notes->notes);
        self::assertSame('Omoplates serrées, pieds ancrés', $this->get($this->alex, $bench->id())->note);

        $this->note($this->alex, $bench->id(), '   ');
        $this->note($this->alex, $bench->id(), null);
        self::assertSame([], $this->notes->notes);
        self::assertNull($this->get($this->alex, $bench->id())->note);
    }

    public function testAUserHasAtMostSoManyExercisesOfTheirOwn(): void
    {
        for ($i = 1; $i <= CreateExerciseHandler::MAX_PER_USER; ++$i) {
            $this->create($this->alex, \sprintf('00000000-0000-7000-8000-%012d', $i), self::details('Exercice '.$i));
        }
        $this->create($this->sam, '00000000-0000-7000-8000-00000000ffff', self::details('Celui de Sam'));

        $this->expectException(InvalidExercise::class);
        $this->create($this->alex, '00000000-0000-7000-8000-00000000fffe', self::details('De trop'));
    }

    public function testNoNoteOnSomeoneElsesExercise(): void
    {
        $this->create($this->alex, self::MINE, self::details('Rowing Yates'));

        $this->expectException(ExerciseNotFound::class);
        $this->note($this->sam, new ExerciseId(self::MINE), 'Espion');
    }

    private function create(UserId $user, string $id, ExerciseDetails $details): void
    {
        new CreateExerciseHandler($this->exercises, $this->clock)(new CreateExercise(new ExerciseId($id), $user, $details));
    }

    private function note(UserId $user, ExerciseId $exercise, ?string $text): void
    {
        new WriteExerciseNoteHandler($this->exercises, $this->notes, $this->clock)(new WriteExerciseNote($user, $exercise, $text, new ExerciseNoteId($this->uuids->generate())));
    }

    /**
     * @return list<ExerciseView>
     */
    private function list(UserId $user, string $query = '', ?Muscle $muscle = null): array
    {
        return new ListExercisesHandler($this->exercises, $this->notes)(new ListExercises($user, $query, $muscle));
    }

    private function get(UserId $user, ExerciseId $id): ExerciseView
    {
        return new GetExerciseHandler($this->exercises, $this->notes)(new GetExercise($user, $id));
    }

    private static function details(string $name, ?string $tempo = null): ExerciseDetails
    {
        return new ExerciseDetails($name, Muscle::UpperBack, [Muscle::Biceps], ExerciseType::Compound, Equipment::Barbell, Measurement::Weight, 8, 12, 2, 120, $tempo, 'Buste à 45°', null);
    }
}
