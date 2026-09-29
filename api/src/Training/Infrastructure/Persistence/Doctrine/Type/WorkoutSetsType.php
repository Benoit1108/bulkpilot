<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Workout\SetId;
use App\Training\Domain\Workout\SetKind;
use App\Training\Domain\Workout\WorkoutSet;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * The sets of a workout, as JSON (a few dozen, always read with it).
 */
final class WorkoutSetsType extends JsonType
{
    public const string NAME = 'workout_sets';

    /**
     * @return list<WorkoutSet>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }
        try {
            return array_values(array_map(static function (mixed $item): WorkoutSet {
                if (!\is_array($item) || !\is_string($item['id'] ?? null) || !\is_int($item['exerciseIndex'] ?? null) || !\is_string($item['kind'] ?? null) || !\is_string($item['doneAt'] ?? null)) {
                    throw new \TypeError('malformed set');
                }
                $optional = static fn (string $key): mixed => $item[$key] ?? null;

                return Reconstitute::of(WorkoutSet::class, [
                    'id' => new SetId($item['id']),
                    'exerciseIndex' => $item['exerciseIndex'],
                    'kind' => SetKind::from($item['kind']),
                    'loadKg' => is_numeric($optional('loadKg')) ? (float) $optional('loadKg') : null,
                    'reps' => \is_int($optional('reps')) ? $optional('reps') : null,
                    'seconds' => \is_int($optional('seconds')) ? $optional('seconds') : null,
                    'rir' => \is_int($optional('rir')) ? $optional('rir') : null,
                    'doneAt' => new \DateTimeImmutable($item['doneAt']),
                ]);
            }, $decoded));
        } catch (\TypeError|\ValueError|\Exception) {
            throw ValueNotConvertible::new($value, self::NAME);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(array_map(static fn (mixed $set): array => $set instanceof WorkoutSet ? [
            'id' => $set->id->value,
            'exerciseIndex' => $set->exerciseIndex,
            'kind' => $set->kind->value,
            'loadKg' => $set->loadKg,
            'reps' => $set->reps,
            'seconds' => $set->seconds,
            'rir' => $set->rir,
            'doneAt' => $set->doneAt->format(\DateTimeInterface::ATOM),
        ] : throw InvalidType::new($value, self::class, ['list<WorkoutSet>']), array_values($value)), $platform);
    }
}
