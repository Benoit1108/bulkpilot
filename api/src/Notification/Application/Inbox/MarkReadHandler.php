<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class MarkReadHandler implements CommandHandler
{
    public function __construct(private NotificationRepository $notifications, private ClockInterface $clock)
    {
    }

    public function __invoke(MarkRead $command): void
    {
        $notification = $this->notifications->getOwnedBy($command->notificationId, $command->userId);
        $notification->markRead($this->clock->now());
        $this->notifications->save($notification);
    }
}
