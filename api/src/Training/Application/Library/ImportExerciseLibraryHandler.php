<?php

declare(strict_types=1);

namespace App\Training\Application\Library;

use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Domain\Identifier\UuidGenerator;
use App\Training\Application\Port\ExerciseLibrary;
use App\Training\Domain\Exercise\Exercise;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\ExerciseRepository;
use Psr\Clock\ClockInterface;

final readonly class ImportExerciseLibraryHandler implements CommandHandler
{
    public function __construct(private ExerciseLibrary $library, private ExerciseRepository $exercises, private UuidGenerator $uuids, private ClockInterface $clock)
    {
    }

    public function __invoke(ImportExerciseLibrary $command): void
    {
        $existing = $this->exercises->library();
        foreach ($this->library->read() as $entry) {
            $exercise = $existing[$entry->key] ?? null;
            if (null === $exercise) {
                $this->exercises->save(Exercise::fromLibrary(new ExerciseId($this->uuids->generate()), $entry->key, $entry->spec, $this->clock->now()));
                ++$command->report->created;
                continue;
            }
            $exercise->refreshFromLibrary($entry->spec);
            $this->exercises->save($exercise);
            ++$command->report->updated;
        }
    }
}
