<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Coach\Application\Analysis\AnalyseCoach;
use App\Coach\Application\Analysis\GetCoach;
use App\Coach\Application\Settings\GetCoachSettings;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Api\CalendarDays;
use App\Shared\Presentation\Http\RateLimit;
use Psr\Clock\ClockInterface;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<AnalysisInput, Coach>
 */
final readonly class CoachProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private ClockInterface $clock,
        private RateLimiterFactoryInterface $coachWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Coach
    {
        $userId = $this->currentUser->id();
        $today = CalendarDays::today($data->today, $this->clock);
        // Every analysis that runs the rules counts, asked again or the first of a day; opening
        // the Coach again the same day is a read.
        $last = $this->queryBus->ask(new GetCoachSettings($userId))->lastAnalysedOn;
        if ($data->again || null === $last || $last < $today) {
            RateLimit::consume($this->coachWritesLimiter, 'coach_writes|'.$userId->value);
        }
        $this->commandBus->dispatch(new AnalyseCoach($userId, $today, $data->again));

        return Coach::of($today, $this->queryBus->ask(new GetCoach($userId)));
    }
}
