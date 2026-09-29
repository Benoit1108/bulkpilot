<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\RecommendationId;
use App\Shared\Application\Bus\Command\Command;
use App\Shared\Domain\Identifier\UserId;

final readonly class DecideRecommendation implements Command
{
    public function __construct(
        public RecommendationId $recommendationId,
        public UserId $userId,
        public Decision $decision,
        /** The user's today: an accepted calorie change starts the day after. */
        public \DateTimeImmutable $today,
    ) {
    }
}
