<?php

declare(strict_types=1);

namespace App\Identity\Application\TwoFactor;

use App\Identity\Domain\User\UserRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use Psr\Clock\ClockInterface;

/**
 * An app code is accepted once (replay protection); a recovery code is burnt when used.
 */
final readonly class VerifySecondFactorHandler implements CommandHandler
{
    public function __construct(
        private UserRepository $users,
        private SecondFactorCheck $secondFactor,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(VerifySecondFactor $command): void
    {
        $user = $this->users->get($command->userId);
        $this->secondFactor->verify($user, $command->code, $this->clock->now());
        $this->users->save($user);
        $this->eventBus->publish(...$user->pullDomainEvents());
    }
}
