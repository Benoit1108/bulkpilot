<?php

declare(strict_types=1);

namespace App\Identity\Application\Audit;

use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class RecordAuthenticationFailureHandler implements CommandHandler
{
    public function __construct(private AuditTrail $audit, private ClockInterface $clock)
    {
    }

    public function __invoke(RecordAuthenticationFailure $command): void
    {
        $this->audit->record($command->userId, $command->failure->value, [], 'user', $this->clock->now());
    }
}
