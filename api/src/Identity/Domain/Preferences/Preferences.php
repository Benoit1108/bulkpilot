<?php

declare(strict_types=1);

namespace App\Identity\Domain\Preferences;

use App\Identity\Domain\Dashboard\DashboardLayout;
use App\Shared\Domain\Identifier\UserId;

/**
 * What the user's devices and choices set about the app rather than the account: the time zone
 * (sent silently by the devices) and the dashboard layout (spec §82). Kept apart from the account
 * so that neither ever competes with a sign-in, a password, an email or a two-factor change.
 *
 * @phpstan-import-type StoredLayout from DashboardLayout
 */
class Preferences
{
    /**
     * @param StoredLayout|null $dashboardLayout null until the user arranges the dashboard
     */
    private function __construct(
        private readonly UserId $ownerId,
        private string $timeZone,
        private ?array $dashboardLayout,
        private \DateTimeImmutable $updatedAt,
    ) {
    }

    /** Until the user or a device says otherwise. */
    public static function defaults(UserId $owner, \DateTimeImmutable $now): self
    {
        return new self($owner, TimeZone::DEFAULT, null, $now);
    }

    /** The zone of the user's devices, sent by them when it changes (travel, a new device). */
    public function moveToTimeZone(TimeZone $timeZone, \DateTimeImmutable $now): void
    {
        $this->timeZone = $timeZone->name;
        $this->updatedAt = $now;
    }

    public function arrangeDashboard(DashboardLayout $layout, \DateTimeImmutable $now): void
    {
        $this->dashboardLayout = $layout->toArray();
        $this->updatedAt = $now;
    }

    public function ownerId(): UserId
    {
        return $this->ownerId;
    }

    public function timeZone(): TimeZone
    {
        return TimeZone::orDefault($this->timeZone);
    }

    /** The default layout until the user arranges it. */
    public function dashboardLayout(): DashboardLayout
    {
        return null === $this->dashboardLayout ? DashboardLayout::default() : DashboardLayout::stored($this->dashboardLayout);
    }

    public function updatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
