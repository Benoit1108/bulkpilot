<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure\Security;

use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * What Symfony Security keeps in the HTTP session: the user and the device (UserSession)
 * the session belongs to. Deliberately not the domain User.
 */
final readonly class SecurityUser implements UserInterface, EquatableInterface
{
    /**
     * @param non-empty-string $userId
     * @param non-empty-string $sessionId
     */
    public function __construct(public string $userId, public string $sessionId)
    {
    }

    public function getUserIdentifier(): string
    {
        return $this->userId;
    }

    public function getRoles(): array
    {
        return ['ROLE_USER'];
    }

    public function isEqualTo(UserInterface $user): bool
    {
        return $user instanceof self && $user->userId === $this->userId && $user->sessionId === $this->sessionId;
    }
}
