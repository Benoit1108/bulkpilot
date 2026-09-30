<?php

declare(strict_types=1);

namespace App\Coach\Application\Analysis;

use App\Shared\Application\Bus\Command\CommandHandler;

/**
 * The same analysis as when the Coach is opened, not asked again: once that day.
 */
final readonly class AnalyseAccountHandler implements CommandHandler
{
    public function __construct(private AnalyseCoachHandler $analyse)
    {
    }

    public function __invoke(AnalyseAccount $command): void
    {
        ($this->analyse)(new AnalyseCoach($command->userId, $command->today, false));
    }
}
