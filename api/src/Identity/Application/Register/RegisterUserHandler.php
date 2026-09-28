<?php

declare(strict_types=1);

namespace App\Identity\Application\Register;

use App\Identity\Application\Port\PasswordHasher;
use App\Identity\Application\Port\RegistrationPolicy;
use App\Identity\Application\VerifyEmail\EmailVerificationSender;
use App\Identity\Domain\Exception\EmailAlreadyUsed;
use App\Identity\Domain\Exception\RegistrationClosed;
use App\Identity\Domain\User\EmailAddress;
use App\Identity\Domain\User\PasswordPolicy;
use App\Identity\Domain\User\Profile;
use App\Identity\Domain\User\User;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Domain\Identifier\UserId;
use Psr\Clock\ClockInterface;

final readonly class RegisterUserHandler implements CommandHandler
{
    public function __construct(
        private RegistrationPolicy $registrationPolicy,
        private UserRepository $users,
        private PasswordHasher $passwordHasher,
        private EmailVerificationSender $emailVerification,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        if (!$this->registrationPolicy->isOpen()) {
            throw RegistrationClosed::create();
        }

        $email = new EmailAddress($command->email);
        if (null !== $this->users->findByEmail($email)) {
            throw EmailAlreadyUsed::create();
        }
        PasswordPolicy::assertAcceptable($command->password);

        $now = $this->clock->now();
        $user = User::register(
            new UserId($command->userId),
            $email,
            $this->passwordHasher->hash($command->password),
            new Profile($command->firstName, $now),
            $now,
        );
        $this->users->save($user);

        // The link is issued here (its secret only exists in this call); the email itself leaves
        // after the response. If it is lost, the user asks for a new one from the settings.
        $this->emailVerification->send($user, $now);

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
