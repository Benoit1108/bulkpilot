<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Persistence\Doctrine;

use App\Shared\Domain\Identifier\Uuid;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\Type;

/**
 * Maps an identifier value object to a native PostgreSQL `uuid` column.
 * One concrete subclass per identifier, registered in config/packages/doctrine.yaml.
 */
abstract class UuidType extends Type
{
    /**
     * @return class-string<Uuid>
     */
    abstract protected function identifierClass(): string;

    public function getSQLDeclaration(array $column, AbstractPlatform $platform): string
    {
        return $platform->getGuidTypeDeclarationSQL($column);
    }

    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): ?Uuid
    {
        if (null === $value || $value instanceof Uuid) {
            return $value;
        }
        if (!\is_string($value)) {
            throw ValueNotConvertible::new($value, $this->identifierClass());
        }

        $class = $this->identifierClass();

        return new $class($value);
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): ?string
    {
        if (null === $value) {
            return null;
        }
        if ($value instanceof Uuid) {
            return $value->value;
        }

        throw InvalidType::new($value, static::class, ['null', Uuid::class]);
    }
}
