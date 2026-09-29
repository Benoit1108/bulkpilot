<?php

declare(strict_types=1);

namespace App\Progression\Infrastructure\Persistence\Doctrine;

use App\Progression\Domain\Weight\WeighIn;
use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineWeighInRepository implements WeighInRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(WeighIn $weighIn): void
    {
        $this->entityManager->persist($weighIn);
    }

    public function remove(WeighIn $weighIn): void
    {
        $this->entityManager->remove($weighIn);
    }

    public function on(UserId $userId, \DateTimeImmutable $day): ?WeighIn
    {
        /** @var WeighIn|null */
        return $this->entityManager->getRepository(WeighIn::class)->findOneBy(['ownerId' => $userId, 'day' => $day->setTime(0, 0)]);
    }

    public function first(UserId $userId): ?WeighIn
    {
        /** @var WeighIn|null */
        return $this->entityManager->getRepository(WeighIn::class)->findOneBy(['ownerId' => $userId], ['day' => 'ASC']);
    }

    public function since(UserId $userId, ?\DateTimeImmutable $day): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(WeighIn::class, 'w')
            ->where('w.ownerId = :owner')
            ->orderBy('w.day', 'ASC')
            ->setParameter('owner', $userId, 'user_id');
        if (null !== $day) {
            $query->andWhere('w.day >= :day')->setParameter('day', $day, 'date_immutable');
        }

        /** @var list<WeighIn> */
        return $query->getQuery()->getResult();
    }

    public function latest(UserId $userId, int $limit, ?\DateTimeImmutable $before = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('w')
            ->from(WeighIn::class, 'w')
            ->where('w.ownerId = :owner')
            ->orderBy('w.day', 'DESC')
            ->setParameter('owner', $userId, 'user_id')
            ->setMaxResults($limit);
        if (null !== $before) {
            $query->andWhere('w.day < :before')->setParameter('before', $before, 'date_immutable');
        }

        /** @var list<WeighIn> */
        return $query->getQuery()->getResult();
    }
}
