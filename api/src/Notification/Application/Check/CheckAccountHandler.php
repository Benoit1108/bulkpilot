<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class CheckAccountHandler implements CommandHandler
{
    public function __construct(private NotificationChecker $checker, private Accounts $accounts)
    {
    }

    /** A message queued before the account was closed (or retried since) finds it gone: nothing to tell. */
    public function __invoke(CheckAccount $command): void
    {
        if (null === $this->accounts->clockOf($command->userId)) {
            return;
        }
        $this->checker->check(new AccountClock($command->userId, $command->timeZone));
    }
}
