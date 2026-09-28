<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Exception\RecipeNotFound;
use App\Nutrition\Domain\Recipe\Recipe;
use App\Nutrition\Domain\Recipe\RecipeId;
use App\Nutrition\Domain\Recipe\RecipeRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRecipeRepository implements RecipeRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Recipe $recipe): void
    {
        $this->entityManager->persist($recipe);
    }

    public function getVisibleTo(RecipeId $id, UserId $userId): Recipe
    {
        $recipe = $this->getOwnedBy($id, $userId);
        if (!$recipe->isVisibleTo($userId)) {
            throw RecipeNotFound::withId($id->value);
        }

        return $recipe;
    }

    public function getOwnedBy(RecipeId $id, UserId $userId): Recipe
    {
        $recipe = $this->entityManager->find(Recipe::class, $id);
        if (null === $recipe || !$recipe->belongsTo($userId)) {
            throw RecipeNotFound::withId($id->value);
        }

        return $recipe;
    }

    public function recipesOf(UserId $userId): array
    {
        /** @var list<Recipe> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(Recipe::class, 'r')
            ->where('r.ownerId = :owner')
            ->andWhere('r.deletedAt IS NULL')
            ->orderBy('LOWER(r.name)')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()
            ->getResult();
    }
}
