<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Event;

use App\Shared\Domain\Event\DomainEvent;

interface EventBus
{
    /**
     * Publishes events to their subscribers. When called while a command is being handled,
     * delivery is deferred until that command has been handled successfully.
     */
    public function publish(DomainEvent ...$events): void;
}
