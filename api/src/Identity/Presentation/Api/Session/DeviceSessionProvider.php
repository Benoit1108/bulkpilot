<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Session;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\Port\SessionAuthenticator;
use App\Identity\Application\Session\ListActiveSessions;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<DeviceSession>
 */
final readonly class DeviceSessionProvider implements ProviderInterface
{
    public function __construct(
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private SessionAuthenticator $sessionAuthenticator,
    ) {
    }

    /**
     * @return list<DeviceSession>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $views = $this->queryBus->ask(new ListActiveSessions($this->currentUser->id(), $this->sessionAuthenticator->currentSessionId()));

        return array_map(DeviceSession::fromView(...), $views);
    }
}
