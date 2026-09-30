<?php

declare(strict_types=1);

namespace App\Identity\Application\Accounts;

use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Domain\Identifier\UserId;

/**
 * Every account and its time zone, for scheduled work (ADR-0029). A stored zone the time zone
 * database no longer knows gives the default one: scheduled work never stops on it.
 */
final readonly class IdentityAccounts implements Accounts
{
    public function __construct(private PreferencesRepository $preferences)
    {
    }

    public function all(): array
    {
        $accounts = [];
        foreach ($this->preferences->timeZones() as $userId => $timeZone) {
            $accounts[] = new AccountClock(new UserId($userId), TimeZone::orDefault($timeZone)->name);
        }

        return $accounts;
    }

    public function clockOf(UserId $userId): ?AccountClock
    {
        $timeZone = $this->preferences->timeZones($userId)[$userId->value] ?? null;

        return null === $timeZone ? null : new AccountClock($userId, TimeZone::orDefault($timeZone)->name);
    }
}
