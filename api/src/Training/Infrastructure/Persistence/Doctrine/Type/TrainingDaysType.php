<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Program\ExerciseBlock;
use App\Training\Domain\Program\TrainingDay;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * The days of a program version, as JSON (a version is immutable and always read whole).
 * Rebuilt without constructors: a version written under older rules stays readable.
 */
final class TrainingDaysType extends JsonType
{
    public const string NAME = 'training_days';

    /**
     * @return list<TrainingDay>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }
        try {
            return array_values(array_map(self::day(...), $decoded));
        } catch (\TypeError|\ValueError) {
            throw ValueNotConvertible::new($value, self::NAME);
        }
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }
        $days = [];
        foreach ($value as $day) {
            if (!$day instanceof TrainingDay) {
                throw InvalidType::new($value, self::class, ['list<TrainingDay>']);
            }
            $days[] = [
                'name' => $day->name,
                'blocks' => array_map(static fn (ExerciseBlock $block): array => array_map(PlannedExerciseJson::encode(...), $block->exercises), $day->blocks),
            ];
        }

        return parent::convertToDatabaseValue($days, $platform);
    }

    private static function day(mixed $day): TrainingDay
    {
        if (!\is_array($day) || !\is_string($day['name'] ?? null) || !\is_array($day['blocks'] ?? null)) {
            throw new \TypeError('malformed day');
        }

        return Reconstitute::of(TrainingDay::class, [
            'name' => $day['name'],
            'blocks' => array_values(array_map(
                static fn (mixed $block): ExerciseBlock => Reconstitute::of(ExerciseBlock::class, ['exercises' => array_values(array_map(PlannedExerciseJson::decode(...), \is_array($block) ? $block : throw new \TypeError('malformed block')))]),
                $day['blocks'],
            )),
        ]);
    }
}
