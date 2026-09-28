<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Application\SignIn\PasswordConfirmation;
use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

final readonly class DisableTwoFactorHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordConfirmation $passwordConfirmation,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DisableTwoFactor $command): void
    {
        $user = $this->users->get($command->userId);
        $this->passwordConfirmation->assert($user, $command->password);

        $user->disableTwoFactor($this->clock->now());
        $this->users->save($user);

        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
