<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Query\QueryHandler;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use App\Training\Domain\Exercise\ExerciseRepository;

final readonly class GetExerciseHandler implements QueryHandler
{
    public function __construct(private ExerciseRepository $exercises, private ExerciseNoteRepository $notes)
    {
    }

    public function __invoke(GetExercise $query): ExerciseView
    {
        $exercise = $this->exercises->getVisibleTo($query->exerciseId, $query->userId);

        return ExerciseView::of($exercise, $this->notes->of($query->userId, $exercise->id()), $query->userId);
    }
}
