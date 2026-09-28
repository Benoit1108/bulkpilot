<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Persistence\Doctrine;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Domain\Identifier\UserId;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineUserSessionRepository implements UserSessionRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(UserSession $session): void
    {
        $this->entityManager->persist($session);
    }

    public function find(UserSessionId $id): ?UserSession
    {
        return $this->entityManager->find(UserSession::class, $id);
    }

    public function activeOf(UserId $userId, \DateTimeImmutable $now): array
    {
        /** @var list<UserSession> */
        return $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(UserSession::class, 's')
            ->where('s.userId = :userId')
            ->andWhere('s.revokedAt IS NULL')
            ->andWhere('s.lastSeenAt > :idleCutoff')
            ->orderBy('s.lastSeenAt', 'DESC')
            ->setParameter('userId', $userId, 'user_id')
            ->setParameter('idleCutoff', $now->modify(\sprintf('-%d seconds', UserSession::IDLE_LIFETIME_SECONDS)), 'datetimetz_immutable')
            ->getQuery()
            ->getResult();
    }
}
