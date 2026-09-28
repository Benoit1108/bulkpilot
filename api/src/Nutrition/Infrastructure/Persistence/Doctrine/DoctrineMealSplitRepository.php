<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Target\MealSplit;
use App\Nutrition\Domain\Target\MealSplitRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineMealSplitRepository implements MealSplitRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(MealSplit $split): void
    {
        $this->entityManager->persist($split);
    }

    public function find(UserId $userId): ?MealSplit
    {
        return $this->entityManager->find(MealSplit::class, $userId);
    }

    public function splitOf(UserId $userId): MealSplit
    {
        return $this->find($userId) ?? MealSplit::defaultFor($userId);
    }
}
