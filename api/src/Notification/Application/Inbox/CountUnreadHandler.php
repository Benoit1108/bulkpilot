<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class CountUnreadHandler implements QueryHandler
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function __invoke(CountUnread $query): int
    {
        return $this->notifications->countUnread($query->userId);
    }
}
