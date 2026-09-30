<?php

declare(strict_types=1);

namespace App\Tests\Support\Notification;

use App\Notification\Domain\Settings\NotificationSettings;
use App\Notification\Domain\Settings\NotificationSettingsRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryNotificationSettingsRepository implements NotificationSettingsRepository
{
    /** @var array<string, NotificationSettings> */
    private array $settings = [];

    public function save(NotificationSettings $settings): void
    {
        $this->settings[$settings->ownerId()->value] = $settings;
    }

    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): NotificationSettings
    {
        return $this->settings[$userId->value] ?? NotificationSettings::defaults($userId, $now);
    }

    public function stored(UserId $userId): ?NotificationSettings
    {
        return $this->settings[$userId->value] ?? null;
    }
}
