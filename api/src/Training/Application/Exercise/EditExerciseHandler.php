<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exercise\ExerciseRepository;

final readonly class EditExerciseHandler implements CommandHandler
{
    public function __construct(private ExerciseRepository $exercises)
    {
    }

    public function __invoke(EditExercise $command): void
    {
        $exercise = $this->exercises->getVisibleTo($command->exerciseId, $command->userId);
        $exercise->edit($command->details->spec());
        $this->exercises->save($exercise);
    }
}
