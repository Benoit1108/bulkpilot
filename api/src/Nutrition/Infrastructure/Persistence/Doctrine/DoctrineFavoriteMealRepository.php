<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Exception\FavoriteMealNotFound;
use App\Nutrition\Domain\Favorite\FavoriteMeal;
use App\Nutrition\Domain\Favorite\FavoriteMealId;
use App\Nutrition\Domain\Favorite\FavoriteMealRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineFavoriteMealRepository implements FavoriteMealRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(FavoriteMeal $meal): void
    {
        $this->entityManager->persist($meal);
    }

    public function remove(FavoriteMeal $meal): void
    {
        $this->entityManager->remove($meal);
    }

    public function getOwnedBy(FavoriteMealId $id, UserId $userId): FavoriteMeal
    {
        $meal = $this->entityManager->find(FavoriteMeal::class, $id);
        if (null === $meal || !$meal->belongsTo($userId)) {
            throw FavoriteMealNotFound::withId($id->value);
        }

        return $meal;
    }

    public function favoritesOf(UserId $userId): array
    {
        /** @var list<FavoriteMeal> */
        return $this->entityManager->createQueryBuilder()
            ->select('m')
            ->from(FavoriteMeal::class, 'm')
            ->where('m.ownerId = :owner')
            ->orderBy('LOWER(m.name)')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }
}
