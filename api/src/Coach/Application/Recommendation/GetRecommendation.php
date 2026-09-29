<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\RecommendationId;
use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<RecommendationView>
 */
final readonly class GetRecommendation implements Query
{
    public function __construct(public RecommendationId $recommendationId, public UserId $userId)
    {
    }
}
