<?php

declare(strict_types=1);

namespace App\Shared\Application\Accounts;

use App\Shared\Domain\Identifier\UserId;

/**
 * The accounts scheduled work runs for, with the time zone their day follows (ADR-0029),
 * implemented by Identity. Every zone given is a valid one.
 */
interface Accounts
{
    /**
     * @return list<AccountClock> every account that is not deleted
     */
    public function all(): array;

    /** One account's clock (a single read), null when there is no such account. */
    public function clockOf(UserId $userId): ?AccountClock;
}
