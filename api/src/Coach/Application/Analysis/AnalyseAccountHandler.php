<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandHandler;

/**
 * The same analysis as when the Coach is opened, not asked again: once that day. A message queued
 * before the account was closed (or retried since) finds it gone and does nothing.
 */
final readonly class AnalyseAccountHandler implements CommandHandler
{
    public function __construct(private AnalyseCoachHandler $analyse, private Accounts $accounts)
    {
    }

    public function __invoke(AnalyseAccount $command): void
    {
        if (null === $this->accounts->clockOf($command->userId)) {
            return;
        }
        ($this->analyse)(new AnalyseCoach($command->userId, $command->today, false));
    }
}
