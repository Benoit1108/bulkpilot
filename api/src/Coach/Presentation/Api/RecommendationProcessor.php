<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Coach\Application\Recommendation\DecideRecommendation;
use App\Coach\Application\Recommendation\Decision;
use App\Coach\Application\Recommendation\GetRecommendation;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Http\RateLimit;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<DecisionInput, Recommendation>
 */
final readonly class RecommendationProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private RateLimiterFactoryInterface $coachWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Recommendation
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->coachWritesLimiter, 'coach_writes|'.$userId->value);
        \assert(null !== $data->decision);
        $id = RecommendationProvider::id($uriVariables['id'] ?? null);

        $this->commandBus->dispatch(new DecideRecommendation($id, $userId, Decision::from($data->decision), CalendarDays::today($data->today, $this->clock)));

        return Recommendation::of($this->queryBus->ask(new GetRecommendation($id, $userId)));
    }
}
