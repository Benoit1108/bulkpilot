<?php

declare(strict_types=1);

namespace App\Notification\Application\Settings;

use App\Shared\Application\Bus\Query\Query;
use App\Shared\Domain\Identifier\UserId;

/**
 * @implements Query<NotificationSettingsView>
 */
final readonly class GetNotificationSettings implements Query
{
    public function __construct(public UserId $userId)
    {
    }
}
