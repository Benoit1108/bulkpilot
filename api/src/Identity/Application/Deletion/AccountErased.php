<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * An account was erased (delivered after the commit, in the worker): remembered next to the
 * backups, so that a restore erases it again.
 */
final readonly class AccountErased implements DomainEvent
{
    public function __construct(public UserId $userId, public \DateTimeImmutable $occurredOn)
    {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
