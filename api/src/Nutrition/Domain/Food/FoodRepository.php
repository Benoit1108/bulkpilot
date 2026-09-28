<?php

declare(strict_types=1);

namespace App\Nutrition\Domain\Food;

use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Shared\Domain\Identifier\UserId;

interface FoodRepository
{
    public function save(Food $food): void;

    /**
     * @throws FoodNotFound when it does not exist, is deleted or is someone else's
     */
    public function getVisibleTo(FoodId $id, UserId $userId): Food;

    /**
     * @return array<array-key, Food> every food of a catalogue, by source reference (numeric references become int keys)
     */
    public function catalogue(FoodSource $source): array;

    /**
     * @param list<string> $references
     *
     * @return list<Food> the catalogue foods among these references, in their order
     */
    public function fromCatalogue(FoodSource $source, array $references): array;

    /**
     * Foods visible to the user whose search key contains every word of the key, best matches
     * first (the user's own foods, then names starting with the query, then closest names).
     *
     * @return list<Food>
     */
    public function search(UserId $userId, string $searchKey, int $limit): array;

    /**
     * @param list<FoodId> $ids
     *
     * @return list<Food> the visible ones, in the order of the ids
     */
    public function visibleAmong(UserId $userId, array $ids): array;

    /**
     * Foods making up the user's recipes and favourites, deleted custom ones included (soft
     * delete keeps them usable there, only search and logging hide them).
     *
     * @param list<FoodId> $ids
     *
     * @return array<string, Food> by food id; ids that are someone else's are left out
     */
    public function composing(UserId $userId, array $ids): array;

    /**
     * The food a scanned code stands for: the user's own first (they may have corrected a
     * product), then the shared product.
     */
    public function findByBarcode(UserId $userId, Barcode $barcode): ?Food;

    /**
     * The product of the shared catalogue for this code, as confirmed from Open Food Facts.
     */
    public function findSharedByBarcode(Barcode $barcode): ?Food;

    /**
     * @return list<Food> the user's custom foods, by name
     */
    public function customFoodsOf(UserId $userId): array;
}
