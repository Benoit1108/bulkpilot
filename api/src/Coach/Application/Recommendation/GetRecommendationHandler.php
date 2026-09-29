<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Shared\Application\Bus\Query\QueryHandler;
use Psr\Clock\ClockInterface;

final readonly class GetRecommendationHandler implements QueryHandler
{
    public function __construct(private RecommendationRepository $recommendations, private ClockInterface $clock)
    {
    }

    public function __invoke(GetRecommendation $query): RecommendationView
    {
        return RecommendationView::of($this->recommendations->getOwnedBy($query->recommendationId, $query->userId), $this->clock->now());
    }
}
