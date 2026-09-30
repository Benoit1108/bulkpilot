<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\Preferences\GetDashboardLayout;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<DashboardLayout>
 */
final readonly class DashboardLayoutProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DashboardLayout
    {
        return DashboardLayout::of($this->queryBus->ask(new GetDashboardLayout($this->currentUser->id())));
    }
}
