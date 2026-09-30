<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class GetNotificationHandler implements QueryHandler
{
    public function __construct(private NotificationRepository $notifications)
    {
    }

    public function __invoke(GetNotification $query): NotificationView
    {
        return NotificationView::of($this->notifications->getOwnedBy($query->notificationId, $query->userId));
    }
}
