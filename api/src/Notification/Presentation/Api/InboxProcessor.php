<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Application\Check\CheckNotifications;
use App\Notification\Application\Inbox\MarkAllRead;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Domain\Exception\ConcurrentUpdate;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, null>
 */
final readonly class InboxProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $notificationWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): null
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->notificationWritesLimiter, 'notification_writes|'.$userId->value);
        if ('notifications_read_all' === $operation->getName()) {
            $this->commandBus->dispatch(new MarkAllRead($userId));

            return null;
        }
        try {
            $this->commandBus->dispatch(new CheckNotifications($userId));
        } catch (ConcurrentUpdate) {
            // Checked by the schedule at the same moment: what it found is the same. Nothing to report.
        }

        return null;
    }
}
