<?php

declare(strict_types=1);

namespace App\Tests\Integration\Shared\Infrastructure\Bus\Fixture;

use App\Shared\Domain\Event\DomainEvent;

final readonly class ProbeHappened implements DomainEvent
{
    public function __construct(public ProbeCommand $command)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-09-28 10:00:00');
    }
}
