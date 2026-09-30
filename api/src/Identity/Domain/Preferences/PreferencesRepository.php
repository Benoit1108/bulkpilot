<?php

declare(strict_types=1);

namespace App\Identity\Domain\Preferences;

use App\Shared\Domain\Identifier\UserId;

interface PreferencesRepository
{
    public function save(Preferences $preferences): void;

    /** Null until the user or a device set one. */
    public function of(UserId $userId): ?Preferences;

    /**
     * The time zone of every account (or only that one), by user id: the stored one, or the
     * default for an account that never set any.
     *
     * @return array<string, string>
     */
    public function timeZones(?UserId $only = null): array;
}
