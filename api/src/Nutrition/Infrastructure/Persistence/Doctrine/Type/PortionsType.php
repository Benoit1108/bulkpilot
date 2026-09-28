<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Food\Portion;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * A food's household measures, as a JSON list of {label, grams}.
 */
final class PortionsType extends JsonType
{
    public const string NAME = 'food_portions';

    /**
     * @return list<Portion>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $portions = [];
        foreach ($decoded as $portion) {
            if (!\is_array($portion) || !\is_string($portion['label'] ?? null) || !is_numeric($portion['grams'] ?? null)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }
            $portions[] = Reconstitute::of(Portion::class, ['label' => $portion['label'], 'grams' => (float) $portion['grams']]);
        }

        return $portions;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(
            array_map(static fn (Portion $portion): array => ['label' => $portion->label, 'grams' => $portion->grams], array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof Portion))),
            $platform,
        );
    }
}
