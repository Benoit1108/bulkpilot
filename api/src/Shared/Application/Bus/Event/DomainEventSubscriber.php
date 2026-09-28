<?php

declare(strict_types=1);

namespace App\Shared\Application\Bus\Event;

/**
 * Marker for reactions to domain events. Implementations expose a single
 * `__invoke(SomeEvent $event): void` method. An event may have zero or many subscribers.
 */
interface DomainEventSubscriber
{
}
