<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Training\Domain\Exercise\Muscle;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * A list of muscles, as a JSON array of their codes.
 */
final class MusclesType extends JsonType
{
    public const string NAME = 'muscles';

    /**
     * @return list<Muscle>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        return array_values(array_map(static fn (mixed $code): Muscle => (\is_string($code) ? Muscle::tryFrom($code) : null) ?? throw ValueNotConvertible::new($value, self::NAME), $decoded));
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(array_values(array_map(static fn (mixed $muscle): string => $muscle instanceof Muscle ? $muscle->value : throw InvalidType::new($value, self::class, ['list<Muscle>']), $value)), $platform);
    }
}
