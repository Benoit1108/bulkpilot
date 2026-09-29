<?php

declare(strict_types=1);

namespace App\Training\Infrastructure\Persistence\Doctrine\Type;

use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use App\Training\Domain\Prescription\Tempo;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;

final class TempoType extends StringType
{
    public const string NAME = 'tempo';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Tempo
    {
        return \is_string($value) && '' !== $value ? Reconstitute::of(Tempo::class, ['value' => $value]) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || $value instanceof Tempo) {
            return $value?->value;
        }

        throw InvalidType::new($value, self::class, ['null', Tempo::class]);
    }
}
