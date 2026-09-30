<?php

declare(strict_types=1);

namespace App\Notification\Application\Inbox;

use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListNotificationsHandler implements QueryHandler
{
    public const int MAX = 50;

    public function __construct(private NotificationRepository $notifications)
    {
    }

    /**
     * @return list<NotificationView>
     */
    public function __invoke(ListNotifications $query): array
    {
        return array_map(
            NotificationView::of(...),
            $this->notifications->latestOf($query->userId, max(1, min(self::MAX, $query->limit)), $query->before, $query->beforeId),
        );
    }
}
