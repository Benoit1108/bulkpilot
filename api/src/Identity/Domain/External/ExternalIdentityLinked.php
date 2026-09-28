<?php

declare(strict_types=1);

namespace App\Identity\Domain\External;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

final readonly class ExternalIdentityLinked implements DomainEvent
{
    public function __construct(public UserId $userId, public IdentityProvider $provider, private \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
