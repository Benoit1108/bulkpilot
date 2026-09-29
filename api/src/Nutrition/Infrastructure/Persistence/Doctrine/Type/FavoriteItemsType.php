<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine\Type;

use App\Nutrition\Domain\Favorite\FavoriteItem;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Shared\Infrastructure\Persistence\Doctrine\Reconstitute;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Types\Exception\InvalidType;
use Doctrine\DBAL\Types\Exception\ValueNotConvertible;
use Doctrine\DBAL\Types\JsonType;

/**
 * A favourite meal's items, as a JSON list of {foodId|recipeId, grams}.
 */
final class FavoriteItemsType extends JsonType
{
    public const string NAME = 'favorite_items';

    /**
     * @return list<FavoriteItem>
     */
    public function convertToPHPValue(mixed $value, AbstractPlatform $platform): array
    {
        $decoded = parent::convertToPHPValue($value, $platform);
        if (!\is_array($decoded)) {
            return [];
        }

        $items = [];
        foreach ($decoded as $item) {
            if (!\is_array($item) || !is_numeric($item['grams'] ?? null)) {
                throw ValueNotConvertible::new($value, self::NAME);
            }
            $foodId = $item['foodId'] ?? null;
            $recipeId = $item['recipeId'] ?? null;
            $items[] = Reconstitute::of(FavoriteItem::class, [
                'foodId' => \is_string($foodId) ? new FoodId($foodId) : null,
                'recipeId' => \is_string($recipeId) ? new RecipeId($recipeId) : null,
                'grams' => (float) $item['grams'],
            ]);
        }

        return $items;
    }

    public function convertToDatabaseValue(mixed $value, AbstractPlatform $platform): string
    {
        if (!\is_array($value)) {
            throw InvalidType::new($value, self::class, ['array']);
        }

        return parent::convertToDatabaseValue(
            array_map(static fn (FavoriteItem $item): array => array_filter([
                'foodId' => $item->foodId?->value,
                'recipeId' => $item->recipeId?->value,
                'grams' => $item->grams,
            ], static fn (mixed $v): bool => null !== $v), array_values(array_filter($value, static fn (mixed $item): bool => $item instanceof FavoriteItem))),
            $platform,
        );
    }
}
