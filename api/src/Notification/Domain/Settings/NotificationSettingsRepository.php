<?php

declare(strict_types=1);

namespace App\Notification\Domain\Settings;

use App\Shared\Domain\Identifier\UserId;

interface NotificationSettingsRepository
{
    public function save(NotificationSettings $settings): void;

    /** The user's settings, the defaults (not yet saved) when they never changed them. */
    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): NotificationSettings;
}
