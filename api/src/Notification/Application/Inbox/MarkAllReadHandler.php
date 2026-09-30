<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class MarkAllReadHandler implements CommandHandler
{
    public function __construct(private NotificationRepository $notifications, private ClockInterface $clock)
    {
    }

    public function __invoke(MarkAllRead $command): void
    {
        $this->notifications->markAllRead($command->userId, $this->clock->now());
    }
}
