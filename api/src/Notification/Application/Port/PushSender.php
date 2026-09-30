<?php

declare(strict_types=1);

namespace App\Notification\Application\Port;

use App\Notification\Domain\Notification\Notification;

/**
 * Where a notification goes besides the app (spec §78: push is not in the first MVP, the
 * architecture is ready for it). Called after the notification is committed, outside the quiet
 * hours, from the asynchronous worker: at least once — a sender dedups on the notification id.
 */
interface PushSender
{
    public function send(Notification $notification): void;
}
