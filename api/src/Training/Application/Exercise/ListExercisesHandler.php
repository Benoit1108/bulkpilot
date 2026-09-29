<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use App\Training\Domain\Exercise\ExerciseRepository;

final readonly class ListExercisesHandler implements QueryHandler
{
    /** The library has about a hundred exercises: enough for everything a filter can return. */
    public const int LIMIT = 200;

    public function __construct(private ExerciseRepository $exercises, private ExerciseNoteRepository $notes)
    {
    }

    /**
     * @return list<ExerciseView>
     */
    public function __invoke(ListExercises $query): array
    {
        $exercises = $this->exercises->search($query->userId, $query->query, $query->muscle, self::LIMIT);
        $notes = $this->notes->forExercises($query->userId, array_map(static fn (Exercise $exercise) => $exercise->id(), $exercises));

        return array_map(static fn (Exercise $exercise): ExerciseView => ExerciseView::of($exercise, $notes[$exercise->id()->value] ?? null, $query->userId), $exercises);
    }
}
