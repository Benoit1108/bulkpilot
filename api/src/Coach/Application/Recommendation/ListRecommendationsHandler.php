<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\Recommendation;
use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class ListRecommendationsHandler implements QueryHandler
{
    public const int MAX = 50;

    public function __construct(private RecommendationRepository $recommendations, private ClockInterface $clock)
    {
    }

    /**
     * @return list<RecommendationView>
     */
    public function __invoke(ListRecommendations $query): array
    {
        $now = $this->clock->now();

        return array_map(
            static fn (Recommendation $recommendation): RecommendationView => RecommendationView::of($recommendation, $now),
            $this->recommendations->historyOf($query->userId, max(1, min(self::MAX, $query->limit)), $query->before, $query->beforeId),
        );
    }
}
