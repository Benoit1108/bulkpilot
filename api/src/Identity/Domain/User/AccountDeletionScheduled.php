<?php

declare(strict_types=1);

namespace App\Identity\Domain\User;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user asked to delete the account (spec §103): closed now, erased on `$dueAt`.
 */
final readonly class AccountDeletionScheduled implements DomainEvent
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $dueAt, private \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
