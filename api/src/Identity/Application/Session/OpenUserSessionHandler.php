<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Session\UserSession;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * Every way of signing in ends here: signing in during the deletion delay keeps the account.
 */
final readonly class OpenUserSessionHandler implements CommandHandler
{
    public function __construct(
        private UserSessionRepository $sessions,
        private UserRepository $users,
        private EventBus $eventBus,
        private AuditTrail $audit,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(OpenUserSession $command): void
    {
        $now = $this->clock->now();
        $user = $this->users->get($command->userId);
        if ($user->isDeletionScheduled()) {
            $user->cancelDeletion($now);
            $this->users->save($user);
            $this->eventBus->publish(...$user->pullDomainEvents());
        }
        $this->sessions->save(UserSession::open($command->sessionId, $command->userId, $command->deviceLabel, $now));
        $this->audit->record($command->userId, 'identity.signed_in', ['device' => $command->deviceLabel], 'user', $now);
    }
}
