<?php

declare(strict_types=1);

namespace App\Notification\Domain\Notification;

use App\Notification\Domain\Exception\NotificationNotFound;
use App\Shared\Domain\Identifier\UserId;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    /**
     * @throws NotificationNotFound also when it is someone else's
     */
    public function getOwnedBy(NotificationId $id, UserId $userId): Notification;

    public function find(NotificationId $id): ?Notification;

    /**
     * @param list<string> $keys
     *
     * @return list<string> those of the keys the user already has a notification for
     */
    public function existingKeys(UserId $userId, array $keys): array;

    /**
     * @return list<Notification> the latest first (then by id), made before the cursor if any
     */
    public function latestOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?NotificationId $beforeId = null): array;

    public function countUnread(UserId $userId): int;

    public function markAllRead(UserId $userId, \DateTimeImmutable $now): void;
}
