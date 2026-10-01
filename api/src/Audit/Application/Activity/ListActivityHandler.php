<?php

declare(strict_types=1);

namespace App\Audit\Application\Activity;

use App\Audit\Domain\AuditEventRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListActivityHandler implements QueryHandler
{
    public const int MAX = 50;

    public function __construct(private AuditEventRepository $events)
    {
    }

    /**
     * @return list<ActivityView>
     */
    public function __invoke(ListActivity $query): array
    {
        return array_map(ActivityView::of(...), $this->events->latestOf($query->userId, max(1, min(self::MAX, $query->limit)), $query->before, $query->beforeId));
    }
}
