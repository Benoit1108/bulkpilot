<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

use App\Nutrition\Domain\Exception\InvalidFood;
use App\Shared\Domain\Aggregate\AggregateRoot;
use App\Shared\Domain\Identifier\UserId;

/**
 * Something that can be eaten, with its composition per 100 g.
 *
 * Catalogue foods (CIQUAL) are shared and read-only; custom foods belong to one user and are
 * soft-deleted (journal entries keep their own snapshot, so history never changes).
 */
class Food extends AggregateRoot
{
    public const int MAX_PORTIONS = 10;
    public const int MAX_NAME_LENGTH = 200;
    public const int MAX_BRAND_LENGTH = 100;

    /** Optimistic lock: two devices editing the same custom food. */
    private int $version = 1;
    private string $searchKey;
    private ?\DateTimeImmutable $deletedAt = null;

    /**
     * @param list<Portion> $portions
     */
    private function __construct(
        private readonly FoodId $id,
        private readonly FoodSource $source,
        private readonly ?string $sourceReference,
        private readonly ?UserId $ownerId,
        private string $name,
        private ?string $brand,
        private ?string $category,
        private FoodState $state,
        private NutritionFacts $facts,
        private array $portions,
    ) {
        $this->searchKey = SearchKey::of($name, $brand ?? '');
    }

    public static function fromCatalogue(FoodId $id, FoodSource $source, string $reference, string $name, ?string $category, NutritionFacts $facts): self
    {
        return new self($id, $source, $reference, null, self::validName($name), null, $category, FoodState::fromName($name), $facts, []);
    }

    /**
     * @param list<Portion> $portions
     */
    public static function createCustom(FoodId $id, UserId $owner, string $name, ?string $brand, FoodState $state, NutritionFacts $facts, array $portions): self
    {
        return new self($id, FoodSource::Custom, null, $owner, self::validName($name), self::optionalText($brand), null, $state, $facts, self::validPortions($portions));
    }

    /**
     * Catalogue refresh (a new CIQUAL release): values change, identity and portions stay.
     */
    public function refreshFromCatalogue(string $name, ?string $category, NutritionFacts $facts): void
    {
        if (FoodSource::Custom === $this->source) {
            throw InvalidFood::because('custom foods are not refreshed from a catalogue');
        }
        $this->name = self::validName($name);
        $this->category = $category;
        $this->state = FoodState::fromName($name);
        $this->facts = $facts;
        $this->searchKey = SearchKey::of($this->name);
    }

    /**
     * @param list<Portion> $portions
     */
    public function edit(string $name, ?string $brand, FoodState $state, NutritionFacts $facts, array $portions): void
    {
        $this->assertCustom();
        $this->name = self::validName($name);
        $this->brand = self::optionalText($brand);
        $this->state = $state;
        $this->facts = $facts;
        $this->portions = self::validPortions($portions);
        $this->searchKey = SearchKey::of($this->name, $this->brand ?? '');
    }

    public function delete(\DateTimeImmutable $now): void
    {
        $this->assertCustom();
        $this->deletedAt ??= $now;
    }

    /**
     * Shared catalogue foods, and the user's own custom foods that are not deleted.
     */
    public function isVisibleTo(UserId $userId): bool
    {
        return null === $this->deletedAt && (null === $this->ownerId || $this->ownerId->equals($userId));
    }

    public function isEditableBy(UserId $userId): bool
    {
        return FoodSource::Custom === $this->source && $this->isVisibleTo($userId);
    }

    public function id(): FoodId
    {
        return $this->id;
    }

    public function source(): FoodSource
    {
        return $this->source;
    }

    public function sourceReference(): ?string
    {
        return $this->sourceReference;
    }

    public function ownerId(): ?UserId
    {
        return $this->ownerId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function brand(): ?string
    {
        return $this->brand;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function state(): FoodState
    {
        return $this->state;
    }

    public function facts(): NutritionFacts
    {
        return $this->facts;
    }

    /**
     * @return list<Portion>
     */
    public function portions(): array
    {
        return $this->portions;
    }

    private function assertCustom(): void
    {
        if (FoodSource::Custom !== $this->source) {
            throw InvalidFood::because('catalogue foods are read-only');
        }
    }

    private static function validName(string $name): string
    {
        $name = trim($name);
        if ('' === $name || mb_strlen($name) > self::MAX_NAME_LENGTH) {
            throw InvalidFood::because('a food name has 1 to 200 characters');
        }

        return $name;
    }

    private static function optionalText(?string $text): ?string
    {
        $text = null === $text ? null : trim($text);
        if (null !== $text && mb_strlen($text) > self::MAX_BRAND_LENGTH) {
            throw InvalidFood::because('a brand has at most 100 characters');
        }

        return '' === $text ? null : $text;
    }

    /**
     * @param list<Portion> $portions
     *
     * @return list<Portion>
     */
    private static function validPortions(array $portions): array
    {
        if (\count($portions) > self::MAX_PORTIONS) {
            throw InvalidFood::because(\sprintf('at most %d portions', self::MAX_PORTIONS));
        }

        return $portions;
    }
}
