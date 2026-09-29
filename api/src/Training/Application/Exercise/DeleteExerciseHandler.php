<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exercise\ExerciseRepository;
use Psr\Clock\ClockInterface;

final readonly class DeleteExerciseHandler implements CommandHandler
{
    public function __construct(private ExerciseRepository $exercises, private ClockInterface $clock)
    {
    }

    public function __invoke(DeleteExercise $command): void
    {
        $exercise = $this->exercises->getVisibleTo($command->exerciseId, $command->userId);
        $exercise->delete($this->clock->now());
        $this->exercises->save($exercise);
    }
}
