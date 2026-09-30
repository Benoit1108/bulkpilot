<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Preferences;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Identity\Application\Preferences\ArrangeDashboard;
use App\Identity\Application\Preferences\GetDashboardLayout;
use App\Shared\Application\Bus\Command\CommandBus;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;
use App\Shared\Presentation\Http\RateLimit;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

/**
 * @implements ProcessorInterface<DashboardLayoutInput, DashboardLayout>
 */
final readonly class DashboardLayoutProcessor implements ProcessorInterface
{
    public function __construct(
        private CommandBus $commandBus,
        private QueryBus $queryBus,
        private CurrentUser $currentUser,
        private RateLimiterFactoryInterface $preferenceWritesLimiter,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DashboardLayout
    {
        $userId = $this->currentUser->id();
        RateLimit::consume($this->preferenceWritesLimiter, 'preference_writes|'.$userId->value);
        $this->commandBus->dispatch(new ArrangeDashboard($userId, $data->widgets()));

        return DashboardLayout::of($this->queryBus->ask(new GetDashboardLayout($userId)));
    }
}
