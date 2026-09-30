<?php

declare(strict_types=1);

namespace App\Notification\Domain\Notification;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * A notification was made: once it is committed, it may go out besides the app (push, spec §78).
 */
final readonly class NotificationCreated implements DomainEvent
{
    public function __construct(public NotificationId $notificationId, public UserId $userId, public \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
