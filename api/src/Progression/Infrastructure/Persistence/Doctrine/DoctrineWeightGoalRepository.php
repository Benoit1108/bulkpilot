<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine;

use App\Progression\Domain\Goal\WeightGoal;
use App\Progression\Domain\Goal\WeightGoalRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWeightGoalRepository implements WeightGoalRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(WeightGoal $goal): void
    {
        $this->entityManager->persist($goal);
        // An ended goal is written at once: the goal set after it becomes the current one, and
        // Doctrine inserts before it updates (the one-current index would refuse it). Still in the
        // command's transaction.
        if (null !== $goal->endedAt()) {
            $this->entityManager->flush();
        }
    }

    public function current(UserId $userId): ?WeightGoal
    {
        /** @var WeightGoal|null */
        return $this->entityManager->getRepository(WeightGoal::class)->findOneBy(['ownerId' => $userId, 'endedAt' => null]);
    }
}
