<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Accounts\Accounts;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;

/**
 * One asynchronous analysis per account in the first quarter of an hour of 3 or 4 in the morning,
 * local time: the day before is over, the user is asleep. Both hours, because a spring-forward
 * night skips 3:00 in some zones (Helsinki goes from 2:59 to 4:00); the second one changes
 * nothing, the analysis runs once a day. An account that fails never stops the others.
 */
final readonly class RunNightlyAnalysesHandler implements CommandHandler
{
    /** @var list<int> */
    public const array HOURS = [3, 4];
    /** The schedule ticks within this many minutes (every quarter of an hour). */
    public const int WINDOW_MINUTES = 15;

    public function __construct(private Accounts $accounts, private CommandBus $commandBus, private ClockInterface $clock, private LoggerInterface $logger)
    {
    }

    public function __invoke(RunNightlyAnalyses $command): void
    {
        $now = $this->clock->now();
        foreach ($this->accounts->all() as $account) {
            try {
                $local = $account->now($now);
                if (\in_array((int) $local->format('G'), self::HOURS, true) && (int) $local->format('i') < self::WINDOW_MINUTES) {
                    $this->commandBus->dispatch(new AnalyseAccount($account->userId, new \DateTimeImmutable($local->format('Y-m-d'))));
                }
            } catch (\Exception $exception) {
                $this->logger->error('coach.nightly_analysis_skipped', ['user_id' => $account->userId->value, 'exception' => $exception]);
            }
        }
    }
}
