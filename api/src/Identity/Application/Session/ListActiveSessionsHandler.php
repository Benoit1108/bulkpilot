<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class ListActiveSessionsHandler implements QueryHandler
{
    public function __construct(private UserSessionRepository $sessions, private ClockInterface $clock)
    {
    }

    /**
     * @return list<SessionView>
     */
    public function __invoke(ListActiveSessions $query): array
    {
        return array_map(
            static fn (UserSession $session): SessionView => new SessionView(
                $session->id()->value,
                $session->deviceLabel(),
                $session->openedAt(),
                $session->lastSeenAt(),
                null !== $query->currentSessionId && $session->id()->equals($query->currentSessionId),
            ),
            $this->sessions->activeOf($query->userId, $this->clock->now()),
        );
    }
}
