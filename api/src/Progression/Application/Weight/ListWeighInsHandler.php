<?php

declare(strict_types=1);

namespace App\Progression\Application\Weight;

use App\Progression\Domain\Weight\WeighInRepository;
use App\Shared\Application\Bus\Query\QueryHandler;

final readonly class ListWeighInsHandler implements QueryHandler
{
    public function __construct(private WeighInRepository $weighIns)
    {
    }

    /**
     * @return list<WeighInView>
     */
    public function __invoke(ListWeighIns $query): array
    {
        $limit = max(1, min(ListWeighIns::MAX_LIMIT, $query->limit));

        return array_map(WeighInView::of(...), $this->weighIns->latest($query->userId, $limit, $query->before));
    }
}
