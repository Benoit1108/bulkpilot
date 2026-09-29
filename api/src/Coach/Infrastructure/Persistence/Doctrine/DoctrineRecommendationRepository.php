<?php

declare(strict_types=1);

namespace App\Coach\Infrastructure\Persistence\Doctrine;

use App\Coach\Domain\Exception\RecommendationNotFound;
use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationId;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Coach\Domain\Recommendation\Status;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineRecommendationRepository implements RecommendationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Recommendation $recommendation): void
    {
        $this->entityManager->persist($recommendation);
    }

    public function getOwnedBy(RecommendationId $id, UserId $userId): Recommendation
    {
        $recommendation = $this->entityManager->find(Recommendation::class, $id);
        if (!$recommendation instanceof Recommendation || !$recommendation->ownerId()->equals($userId)) {
            throw RecommendationNotFound::because(\sprintf('No recommendation %s.', $id->value));
        }

        return $recommendation;
    }

    public function openOf(UserId $userId): array
    {
        /** @var list<Recommendation> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(Recommendation::class, 'r')
            ->where('r.ownerId = :owner')->andWhere('r.status IN (:open)')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('open', [Status::Pending->value, Status::Postponed->value])
            ->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC')
            ->getQuery()->getResult();
    }

    public function decidedSince(UserId $userId, \DateTimeImmutable $since): array
    {
        /** @var list<Recommendation> */
        return $this->entityManager->createQueryBuilder()
            ->select('r')->from(Recommendation::class, 'r')
            ->where('r.ownerId = :owner')->andWhere('r.status IN (:decided)')->andWhere('r.decidedAt >= :since')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('decided', [Status::Accepted->value, Status::Rejected->value])
            ->setParameter('since', $since, 'datetimetz_immutable')
            ->getQuery()->getResult();
    }

    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?string $beforeId = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('r')->from(Recommendation::class, 'r')
            ->where('r.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id')
            ->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit);
        if (null !== $before && null !== $beforeId) {
            $query->andWhere('r.createdAt < :before OR (r.createdAt = :before AND r.id < :beforeId)')
                ->setParameter('before', $before, 'datetimetz_immutable')
                ->setParameter('beforeId', new RecommendationId($beforeId), 'recommendation_id');
        } elseif (null !== $before) {
            $query->andWhere('r.createdAt < :before')->setParameter('before', $before, 'datetimetz_immutable');
        }

        /** @var list<Recommendation> */
        return $query->getQuery()->getResult();
    }
}
