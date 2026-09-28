<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\User\EncryptedSecret;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\TextType;

final class EncryptedSecretType extends TextType
{
    public const string NAME = 'encrypted_secret';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EncryptedSecret
    {
        return \is_string($value) && '' !== $value ? new EncryptedSecret($value) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || $value instanceof EncryptedSecret) {
            return $value?->value;
        }

        throw InvalidType::new($value, self::class, ['null', EncryptedSecret::class]);
    }
}
