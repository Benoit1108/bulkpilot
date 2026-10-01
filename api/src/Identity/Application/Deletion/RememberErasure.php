<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Shared\Application\Bus\Event\DomainEventSubscriber;
use App\Shared\Application\PersonalData\ErasedAccounts;

/**
 * Once the erasure committed (retried in the worker until it is written).
 */
final readonly class RememberErasure implements DomainEventSubscriber
{
    public function __construct(private ErasedAccounts $erased)
    {
    }

    public function __invoke(AccountErased $event): void
    {
        $this->erased->remember($event->userId, $event->occurredOn);
    }
}
