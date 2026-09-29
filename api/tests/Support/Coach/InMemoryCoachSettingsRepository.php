<?php

declare(strict_types=1);

namespace App\Tests\Support\Coach;

use App\Coach\Domain\Settings\CoachSettings;
use App\Coach\Domain\Settings\CoachSettingsRepository;
use App\Shared\Domain\Identifier\UserId;

final class InMemoryCoachSettingsRepository implements CoachSettingsRepository
{
    /** @var array<string, CoachSettings> */
    public array $settings = [];

    public function save(CoachSettings $settings): void
    {
        $this->settings[$settings->ownerId()->value] = $settings;
    }

    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): CoachSettings
    {
        return $this->settings[$userId->value] ?? CoachSettings::defaults($userId, $now);
    }
}
