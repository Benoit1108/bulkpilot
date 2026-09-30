<?php

declare(strict_types=1);

namespace App\Notification\Application\Check;

use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Log\LoggerInterface;

/**
 * Fans the scheduled checks out, one asynchronous message per account: each one is checked, and
 * retried, on its own. An account that cannot be sent out never stops the others.
 */
final readonly class RunScheduledChecksHandler implements CommandHandler
{
    public function __construct(private Accounts $accounts, private CommandBus $commandBus, private LoggerInterface $logger)
    {
    }

    public function __invoke(RunScheduledChecks $command): void
    {
        foreach ($this->accounts->all() as $account) {
            try {
                $this->commandBus->dispatch(new CheckAccount($account->userId, $account->timeZone));
            } catch (\Exception $exception) {
                $this->logger->error('notification.scheduled_check_skipped', ['user_id' => $account->userId->value, 'exception' => $exception]);
            }
        }
    }
}
