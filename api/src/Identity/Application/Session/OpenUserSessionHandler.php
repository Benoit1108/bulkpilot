<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

final readonly class OpenUserSessionHandler implements CommandHandler
{
    public function __construct(private UserSessionRepository $sessions, private ClockInterface $clock)
    {
    }

    public function __invoke(OpenUserSession $command): void
    {
        $this->sessions->save(UserSession::open($command->sessionId, $command->userId, $command->deviceLabel, $this->clock->now()));
    }
}
