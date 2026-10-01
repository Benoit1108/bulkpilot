<?php

declare(strict_types=1);

namespace App\Identity\Application\Deletion;

use App\Identity\Application\Session\SessionTerminator;
use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Application\TwoFactor\SecondFactorCheck;
use App\Identity\Domain\Exception\InvalidSecondFactorCode;
use App\Identity\Domain\Exception\ReauthenticationRequired;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * A strong confirmation (a session alone is not enough: the password, or for an account created
 * with a provider and no password, a sign-in of the last minutes), then the account is closed at once:
 * every session revoked, erased after the delay unless the user signs in again (ADR-0030).
 */
final readonly class ScheduleAccountDeletionHandler implements CommandHandler
{
    /** How recent a sign-in must be to stand for the password of an account without one. */
    public const int RECENT_SIGN_IN_MINUTES = 10;

    public function __construct(
        private UserRepository $users,
        private UserSessionRepository $userSessions,
        private PasswordConfirmation $passwordConfirmation,
        private SecondFactorCheck $secondFactor,
        private SessionTerminator $sessions,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @throws WrongCurrentPassword
     * @throws ReauthenticationRequired an account without a password, not signed in recently on this device
     * @throws InvalidSecondFactorCode  missing or wrong while two-factor authentication is on
     */
    public function __invoke(ScheduleAccountDeletion $command): void
    {
        $now = $this->clock->now();
        $user = $this->users->get($command->userId);
        if ($user->hasPassword()) {
            $this->passwordConfirmation->assert($user, $command->password);
        } else {
            $session = null === $command->sessionId ? null : $this->userSessions->find($command->sessionId);
            $since = $now->modify(\sprintf('-%d minutes', self::RECENT_SIGN_IN_MINUTES));
            if (null === $session || !$session->belongsTo($user->id()) || !$session->isActive($now) || $session->openedAt() < $since) {
                throw ReauthenticationRequired::create(self::RECENT_SIGN_IN_MINUTES);
            }
        }
        if ($user->isTwoFactorEnabled()) {
            $this->secondFactor->verify($user, $command->code ?? '', $now);
        }

        $user->scheduleDeletion($now);
        $this->sessions->terminateAll($user->id(), null, $now);
        $this->users->save($user);
        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
