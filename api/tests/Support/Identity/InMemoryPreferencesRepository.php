<?php

declare(strict_types=1);

namespace App\Tests\Support\Identity;

use App\Identity\Domain\Preferences\Preferences;
use App\Identity\Domain\Preferences\PreferencesRepository;
use App\Identity\Domain\Preferences\TimeZone;
use App\Identity\Domain\User\User;
use App\Shared\Domain\Identifier\UserId;

/**
 * Like the Doctrine one, the accounts come from the users (the zone of one without preferences is
 * the default).
 */
final class InMemoryPreferencesRepository implements PreferencesRepository
{
    /** @var array<string, Preferences> */
    private array $preferences = [];

    public function __construct(private readonly InMemoryUserRepository $users = new InMemoryUserRepository())
    {
    }

    public function save(Preferences $preferences): void
    {
        $this->preferences[$preferences->ownerId()->value] = $preferences;
    }

    public function of(UserId $userId): ?Preferences
    {
        return $this->preferences[$userId->value] ?? null;
    }

    public function timeZones(?UserId $only = null): array
    {
        $users = array_filter(
            $this->users->users,
            static fn (User $user): bool => !$user->isDeletionScheduled() && (null === $only || $user->id()->equals($only)),
        );

        return array_map(fn (User $user): string => ($this->preferences[$user->id()->value] ?? null)?->timeZone()->name ?? TimeZone::DEFAULT, $users);
    }
}
