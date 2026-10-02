<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Coach\Application\Report\ListWeeklyReports;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\Cursors;
use Symfony\Component\HttpFoundation\Request;

/**
 * @implements ProviderInterface<WeeklyReport>
 */
final readonly class WeeklyReportProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    /**
     * @return list<WeeklyReport>
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array
    {
        $request = $context['request'] ?? null;
        $query = $request instanceof Request ? $request->query : null;

        return array_map(WeeklyReport::of(...), $this->queryBus->ask(new ListWeeklyReports(
            $this->currentUser->id(),
            $query?->getInt('limit', ListWeeklyReports::PAGE) ?? ListWeeklyReports::PAGE,
            Cursors::day($query?->getString('before'), 'before'),
        )));
    }
}
