<?php

declare(strict_types=1);

namespace App\Audit\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Audit\Application\Activity\ListActivity;
use App\Audit\Domain\AuditEventId;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Cursors;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<Activity>
 */
final readonly class ActivityProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return list<Activity>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;
        $beforeId = Cursors::id($query?->getString('beforeId'), 'beforeId');

        return array_map(Activity::of(...), $this->queryBus->ask(new ListActivity(
            $this->currentUser->id(),
            $query?->getInt('limit', 20) ?? 20,
            Cursors::time($query?->getString('before'), 'before'),
            null === $beforeId ? null : new AuditEventId($beforeId),
        )));
    }
}
