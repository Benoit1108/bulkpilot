<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine\Type;

use App\Identity\Domain\User\EmailAddress;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;

final class EmailAddressType extends StringType
{
    public const string NAME = 'email_address';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?EmailAddress
    {
        return \is_string($value) ? new EmailAddress($value) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || $value instanceof EmailAddress) {
            return $value?->value;
        }

        throw InvalidType::new($value, self::class, ['null', EmailAddress::class]);
    }
}
