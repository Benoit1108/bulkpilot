<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exception\InvalidExercise;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseRepository;
use Psr\Clock\ClockInterface;

final readonly class CreateExerciseHandler implements CommandHandler
{
    public function __construct(private ExerciseRepository $exercises, private ClockInterface $clock)
    {
    }

    /** Far more than anyone needs; keeps a runaway client from filling the table. */
    public const int MAX_PER_USER = 300;

    public function __invoke(CreateExercise $command): void
    {
        if ($this->exercises->countCustomOf($command->userId) >= self::MAX_PER_USER) {
            throw InvalidExercise::because(\sprintf('at most %d exercises of your own', self::MAX_PER_USER));
        }
        $this->exercises->save(Exercise::createCustom($command->exerciseId, $command->userId, $command->details->spec(), $this->clock->now()));
    }
}
