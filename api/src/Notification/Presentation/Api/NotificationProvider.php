<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notification\Application\Inbox\GetNotification;
use App\Notification\Application\Inbox\ListNotifications;
use App\Notification\Domain\Exception\NotificationNotFound;
use App\Notification\Domain\Notification\NotificationId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Cursors;
use App\Shared\Presentation\Api\Ids;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Notification>
 */
final readonly class NotificationProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return Notification|list<Notification>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): Notification|array
    {
        $userId = $this->currentUser->id();
        if ($operation instanceof CollectionOperationInterface) {
            $request = $context['request'] ?? null;
            $query = $request instanceof Request ? $request->query : null;
            $beforeId = Cursors::id($query?->getString('beforeId'), 'beforeId');

            return array_map(Notification::of(...), $this->queryBus->ask(new ListNotifications(
                $userId,
                $query?->getInt('limit', 20) ?? 20,
                Cursors::time($query?->getString('before'), 'before'),
                null === $beforeId ? null : new NotificationId($beforeId),
            )));
        }

        return Notification::of($this->queryBus->ask(new GetNotification(self::id($uriVariables['id'] ?? null), $userId)));
    }

    public static function id(mixed $id): NotificationId
    {
        return Ids::parse($id, NotificationId::class, NotificationNotFound::withId(...));
    }
}
