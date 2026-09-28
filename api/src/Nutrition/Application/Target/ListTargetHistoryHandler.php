<?php

declare(strict_types=1);

namespace App\Nutrition\Application\Target;

use App\Nutrition\Domain\Target\NutritionTargetRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListTargetHistoryHandler implements QueryHandler
{
    public function __construct(private NutritionTargetRepository $targets)
    {
    }

    /**
     * @return list<TargetView>
     */
    public function __invoke(ListTargetHistory $query): array
    {
        return array_map(TargetView::of(...), $this->targets->history($query->userId, ListTargetHistory::LIMIT));
    }
}
