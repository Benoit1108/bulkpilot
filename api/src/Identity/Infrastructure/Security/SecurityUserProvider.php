<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use App\Identity\Domain\Session\UserSessionId;
use App\Identity\Domain\Session\UserSessionRepository;
use App\Shared\Domain\Exception\InvalidIdentifier;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Called on every authenticated request: a revoked (or unknown) device session signs the request
 * out immediately, which is how "sign out this device" and password changes take effect.
 *
 * @implements UserProviderInterface<SecurityUser>
 */
final readonly class SecurityUserProvider implements UserProviderInterface
{
    public function __construct(
        private UserSessionRepository $sessions,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {
    }

    public function refreshUser(UserInterface $user): SecurityUser
    {
        if (!$user instanceof SecurityUser) {
            throw new UnsupportedUserException(\sprintf('Unsupported user "%s".', $user::class));
        }

        try {
            $session = $this->sessions->find(new UserSessionId($user->sessionId));
        } catch (InvalidIdentifier) {
            $session = null;
        }
        $now = $this->clock->now();
        if (null === $session || !$session->isActive($now) || $session->userId()->value !== $user->userId) {
            throw new UserNotFoundException('The device session is no longer active.');
        }

        // Only the session is loaded at this point of the request: flushing writes nothing else.
        if ($session->recordActivity($now)) {
            $this->entityManager->flush();
        }

        return $user;
    }

    public function supportsClass(string $class): bool
    {
        return SecurityUser::class === $class;
    }

    public function loadUserByIdentifier(string $identifier): SecurityUser
    {
        // Users only ever enter the session through SessionAuthenticator::signIn().
        throw new UserNotFoundException('Users are not loaded by identifier.');
    }
}
