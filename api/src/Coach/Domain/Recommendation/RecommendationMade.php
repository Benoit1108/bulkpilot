<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

use App\Coach\Domain\Rule\Kind;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * The Coach offered a new recommendation, phrased by a provider (spec §122: which one is traced).
 */
final readonly class RecommendationMade implements DomainEvent
{
    public function __construct(
        public RecommendationId $recommendationId,
        public UserId $userId,
        public Kind $kind,
        public string $provider,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
