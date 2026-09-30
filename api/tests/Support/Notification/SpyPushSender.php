<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Notification\Application\Port\PushSender;
use App\Notification\Domain\Notification\Notification;

final class SpyPushSender implements PushSender
{
    /** @var list<Notification> */
    public array $sent = [];

    public function send(Notification $notification): void
    {
        $this->sent[] = $notification;
    }
}
