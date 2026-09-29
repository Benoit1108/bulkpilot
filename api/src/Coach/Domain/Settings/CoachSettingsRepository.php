<?php

declare(strict_types=1);

namespace App\Coach\Domain\Settings;

use App\Shared\Domain\Identifier\UserId;

interface CoachSettingsRepository
{
    public function save(CoachSettings $settings): void;

    /** The user's settings, or the defaults (not saved) before they have any. */
    public function ofOrDefault(UserId $userId, \DateTimeImmutable $now): CoachSettings;
}
