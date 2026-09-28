<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Exception\FoodNotFound;
use App\Nutrition\Domain\Food\Food;
use App\Nutrition\Domain\Food\FoodId;
use App\Nutrition\Domain\Food\FoodRepository;
use App\Nutrition\Domain\Food\FoodSource;
use App\Nutrition\Domain\Food\SearchKey;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFoodRepository implements FoodRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Food $food): void
    {
        $this->entityManager->persist($food);
    }

    public function getVisibleTo(FoodId $id, UserId $userId): Food
    {
        $food = $this->entityManager->find(Food::class, $id);
        if (null === $food || !$food->isVisibleTo($userId)) {
            throw FoodNotFound::withId($id->value);
        }

        return $food;
    }

    public function catalogue(FoodSource $source): array
    {
        $foods = [];
        foreach ($this->entityManager->getRepository(Food::class)->findBy(['source' => $source]) as $food) {
            $foods[(string) $food->sourceReference()] = $food;
        }

        return $foods;
    }

    public function fromCatalogue(FoodSource $source, array $references): array
    {
        if ([] === $references) {
            return [];
        }
        $found = [];
        foreach ($this->entityManager->getRepository(Food::class)->findBy(['source' => $source, 'sourceReference' => $references]) as $food) {
            $found[(string) $food->sourceReference()] = $food;
        }

        return array_values(array_filter(array_map(static fn (string $reference): ?Food => $found[$reference] ?? null, $references)));
    }

    /**
     * Every word must appear (served by the trigram index); ranking favours the user's own foods,
     * then names starting with the query, then trigram similarity, then shorter (more generic) names.
     */
    public function search(UserId $userId, string $searchKey, int $limit): array
    {
        $words = SearchKey::words($searchKey);
        if ([] === $words) {
            return [];
        }
        $conditions = [];
        $parameters = ['user' => $userId->value, 'key' => $searchKey, 'prefix' => self::escapeLike($searchKey).'%', 'limit' => $limit];
        foreach ($words as $i => $word) {
            $conditions[] = "search_key LIKE :w{$i}";
            $parameters["w{$i}"] = '%'.self::escapeLike($word).'%';
        }

        $ids = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT id FROM foods
             WHERE deleted_at IS NULL AND (owner_id IS NULL OR owner_id = :user) AND '.implode(' AND ', $conditions).'
             ORDER BY owner_id IS NOT NULL DESC, search_key LIKE :prefix DESC, similarity(search_key, :key) DESC, length(search_key), id
             LIMIT :limit',
            $parameters,
        );

        return $this->visibleAmong($userId, array_map(static fn (mixed $id): FoodId => new FoodId(\is_string($id) ? $id : ''), $ids));
    }

    public function visibleAmong(UserId $userId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Food> $foods */
        $foods = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(Food::class, 'f')
            ->where('f.id IN (:ids)')
            ->setParameter('ids', array_map(static fn (FoodId $id): string => $id->value, $ids))
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($foods as $food) {
            if ($food->isVisibleTo($userId)) {
                $byId[$food->id()->value] = $food;
            }
        }

        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id->value])) {
                $ordered[] = $byId[$id->value];
            }
        }

        return $ordered;
    }

    public function composing(UserId $userId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Food> $foods */
        $foods = $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(Food::class, 'f')
            ->where('f.id IN (:ids)')
            ->andWhere('f.ownerId IS NULL OR f.ownerId = :owner')
            ->setParameter('ids', array_map(static fn (FoodId $id): string => $id->value, $ids))
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();

        $byId = [];
        foreach ($foods as $food) {
            $byId[$food->id()->value] = $food;
        }

        return $byId;
    }

    public function customFoodsOf(UserId $userId): array
    {
        /** @var list<Food> */
        return $this->entityManager->createQueryBuilder()
            ->select('f')
            ->from(Food::class, 'f')
            ->where('f.ownerId = :owner')
            ->andWhere('f.deletedAt IS NULL')
            ->orderBy('f.searchKey')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }

    private static function escapeLike(string $text): string
    {
        return addcslashes($text, '%_\\');
    }
}
