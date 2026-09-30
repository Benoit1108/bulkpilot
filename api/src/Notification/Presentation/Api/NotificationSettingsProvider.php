<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Api;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\Notification\Application\Settings\GetNotificationSettings;
use App\Shared\Application\Bus\Query\QueryBus;
use App\Shared\Application\Security\CurrentUser;

/**
 * @implements ProviderInterface<NotificationSettings>
 */
final readonly class NotificationSettingsProvider implements ProviderInterface
{
    public function __construct(private QueryBus $queryBus, private CurrentUser $currentUser)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): NotificationSettings
    {
        return NotificationSettings::of($this->queryBus->ask(new GetNotificationSettings($this->currentUser->id())));
    }
}
