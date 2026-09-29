<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Library;

use App\Training\Application\Library\LibraryExercise;
use App\Training\Application\Port\ExerciseLibrary;
use App\Training\Domain\Exercise\Equipment;
use App\Training\Domain\Exercise\ExerciseDefaults;
use App\Training\Domain\Exercise\ExerciseSpec;
use App\Training\Domain\Exercise\ExerciseType;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Exercise\Muscle;
use App\Training\Domain\Prescription\RepRange;

/**
 * The library shipped in exercises.json (keys are stable: renaming an exercise keeps its key).
 */
final readonly class JsonExerciseLibrary implements ExerciseLibrary
{
    public function __construct(private string $path = __DIR__.'/exercises.json')
    {
    }

    public function read(): iterable
    {
        $entries = json_decode((string) file_get_contents($this->path), true, 8, \JSON_THROW_ON_ERROR);
        if (!\is_array($entries)) {
            throw new \UnexpectedValueException('The exercise library is not a list.');
        }
        foreach ($entries as $entry) {
            yield self::exercise($entry);
        }
    }

    private static function exercise(mixed $entry): LibraryExercise
    {
        if (!\is_array($entry)) {
            throw new \UnexpectedValueException('A library exercise is an object.');
        }
        $string = static fn (string $key): string => \is_string($entry[$key] ?? null) ? $entry[$key] : throw new \UnexpectedValueException(\sprintf('Library exercise without "%s".', $key));
        $int = static fn (string $key): int => \is_int($entry[$key] ?? null) ? $entry[$key] : throw new \UnexpectedValueException(\sprintf('Library exercise without "%s".', $key));
        $secondary = \is_array($entry['secondaryMuscles'] ?? null) ? $entry['secondaryMuscles'] : [];

        return new LibraryExercise($string('key'), new ExerciseSpec(
            $string('name'),
            Muscle::from($string('primaryMuscle')),
            array_values(array_map(static fn (mixed $code): Muscle => Muscle::from(\is_string($code) ? $code : ''), $secondary)),
            ExerciseType::from($string('type')),
            Equipment::from($string('equipment')),
            Measurement::from($string('measurement')),
            new ExerciseDefaults(new RepRange($int('repMin'), $int('repMax')), \is_int($entry['targetRir'] ?? null) ? $entry['targetRir'] : null, $int('restSeconds')),
        ));
    }
}
