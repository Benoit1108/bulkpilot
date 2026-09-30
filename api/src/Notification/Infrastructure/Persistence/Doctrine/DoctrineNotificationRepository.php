<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Persistence\Doctrine;

use App\Notification\Domain\Exception\NotificationNotFound;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationId;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Domain\Identifier\UserId;
use App\Shared\Infrastructure\Persistence\Doctrine\LatestFirst;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineNotificationRepository implements NotificationRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function save(Notification $notification): void
    {
        $this->entityManager->persist($notification);
    }

    public function getOwnedBy(NotificationId $id, UserId $userId): Notification
    {
        $notification = $this->entityManager->find(Notification::class, $id);
        if (!$notification instanceof Notification || !$notification->ownerId()->equals($userId)) {
            throw NotificationNotFound::withId($id->value);
        }

        return $notification;
    }

    public function find(NotificationId $id): ?Notification
    {
        return $this->entityManager->find(Notification::class, $id);
    }

    public function existingKeys(UserId $userId, array $keys): array
    {
        if ([] === $keys) {
            return [];
        }

        /** @var list<string> */
        return $this->entityManager->createQueryBuilder()
            ->select('n.dedupKey')->from(Notification::class, 'n')
            ->where('n.ownerId = :owner')->andWhere('n.dedupKey IN (:keys)')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('keys', $keys)
            ->getQuery()->getSingleColumnResult();
    }

    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?NotificationId $beforeId = null): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('n')->from(Notification::class, 'n')
            ->where('n.ownerId = :owner')
            ->setParameter('owner', $userId, 'user_id');

        /** @var list<Notification> */
        return LatestFirst::page($query, 'n', $limit, $before, $beforeId, 'notification_id')->getQuery()->getResult();
    }

    public function countUnread(UserId $userId): int
    {
        return (int) $this->entityManager->createQueryBuilder()
            ->select('COUNT(n.id)')->from(Notification::class, 'n')
            ->where('n.ownerId = :owner')->andWhere('n.readAt IS NULL')
            ->setParameter('owner', $userId, 'user_id')
            ->getQuery()->getSingleScalarResult();
    }

    public function markAllRead(UserId $userId, \DateTimeImmutable $now): void
    {
        $this->entityManager->createQueryBuilder()
            ->update(Notification::class, 'n')->set('n.readAt', ':now')
            ->where('n.ownerId = :owner')->andWhere('n.readAt IS NULL')
            ->setParameter('owner', $userId, 'user_id')
            ->setParameter('now', $now, 'datetimetz_immutable')
            ->getQuery()->execute();
    }
}
