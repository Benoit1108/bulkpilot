<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Favorite;

use App\Nutrition\Domain\Exception\InvalidFavoriteMeal;
use App\Nutrition\Domain\Journal\JournalEntry;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * A meal eaten often ("petit-déjeuner habituel"), logged again in one go. It refers to its
 * foods and recipes, so it follows their current values.
 */
class FavoriteMeal extends AggregateRoot
{
    public const int MAX_ITEMS = 30;

    /**
     * @param list<FavoriteItem> $items
     */
    private function __construct(
        private readonly FavoriteMealId $id,
        private readonly UserId $ownerId,
        private string $name,
        private array $items,
        private readonly \DateTimeImmutable $createdAt,
    ) {
    }

    /**
     * @param list<FavoriteItem> $items
     */
    public static function create(FavoriteMealId $id, UserId $owner, string $name, array $items, \DateTimeImmutable $now): self
    {
        return new self($id, $owner, self::validName($name), self::validItems($items), $now);
    }

    /**
     * From what was logged in a meal: the foods and recipes that entries came from.
     *
     * @param list<JournalEntry> $entries
     */
    public static function fromEntries(FavoriteMealId $id, UserId $owner, string $name, array $entries, \DateTimeImmutable $now): self
    {
        $items = [];
        foreach ($entries as $entry) {
            $food = $entry->food();
            if (null !== $food->foodId || null !== $food->recipeId) {
                $items[] = new FavoriteItem($food->foodId, $food->recipeId, $entry->grams());
            }
        }

        return self::create($id, $owner, $name, $items, $now);
    }

    public function rename(string $name): void
    {
        $this->name = self::validName($name);
    }

    public function belongsTo(UserId $userId): bool
    {
        return $this->ownerId->equals($userId);
    }

    public function id(): FavoriteMealId
    {
        return $this->id;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return list<FavoriteItem>
     */
    public function items(): array
    {
        return $this->items;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > 100) {
            throw InvalidFavoriteMeal::because('a favourite meal name has 1 to 100 characters');
        }

        return $name;
    }

    /**
     * @param list<FavoriteItem> $items
     *
     * @return list<FavoriteItem>
     */
    private static function validItems(array $items): array
    {
        if ([] === $items || \count($items) > self::MAX_ITEMS) {
            throw InvalidFavoriteMeal::because(\sprintf('a favourite meal has 1 to %d items', self::MAX_ITEMS));
        }

        return $items;
    }
}
