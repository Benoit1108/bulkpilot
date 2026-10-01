<?php

declare(strict_types=1);

namespace App\Identity\Application\PasswordReset;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Session\SessionTerminator;
use App\Identity\Application\Token\OneTimeLinks;
use App\Identity\Domain\Token\TokenPurpose;
use App\Identity\Domain\User\PasswordPolicy;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * Whoever knew the old password is signed out everywhere.
 */
final readonly class ResetPasswordHandler implements CommandHandler
{
    public function __construct(
        private OneTimeLinks $links,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private SessionTerminator $sessions,
        private EventBus $eventBus,
        private AuditTrail $audit,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        PasswordPolicy::assertAcceptable($command->newPassword);

        $now = $this->clock->now();
        $user = $this->users->get($this->links->redeem(TokenPurpose::PasswordReset, $command->token, $now));

        $user->changePassword($this->passwordHasher->hash($command->newPassword), $now);
        $this->users->save($user);
        $this->sessions->terminateAll($user->id(), except: null, now: $now);
        $this->audit->record($user->id(), 'identity.password_reset', [], 'user', $now);

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
