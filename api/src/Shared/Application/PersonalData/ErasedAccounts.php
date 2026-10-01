<?php

declare(strict_types=1);

namespace App\Shared\Application\PersonalData;

use App\Shared\Domain\Identifier\UserId;

/**
 * The accounts erased since the backups still kept were taken (ADR-0031), implemented next to
 * the backups: after a restore, each is erased again, so that no erased account comes back.
 */
interface ErasedAccounts
{
    /** Remembers an erasure once it committed; nothing when backups are off. */
    public function remember(UserId $userId, \DateTimeImmutable $erasedAt): void;

    /**
     * @return list<UserId> every account erased since the oldest backup kept
     */
    public function all(): array;
}
