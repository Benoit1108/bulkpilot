<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class CheckNotificationsHandler implements CommandHandler
{
    public function __construct(private NotificationChecker $checker, private Accounts $accounts)
    {
    }

    public function __invoke(CheckNotifications $command): void
    {
        // An account deleted meanwhile has nothing left to be told.
        $account = $this->accounts->clockOf($command->userId);
        if (null !== $account) {
            $this->checker->check($account);
        }
    }
}
