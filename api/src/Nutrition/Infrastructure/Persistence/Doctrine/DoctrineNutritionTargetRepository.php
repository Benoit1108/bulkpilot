<?php

declare(strict_types=1);

namespace App\Nutrition\Infrastructure\Persistence\Doctrine;

use App\Nutrition\Domain\Exception\NutritionTargetNotFound;
use App\Nutrition\Domain\Target\NutritionTarget;
use App\Nutrition\Domain\Target\NutritionTargetId;
use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNutritionTargetRepository implements NutritionTargetRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(NutritionTarget $target): void
    {
        $this->entityManager->persist($target);
    }

    public function getOwnedBy(NutritionTargetId $id, UserId $userId): NutritionTarget
    {
        $target = $this->entityManager->find(NutritionTarget::class, $id);
        if (null === $target || !$target->ownerId()->equals($userId)) {
            throw NutritionTargetNotFound::withId($id->value);
        }

        return $target;
    }

    public function inForceOn(UserId $userId, \DateTimeImmutable $day): ?NutritionTarget
    {
        /** @var NutritionTarget|null */
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(NutritionTarget::class, 't')
            ->where('t.ownerId = :owner')
            ->andWhere('t.effectiveFrom <= :day')
            // The last decision wins (see NutritionTarget); time-ordered ids settle the same second.
            ->orderBy('t.setAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('day', $day, 'date_immutable')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function history(UserId $userId, int $limit): array
    {
        /** @var list<NutritionTarget> */
        return $this->entityManager->createQueryBuilder()
            ->select('t')
            ->from(NutritionTarget::class, 't')
            ->where('t.ownerId = :owner')
            ->orderBy('t.setAt', 'DESC')
            ->addOrderBy('t.id', 'DESC')
            ->setParameter('owner', $userId, 'user_id')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
