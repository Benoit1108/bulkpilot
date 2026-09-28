<?php

declare(strict_types=1);

namespace App\Identity\Application\SignIn;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Exception\WrongCurrentPassword;
use App\Identity\Domain\User\User;

/**
 * Sensitive changes on a signed-in device ask for the password again: a borrowed or stolen
 * session alone must not be enough (guesses are throttled per account by the presentation layer).
 */
final readonly class PasswordConfirmation
{
    public function __construct(private PasswordHasher $hasher)
    {
    }

    /**
     * @throws WrongCurrentPassword
     */
    public function assert(User $user, string $password): void
    {
        if (!$this->hasher->verify($user->password(), $password)) {
            throw WrongCurrentPassword::create();
        }
    }
}
