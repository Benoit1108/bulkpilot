<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Api\Deletion;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Identity\Application\Deletion\ScheduleAccountDeletionHandler;
use App\Identity\Domain\User\User;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * The export's and the activity's delays are the other contexts' configuration, given here as
 * container parameters (config/services.yaml), not read from their code.
 *
 * @implements ProviderInterface<DataPolicy>
 */
final readonly class DataPolicyProvider implements ProviderInterface
{
    public function __construct(
        #[Autowire('%app.export_available_hours%')]
        private int $exportAvailableHours,
        #[Autowire('%env(int:AUDIT_RETENTION_DAYS)%')]
        private int $activityRetentionDays,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): DataPolicy
    {
        return new DataPolicy(User::DELETION_DELAY_DAYS, $this->exportAvailableHours, $this->activityRetentionDays, ScheduleAccountDeletionHandler::RECENT_SIGN_IN_MINUTES);
    }
}
