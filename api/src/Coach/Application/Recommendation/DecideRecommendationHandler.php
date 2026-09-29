<?php

declare(strict_types=1);

namespace App\Coach\Application\Recommendation;

use App\Coach\Domain\Recommendation\RecommendationRepository;
use App\Shared\Application\Bus\Command\CommandHandler;
use App\Shared\Application\Bus\Event\EventBus;
use App\Shared\Application\Coaching\CalorieTargets;
use Psr\Clock\ClockInterface;

/**
 * The user's answer to a recommendation. Accepting a calorie proposal is the only way the Coach
 * changes anything (spec §63, §132): a new target from tomorrow, the old one kept.
 */
final readonly class DecideRecommendationHandler implements CommandHandler
{
    public function __construct(
        private RecommendationRepository $recommendations,
        private CalorieTargets $targets,
        private EventBus $eventBus,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(DecideRecommendation $command): void
    {
        $now = $this->clock->now();
        $recommendation = $this->recommendations->getOwnedBy($command->recommendationId, $command->userId);

        match ($command->decision) {
            Decision::Accept => $recommendation->accept($now),
            Decision::Reject => $recommendation->reject($now),
            Decision::Postpone => $recommendation->postpone($now),
        };
        $proposal = $recommendation->proposal();
        if (Decision::Accept === $command->decision && null !== $proposal) {
            // The user's tomorrow. Should it fail, nothing of the decision is kept: the command's
            // transaction rolls back and no event is published.
            $this->targets->adjust($command->userId, $proposal->fromKcal, $proposal->byKcal, $command->today->setTime(0, 0)->modify('+1 day'));
        }
        $this->recommendations->save($recommendation);
        $this->eventBus->publish(...$recommendation->pullDomainEvents());
    }
}
