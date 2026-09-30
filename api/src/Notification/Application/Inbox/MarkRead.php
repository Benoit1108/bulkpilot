<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class MarkRead implements Command
{
    public function __construct(public NotificationId $notificationId, public UserId $userId)
    {
    }
}
