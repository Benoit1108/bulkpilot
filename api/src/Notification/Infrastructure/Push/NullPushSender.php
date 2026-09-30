<?php

declare(strict_types=1);

namespace App\Notification\Infrastructure\Push;

use App\Notification\Application\Port\PushSender;
use App\Notification\Domain\Notification\Notification;

/**
 * No push yet (spec §78): notifications stay in the app. A Web Push sender replaces this one.
 */
final readonly class NullPushSender implements PushSender
{
    public function send(Notification $notification): void
    {
    }
}
