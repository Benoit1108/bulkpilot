<?php

declare(strict_types=1);

namespace App\Coach\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Coach\Application\Settings\GetCoachSettings;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<CoachSettings>
 */
final readonly class CoachSettingsProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): CoachSettings
    {
        return CoachSettings::of($this->queryBus->ask(new GetCoachSettings($this->currentUser->id())));
    }
}
