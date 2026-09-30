<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Notification\Domain\Exception\NotificationNotFound;
use App\Notification\Domain\Notification\Notification;
use App\Notification\Domain\Notification\NotificationId;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Domain\Exception\ConcurrentUpdate;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryNotificationRepository implements NotificationRepository
{
    /** @var array<string, Notification> */
    private array $notifications = [];

    /** Like the unique index of the table: one notification per key and user. */
    public function save(Notification $notification): void
    {
        foreach ($this->of($notification->ownerId()) as $known) {
            if ($known->dedupKey() === $notification->dedupKey() && !$known->id()->equals($notification->id())) {
                throw ConcurrentUpdate::create();
            }
        }
        $this->notifications[$notification->id()->value] = $notification;
    }

    public function find(NotificationId $id): ?Notification
    {
        return $this->notifications[$id->value] ?? null;
    }

    public function getOwnedBy(NotificationId $id, UserId $userId): Notification
    {
        $notification = $this->notifications[$id->value] ?? null;
        if (null === $notification || !$notification->ownerId()->equals($userId)) {
            throw NotificationNotFound::withId($id->value);
        }

        return $notification;
    }

    public function existingKeys(UserId $userId, array $keys): array
    {
        return array_values(array_intersect($keys, array_map(static fn (Notification $notification): string => $notification->dedupKey(), $this->of($userId))));
    }

    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?NotificationId $beforeId = null): array
    {
        $mine = array_filter($this->of($userId), static fn (Notification $notification): bool => null === $before
            || $notification->createdAt() < $before
            || (null !== $beforeId && $notification->createdAt()->format('U') === $before->format('U') && $notification->id()->value < $beforeId->value));
        usort($mine, static fn (Notification $a, Notification $b): int => [$b->createdAt(), $b->id()->value] <=> [$a->createdAt(), $a->id()->value]);

        return \array_slice($mine, 0, $limit);
    }

    public function countUnread(UserId $userId): int
    {
        return \count(array_filter($this->of($userId), static fn (Notification $notification): bool => null === $notification->readAt()));
    }

    public function markAllRead(UserId $userId, \DateTimeImmutable $now): void
    {
        foreach ($this->of($userId) as $notification) {
            $notification->markRead($now);
        }
    }

    /**
     * @return list<Notification> in the order they were made
     */
    public function of(UserId $userId): array
    {
        return array_values(array_filter($this->notifications, static fn (Notification $notification): bool => $notification->ownerId()->equals($userId)));
    }
}
