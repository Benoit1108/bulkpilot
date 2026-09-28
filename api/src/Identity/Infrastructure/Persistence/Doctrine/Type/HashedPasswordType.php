<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\User\HashedPassword;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;

final class HashedPasswordType extends StringType
{
    public const string NAME = 'hashed_password';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?HashedPassword
    {
        return \is_string($value) && '' !== $value ? new HashedPassword($value) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || $value instanceof HashedPassword) {
            return $value?->value;
        }

        throw InvalidType::new($value, self::class, ['null', HashedPassword::class]);
    }
}
