<?php

declare(strict_types=1);

namespace App\Notification\Application\Push;

use App\Notification\Application\Port\PushSender;
use App\Notification\Domain\Notification\NotificationCreated;
use App\Notification\Domain\Notification\NotificationRepository;
use App\Shared\Application\Bus\Event\DomainEventSubscriber;

/**
 * Sends a notification besides the app once it is committed (ADR-0014: events are delivered
 * after the commit; routed to the worker, so a slow push never holds a request). One that was
 * rolled back never existed, and is never sent.
 */
final readonly class PushNotification implements DomainEventSubscriber
{
    public function __construct(private NotificationRepository $notifications, private PushSender $push)
    {
    }

    public function __invoke(NotificationCreated $event): void
    {
        $notification = $this->notifications->find($event->notificationId);
        if (null !== $notification) {
            $this->push->send($notification);
        }
    }
}
