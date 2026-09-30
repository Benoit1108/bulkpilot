<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Notification\Application\Inbox\GetNotification;
use App\Notification\Application\Inbox\MarkRead;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<null, Notification>
 */
final readonly class NotificationProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $notificationWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Notification
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->notificationWritesLimiter, 'notification_writes|'.$userId->value);
        $id = NotificationProvider::id($uriVariables['id'] ?? null);
        $this->commandBus->dispatch(new MarkRead($id, $userId));

        return Notification::of($this->queryBus->ask(new GetNotification($id, $userId)));
    }
}
