<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

use App\Coach\Domain\Exception\RecommendationNotFound;
use App\Shared\Domain\Identifier\UserId;

interface RecommendationRepository
{
    public function save(Recommendation $recommendation): void;

    /**
     * @throws RecommendationNotFound also when it is someone else's
     */
    public function getOwnedBy(RecommendationId $id, UserId $userId): Recommendation;

    /**
     * @return list<Recommendation> pending or postponed (possibly past their time), the latest first (then by id, descending)
     */
    public function openOf(UserId $userId): array;

    /**
     * @return list<Recommendation> decided (accepted or rejected) since that time
     */
    public function decidedSince(UserId $userId, \DateTimeImmutable $since): array;

    /**
     * Every one, the latest first then by id (descending): a page starts after the (time, id) of
     * the last one received, since those of one analysis share their time.
     *
     * @return list<Recommendation>
     */
    public function historyOf(UserId $userId, int $limit, ?\DateTimeImmutable $before = null, ?string $beforeId = null): array;
}
