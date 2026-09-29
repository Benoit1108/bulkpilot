<?php

declare(strict_types=1);

namespace App\Coach\Domain\Recommendation;

use App\Coach\Domain\Rule\Kind;
use App\Shared\Domain\Event\DomainEvent;
use App\Shared\Domain\Identifier\UserId;

/**
 * The user accepted, rejected or postponed a recommendation (spec §70: the decision is kept).
 */
final readonly class RecommendationDecided implements DomainEvent
{
    public function __construct(
        public RecommendationId $recommendationId,
        public UserId $userId,
        public Kind $kind,
        public Status $status,
        public \DateTimeImmutable $occurredOn,
    ) {
    }

    public function occurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
