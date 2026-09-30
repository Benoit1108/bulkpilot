<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<list<NotificationView>>
 */
final readonly class ListNotifications implements Query
{
    public function __construct(public UserId $userId, public int $limit, public ?\DateTimeImmutable $before = null, public ?NotificationId $beforeId = null)
    {
    }
}
