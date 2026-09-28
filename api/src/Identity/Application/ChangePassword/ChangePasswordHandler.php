<?php

declare(strict_types=1);

namespace App\Identity\Application\ChangePassword;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Session\SessionTerminator;
use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Domain\User\PasswordPolicy;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * Requires the current password; every other device is signed out, this one stays.
 */
final readonly class ChangePasswordHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordConfirmation $passwordConfirmation,
        private PasswordHasher $passwordHasher,
        private SessionTerminator $sessions,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(ChangePassword $command): void
    {
        $user = $this->users->get($command->userId);
        $this->passwordConfirmation->assert($user, $command->currentPassword);
        PasswordPolicy::assertAcceptable($command->newPassword);

        $now = $this->clock->now();
        $user->changePassword($this->passwordHasher->hash($command->newPassword), $now);
        $this->users->save($user);
        $this->sessions->terminateAll($user->id(), except: $command->currentSessionId, now: $now);

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
