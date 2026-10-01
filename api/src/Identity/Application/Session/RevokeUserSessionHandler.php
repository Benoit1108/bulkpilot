<?php

declare(strict_types=1);

namespace App\Identity\Application\Session;

use App\Identity\Domain\Exception\SessionNotFound;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use Psr\Clock\ClockInterface;

/**
 * Someone else's session is reported as not found, exactly like a missing one.
 */
final readonly class RevokeUserSessionHandler implements CommandHandler
{
    public function __construct(private UserSessionRepository $sessions, private AuditTrail $audit, private ClockInterface $clock)
    {
    }

    public function __invoke(RevokeUserSession $command): void
    {
        $now = $this->clock->now();
        $session = $this->sessions->find($command->sessionId);
        if (null === $session || !$session->belongsTo($command->userId) || !$session->isActive($now)) {
            throw SessionNotFound::create();
        }

        $session->revoke($now);
        $this->sessions->save($session);
        if ($command->fromDeviceList) {
            $this->audit->record($command->userId, 'identity.session_revoked', ['device' => $session->deviceLabel()], 'user', $now);
        }
    }
}
