<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

final readonly class UserRegistered implements DomainEvent
{
    public function __construct(
        public UserId $userId,
        public EmailAddress $email,
        private \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
