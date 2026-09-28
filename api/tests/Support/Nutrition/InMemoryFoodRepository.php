<?php

declare(strict_types=1);

namespace App\Tests\Support\Nutrition;

use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Food\Barcode;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\SearchKey;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryFoodRepository implements FoodRepository
{
    /** @var array<string, Food> */
    public array $foods = [];

    public function save(Food $food): void
    {
        $this->foods[$food->id()->value] = $food;
    }

    public function getVisibleTo(FoodId $id, UserId $userId): Food
    {
        $food = $this->foods[$id->value] ?? null;
        if (null === $food || !$food->isVisibleTo($userId)) {
            throw FoodNotFound::withId($id->value);
        }

        return $food;
    }

    public function catalogue(FoodSource $source): array
    {
        $foods = [];
        foreach ($this->foods as $food) {
            if ($food->source() === $source) {
                $foods[(string) $food->sourceReference()] = $food;
            }
        }

        return $foods;
    }

    public function fromCatalogue(FoodSource $source, array $references): array
    {
        $catalogue = $this->catalogue($source);

        return array_values(array_filter(array_map(static fn (string $reference): ?Food => $catalogue[$reference] ?? null, $references)));
    }

    public function search(UserId $userId, string $searchKey, int $limit): array
    {
        $found = array_filter($this->foods, static function (Food $food) use ($userId, $searchKey): bool {
            $key = SearchKey::of($food->name(), $food->brand() ?? '');
            foreach (SearchKey::words($searchKey) as $word) {
                if (!str_contains($key, $word)) {
                    return false;
                }
            }

            return $food->isVisibleTo($userId);
        });

        return \array_slice(array_values($found), 0, $limit);
    }

    public function visibleAmong(UserId $userId, array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $food = $this->foods[$id->value] ?? null;
            if (null !== $food && $food->isVisibleTo($userId)) {
                $found[] = $food;
            }
        }

        return $found;
    }

    public function composing(UserId $userId, array $ids): array
    {
        $found = [];
        foreach ($ids as $id) {
            $food = $this->foods[$id->value] ?? null;
            if (null !== $food && (null === $food->ownerId() || $food->ownerId()->equals($userId))) {
                $found[$id->value] = $food;
            }
        }

        return $found;
    }

    public function findByBarcode(UserId $userId, Barcode $barcode): ?Food
    {
        $shared = null;
        foreach ($this->foods as $food) {
            if (null !== $food->barcode() && $food->barcode()->equals($barcode) && $food->isVisibleTo($userId)) {
                if (null !== $food->ownerId()) {
                    return $food;
                }
                $shared = $food;
            }
        }

        return $shared;
    }

    public function findSharedByBarcode(Barcode $barcode): ?Food
    {
        return $this->catalogue(FoodSource::OpenFoodFacts)[$barcode->value] ?? null;
    }

    public function customFoodsOf(UserId $userId): array
    {
        return array_values(array_filter($this->foods, static fn (Food $food): bool => $food->isEditableBy($userId)));
    }
}
