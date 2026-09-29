<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * The Coach's history (spec §70), the latest first, a page at a time.
 *
 * @implements Query<list<RecommendationView>>
 */
final readonly class ListRecommendations implements Query
{
    /**
     * @param ?\DateTimeImmutable $before   the createdAt of the last one received…
     * @param ?string             $beforeId …and its id: the next page starts after both (several share a time)
     */
    public function __construct(public UserId $userId, public int $limit, public ?\DateTimeImmutable $before = null, public ?string $beforeId = null)
    {
    }
}
