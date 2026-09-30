<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Accounts\AccountClock;
use App\Shared\Application\Bus\Command\CommandHandler;

final readonly class CheckAccountHandler implements CommandHandler
{
    public function __construct(private NotificationChecker $checker)
    {
    }

    public function __invoke(CheckAccount $command): void
    {
        $this->checker->check(new AccountClock($command->userId, $command->timeZone));
    }
}
