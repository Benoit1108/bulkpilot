<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Food\Barcode;
use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\StringType;

final class BarcodeType extends StringType
{
    public const string NAME = 'barcode';

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Barcode
    {
        return \is_string($value) && '' !== $value ? Reconstitute::of(Barcode::class, ['value' => $value]) : null;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value || $value instanceof Barcode) {
            return $value?->value;
        }

        throw InvalidType::new($value, self::class, ['null', Barcode::class]);
    }
}
