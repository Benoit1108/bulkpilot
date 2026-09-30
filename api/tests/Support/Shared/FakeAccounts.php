<?php

declare(strict_types=1);

namespace App\Tests\Support\Shared;

use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Domain\Identifier\UserId;

final class FakeAccounts implements Accounts
{
    /** @var list<AccountClock> */
    public array $accounts = [];

    public function add(string $userId, string $timeZone): void
    {
        $this->accounts[] = new AccountClock(new UserId($userId), $timeZone);
    }

    public function all(): array
    {
        return $this->accounts;
    }

    public function clockOf(UserId $userId): ?AccountClock
    {
        foreach ($this->accounts as $account) {
            if ($account->userId->equals($userId)) {
                return $account;
            }
        }

        return null;
    }
}
