<?php

declare(strict_types=1);

namespace App\Identity\Application\SignIn;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Domain\Exception\InvalidCredentials;
use App\Identity\Domain\Exception\InvalidEmailAddress;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use App\Shared\Domain\Identifier\UserId;

/**
 * Unknown email and wrong password are indistinguishable, including in response time.
 */
final readonly class CheckCredentialsHandler implements QueryHandler
{
    public function __construct(private UserRepository $users, private PasswordHasher $passwordHasher)
    {
    }

    public function __invoke(CheckCredentials $query): UserId
    {
        try {
            $user = $this->users->findByEmail(new EmailAddress($query->email));
        } catch (InvalidEmailAddress) {
            $user = null;
        }

        if (null === $user) {
            $this->passwordHasher->verifyAgainstDummy($query->password);

            throw InvalidCredentials::create();
        }
        if (!$this->passwordHasher->verify($user->password(), $query->password)) {
            // Someone tried the account's password: the processor traces it for the owner.
            throw InvalidCredentials::forAccount($user->id());
        }

        return $user->id();
    }
}
