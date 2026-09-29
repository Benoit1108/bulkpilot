<?php

declare(strict_types=1);

namespace App\Training\Application\Exercise;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Training\Domain\Exercise\ExerciseNote;
use App\Training\Domain\Exercise\ExerciseNoteRepository;
use App\Training\Domain\Exercise\ExerciseRepository;
use Psr\Clock\ClockInterface;

final readonly class WriteExerciseNoteHandler implements CommandHandler
{
    public function __construct(private ExerciseRepository $exercises, private ExerciseNoteRepository $notes, private ClockInterface $clock)
    {
    }

    public function __invoke(WriteExerciseNote $command): void
    {
        $this->exercises->getVisibleTo($command->exerciseId, $command->userId);
        $note = $this->notes->of($command->userId, $command->exerciseId);
        $text = trim((string) $command->text);

        if ('' === $text) {
            if (null !== $note) {
                $this->notes->remove($note);
            }

            return;
        }
        if (null === $note) {
            $this->notes->save(ExerciseNote::write($command->noteId, $command->userId, $command->exerciseId, $text, $this->clock->now()));

            return;
        }
        $note->rewrite($text, $this->clock->now());
        $this->notes->save($note);
    }
}
