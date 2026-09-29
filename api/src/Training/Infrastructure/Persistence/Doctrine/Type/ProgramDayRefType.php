<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Program\ProgramId;
use App\Training\Domain\Program\ProgramVersionId;
use App\Training\Domain\Workout\ProgramDayRef;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * The program day a workout followed, or null for a free workout.
 */
final class ProgramDayRefType extends JsonType
{
    public const string NAME = 'program_day';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?ProgramDayRef
    {
        $day = parent::convertToPHPValue($value, $platform);
        if (null === $day) {
            return null;
        }
        if (!\is_array($day) || !\is_string($day['programId'] ?? null) || !\is_string($day['versionId'] ?? null) || !\is_int($day['versionNumber'] ?? null) || !\is_int($day['dayIndex'] ?? null) || !\is_string($day['dayName'] ?? null)) {
            throw ValueNotConvertible::new($value, self::NAME);
        }

        return Reconstitute::of(ProgramDayRef::class, [
            'programId' => new ProgramId($day['programId']),
            'programName' => \is_string($day['programName'] ?? null) ? $day['programName'] : '',
            'versionId' => new ProgramVersionId($day['versionId']),
            'versionNumber' => $day['versionNumber'],
            'dayIndex' => $day['dayIndex'],
            'dayName' => $day['dayName'],
        ]);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if (!$value instanceof ProgramDayRef) {
            throw InvalidType::new($value, self::class, ['null', ProgramDayRef::class]);
        }

        return parent::convertToDatabaseValue([
            'programId' => $value->programId->value,
            'programName' => $value->programName,
            'versionId' => $value->versionId->value,
            'versionNumber' => $value->versionNumber,
            'dayIndex' => $value->dayIndex,
            'dayName' => $value->dayName,
        ], $platform);
    }
}
