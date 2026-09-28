<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Event\DomainEvent;

final class SpyEventBus implements EventBus
{
    /** @var list<DomainEvent> */
    public array $published = [];

    public function publish(DomainEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }

    /**
     * @return list<class-string>
     */
    public function publishedClasses(): array
    {
        return array_map(static fn (DomainEvent $event): string => $event::class, $this->published);
    }
}
