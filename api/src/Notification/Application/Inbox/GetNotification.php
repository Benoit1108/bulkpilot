<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<NotificationView>
 */
final readonly class GetNotification implements Query
{
    public function __construct(public NotificationId $notificationId, public UserId $userId)
    {
    }
}
