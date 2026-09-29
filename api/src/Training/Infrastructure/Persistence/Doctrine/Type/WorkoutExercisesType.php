<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Exercise\ExerciseId;
use App\Training\Domain\Exercise\Measurement;
use App\Training\Domain\Workout\WorkoutExercise;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * The exercises a workout planned, as JSON (copies: always read with the workout).
 */
final class WorkoutExercisesType extends JsonType
{
    public const string NAME = 'workout_exercises';

    /**
     * @return list<WorkoutExercise>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }
        try {
            return array_values(array_map(static function (mixed $item): WorkoutExercise {
                if (!\is_array($item) || !\is_string($item['exerciseId'] ?? null) || !\is_string($item['name'] ?? null) || !\is_string($item['measurement'] ?? null) || !\is_int($item['block'] ?? null)) {
                    throw new \TypeError('malformed workout exercise');
                }

                return Reconstitute::of(WorkoutExercise::class, [
                    'exerciseId' => new ExerciseId($item['exerciseId']),
                    'name' => $item['name'],
                    'measurement' => Measurement::from($item['measurement']),
                    'block' => $item['block'],
                    'planned' => PlannedExerciseJson::decode($item['planned'] ?? null),
                    'added' => true === ($item['added'] ?? false),
                ]);
            }, $decoded));
        } catch (\TypeError|\ValueError) {
            throw ValueNotConvertible::new($value, self::NAME);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(array_map(static fn (mixed $exercise): array => $exercise instanceof WorkoutExercise ? [
            'exerciseId' => $exercise->exerciseId->value,
            'name' => $exercise->name,
            'measurement' => $exercise->measurement->value,
            'block' => $exercise->block,
            'planned' => PlannedExerciseJson::encode($exercise->planned),
            'added' => $exercise->added,
        ] : throw InvalidType::new($value, self::class, ['list<WorkoutExercise>']), array_values($value)), $platform);
    }
}
