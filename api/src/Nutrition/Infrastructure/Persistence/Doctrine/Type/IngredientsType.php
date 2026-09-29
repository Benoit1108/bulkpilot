<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Recipe\Ingredient;
use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * A recipe's ingredients, as a JSON list of {foodId, grams} (always read with the recipe).
 */
final class IngredientsType extends JsonType
{
    public const string NAME = 'recipe_ingredients';

    /**
     * @return list<Ingredient>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $ingredients = [];
        foreach ($decoded as $item) {
            if (!\is_array($item) || !\is_string($item['foodId'] ?? null) || !is_numeric($item['grams'] ?? null)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }
            $ingredients[] = Reconstitute::of(Ingredient::class, ['foodId' => new FoodId($item['foodId']), 'grams' => (float) $item['grams']]);
        }

        return $ingredients;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(
            array_map(static fn (Ingredient $ingredient): array => ['foodId' => $ingredient->foodId->value, 'grams' => $ingredient->grams], array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof Ingredient))),
            $platform,
        );
    }
}
