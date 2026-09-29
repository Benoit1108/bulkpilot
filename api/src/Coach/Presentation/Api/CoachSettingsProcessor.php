<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Coach\Application\Settings\ChangeCoachSettings;
use App\Coach\Application\Settings\GetCoachSettings;
use App\Coach\Domain\Settings\Personality;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<CoachSettingsInput, CoachSettings>
 */
final readonly class CoachSettingsProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $coachWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): CoachSettings
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->coachWritesLimiter, 'coach_writes|'.$userId->value);
        \assert(null !== $data->personality);
        $this->commandBus->dispatch(new ChangeCoachSettings($userId, Personality::from($data->personality), $data->paceKgPerWeek));

        return CoachSettings::of($this->queryBus->ask(new GetCoachSettings($userId)));
    }
}
